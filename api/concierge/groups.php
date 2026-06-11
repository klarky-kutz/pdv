<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_groups_helper.php';
require_once DIR_HELPER . 'ai_evolution.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_groups_extract_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_CONCIERGE_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    }
    if (stripos($token, 'Bearer ') === 0) {
        $token = trim(substr($token, 7));
    }
    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    }
    return $token;
}

function concierge_groups_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function concierge_groups_has_table(string $tableName): bool
{
    try {
        $quoted = db()->quote($tableName);
        $stmt = db()->query("SHOW TABLES LIKE {$quoted}");
        return $stmt ? (bool)$stmt->fetchColumn() : false;
    } catch (Throwable $e) {
        return false;
    }
}

function concierge_groups_forbid_if_no_permission(string $perm, bool $isTokenAuth): void
{
    if ($isTokenAuth || user_group_id() == 1) {
        return;
    }
    if (!has_permission('access', $perm)) {
        http_response_code(403);
        echo json_encode(ai_groups_response(true, 'Permissão insuficiente.', null));
        exit;
    }
}

function concierge_groups_plan_meta(int $tenantId): array
{
    $plan = function_exists('ai_get_active_plan') ? ai_get_active_plan($tenantId) : null;
    $rawLimit = max(0, (int)($plan['ai_groups_limit'] ?? 0));
    $effectiveLimit = $rawLimit > 0 ? $rawLimit : 0;
    $displayLimit = 5;

    return [
        'groups_limit' => $rawLimit,
        'effective_groups_limit' => $effectiveLimit,
        'is_unlimited' => $rawLimit === 0,
        'display_groups_limit' => $displayLimit,
    ];
}

