<?php
namespace AngularFilemanager\LocalBridge;

use DateTime;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * File Manager API Class
 *
 * Made for PHP Local filesystem bridge for angular-filemanager to handle file manipulations
 * @author Jakub Ďuraš <jakub@duras.me>
 */
class FileManagerApi
{
    private $basePath = null;

    private $translate;

    public function __construct($basePath = null, $lang = 'en', $muteErrors = true)
    {
        if ($muteErrors) {
            ini_set('display_errors', 0);
        }

        $this->basePath = $this->canonicalizePath($basePath ?: dirname(__DIR__) . '/../../files');
        $this->translate = new Translate($lang);
    }
    
    /**
     * Valida se o caminho está dentro do basePath do tenant
     * Impede path traversal (/../) para acessar arquivos de outros tenants
     * 
     * @param string $path Caminho a validar
     * @return bool True se o caminho é seguro, false caso contrário
     */
    private function isPathSafe($path)
    {
        // Canonicaliza o path para resolver .. e .
        $realPath = $this->canonicalizePath($this->basePath . DIRECTORY_SEPARATOR . $path);
        
        // Verifica se o realPath começa com o basePath
        $basePathNormalized = rtrim($this->basePath, DIRECTORY_SEPARATOR);
        
        // O path é seguro se começa com o basePath
        return strpos($realPath, $basePathNormalized) === 0;
    }
    
    /**
     * Obtém o caminho seguro dentro do basePath
     * Lança exceção se o caminho tentar sair do basePath
     * 
     * @param string $relativePath Caminho relativo
     * @return string Caminho absoluto seguro
     * @throws \Exception Se o caminho tentar sair do basePath
     */
    private function getSecurePath($relativePath)
    {
        if (!$this->isPathSafe($relativePath)) {
            error_log('[FileManager] Tentativa de acesso a path não permitido: ' . $relativePath);
            throw new \Exception('Access denied: path outside allowed directory');
        }
        
        return $this->canonicalizePath($this->basePath . DIRECTORY_SEPARATOR . $relativePath);
    }

    public function postHandler($query, $request, $files)
    {
        $t = $this->translate;
        // Probably file upload
        if (!isset($request['action'])
            && (isset($_SERVER["CONTENT_TYPE"])
            && strpos($_SERVER["CONTENT_TYPE"], 'multipart/form-data') !== false)
        ) {
            $uploaded = $this->uploadAction($request['destination'], $files);
            if ($uploaded === true) {
                $response = $this->simpleSuccessResponse();
            } else {
                $response = $this->simpleErrorResponse($t->upload_failed);
            }

            return $response;
        }

        switch ($request['action']) {
            case 'list':
                $list = $this->listAction($request['path']);

                if (!is_array($list)) {
                    $response = $this->simpleErrorResponse($t->listing_filed);
                } else {
                    $response = new Response();
                    $response->setData([
                        'result' => $list
                    ]);
                }
                break;

            case 'rename':
                $renamed = $this->renameAction($request['item'], $request['newItemPath']);
                if ($renamed === true) {
                    $response = $this->simpleSuccessResponse();
                } elseif ($renamed === 'notfound') {
                    $response = $this->simpleErrorResponse($t->file_not_found);
                } else {
                    $response = $this->simpleErrorResponse($t->renaming_failed);
                }
                break;

            case 'move':
                $moved = $this->moveAction($request['items'], $request['newPath']);
                if ($moved === true) {
                    $response = $this->simpleSuccessResponse();
                } else {
                    $response = $this->simpleErrorResponse($t->moving_failed);
                }
                break;

            case 'copy':
                $copied = $this->copyAction($request['items'], $request['newPath']);
                if ($copied === true) {
                    $response = $this->simpleSuccessResponse();
                } else {
                    $response = $this->simpleErrorResponse($t->copying_failed);
                }
                break;

            case 'remove':
                $removed = $this->removeAction($request['items']);
                if ($removed === true) {
                    $response = $this->simpleSuccessResponse();
                } elseif ($removed === 'notempty') {
                    $response = $this->simpleErrorResponse($t->removing_failed_directory_not_empty);
                } else {
                    $response = $this->simpleErrorResponse($t->removing_failed);
                }
                break;

            case 'edit':
                $edited = $this->editAction($request['item'], $request['content']);
                if ($edited !== false) {
                    $response = $this->simpleSuccessResponse();
                } else {
                    $response = $this->simpleErrorResponse($t->saving_failed);
                }
                break;

            case 'getContent':
                $content = $this->getContentAction($request['item']);
                if ($content !== false) {
                    $response = new Response();
                    $response->setData([
                        'result' => $content
                    ]);
                } else {
                    $response = $this->simpleErrorResponse($t->file_not_found);
                }
                break;

            case 'createFolder':
                $created = $this->createFolderAction($request['newPath']);
                if ($created === true) {
                    $response = $this->simpleSuccessResponse();
                } elseif ($created === 'exists') {
                    $response = $this->simpleErrorResponse($t->folder_already_exists);
                } else {
                    $response = $this->simpleErrorResponse($t->folder_creation_failed);
                }
                break;

            case 'changePermissions':
                $changed = $this->changePermissionsAction($request['items'], $request['perms'], $request['recursive']);
                if ($changed === true) {
                    $response = $this->simpleSuccessResponse();
                } elseif ($changed === 'missing') {
                    $response = $this->simpleErrorResponse($t->file_not_found);
                } else {
                    $response = $this->simpleErrorResponse($t->permissions_change_failed);
                }
                break;

            case 'compress':
                $compressed = $this->compressAction(
                    $request['items'],
                    $request['destination'],
                    $request['compressedFilename']
                );
                if ($compressed === true) {
                    $response = $this->simpleSuccessResponse();
                } else {
                    $response = $this->simpleErrorResponse($t->compression_failed);
                }
                break;

            case 'extract':
                $extracted = $this->extractAction($request['destination'], $request['item'], $request['folderName']);
                if ($extracted === true) {
                    $response = $this->simpleSuccessResponse();
                } elseif ($extracted === 'unsupported') {
                    $response = $this->simpleErrorResponse($t->archive_opening_failed);
                } else {
                    $response = $this->simpleErrorResponse($t->extraction_failed);
                }
                break;
            
            default:
                $response = $this->simpleErrorResponse($t->function_not_implemented);
                break;
        }

        return $response;
    }

