<?php
session_start();
require_once __DIR__ . '/session_presence.php';
enforceSessionIdleTimeout();
recordSessionPresence();
require_once __DIR__ . '/folder_access.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }

session_write_close(); // Release session lock
set_time_limit(0); // Remove execution time limit
ini_set('memory_limit', '-1');

$fileParam = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
if ($fileParam === '') {
    http_response_code(400);
    die("<h2 style='color:red;'>[Error] File path not provided.</h2>");
}

// Prevent null-byte injection
if (strpos($fileParam, "\0") !== false) {
    http_response_code(400);
    die("<h2 style='color:red;'>[Error] Invalid path.</h2>");
}

// URL decode and normalize slashes
$fileParam = urldecode($fileParam);
$fileParam = str_replace('\\', '/', $fileParam);
$fileParam = preg_replace('#/+#', '/', $fileParam);
$cleanPath = trim($fileParam, '/');

// Strictly block directory traversal (..)
if ($cleanPath === '.' || $cleanPath === '..' || preg_match('#(^|/)\.\.?(/|$)#', $cleanPath)) {
    http_response_code(403);
    die("<h2 style='color:red;'>[Error] Access denied: Invalid path traversal.</h2>");
}

$basePath = QPLUS_STORAGE_DIR;
$filePath = $basePath . $cleanPath;
$systemFilePath = function_exists('mb_convert_encoding') ? mb_convert_encoding($filePath, 'CP949', 'UTF-8') : $filePath;

if (!file_exists($systemFilePath)) {
    http_response_code(404);
    die("<h2 style='color:red;'>[Error] File or directory not found.</h2>");
}

// Block symlink downloads
if (is_link($systemFilePath)) {
    http_response_code(403);
    die("<h2 style='color:red;'>[Error] Symlink downloads are not permitted.</h2>");
}

// realpath validation: target path must strictly reside under QPLUS_STORAGE_DIR
$baseRealPath = realpath($basePath);
$targetRealPath = realpath($systemFilePath);

if ($baseRealPath === false || $targetRealPath === false) {
    http_response_code(404);
    die("<h2 style='color:red;'>[Error] Could not resolve path.</h2>");
}

$baseRealPath = rtrim(str_replace('\\', '/', $baseRealPath), '/');
$targetRealPath = rtrim(str_replace('\\', '/', $targetRealPath), '/');
$basePrefix = $baseRealPath . '/';

if (strcasecmp($targetRealPath, $baseRealPath) === 0
    || strncasecmp($targetRealPath, $basePrefix, strlen($basePrefix)) !== 0) {
    http_response_code(403);
    die("<h2 style='color:red;'>[Error] Access denied: Path outside storage root.</h2>");
}

if ($_SESSION['role'] !== 'admin') {
    $config = include __DIR__ . '/config.php';
    $folderPasswords = $config['folder_pws'] ?? array();
    $protectedPath = is_dir($systemFilePath) ? $cleanPath : dirname($cleanPath);
    $lockedFolder = findLockedFolderPath($protectedPath, $folderPasswords, $_SESSION['unlocked_folders'] ?? array());
    if ($lockedFolder !== null) {
        http_response_code(403);
        exit("Please unlock this folder first.");
    }
}

