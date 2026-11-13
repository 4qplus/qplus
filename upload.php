<?php
session_start();
require_once __DIR__ . '/session_presence.php';
enforceSessionIdleTimeout();
recordSessionPresence();
require_once __DIR__ . '/folder_access.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); 
    exit("Unauthorized."); 
}

$requestToken = $_POST['csrf_token'] ?? '';
if (!is_string($requestToken) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
    http_response_code(403);
    exit("Invalid request.");
}

session_write_close(); // Release session lock

set_time_limit(0);
ini_set('memory_limit', '-1');

function toSys($str) {
    if (function_exists('mb_detect_encoding') && mb_detect_encoding($str, 'UTF-8', true) !== 'UTF-8') return $str;
    if (function_exists('iconv')) { $converted = @iconv('UTF-8', 'CP949//TRANSLIT//IGNORE', $str); if ($converted) return $converted; }
    if (function_exists('mb_convert_encoding')) return @mb_convert_encoding($str, 'CP949', 'UTF-8');
    return $str;
}

function cleanupUploadChunks($tempDir, $fileGuid) {
    if ($fileGuid === '') return;
    $files = glob($tempDir . $fileGuid . "_*.tmp");
    if ($files) {
        foreach ($files as $file) { @unlink($file); }
    }
}

function failMerge($statusCode, $message, $tempDir, $fileGuid) {
    cleanupUploadChunks($tempDir, $fileGuid);
    http_response_code($statusCode);
    exit($message);
}

$action = isset($_POST['action']) ? $_POST['action'] : 'upload_chunk';
$tempDir = QPLUS_TEMP_DIR;
if (!file_exists($tempDir) && !file_exists(toSys($tempDir))) {
    @mkdir($tempDir, 0777, true);
    @mkdir(toSys($tempDir), 0777, true);
}

if ($action === 'upload_chunk') {
    $fileGuid = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['fileGuid'] ?? '');
    if (empty($fileGuid) || !isset($_FILES['file'])) {
        http_response_code(400); echo "Invalid Chunk Request";
        exit;
    }
    $chunkIndex = (int)($_POST['chunkIndex'] ?? 0);
    $chunkFilePath = $tempDir . $fileGuid . "_" . $chunkIndex . ".tmp";

    if (move_uploaded_file($_FILES['file']['tmp_name'], $chunkFilePath) ||
        move_uploaded_file($_FILES['file']['tmp_name'], toSys($chunkFilePath))) {
        echo "Chunk OK";
    } else {
        http_response_code(500); echo "Chunk Move Fail";
    }
    exit;
}