    public function getHandler($queries)
    {
        $t = $this->translate;

        if (empty($queries['action'])) {
        	return $this->simpleErrorResponse($t->function_not_implemented);
        }

        switch ($queries['action']) {
            case 'download':
                $downloaded = $this->downloadAction($queries['path']);
                if ($downloaded === true) {
                    exit;
                } else {
                    $response = $this->simpleErrorResponse($t->file_not_found);
                }
                
                break;

            case 'downloadMultiple':
                $downloaded = $this->downloadMultipleAction($queries['items'], $queries['toFilename']);
                if ($downloaded === true) {
                    exit;
                } else {
                    $response = $this->simpleErrorResponse($t->file_not_found);
                }

                break;

            default:
                $response = $this->simpleErrorResponse($t->function_not_implemented);
                break;
        }

        return $response;
    }

    private function downloadAction($path)
    {
        $file_name = basename($path);
        
        // Validação de segurança
        try {
            $securePath = $this->getSecurePath($path);
        } catch (\Exception $e) {
            return false;
        }

        if (!file_exists($securePath)) {
            return false;
        }
        $path = $securePath;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header("Content-Disposition: attachment; filename=\"$file_name\"");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Content-Type: $mime_type");
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);

        return true;
    }

    private function downloadMultipleAction($items, $archiveName)
    {
        $archivePath = tempnam('../', 'archive');

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            unlink($archivePath);
            return false;
        }

        foreach ($items as $path) {
            $zip->addFile($this->basePath . $path, basename($path));
        }

        $zip->close();

        header("Content-Disposition: attachment; filename=\"$archiveName\"");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Content-Type: application/zip");
        header('Pragma: public');
        header('Content-Length: ' . filesize($archivePath));
        readfile($archivePath);

        unlink($archivePath);

        return true;
    }

    private function uploadAction($path, $files)
    {
        // Validação de segurança
        try {
            $securePath = $this->getSecurePath($path);
        } catch (\Exception $e) {
            error_log('[FileManager] Upload bloqueado - path fora do permitido: ' . $path);
            return false;
        }
        $path = $securePath;
        
        // Verifica se o diretório de destino existe
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        
        // Verifica se há arquivos para processar
        if (empty($_FILES)) {
            error_log('[FileManager] Nenhum arquivo recebido em \$_FILES');
            return false;
        }

        // Extensões permitidas (expandidas)
        $allowedExtensions = array(
            'jpg', 'jpeg', 'gif', 'svg', 'png', 'webp', 'ico', 'bmp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
            'mp4', 'mov', 'webm', 'avi',
            'zip', 'rar'
        );

        foreach ($_FILES as $key => $file) {
            // Verifica se o upload teve erro
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                error_log('[FileManager] Arquivo vazio ou inválido: ' . $key);
                continue;
            }
            
            if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                error_log('[FileManager] Erro de upload: ' . $file['error'] . ' para arquivo: ' . ($file['name'] ?? 'desconhecido'));
                return false;
            }
            
            $fileInfo = pathinfo($file['name']);
            
            // Verifica se a extensão existe
            $extension = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';
            
            if (empty($extension) || !in_array($extension, $allowedExtensions)) {
                error_log('[FileManager] Extensão não permitida: ' . $extension . ' para arquivo: ' . $file['name']);
                return false;
            }
            
            $fileName = $this->normalizeName($fileInfo['filename']) . '.' . $extension;
            $destPath = $path . DIRECTORY_SEPARATOR . $fileName;
            
            $uploaded = move_uploaded_file($file['tmp_name'], $destPath);
            
            if ($uploaded === false) {
                error_log('[FileManager] Falha ao mover arquivo: ' . $file['tmp_name'] . ' para ' . $destPath);
                return false;
            }
        }

        return true;
    }

    private function listAction($path)
    {
        // Validação de segurança - impede acesso fora da pasta do tenant
        try {
            $securePath = $this->getSecurePath($path);
        } catch (\Exception $e) {
            return false;
        }
        
        if (!is_dir($securePath)) {
            return false;
        }
        
        $files = array_values(array_filter(
            scandir($securePath),
            function ($item) {
                return !($item === '.' || $item === '..');
            }
        ));

        return array_map(function ($file) use ($securePath) {
            $filePath = $securePath . DIRECTORY_SEPARATOR . $file;
            $date = new DateTime('@' . filemtime($filePath));

            return [
                'name' => basename($filePath),
                'rights' => $this->parsePerms(fileperms($filePath)),
                'size' => filesize($filePath),
                'date' => $date->format('Y-m-d H:i:s'),
                'type' => is_dir($filePath) ? 'dir' : 'file'
            ];
        }, $files);
    }

    private function renameAction($oldPath, $newPath)
    {
    	return false;
    }

    private function moveAction($oldPaths, $newPath)
    {
        // Validação de segurança do destino
        try {
            $secureNewPath = $this->getSecurePath($newPath) . DIRECTORY_SEPARATOR;
        } catch (\Exception $e) {
            return false;
        }

        foreach ($oldPaths as $oldPath) {
            // Validação de segurança da origem
            try {
                $secureOldPath = $this->getSecurePath($oldPath);
            } catch (\Exception $e) {
                return false;
            }
            
            if (!file_exists($secureOldPath)) {
                return false;
            }

            $renamed = rename($secureOldPath, $secureNewPath . basename($oldPath));
            if ($renamed === false) {
                return false;
            }
        }

        return true;
    }

    private function copyAction($oldPaths, $newPath)
    {
        // Validação de segurança do destino
        try {
            $secureNewPath = $this->getSecurePath($newPath) . DIRECTORY_SEPARATOR;
        } catch (\Exception $e) {
            return false;
        }

        foreach ($oldPaths as $oldPath) {
            // Validação de segurança da origem
            try {
                $secureOldPath = $this->getSecurePath($oldPath);
            } catch (\Exception $e) {
                return false;
            }
            
            if (!file_exists($secureOldPath)) {
                return false;
            }

            $copied = copy($secureOldPath, $secureNewPath . basename($oldPath));
            if ($copied === false) {
                return false;
            }
        }

        return true;
    }

    private function removeAction($paths)
    {
        foreach ($paths as $relativePath) {
            // Validação de segurança
            try {
                $path = $this->getSecurePath($relativePath);
            } catch (\Exception $e) {
                return false;
            }

            if (is_dir($path)) {
                $dirEmpty = (new \FilesystemIterator($path))->valid();

                if ($dirEmpty) {
                    return 'notempty';
                } else {
                    $removed = rmdir($path);
                }
            } else {
                $removed = unlink($path);
            }

            if ($removed === false) {
                return false;
            }
        }

        return true;
    }

    private function editAction($path, $content)
    {
    	return false;
    }

    private function getContentAction($path)
    {
        // Validação de segurança
        try {
            $securePath = $this->getSecurePath($path);
        } catch (\Exception $e) {
            return false;
        }

        if (! file_exists($securePath)) {
            return false;
        }

        return file_get_contents($securePath);
    }

    private function createFolderAction($path)
    {
        // Validação de segurança
        try {
            $securePath = $this->getSecurePath($path);
        } catch (\Exception $e) {
            return false;
        }

        if (file_exists($securePath) && is_dir($securePath)) {
            return 'exists';
        }

        return mkdir($securePath);
    }

    private function changePermissionsAction($paths, $permissions, $recursive)
    {
        foreach ($paths as $path) {
            if (!file_exists($this->basePath . $path)) {
                return 'missing';
            }

            if (is_dir($path) && $recursive === true) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    $changed = chmod($this->basePath . $item, octdec($permissions));
                    
                    if ($changed === false) {
                        return false;
                    }
                }
            }

            return chmod($this->basePath . $path, octdec($permissions));
        }
    }

    private function compressAction($paths, $destination, $archiveName)
    {
        $archivePath = $this->basePath . $destination . $archiveName;

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            return false;
        }

        foreach ($paths as $path) {
            $fullPath = $this->basePath . $path;

            if (is_dir($fullPath)) {
                $dirs = [
                    [
                        'dir' => basename($path),
                        'path' => $this->canonicalizePath($this->basePath . $path),
                    ]
                ];

                while (count($dirs)) {
                    $dir = current($dirs);
                    $zip->addEmptyDir($dir['dir']);

                    $dh = opendir($dir['path']);
                    while ($file = readdir($dh)) {
                        if ($file != '.' && $file != '..') {
                            $filePath = $dir['path'] . DIRECTORY_SEPARATOR . $file;
                            if (is_file($filePath)) {
                                $zip->addFile(
                                    $dir['path'] . DIRECTORY_SEPARATOR . $file,
                                    $dir['dir'] . '/' . basename($file)
                                );
                            } elseif (is_dir($filePath)) {
                                $dirs[] = [
                                    'dir' => $dir['dir'] . '/' . $file,
                                    'path' => $dir['path'] . DIRECTORY_SEPARATOR . $file
                                ];
                            }
                        }
                    }
                    closedir($dh);
                    array_shift($dirs);
                }
            } else {
                $zip->addFile($path, basename($path));
            }
        }

        return $zip->close();
    }

    private function extractAction($destination, $archivePath, $folderName)
    {
        $archivePath = $this->basePath . $archivePath;
        $folderPath = $this->basePath . $this->canonicalizePath($destination) . DIRECTORY_SEPARATOR . $folderName;

        $zip = new ZipArchive;
        if ($zip->open($archivePath) === false) {
            return 'unsupported';
        }

        mkdir($folderPath);
        $zip->extractTo($folderPath);
        return $zip->close();
    }

    private function simpleSuccessResponse()
    {
        $response = new Response();
        $response->setData([
            'result' => [
                'success' => true
            ]
        ]);

        return $response;
    }

    private function simpleErrorResponse($message)
    {
        $response = new Response();
        $response
            ->setStatus(500, 'Internal Server Error')
            ->setData([
                'result' => [
                    'success' => false,
                    'error' => $message
                ]
            ]);

        return $response;
    }

    private function parsePerms($perms)
    {
        if (($perms & 0xC000) == 0xC000) {
            // Socket
            $info = 's';
        } elseif (($perms & 0xA000) == 0xA000) {
            // Symbolic Link
            $info = 'l';
        } elseif (($perms & 0x8000) == 0x8000) {
            // Regular
            $info = '-';
        } elseif (($perms & 0x6000) == 0x6000) {
            // Block special
            $info = 'b';
        } elseif (($perms & 0x4000) == 0x4000) {
            // Directory
            $info = 'd';
        } elseif (($perms & 0x2000) == 0x2000) {
            // Character special
            $info = 'c';
        } elseif (($perms & 0x1000) == 0x1000) {
            // FIFO pipe
            $info = 'p';
        } else {
            // Unknown
            $info = 'u';
        }

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ?
                    (($perms & 0x0800) ? 's' : 'x' ) :
                    (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ?
                    (($perms & 0x0400) ? 's' : 'x' ) :
                    (($perms & 0x0400) ? 'S' : '-'));

        // World
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ?
                    (($perms & 0x0200) ? 't' : 'x' ) :
                    (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

    private function canonicalizePath($path)
    {
        $dirSep = DIRECTORY_SEPARATOR;
        $wrongDirSep = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';

        // Replace incorrect dir separators
        $path = str_replace($wrongDirSep, $dirSep, $path);

        $path = explode($dirSep, $path);
        $stack = array();
        foreach ($path as $seg) {
            if ($seg == '..') {
                // Ignore this segment, remove last segment from stack
                array_pop($stack);
                continue;
            }

            if ($seg == '.') {
                // Ignore this segment
                continue;
            }

            $stack[] = $seg;
        }

        // Remove last /
        if (empty($stack[count($stack) - 1])) {
            array_pop($stack);
        }

        return implode($dirSep, $stack);
    }

    /**
    * Creates ASCII name
    *
    * @param string $name name encoded in UTF-8
    * @return string name containing only numbers, chars without diacritics, underscore and dash
    * @copyright Jakub Vrána, https://php.vrana.cz/
    */
    private function normalizeName($name)
    {
        $name = preg_replace('~[^\\pL0-9_]+~u', '-', $name);
        $name = trim($name, "-");
        $name = iconv("utf-8", "us-ascii//TRANSLIT", $name);
        $name = preg_replace('~[^-a-z0-9_]+~', '', $name);
        return $name;
    }
}
