<?php
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_evolution.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Não logado.']);
    exit;
}

if (user_group_id() != 1 && !has_permission('access', 'access_concierge_ia')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Sem permissão para acessar o módulo Moda IA.']);
    exit;
}

function mia_json(array $payload, int $statusCode = 200): void
{
    if (ob_get_level() > 0) ob_clean();
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mia_time_ago(?string $datetime): string
{
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'agora';
    if ($diff < 3600) return 'há ' . floor($diff / 60) . 'min';
    if ($diff < 86400) return 'há ' . floor($diff / 3600) . 'h';
    return 'há ' . floor($diff / 86400) . 'd';
}

function mia_phone_digits(string $raw): string
{
    // Remove tudo que não é dígito
    $digits = preg_replace('/\D+/', '', $raw);
    
    // Se começar com 55 e tiver 12 ou 13 dígitos, é um número BR completo
    // Se tiver 10 ou 11 dígitos sem o 55, adicionamos o 55 para normalizar
    if (strlen($digits) >= 10 && strlen($digits) <= 11 && strpos($digits, '55') !== 0) {
        $digits = '55' . $digits;
    }
    
    return $digits;
}

function mia_remote_jid_from_phone(string $raw): string
{
    $digits = mia_phone_digits($raw);
    if ($digits === '') return '';
    // Garante que o JID tenha o formato correto sem o sinal de +
    return $digits . '@s.whatsapp.net';
}

function mia_profile_phone_key(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    
    // Para agrupamento, usamos o número sem o DDI 55 se presente
    if (strpos($digits, '55') === 0 && strlen($digits) >= 12) {
        return substr($digits, 2);
    }
    return $digits;
}

function mia_has_memory_payload(?string $json): bool
{
    if (!is_string($json)) return false;
    $trimmed = trim($json);
    return $trimmed !== '' && $trimmed !== '{}' && $trimmed !== '[]' && $trimmed !== 'null';
}

function mia_order_phone_candidates(string $raw): array
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return [];

    $candidates = [$digits];
    
    // Se for um número completo com 55 (DDI Brasil)
    if (strpos($digits, '55') === 0 && strlen($digits) >= 12) {
        $withoutCountry = substr($digits, 2);
        $candidates[] = $withoutCountry;
        $candidates[] = '+' . $digits; // Formato padrão do banco em ai_chat_profiles
    } else {
        // Se for número sem 55, adiciona a versão com 55 e com +55
        $candidates[] = '55' . $digits;
        $candidates[] = '+55' . $digits;
    }

    $final = [];
    foreach ($candidates as $c) {
        $final[] = $c;
        // Evita variações curtas demais que podem cruzar dados de outros clientes
        if (strlen($c) > 8) {
            $final[] = $c . '@s.whatsapp.net';
            $final[] = $c . '@c.us';
        }
    }

    return array_values(array_unique(array_filter($final)));
}