if ($action === 'merge') {
    $rawTargetDir = isset($_POST['path']) ? (string)$_POST['path'] : '';
    $rawRelPath = isset($_POST['relPath']) ? (string)$_POST['relPath'] : '';
    $totalChunks = (int)($_POST['totalChunks'] ?? 0);
    $fileGuid = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['fileGuid'] ?? '');

    if (empty($fileGuid) || $totalChunks <= 0) {
        failMerge(400, "Invalid merge request.", $tempDir, $fileGuid);
    }

    // Path normalization and traversal checks
    $targetDir = str_replace('\\', '/', $rawTargetDir);
    $targetDir = preg_replace('#/+#', '/', $targetDir);
    if ($targetDir === '.' || $targetDir === '..' || preg_match('#(^|/)\.\.?(/|$)#', $targetDir)) {
        failMerge(400, "Invalid target path.", $tempDir, $fileGuid);
    }
    $cleanTargetDir = trim($targetDir, '/');

    $relPath = str_replace('\\', '/', $rawRelPath);
    $relPath = preg_replace('#/+#', '/', $relPath);
    if ($relPath === '.' || $relPath === '..' || preg_match('#(^|/)\.\.?(/|$)#', $relPath)) {
        failMerge(400, "Invalid relative file path.", $tempDir, $fileGuid);
    }
    $cleanRelPath = trim($relPath, '/');

    $basePath = QPLUS_STORAGE_DIR;
    if ($_SESSION['role'] !== 'admin') {
        $config = include QPLUS_CONFIG_FILE;
        $folderPasswords = $config['folder_pws'] ?? array();
        $lockedFolder = findLockedFolderPath($cleanTargetDir, $folderPasswords, $_SESSION['unlocked_folders'] ?? array());
        if ($lockedFolder !== null) {
            failMerge(403, "Please unlock this folder first.", $tempDir, $fileGuid);
        }
    }

    $uploadDir = $basePath . ($cleanTargetDir === '' ? '' : $cleanTargetDir . '/');
    $fileName = !empty($cleanRelPath) ? basename($cleanRelPath) : "uploaded_file";
    $fileName = trim($fileName, ". ");

    if ($fileName === '' || strpos($fileName, "\0") !== false || preg_match('/[<>:"\/\\\\|?*]/', $fileName)) {
        failMerge(400, "Invalid file name.", $tempDir, $fileGuid);
    }

    // Allowed extension whitelist (excludes executable scripts .php, .asp, .cgi and XSS risks .svg, .html)
    $allowed = array(
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'tif', 'tiff', 'heic', 'raw', 'cr2', 'nef',
        // Videos
        'mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'ts', 'mts', 'm2ts', 'vob', '3gp',
        // Audio
        'mp3', 'wav', 'flac', 'aac', 'ogg', 'wma', 'm4a', 'aiff', 'alac', 'mid', 'midi',
        // Documents & Text
        'pdf', 'txt', 'md', 'csv', 'rtf', 'hwp', 'hwpx', 'doc', 'docx',
        'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'log', 'json', 'xml', 'yaml', 'yml',
        // Archives & Disk Images
        'zip', '7z', 'rar', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'iso', 'img', 'dmg', 'wim',
        // 3D & Creative Assets
        'blend', 'fbx', 'obj', 'max', 'c4d', 'mb', 'ma', 'psd', 'psb', 'ai', 'eps', 'clip',
        'prproj', 'aep', 'skp', 'dae', '3ds', 'stl', 'step', 'stp', 'iges', 'igs', 'dxf', 'dwg',
        // Applications & System Resources
        'apk', 'apks', 'xapk', 'exe', 'msi', 'cmd', 'bat', 'reg', 'ttf', 'otf', 'woff', 'woff2'
    );
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowed, true)) {
        failMerge(400, "File extension not allowed: ." . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8'), $tempDir, $fileGuid);
    }

    if (!empty($cleanRelPath) && strpos($cleanRelPath, '/') !== false) {
        $subDir = trim(dirname($cleanRelPath), '/');
        if ($subDir !== '' && $subDir !== '.') {
            $uploadDir = $uploadDir . $subDir . "/";
        }
    }

    $sysUploadDir = toSys($uploadDir);
    if (!file_exists($uploadDir) && !file_exists($sysUploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @mkdir($sysUploadDir, 0777, true);
    }

    // Verify upload path strictly resides inside QPLUS_STORAGE_DIR (Block path traversal)
    $baseRealPath = realpath($basePath);
    $uploadRealPath = realpath($uploadDir);
    if ($uploadRealPath === false) {
        $uploadRealPath = realpath($sysUploadDir);
    }
    if ($baseRealPath === false || $uploadRealPath === false) {
        failMerge(400, "Invalid directory path.", $tempDir, $fileGuid);
    }
    $baseNorm = rtrim(str_replace('\\', '/', $baseRealPath), '/') . '/';
    $uploadNorm = rtrim(str_replace('\\', '/', $uploadRealPath), '/') . '/';
    if (strncasecmp($uploadNorm, $baseNorm, strlen($baseNorm)) !== 0) {
        failMerge(403, "Upload path outside storage root is not permitted.", $tempDir, $fileGuid);
    }

    $utf8TargetFilePath = $uploadDir . $fileName;
    $sysTargetFilePath = toSys($utf8TargetFilePath);

    if (file_exists($utf8TargetFilePath) || file_exists($sysTargetFilePath)) {
        failMerge(409, "A file with the same name already exists.", $tempDir, $fileGuid);
    }

    $out = @fopen($utf8TargetFilePath, "wb");
    $targetPathToUse = $utf8TargetFilePath;
    if (!$out) {
        $out = @fopen($sysTargetFilePath, "wb");
        if ($out) {
            $targetPathToUse = $sysTargetFilePath;
        }
    }

    if (!$out) {
        failMerge(500, "Permission denied creating target file: " . $uploadDir, $tempDir, $fileGuid);
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkFilePath = $tempDir . $fileGuid . "_" . $i . ".tmp";
        if (!file_exists($chunkFilePath)) {
            $chunkFilePath = toSys($tempDir . $fileGuid . "_" . $i . ".tmp");
        }
        
        // Abort and purge if any chunk is missing
        if (!file_exists($chunkFilePath)) {
            @fclose($out);
            @unlink($targetPathToUse); // Remove corrupted partial file
            failMerge(500, "Fatal error: Chunk " . $i . " is missing. Please check network connection.", $tempDir, $fileGuid);
        }

        $in = @fopen($chunkFilePath, "rb");
        if ($in) {
            stream_copy_to_stream($in, $out);
            @fclose($in);
            @unlink($chunkFilePath);
        }
    }
    @fclose($out);
    echo "Merge Success";
    exit;
}

if ($action === 'cancel_upload') {
    $fileGuid = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['fileGuid'] ?? '');
    cleanupUploadChunks($tempDir, $fileGuid);
    echo "Cancelled";
    exit;
}

http_response_code(400);
exit("Invalid request.");
?>