function concierge_groups_sort_by_members(array $groups): array
{
    usort($groups, static function (array $a, array $b): int {
        $membersA = (int)($a['member_count'] ?? 0);
        $membersB = (int)($b['member_count'] ?? 0);
        if ($membersA !== $membersB) {
            return $membersB <=> $membersA;
        }
        return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    return $groups;
}

function concierge_groups_attach_campaign_metrics(int $tenantId, array $groups): array
{
    if (empty($groups)) {
        return [];
    }

    foreach ($groups as &$group) {
        $gid = (int)($group['id'] ?? 0);
        $scheduled = 0;
        $completed = 0;
        $total = 0;
        $sentToday = 0;
        $dailyLimit = max(0, (int)($group['daily_limit'] ?? 0));
        $nextDispatchAt = null;
        
        if (concierge_groups_has_table('concierge_broadcasts') && concierge_groups_has_table('concierge_campaigns')) {
            try {
                $stmt = db()->prepare("
                    SELECT
                        COUNT(DISTINCT CASE
                            WHEN c.status IN ('pending','scheduled','queued','sending')
                              OR b.status IN ('pending','queued','scheduled','sending')
                            THEN c.id
                        END) AS scheduled_campaigns,
                        COUNT(DISTINCT CASE WHEN b.status IN ('sent','completed') THEN c.id END) AS completed_campaigns,
                        COUNT(DISTINCT c.id) AS total_campaigns,
                        SUM(CASE WHEN b.status = 'sent' AND DATE(COALESCE(b.sent_at, b.updated_at, b.created_at)) = CURDATE() THEN 1 ELSE 0 END) AS sent_today_count
                    FROM concierge_broadcasts b
                    INNER JOIN concierge_campaigns c
                        ON c.id = b.campaign_id
                    WHERE b.group_id = ?
                      AND b.tenant_id = ?
                      AND c.tenant_id = ?
                      AND c.status NOT IN ('canceled','draft')
                ");
                $stmt->execute([$gid, $tenantId, $tenantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $scheduled = (int)($row['scheduled_campaigns'] ?? 0);
                    $completed = (int)($row['completed_campaigns'] ?? 0);
                    $total = max((int)($row['total_campaigns'] ?? 0), $scheduled + $completed);
                    $sentToday = max(0, (int)($row['sent_today_count'] ?? 0));
                }
                
                // Get queue data to calculate next dispatch
                $queueData = ai_groups_get_group_dispatch_queue($tenantId, $gid, 1);
                if (!empty($queueData['next_items']) && count($queueData['next_items']) > 0) {
                    $firstItem = $queueData['next_items'][0];
                    if (!empty($firstItem['next_dispatch_at'])) {
                        $nextDispatchAt = (string)$firstItem['next_dispatch_at'];
                    }
                }
            } catch (Throwable $e) {
                $scheduled = 0;
                $completed = 0;
                $total = 0;
                $sentToday = 0;
                $nextDispatchAt = null;
            }
        }

        $group['scheduled_campaigns'] = $scheduled;
        $group['completed_campaigns'] = $completed;
        $group['total_campaigns'] = $total;
        $group['sent_today'] = $sentToday;
        
        if ($dailyLimit > 0) {
            $group['progress_pct'] = min(100, (int)round(($sentToday / $dailyLimit) * 100));
        } else {
            $progressBase = max(1, $scheduled + $completed);
            $group['progress_pct'] = min(100, (int)round(($completed / $progressBase) * 100));
        }
        
        $group['next_dispatch_at'] = $nextDispatchAt;
        $settingsRaw = $group['settings_json'] ?? null;
        $settings = [];
        if (is_array($settingsRaw)) {
            $settings = $settingsRaw;
        } elseif (is_string($settingsRaw) && trim($settingsRaw) !== '') {
            $decoded = json_decode($settingsRaw, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        $group['settings_json'] = $settings;
        $groupIntervalRaw = max(0, (int)($settings['dispatch_interval_minutes'] ?? $settings['interval_between_dispatches'] ?? 0));
        $groupIntervalDefault = max(1, (int)ai_get_setting('mia_group_delay', 5, $tenantId));
        $group['dispatch_interval_minutes'] = $groupIntervalRaw > 0 ? $groupIntervalRaw : $groupIntervalDefault;
    }
    unset($group);

    return $groups;
}

function concierge_groups_enforce_active_limit(int $tenantId, int $effectiveLimit): void
{
    if ($tenantId <= 0 || $effectiveLimit <= 0 || !concierge_groups_has_table('concierge_groups')) {
        return;
    }

    try {
        $stmt = db()->prepare("
            SELECT id
            FROM concierge_groups
            WHERE tenant_id = :tid
            ORDER BY member_count DESC, name ASC
        ");
        $stmt->execute([':tid' => $tenantId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (empty($ids)) {
            return;
        }

        $keepIds = array_slice($ids, 0, $effectiveLimit);
        if (empty($keepIds)) {
            db()->prepare("UPDATE concierge_groups SET is_active = 0, updated_at = NOW() WHERE tenant_id = :tid")
                ->execute([':tid' => $tenantId]);
            return;
        }

        $in = implode(',', array_fill(0, count($keepIds), '?'));
        $sql = "UPDATE concierge_groups
                SET is_active = CASE WHEN id IN ($in) THEN 1 ELSE 0 END,
                    updated_at = NOW()
                WHERE tenant_id = ?";
        $params = array_merge($keepIds, [$tenantId]);
        db()->prepare($sql)->execute($params);
    } catch (Throwable $e) {
    }
}

try {
    $json = concierge_groups_read_json_body();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $token = concierge_groups_extract_token();
    $isTokenAuth = false;

    if ($tenantId > 0 && $token !== '') {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantId]);
        $storedToken = (string)$stmt->fetchColumn();
        if ($storedToken !== '' && hash_equals($storedToken, $token)) {
            $isTokenAuth = true;
        } else {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Token inválido.', null));
            exit;
        }
    }

    if (!$isTokenAuth) {
        if (!is_loggedin()) {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Sessão inválida.', null));
            exit;
        }
        $tenantId = ai_tenant_id();
        if ($tenantId <= 0) {
            throw new Exception('Tenant inválido.');
        }
        concierge_groups_forbid_if_no_permission('concierge_groups_access', false);
    }

    if (!ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }

    $planMeta = concierge_groups_plan_meta($tenantId);

    if ($method === 'GET') {
        $action = strtolower(trim((string)($_GET['action'] ?? '')));
        if ($action === 'queue' || $action === 'performance_queue') {
            $groupId = (int)($_GET['group_id'] ?? 0);
            if ($groupId <= 0) {
                throw new Exception('group_id inválido para consultar fila.');
            }

            $queueData = ai_groups_get_group_dispatch_queue($tenantId, $groupId, 3);
            echo json_encode(ai_groups_response(false, 'OK', [
                'queue' => $queueData,
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $onlyActive = (int)($_GET['include_inactive'] ?? 0) !== 1;
        $groups = ai_get_concierge_groups($tenantId, $onlyActive);
        $groups = concierge_groups_attach_campaign_metrics($tenantId, $groups);
        $groups = concierge_groups_sort_by_members($groups);

        $allGroups = ai_get_concierge_groups($tenantId, false);
        $allGroups = concierge_groups_attach_campaign_metrics($tenantId, $allGroups);
        $allGroups = concierge_groups_sort_by_members($allGroups);
        $displaySource = !empty($groups) ? $groups : $allGroups;
        $displayLimit = (int)($planMeta['display_groups_limit'] ?? 5);
        if ((int)$planMeta['effective_groups_limit'] > 0) {
            $displayLimit = min($displayLimit, (int)$planMeta['effective_groups_limit']);
        }
        $displayGroups = array_slice($displaySource, 0, max(0, $displayLimit));
        $stats = ai_get_groups_stats($tenantId);
        $settings = ai_get_settings($tenantId);
        $settings['mia_group_plan_limit'] = (string)$planMeta['groups_limit'];
        $settings['mia_group_effective_limit'] = (string)$planMeta['effective_groups_limit'];

        echo json_encode(ai_groups_response(false, 'OK', [
            'groups' => $groups,
            'display_groups' => $displayGroups,
            'all_groups' => $allGroups,
            'stats' => $stats,
            'settings' => $settings,
            'plan' => $planMeta,
            'ai_stats' => ai_groups_get_ai_bar_stats($tenantId),
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        concierge_groups_forbid_if_no_permission('concierge_groups_manage', $isTokenAuth);
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? $json['action'] ?? '')));

        if ($action === 'sync') {
            $sync = ai_evolution_fetch_groups($tenantId);
            if (empty($sync['ok'])) {
                $err = (string)($sync['error'] ?? 'Falha ao sincronizar grupos.');
                if (isset($sync['http_code']) && $sync['http_code'] > 0) {
                    $err .= ' (HTTP ' . $sync['http_code'] . ')';
                }
                throw new Exception($err);
            }

            $groups = is_array($sync['groups'] ?? null) ? $sync['groups'] : [];
            $remoteJids = [];
            $saved = 0;
            foreach ($groups as $group) {
                $remoteJid = trim((string)($group['id'] ?? ''));
                if ($remoteJid === '') {
                    continue;
                }
                $savedId = ai_sync_evolution_group($tenantId, $group);
                if ($savedId > 0) {
                    $saved++;
                    $remoteJids[] = $remoteJid;
                    db()->prepare("
                        UPDATE concierge_groups
                        SET last_synced_at = NOW(), updated_at = NOW()
                        WHERE tenant_id = :tid AND remote_jid = :jid
                        LIMIT 1
                    ")->execute([':tid' => $tenantId, ':jid' => $remoteJid]);
                }
            }

            if (concierge_groups_has_table('concierge_groups')) {
                if (!empty($remoteJids)) {
                    $in = implode(',', array_fill(0, count($remoteJids), '?'));
                    $params = array_merge([$tenantId], $remoteJids);
                    $sql = "UPDATE concierge_groups SET is_active = 0, updated_at = NOW() WHERE tenant_id = ? AND remote_jid NOT IN ($in)";
                    db()->prepare($sql)->execute($params);
                } else {
                    db()->prepare("UPDATE concierge_groups SET is_active = 0, updated_at = NOW() WHERE tenant_id = :tid")
                        ->execute([':tid' => $tenantId]);
                }
            }

            concierge_groups_enforce_active_limit($tenantId, (int)$planMeta['effective_groups_limit']);
            $allGroups = ai_get_concierge_groups($tenantId, false);
            $allGroups = concierge_groups_attach_campaign_metrics($tenantId, $allGroups);
            $allGroups = concierge_groups_sort_by_members($allGroups);
            $activeGroups = ai_get_concierge_groups($tenantId, true);
            $activeGroups = concierge_groups_attach_campaign_metrics($tenantId, $activeGroups);
            $activeGroups = concierge_groups_sort_by_members($activeGroups);
            $displayLimit = (int)($planMeta['display_groups_limit'] ?? 5);
            if ((int)$planMeta['effective_groups_limit'] > 0) {
                $displayLimit = min($displayLimit, (int)$planMeta['effective_groups_limit']);
            }

            echo json_encode(ai_groups_response(false, 'Sincronização concluída.', [
                'detected' => count($groups),
                'saved' => $saved,
                'groups' => $allGroups,
                'display_groups' => array_slice($activeGroups, 0, max(0, $displayLimit)),
                'plan' => $planMeta,
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'leave' || $action === 'leave_group') {
            $groupId = (int)($json['group_id'] ?? $json['id'] ?? $_POST['group_id'] ?? $_GET['group_id'] ?? 0);
            $remoteJid = trim((string)($json['remote_jid'] ?? $_POST['remote_jid'] ?? $_GET['remote_jid'] ?? ''));

            $group = null;
            if ($groupId > 0) {
                $stmt = db()->prepare("SELECT * FROM concierge_groups WHERE tenant_id = :tid AND id = :gid LIMIT 1");
                $stmt->execute([':tid' => $tenantId, ':gid' => $groupId]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif ($remoteJid !== '') {
                $stmt = db()->prepare("SELECT * FROM concierge_groups WHERE tenant_id = :tid AND remote_jid = :jid LIMIT 1");
                $stmt->execute([':tid' => $tenantId, ':jid' => $remoteJid]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!$group) {
                throw new Exception('Grupo não encontrado para sair.');
            }

            $leave = ai_evolution_leave_group($tenantId, (string)$group['remote_jid']);
            if (empty($leave['ok'])) {
                $err = (string)($leave['error'] ?? 'Falha ao sair do grupo.');
                if ((int)($leave['http_code'] ?? 0) > 0) {
                    $err .= ' (HTTP ' . (int)$leave['http_code'] . ')';
                }
                throw new Exception($err);
            }

            db()->prepare("
                UPDATE concierge_groups
                SET is_active = 0, updated_at = NOW()
                WHERE tenant_id = :tid AND id = :gid
                LIMIT 1
            ")->execute([':tid' => $tenantId, ':gid' => (int)$group['id']]);

            echo json_encode(ai_groups_response(false, 'Grupo removido da instância com sucesso.', [
                'group_id' => (int)$group['id'],
                'remote_jid' => (string)$group['remote_jid'],
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        throw new Exception('Ação POST inválida. Use action=sync ou action=leave.');
    }

    if ($method === 'PATCH') {
        concierge_groups_forbid_if_no_permission('concierge_groups_manage', $isTokenAuth);
        $groupId = (int)($json['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);

        if ($groupId <= 0) {
            $savedGlobal = 0;
            if (isset($json['mia_group_delay'])) {
                ai_save_setting('mia_group_delay', (int)$json['mia_group_delay'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_group_global_limit'])) {
                ai_save_setting('mia_group_global_limit', (int)$json['mia_group_global_limit'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_allowed_categories'])) {
                $rawCats = is_array($json['mia_allowed_categories']) ? $json['mia_allowed_categories'] : explode(',', (string)$json['mia_allowed_categories']);
                $cleanCats = array_unique(array_filter(array_map('trim', $rawCats)));
                ai_save_setting('mia_allowed_categories', implode(',', $cleanCats), $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_status_auto_enable'])) {
                ai_save_setting('mia_status_auto_enable', (int)$json['mia_status_auto_enable'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_status_auto_count'])) {
                ai_save_setting('mia_status_auto_count', (int)$json['mia_status_auto_count'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_status_auto_rep'])) {
                ai_save_setting('mia_status_auto_rep', (int)$json['mia_status_auto_rep'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_status_auto_days'])) {
                ai_save_setting('mia_status_auto_days', (int)$json['mia_status_auto_days'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['mia_status_auto_interval'])) {
                ai_save_setting('mia_status_auto_interval', (int)$json['mia_status_auto_interval'], $tenantId);
                $savedGlobal++;
            }
            if (isset($json['ai_groups_dispatch_webhook_url'])) {
                ai_save_setting('ai_groups_dispatch_webhook_url', trim((string)$json['ai_groups_dispatch_webhook_url']), $tenantId);
                $savedGlobal++;
            }

            if ($savedGlobal > 0) {
                echo json_encode(ai_groups_response(false, 'Configurações globais atualizadas.', null));
                exit;
            }
            throw new Exception('ID do grupo ou configurações globais não informados.');
        }

        if (
            (int)$planMeta['effective_groups_limit'] > 0
            && array_key_exists('is_active', $json)
            && (int)$json['is_active'] === 1
        ) {
            $stmtActive = db()->prepare("
                SELECT COUNT(*)
                FROM concierge_groups
                WHERE tenant_id = :tid AND is_active = 1 AND id <> :gid
            ");
            $stmtActive->execute([':tid' => $tenantId, ':gid' => $groupId]);
            $activeCount = (int)$stmtActive->fetchColumn();
            if ($activeCount >= (int)$planMeta['effective_groups_limit']) {
                $msg = 'Limite de grupos ativos do plano atingido (' . (int)$planMeta['effective_groups_limit'] . ').';
                throw new Exception($msg);
            }
        }

        $sets = [];
        $params = [':tid' => $tenantId, ':gid' => $groupId];

        if (array_key_exists('is_active', $json)) {
            $sets[] = 'is_active = :is_active';
            $params[':is_active'] = (int)((int)$json['is_active'] === 1 ? 1 : 0);
        }
        if (array_key_exists('name', $json)) {
            $sets[] = 'name = :name';
            $params[':name'] = trim((string)$json['name']);
        }
        if (array_key_exists('category', $json)) {
            $sets[] = 'category = :category';
            $params[':category'] = mb_substr(trim((string)$json['category']), 0, 60, 'UTF-8');
        }
        if (array_key_exists('daily_limit', $json)) {
            $sets[] = 'daily_limit = :daily_limit';
            $params[':daily_limit'] = max(0, (int)$json['daily_limit']);
        }
        if (array_key_exists('settings_json', $json)) {
            $sets[] = 'settings_json = :settings_json';
            $settings = $json['settings_json'];
            $params[':settings_json'] = is_array($settings)
                ? json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : trim((string)$settings);
        }

        if (empty($sets)) {
            throw new Exception('Nenhum campo para atualizar.');
        }

        $sql = 'UPDATE concierge_groups SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE tenant_id = :tid AND id = :gid LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $st = db()->prepare('SELECT * FROM concierge_groups WHERE tenant_id = :tid AND id = :gid LIMIT 1');
        $st->execute([':tid' => $tenantId, ':gid' => $groupId]);
        $group = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($group) {
            $group = concierge_groups_attach_campaign_metrics($tenantId, [$group])[0];
        }

        echo json_encode(ai_groups_response(false, 'Grupo atualizado.', [
            'group' => $group,
            'plan' => $planMeta,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(ai_groups_response(true, 'Método não suportado.', null));
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