if (is_dir($systemFilePath)) {
    $zipName = basename($cleanPath) . ".zip";
    $tempDir = QPLUS_TEMP_DIR;
    if (!is_dir($tempDir)) { mkdir($tempDir, 0777, true); }

    $tempZip = $tempDir . 'temp_download_' . time() . '_' . rand(1000, 9999) . '.zip';
    register_shutdown_function(function () use ($tempZip) {
        if (file_exists($tempZip)) {
            @unlink($tempZip);
        }
    });

    $baseDirReal = realpath($filePath);
    if ($baseDirReal === false) {
        $baseDirReal = realpath($systemFilePath);
    }
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDirReal, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $realFilePath = $file->getRealPath();
                if (empty($realFilePath) || $baseDirReal === false) continue;
                $relativePath = substr($realFilePath, strlen($baseDirReal) + 1);
                $isUtf8 = function_exists('mb_detect_encoding') && mb_detect_encoding($relativePath, 'UTF-8', true) === 'UTF-8';
                $zipInternalPath = (!$isUtf8 && function_exists('mb_convert_encoding'))
                    ? mb_convert_encoding($relativePath, 'UTF-8', 'CP949')
                    : $relativePath;
                $zipInternalPath = str_replace('\\', '/', $zipInternalPath);
                
                if ($file->isDir()) { $zip->addEmptyDir($zipInternalPath); } 
                else if (is_file($realFilePath)) { $zip->addFile($realFilePath, $zipInternalPath); }
            }
        } catch (Exception $e) {
            $zip->close(); @unlink($tempZip); die("Compression error occurred.");
        }
        $zip->close();
    } else { die("Permission denied creating ZIP archive."); }
    
    if (file_exists($tempZip) && filesize($tempZip) > 0) {
        if (ob_get_level()) { ob_end_clean(); }
        $safeZipName = str_replace(array('"', "\r", "\n"), '', $zipName);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeZipName . '"; filename*=UTF-8\'\'' . rawurlencode($safeZipName));
        header('Content-Length: ' . filesize($tempZip));
        
        $handle = @fopen($tempZip, "rb");
        if ($handle) {
            while (!feof($handle) && !connection_aborted()) { echo fread($handle, 1024 * 1024); @ob_flush(); @flush(); }
            fclose($handle);
        }
        @unlink($tempZip); 
    } else {
        if(file_exists($tempZip)) @unlink($tempZip);
        die("Compression failed.");
    }
} 
// Handle single file download (supports HTTP Range resume)
else {
    $fileName = basename($cleanPath);
    $handle = @fopen($systemFilePath, "rb");
    if (!$handle) {
        http_response_code(404);
        exit;
    }
    $fileStat = fstat($handle);
    if ($fileStat === false) {
        fclose($handle);
        http_response_code(500);
        exit;
    }
    $fileSize = $fileStat['size'];
    
    if (ob_get_level()) { ob_end_clean(); }
    
    $isView = isset($_GET['view']) && $_GET['view'] === '1';
    $mime = 'application/octet-stream';
    $disposition = 'attachment';
    
    if ($isView) {
        $ext = strtolower(pathinfo($systemFilePath, PATHINFO_EXTENSION));
        $mimeTypes = array(
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'mp4' => 'video/mp4', 'webm' => 'video/webm'
        );
        $mime = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
        $disposition = 'inline';
    }

    // Inform browser that HTTP Range requests are accepted
    header('Accept-Ranges: bytes');
    
    $length = $fileSize;
    $start = 0;
    $end = $fileSize - 1;

    if (isset($_SERVER['HTTP_RANGE'])) {
        $rangeHeader = trim($_SERVER['HTTP_RANGE']);
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $matches)
            || ($matches[1] === '' && $matches[2] === '')
            || $fileSize === 0) {
            fclose($handle);
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        if ($matches[1] === '') {
            $suffixLength = (int)$matches[2];
            if ($suffixLength === 0) {
                fclose($handle);
                http_response_code(416);
                header("Content-Range: bytes */$fileSize");
                exit;
            }
            $start = max(0, $fileSize - $suffixLength);
        } else {
            $start = (int)$matches[1];
            if ($matches[2] !== '') {
                $end = min((int)$matches[2], $fileSize - 1);
            }
        }

        if ($start > $end || $start >= $fileSize) {
            fclose($handle);
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
    } else {
        http_response_code(200);
    }

    if (fseek($handle, $start) !== 0) {
        fclose($handle);
        http_response_code(500);
        exit;
    }

    $safeFileName = str_replace(array('"', "\r", "\n"), '', $fileName);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"; filename*=UTF-8\'\'' . rawurlencode($safeFileName));
    header('Content-Length: ' . $length);

    $remaining = $length;
    while ($remaining > 0) {
        $chunk = fread($handle, min(1024 * 1024, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
}
exit;
?>
