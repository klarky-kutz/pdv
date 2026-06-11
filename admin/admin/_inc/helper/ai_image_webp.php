<?php
/**
 * Helper: ai_image_webp.php
 * Conversão e upload de imagens para WebP — Módulo Moda IA
 */

/**
 * Converte uma imagem existente no servidor para WebP.
 *
 * @param  string $sourcePath  Caminho absoluto da imagem original
 * @param  string $destPath    Caminho absoluto de destino (.webp)
 * @param  int    $quality     Qualidade (0-100), padrão 82
 * @return bool   true em sucesso
 */
function ai_convert_to_webp(string $sourcePath, string $destPath, int $quality = 82): bool
{
    if (!extension_loaded('gd')) {
        // Fallback: se GD não estiver carregado, apenas movemos o arquivo sem converter
        // Mas como o destino termina em .webp, isso pode ser um problema.
        // Melhor retornar false e deixar o chamador lidar.
        return false;
    }

    if (!file_exists($sourcePath)) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        return false;
    }

    $mime = $info['mime'] ?? '';

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $img = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $img = imagecreatefrompng($sourcePath);
            if ($img) {
                // Preservar transparência PNG
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
            }
            break;
        case 'image/webp':
            $img = imagecreatefromwebp($sourcePath);
            break;
        case 'image/gif':
            $img = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$img) {
        return false;
    }

    // Garantir diretório de destino
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $ok = imagewebp($img, $destPath, $quality);
    imagedestroy($img);
    return $ok;
}

/**
 * Processa um upload de arquivo de imagem, converte para WebP e salva.
 *
 * @param  array  $file        Entrada de $_FILES['campo']
 * @param  string $destDir     Diretório de destino absoluto (sem barra final)
 * @param  string $filename    Nome do arquivo sem extensão (ex: 'model_5')
 * @param  int    $quality     Qualidade WebP
 * @param  int    $maxSizeKB   Tamanho máximo em KB (padrão 4096 = 4MB)
 * @return array  ['ok' => bool, 'path' => string|null, 'url' => string|null, 'error' => string|null, 'kb' => float]
 */
function ai_save_upload_webp(array $file, string $destDir, string $filename, int $quality = 82, int $maxSizeKB = 4096): array
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco',
        ];
        $code    = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $message = $errorMessages[$code] ?? 'Erro de upload desconhecido';
        return ['ok' => false, 'path' => null, 'url' => null, 'error' => $message, 'kb' => 0];
    }

    // Validar tipo MIME
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
    } else if (function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
    } else {
        $mime = $file['type'] ?? '';
    }

    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        return ['ok' => false, 'path' => null, 'url' => null, 'error' => 'Tipo de arquivo não permitido (' . $mime . '). Use JPEG, PNG, WebP ou GIF.', 'kb' => 0];
    }

    // Validar tamanho
    $sizeKB = $file['size'] / 1024;
    if ($sizeKB > $maxSizeKB) {
        return ['ok' => false, 'path' => null, 'url' => null, 'error' => "Arquivo muito grande ({$sizeKB}KB). Máximo: {$maxSizeKB}KB.", 'kb' => 0];
    }

    // Garantir diretório de destino
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $destPath = $destDir . '/' . $filename . '.webp';
    $tmpPath  = $file['tmp_name'];

    if ($mime === 'image/webp') {
        // Se já é WebP, apenas mover (sem recomprimir desnecessariamente)
        $ok = move_uploaded_file($tmpPath, $destPath);
        if (!$ok) {
            return ['ok' => false, 'path' => null, 'url' => null, 'error' => 'Falha ao mover arquivo WebP.', 'kb' => 0];
        }
    } else {
        $ok = ai_convert_to_webp($tmpPath, $destPath, $quality);
        if (!$ok) {
            return ['ok' => false, 'path' => null, 'url' => null, 'error' => 'Falha ao converter imagem para WebP.', 'kb' => 0];
        }
    }

    $finalKB = round(filesize($destPath) / 1024, 2);

    // Calcular URL absoluta
    $destPathNorm    = str_replace('\\', '/', $destPath);
    $storageRootNorm = rtrim(str_replace('\\', '/', DIR_STORAGE), '/') . '/';
    $relativePath    = strpos($destPathNorm, $storageRootNorm) === 0
        ? substr($destPathNorm, strlen($storageRootNorm))
        : ltrim($destPathNorm, '/');
    $storage_url = rtrim(ROOT_URL, '/') . '/storage/';
    $url = $storage_url . $relativePath;

    return [
        'ok'    => true,
        'path'  => $destPath,
        'url'   => $url,
        'error' => null,
        'kb'    => $finalKB,
    ];
}

/**
 * Exclui uma imagem WebP do storage com segurança.
 * Verifica se o path está dentro de DIR_STORAGE para evitar path traversal.
 *
 * @param  string $path Caminho absoluto do arquivo
 * @return bool
 */
function ai_delete_webp(string $path): bool
{
    if (empty($path)) {
        return false;
    }

    // Segurança: o arquivo deve estar dentro de DIR_STORAGE
    $realPath    = realpath($path);
    $realStorage = realpath(DIR_STORAGE);
    if (!$realPath || !$realStorage || strpos($realPath, $realStorage) !== 0) {
        return false;
    }

    if (is_file($realPath)) {
        return unlink($realPath);
    }

    return false;
}
