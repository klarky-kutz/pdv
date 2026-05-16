<?php
/**
 * API: api/concierge/perfil_cliente.php
 * Consulta ou atualiza o perfil de um cliente no WhatsApp (Moda IA).
 * 
 * GET:
 *   phone    = string (ex: +5511999999999)
 * POST:
 *   phone    = string
 *   name     = string (opcional)
 *   usual_size = string (opcional)
 *   preferences = string (json opcional)
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

try {
    $phone = trim($request->get['phone'] ?? $request->post['phone'] ?? '');
    
    // Ignorar grupos
    if (strpos($phone, '@g.us') !== false) {
        echo json_encode(['error' => false, 'message' => 'Grupos são ignorados.', 'profile' => null]);
        exit;
    }

    // Padroniza: Apenas dígitos para evitar duplicidade
    $phone = preg_replace('/\D+/', '', $phone);

    if (!$phone) {
        throw new Exception('Número de WhatsApp não informado.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // UPSERT
        $name    = trim($request->post['name'] ?? $request->get['name'] ?? '');
        
        // Bloquear "Você" como nome (visto que é um placeholder de auto-mensagem do WhatsApp)
        if ($name === 'Você' || $name === 'Voce') {
            $name = '';
        }

        $size    = trim($request->post['usual_size'] ?? $request->get['usual_size'] ?? '');
        $pref    = trim($request->post['preferences'] ?? $request->get['preferences'] ?? '');
        $int_duv = trim($request->post['interesse_duvida'] ?? $request->get['interesse_duvida'] ?? '');

        // Se o JSON vier com aspas convertidas por segurança, decodifica antes de validar
        $prefDecoded = htmlspecialchars_decode($pref);
        
        // Validar JSON se for enviado
        if ($pref && !is_array(json_decode($prefDecoded, true))) {
            $pref = '{}'; // Garante JSON válido se falhar
        } else {
            $pref = $prefDecoded ?: '{}'; // Se estiver vazio, envia {} para evitar erro de constraint
        }

        // Se o nome foi informado, garantir que ele também conste no JSON de preferências (longo prazo)
        if ($name !== '') {
            $decodedPref = json_decode($pref, true) ?: [];
            $decodedPref['nome_informado'] = $name;
            $pref = json_encode($decodedPref);
        }

        $stmt = db()->prepare("
            INSERT INTO ai_chat_profiles (tenant_id, whatsapp_phone, name, usual_size, preferences_json, interesse_duvida, last_interaction, total_interactions)
            VALUES (:tid, :phone, :name, :size, :pref, :int_duv, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
                name = COALESCE(NULLIF(:name2, ''), name),
                usual_size = COALESCE(NULLIF(:size2, ''), usual_size),
                preferences_json = COALESCE(NULLIF(:pref2, ''), preferences_json),
                interesse_duvida = COALESCE(NULLIF(:int_duv2, ''), interesse_duvida),
                last_interaction = NOW(),
                total_interactions = total_interactions + 1
        ");
        $stmt->execute([
            ':tid'      => $tid,
            ':phone'    => $phone,
            ':name'     => $name,
            ':size'     => $size,
            ':pref'     => $pref,
            ':int_duv'  => $int_duv,
            ':name2'    => $name,
            ':size2'    => $size,
            ':pref2'     => $pref,
            ':int_duv2' => $int_duv,
        ]);

        // Buscar o perfil atualizado para retornar
        $stmt = db()->prepare("
            SELECT * FROM ai_chat_profiles 
            WHERE tenant_id = :tid AND whatsapp_phone = :phone 
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tid, ':phone' => $phone]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profile) {
            $profile['preferences_json'] = json_decode($profile['preferences_json'] ?? '{}', true);
        }

        echo json_encode([
            'error' => false, 
            'message' => 'Perfil atualizado com sucesso.',
            'profile' => $profile
        ]);
    } else {
        // GET
        $stmt = db()->prepare("
            SELECT * FROM ai_chat_profiles 
            WHERE tenant_id = :tid AND whatsapp_phone = :phone 
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tid, ':phone' => $phone]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profile) {
            $profile['preferences_json'] = json_decode($profile['preferences_json'] ?? '[]', true);
            echo json_encode(['error' => false, 'profile' => $profile]);
        } else {
            echo json_encode(['error' => false, 'profile' => null]);
        }
    }

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