function mia_table_has_column(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $stmt->execute([':table' => $table, ':column' => $column]);
    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function mia_extract_last_product(array $profile): string
{
    $candidateFields = [
        'last_product_requested',
        'last_product_name',
        'last_product',
        'last_requested_product',
        'produto_nome',
        'produto',
        'produto_solicitado',
        'ultimo_produto',
        'ultimo_produto_solicitado',
        'último_produto',
        'último_produto_solicitado',
        'interesse_duvida', // Fallback se tiver o produto na dúvida
    ];

    foreach ($candidateFields as $field) {
        if (!empty($profile[$field]) && is_scalar($profile[$field])) {
            $val = trim((string)$profile[$field]);
            if ($val !== '') return $val;
        }
    }

    $prefs = [];
    if (!empty($profile['preferences']) && is_array($profile['preferences'])) {
        $prefs = $profile['preferences'];
    } else {
        $prefsJson = (string)($profile['preferences_json'] ?? '');
        if ($prefsJson !== '') {
            $decoded = json_decode($prefsJson, true);
            if (is_array($decoded)) {
                $prefs = $decoded;
            }
        }
    }

    if (!empty($prefs)) {
        foreach ([
            'last_product',
            'last_product_name',
            'last_product_requested',
            'last_requested_product',
            'produto',
            'produto_nome',
            'produto_solicitado',
            'ultimo_produto',
            'ultimo_produto_solicitado',
            'último_produto',
            'último_produto_solicitado',
            'interesse',
            'product_name',
            'product'
        ] as $key) {
            if (!empty($prefs[$key]) && is_scalar($prefs[$key])) {
                $val = trim((string)$prefs[$key]);
                if ($val !== '') return $val;
            }
        }
    }

    return 'Não informado';
}

function mia_rank_profile(array $p): int
{
    $score = 0;
    if (mia_has_memory_payload($p['preferences_json'] ?? null)) $score += 2;
    if (!empty($p['conversation_summary'])) $score += 2;
    if (!empty($p['usual_size'])) $score += 1;
    if (!empty($p['interesse_duvida'])) $score += 1;
    return $score;
}

function mia_build_order_context_map(int $tenantId, array $candidatePhones): array
{
    $candidatePhones = array_values(array_unique(array_filter(array_map('strval', $candidatePhones))));
    if (empty($candidatePhones)) {
        return ['active_by_phone' => [], 'latest_product_by_phone' => []];
    }

    $in = implode(',', array_fill(0, count($candidatePhones), '?'));
    $activeStatuses = ['pendente', 'pago', 'separando', 'rota'];
    $activeByPhone = [];
    $latestProductByPhone = [];

    try {
        $stmt = db()->prepare("
            SELECT o.whatsapp_phone, o.status, o.updated_at, o.id AS order_id, oi.id AS order_item_id,
                   COALESCE(NULLIF(oi.model_name, ''), m.name, v.sku, '') AS model_name
            FROM ai_orders o
            LEFT JOIN ai_order_items oi ON oi.order_id = o.id
            LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
            LEFT JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE o.tenant_id = ?
              AND o.whatsapp_phone IN ($in)
            ORDER BY o.updated_at DESC, o.id DESC, oi.id DESC
        ");
        $stmt->execute(array_merge([$tenantId], $candidatePhones));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $phone = (string)($row['whatsapp_phone'] ?? '');
            if ($phone === '') {
                continue;
            }

            if (!array_key_exists($phone, $activeByPhone)) {
                $activeByPhone[$phone] = false;
            }
            if (in_array(mb_strtolower((string)($row['status'] ?? ''), 'UTF-8'), $activeStatuses, true)) {
                $activeByPhone[$phone] = true;
            }

            if (!isset($latestProductByPhone[$phone])) {
                $modelName = trim((string)($row['model_name'] ?? ''));
                if ($modelName !== '') {
                    $latestProductByPhone[$phone] = $modelName;
                }
            }
        }
    } catch (Exception $e) {
        return ['active_by_phone' => [], 'latest_product_by_phone' => []];
    }

    return [
        'active_by_phone' => $activeByPhone,
        'latest_product_by_phone' => $latestProductByPhone,
    ];
}

function mia_decode_json_array(?string $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

try {
    $tid = ai_tenant_id();
    $action = (string)($request->post['action'] ?? $request->get['action'] ?? '');

    if ($action === 'list') {
        $search = trim((string)($request->get['search'] ?? $request->post['search'] ?? ''));
        $filter = strtolower(trim((string)($request->get['filter'] ?? $request->post['filter'] ?? 'todas')));
        $date = (string)($request->get['date'] ?? $request->post['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if (!in_array($filter, ['todas', 'ativas', 'manual'], true)) {
            $filter = 'todas';
        }

        $where = ["p.tenant_id = :tid"];
        $params = [":tid" => $tid];

        // Ignorar grupos (JIDs que terminam em @g.us ou números que não pareçam ser de usuário direto se necessário)
        // Como estamos limpando para apenas dígitos, JIDs de grupo antigos podem estar no banco.
        $where[] = "p.whatsapp_phone NOT LIKE '%@g.us'";
        if ($date !== '') {
            $where[] = "DATE(p.last_interaction) = :date";
            $params[':date'] = $date;
        }

        if ($search !== '') {
            $searchClean = mia_phone_digits($search);
            if ($searchClean !== '') {
                $where[] = "(p.name LIKE :s1 OR p.whatsapp_phone LIKE :s2 OR p.whatsapp_phone LIKE :s3)";
                $params[':s1'] = '%' . $search . '%';
                $params[':s2'] = '%' . $search . '%';
                $params[':s3'] = '%' . $searchClean . '%';
            } else {
                $where[] = "(p.name LIKE :s1 OR p.whatsapp_phone LIKE :s2)";
                $params[':s1'] = '%' . $search . '%';
                $params[':s2'] = '%' . $search . '%';
            }
        }

        $sql = "SELECT
                    p.id,
                    p.tenant_id,
                    p.whatsapp_phone,
                    p.name,
                    p.total_interactions,
                    p.last_interaction,
                    p.preferences_json,
                    p.usual_size,
                    p.interesse_duvida,
                    p.conversation_summary
                FROM ai_chat_profiles p
                WHERE " . implode(" AND ", $where) . "
                ORDER BY p.last_interaction DESC
                LIMIT 300";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $profilesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $profilesByPhone = [];
        foreach ($profilesRaw as $profileRow) {
            $phoneKey = mia_profile_phone_key((string)($profileRow['whatsapp_phone'] ?? ''));
            if ($phoneKey === '') {
                continue;
            }

            if (!isset($profilesByPhone[$phoneKey])) {
                $profilesByPhone[$phoneKey] = $profileRow;
                continue;
            }

            $current = $profilesByPhone[$phoneKey];
            $currentScore = mia_rank_profile($current);
            $nextScore = mia_rank_profile($profileRow);
            
            if ($nextScore > $currentScore) {
                $profilesByPhone[$phoneKey] = $profileRow;
            } elseif ($nextScore === $currentScore) {
                $currentTs = strtotime((string)($current['last_interaction'] ?? '')) ?: 0;
                $nextTs = strtotime((string)($profileRow['last_interaction'] ?? '')) ?: 0;
                if ($nextTs > $currentTs) {
                    $profilesByPhone[$phoneKey] = $profileRow;
                }
            }
        }

        $profiles = array_values($profilesByPhone);
        usort($profiles, static function (array $a, array $b): int {
            $aTs = strtotime((string)($a['last_interaction'] ?? '')) ?: 0;
            $bTs = strtotime((string)($b['last_interaction'] ?? '')) ?: 0;
            return $bTs <=> $aTs;
        });
        if (count($profiles) > 100) {
            $profiles = array_slice($profiles, 0, 100);
        }

        // Estatísticas para os cards
        $stats = [
            'total' => count($profiles),
            'novas' => 0,
            'pedidos' => 0,
            'ativas' => 0
        ];

        // Para as estatísticas, precisamos olhar o dia todo sem o filtro de busca/status
        $hasProfileCreatedAt = mia_table_has_column('ai_chat_profiles', 'created_at');

        if ($hasProfileCreatedAt) {
            $stmtStats = db()->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN DATE(created_at) = :d1 THEN 1 ELSE 0 END) as novas,
                    (SELECT COUNT(DISTINCT whatsapp_phone) FROM ai_orders WHERE tenant_id = :tid_orders AND DATE(created_at) = :d2) as pedidos
                FROM ai_chat_profiles
                WHERE tenant_id = :tid_profiles AND DATE(last_interaction) = :d3
            ");
            $stmtStats->execute([
                ':tid_orders' => $tid,
                ':tid_profiles' => $tid,
                ':d1' => $date,
                ':d2' => $date,
                ':d3' => $date,
            ]);
            $resStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
            if ($resStats) {
                $stats['total'] = (int)$resStats['total'];
                $stats['novas'] = (int)$resStats['novas'];
                $stats['pedidos'] = (int)$resStats['pedidos'];
            }
        } else {
            $stmtStats = db()->prepare("
                SELECT
                    COUNT(*) as total,
                    (SELECT COUNT(DISTINCT whatsapp_phone) FROM ai_orders WHERE tenant_id = :tid_orders AND DATE(created_at) = :d2) as pedidos
                FROM ai_chat_profiles
                WHERE tenant_id = :tid_profiles AND DATE(last_interaction) = :d3
            ");
            $stmtStats->execute([
                ':tid_orders' => $tid,
                ':tid_profiles' => $tid,
                ':d2' => $date,
                ':d3' => $date,
            ]);
            $resStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
            if ($resStats) {
                $stats['total'] = (int)$resStats['total'];
                $stats['pedidos'] = (int)$resStats['pedidos'];
            }
        }

        $remoteJids = [];
        $profileCandidates = [];
        $allOrderCandidates = [];
        foreach ($profiles as $idx => $p) {
            $jid = mia_remote_jid_from_phone((string)$p['whatsapp_phone']);
            if ($jid !== '') $remoteJids[] = $jid;
            $candidates = mia_order_phone_candidates((string)($p['whatsapp_phone'] ?? ''));
            $profileCandidates[$idx] = $candidates;
            foreach ($candidates as $candidatePhone) {
                $allOrderCandidates[$candidatePhone] = true;
            }
        }
        $atendimentoMap = ai_evolution_get_atendimento_map($tid, array_values(array_unique($remoteJids)));
        $orderContextMap = mia_build_order_context_map($tid, array_keys($allOrderCandidates));
        $hasActiveOrderByPhone = $orderContextMap['active_by_phone'] ?? [];
        $latestProductByPhone = $orderContextMap['latest_product_by_phone'] ?? [];

        $html = '';
        $visibleCount = 0;
        foreach ($profiles as $idx => $p) {
            $phone = mia_phone_digits((string)$p['whatsapp_phone']);
            $remoteJid = mia_remote_jid_from_phone($phone);
            $att = $atendimentoMap[$remoteJid] ?? ['status' => 'Ativo'];
            $isIA = ($att['status'] ?? 'Ativo') === 'Ativo';
            $lastProduct = mia_extract_last_product($p);
            $hasOrder = false;
            $fallbackLastProduct = '';
            foreach (($profileCandidates[$idx] ?? []) as $candidatePhone) {
                if (!$hasOrder && !empty($hasActiveOrderByPhone[$candidatePhone])) {
                    $hasOrder = true;
                }
                if ($fallbackLastProduct === '' && !empty($latestProductByPhone[$candidatePhone])) {
                    $fallbackLastProduct = (string)$latestProductByPhone[$candidatePhone];
                }
            }
            if (($lastProduct === '' || $lastProduct === 'Não informado') && $fallbackLastProduct !== '') {
                $lastProduct = $fallbackLastProduct;
            }

            if ($filter === 'ativas' && !$isIA) {
                continue;
            }
            if ($filter === 'manual' && $isIA) {
                continue;
            }
            $visibleCount++;
            
            if ($isIA) $stats['ativas']++;

            $statusBadge = $isIA 
                ? '<span class="badge badge-ativa"><i class="fa fa-circle" style="font-size:7px"></i> IA Ativa</span>'
                : '<span class="badge badge-manual"><i class="fa fa-user"></i> Humano</span>';

            $btnClass = $isIA ? 'btn-pause' : 'btn-resume';
            $btnText = $isIA ? '<i class="fa fa-pause"></i> Pausar' : '<i class="fa fa-play"></i> Ativar';
            $targetStatus = $isIA ? 'Manual' : 'Ativo';
            $orderBadge = $hasOrder ? '<span class="badge badge-auto" style="margin-left:5px"><i class="fa fa-shopping-cart"></i> Pedido</span>' : '';

            $html .= '<tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="av av-e">' . htmlspecialchars(mb_substr($p['name'] ?: 'C', 0, 1)) . '</div>
                        <div>
                            <div style="font-weight:700;color:#111827;font-size:13px">' . htmlspecialchars($p['name'] ?: 'Cliente') . $orderBadge . '</div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:1px;display:flex;align-items:center;gap:6px">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#25d366" aria-hidden="true"><path d="M20.52 3.48A11.79 11.79 0 0 0 12.05 0C5.58 0 .3 5.28.3 11.75c0 2.07.54 4.1 1.57 5.9L0 24l6.53-1.82a11.72 11.72 0 0 0 5.52 1.4h.01c6.47 0 11.74-5.27 11.74-11.74 0-3.14-1.22-6.08-3.28-8.36ZM12.06 21.6h-.01a9.8 9.8 0 0 1-5-1.37l-.36-.21-3.87 1.08 1.03-3.77-.24-.39a9.8 9.8 0 0 1-1.5-5.19c0-5.43 4.42-9.85 9.86-9.85 2.63 0 5.11 1.03 6.96 2.9a9.76 9.76 0 0 1 2.88 6.95c0 5.43-4.43 9.85-9.86 9.85Zm5.4-7.37c-.3-.15-1.75-.87-2.02-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.47-.88-.78-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.14-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.91-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.01-1.04 2.47 0 1.46 1.07 2.87 1.22 3.07.15.2 2.1 3.21 5.08 4.5.7.3 1.24.49 1.66.62.7.22 1.33.19 1.83.11.56-.08 1.75-.72 2-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/></svg>
                                <span>' . htmlspecialchars($phone) . '</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight:700;color:#111827;font-size:12.5px">' . htmlspecialchars($lastProduct) . '</div>
                    <div style="font-size:10.5px;color:#9ca3af;margin-top:2px"><i class="fa fa-tag"></i> Último produto solicitado</div>
                </td>
                <td>
                    <div class="inter-num">' . (int)$p['total_interactions'] . '</div>
                    <div class="inter-sub">mensagens trocadas</div>
                </td>
                <td>
                    <div style="font-size:12px;font-weight:700;color:#374151">' . mia_time_ago($p['last_interaction']) . '</div>
                    <div style="font-size:11px;color:#9ca3af">' . date('H:i', strtotime($p['last_interaction'])) . '</div>
                </td>
                <td>' . $statusBadge . '</td>
                <td>
                    <div style="display:flex;gap:5px">
                        <button class="btn ' . $btnClass . ' btn-sm" onclick="toggleIA(\'' . $remoteJid . '\', \'' . $targetStatus . '\')" title="Alternar entre IA e Humano">' . $btnText . '</button>
                        <button class="btn btn-secondary btn-sm" onclick="verMemoria(\'' . addslashes($p['whatsapp_phone']) . '\')" title="Ver memória da IA para este cliente"><i class="fa fa-brain"></i> Memória</button>
                        <button class="btn btn-secondary btn-sm" onclick="limparConversa(\'' . $p['whatsapp_phone'] . '\')" title="Resetar memória da IA para este cliente"><i class="fa fa-eraser"></i> Limpar</button>
                        <button class="btn btn-secondary btn-sm" onclick="deletarConversa(\'' . $p['whatsapp_phone'] . '\')" title="Apagar registro desta conversa"><i class="fa fa-trash" style="color:#ef4444"></i></button>
                        <button class="btn btn-stats btn-sm" onclick="abrirWhatsApp(\'' . $phone . '\')"><i class="fa fa-whatsapp"></i> Chat</button>
                    </div>
                </td>
            </tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#9ca3af">Nenhuma conversa encontrada para este filtro/data.</td></tr>';
        }

        mia_json([
            'error' => false,
            'html' => $html,
            'count' => $visibleCount,
            'stats' => $stats
        ]);
    }

    if ($action === 'clear_memory') {
        $phone = (string)($request->post['phone'] ?? '');
        if ($phone === '') throw new Exception('Telefone inválido.');

        $candidates = mia_order_phone_candidates($phone);
        if (empty($candidates)) $candidates = [$phone];
        $in = implode(',', array_fill(0, count($candidates), '?'));

        // Limpa a memória (preferências, resumos e interações) mantendo o perfil básico (nome)
        $sql = "UPDATE ai_chat_profiles 
                SET preferences_json = NULL, 
                    interesse_duvida = NULL, 
                    conversation_summary = NULL, 
                    usual_size = NULL,
                    total_interactions = 0 
                WHERE tenant_id = ? AND whatsapp_phone IN ($in)";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$tid], $candidates));

        // Também cancelamos pedidos pendentes para que não poluam o contexto da nova conversa
        $sqlOrders = "UPDATE ai_orders SET status = 'cancelado', updated_at = NOW() 
                      WHERE tenant_id = ? AND whatsapp_phone IN ($in) AND status = 'pendente'";
        $stmtOrders = db()->prepare($sqlOrders);
        $stmtOrders->execute(array_merge([$tid], $candidates));
        
        mia_json(['error' => false, 'message' => 'Memória da conversa limpa e pedidos pendentes cancelados.']);
    }

    if ($action === 'delete') {
        $phone = (string)($request->post['phone'] ?? '');
        if ($phone === '') throw new Exception('Telefone inválido.');

        $candidates = mia_order_phone_candidates($phone);
        if (empty($candidates)) $candidates = [$phone];
        $in = implode(',', array_fill(0, count($candidates), '?'));

        // 1. Buscar IDs dos pedidos para limpar itens
        $stmtIds = db()->prepare("SELECT id FROM ai_orders WHERE tenant_id = ? AND whatsapp_phone IN ($in)");
        $stmtIds->execute(array_merge([$tid], $candidates));
        $orderIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($orderIds)) {
            $inIds = implode(',', array_fill(0, count($orderIds), '?'));
            // 2. Deletar itens dos pedidos
            db()->prepare("DELETE FROM ai_order_items WHERE order_id IN ($inIds)")->execute($orderIds);
            // 3. Deletar os pedidos
            db()->prepare("DELETE FROM ai_orders WHERE id IN ($inIds)")->execute($orderIds);
        }

        // 4. Deletar o perfil
        $sql = "DELETE FROM ai_chat_profiles WHERE tenant_id = ? AND whatsapp_phone IN ($in)";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$tid], $candidates));
        
        mia_json(['error' => false, 'message' => 'Todas as informações do cliente e pedidos foram removidas.']);
    }

    if ($action === 'get_memory') {
        $phone = trim((string)($request->get['phone'] ?? $request->post['phone'] ?? ''));
        if ($phone === '') {
            throw new Exception('Telefone inválido.');
        }
        $profile = ai_evolution_get_customer_memory($tid, $phone);
        if (!$profile) {
            $hasInteresse = mia_table_has_column('ai_chat_profiles', 'interesse_duvida');
            $hasSummary = mia_table_has_column('ai_chat_profiles', 'conversation_summary');
            $sql = "
                SELECT name, whatsapp_phone, preferences_json, total_interactions, last_interaction" .
                ($hasInteresse ? ", interesse_duvida" : ", '' AS interesse_duvida") .
                ($hasSummary ? ", conversation_summary" : ", '' AS conversation_summary") . "
                FROM ai_chat_profiles
                WHERE tenant_id = :tid AND whatsapp_phone = :phone
                LIMIT 1
            ";
            $stmt = db()->prepare($sql);
            $stmt->execute([':tid' => $tid, ':phone' => $phone]);
            $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fallback) {
                throw new Exception('Perfil não encontrado para este contato.');
            }

            $profile = [
                'pushName' => (string)($fallback['name'] ?? 'Cliente'),
                'remoteJid' => (string)($fallback['whatsapp_phone'] ?? ''),
                'preferences' => mia_decode_json_array($fallback['preferences_json'] ?? ''),
                'interesse_duvida' => (string)($fallback['interesse_duvida'] ?? ''),
                'conversation_summary' => (string)($fallback['conversation_summary'] ?? ''),
                'total_interactions' => (int)($fallback['total_interactions'] ?? 0),
                'last_interaction' => (string)($fallback['last_interaction'] ?? ''),
            ];
        }

        $memory = is_array($profile['preferences'] ?? null) ? $profile['preferences'] : [];
        $userUpdate = trim((string)($profile['interesse_duvida'] ?? ''));
        $conversationSummary = trim((string)($profile['conversation_summary'] ?? ''));

        // Normalização de nomes de campos de memória para o frontend
        if ($userUpdate !== '') {
            $memory['atualizar_usuario'] = $userUpdate;
        }
        if ($conversationSummary !== '') {
            $memory['resumo_conversa'] = $conversationSummary;
        }

        // Adicionar campos de perfil se existirem
        if (!empty($profile['usual_size'])) {
            $memory['tamanho_usual'] = $profile['usual_size'];
        }

        mia_json([
            'error' => false,
            'profile' => [
                'name' => (string)($profile['pushName'] ?? 'Cliente'),
                'phone' => (string)($profile['remoteJid'] ?? $phone),
                'total_interactions' => (int)($profile['total_interactions'] ?? 0),
                'last_interaction' => (string)($profile['last_interaction'] ?? ''),
                'interesse_duvida' => $userUpdate,
                'conversation_summary' => $conversationSummary,
                'usual_size' => (string)($profile['usual_size'] ?? ''),
            ],
            'memory' => $memory
        ]);
    }

    if ($action === 'toggle_atendimento') {
        $remoteJid = (string)($request->post['remote_jid'] ?? '');
        $status = (string)($request->post['status'] ?? '');

        if ($remoteJid === '' || ($status !== 'Ativo' && $status !== 'Manual')) {
            throw new Exception('Parâmetros inválidos.');
        }

        $uid = function_exists('user_id') ? (int)user_id() : 0;
        ai_evolution_set_atendimento_status($tid, $remoteJid, $status, $uid);
        mia_json(['error' => false, 'message' => 'Status da IA atualizado.']);
    }

    throw new Exception('Ação inválida.');
} catch (Exception $e) {
    mia_json(['error' => true, 'message' => $e->getMessage()], 422);
}
