<?php
/**
 * API: api/concierge/status_atendimento.php
 * Consulta ou atualiza o modo de atendimento da conversa (Ativo/Manual).
 *
 * GET:
 *   - remote_jid ou phone
 *
 * POST:
 *   - remote_jid ou phone
 *   - status (Ativo|Manual)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

try {
    $remoteJid = trim((string)($request->get['remote_jid'] ?? $request->post['remote_jid'] ?? ''));
    $phone = trim((string)($request->get['phone'] ?? $request->post['phone'] ?? ''));
    $statusRaw = trim((string)($request->post['status'] ?? $request->get['status'] ?? ''));
    $userId = (int)($request->post['takeover_by_user_id'] ?? $request->get['takeover_by_user_id'] ?? 0);

    if ($remoteJid === '' && $phone !== '') {
        $digits = ai_evolution_number_from_jid($phone);
        if ($digits !== '') {
            $remoteJid = $digits . '@s.whatsapp.net';
        }
    }

    if ($remoteJid === '') {
        throw new Exception('Informe remote_jid ou phone.');
    }

    $isUpdate = $_SERVER['REQUEST_METHOD'] === 'POST' || $statusRaw !== '';
    if ($isUpdate) {
        $normalized = strtolower($statusRaw);
        if (in_array($normalized, ['manual', 'humano', 'human'], true)) {
            $status = 'Manual';
        } elseif (in_array($normalized, ['ativo', 'active', 'ia', 'on', '1'], true)) {
            $status = 'Ativo';
        } else {
            throw new Exception('Status inválido. Use Ativo ou Manual.');
        }

        ai_evolution_set_atendimento_status($tid, $remoteJid, $status, $userId);
    }

    $current = ai_evolution_get_atendimento_status($tid, $remoteJid);

    echo json_encode([
        'error' => false,
        'remote_jid' => $remoteJid,
        'status' => (string)($current['status'] ?? 'Ativo'),
        'takeover_by_user_id' => (int)($current['takeover_by_user_id'] ?? 0),
        'updated_at' => $current['updated_at'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
