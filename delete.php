<?php
session_start();
require_once __DIR__ . '/session_presence.php';
enforceSessionIdleTimeout();
recordSessionPresence();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    exit("Unauthorized.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$data = json_decode(file_get_contents('php://input'), true);
$fileParam = isset($data['path']) ? trim($data['path']) : '';
$requestToken = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
    ? $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($data['csrf_token']) ? $data['csrf_token'] : '');

if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
    http_response_code(403);
    exit("Invalid request.");
}

if ($fileParam === '' || strpos($fileParam, "\0") !== false) {
    http_response_code(400);
    exit("Invalid path.");
}

$fileParam = urldecode($fileParam);
$fileParam = str_replace('\\', '/', $fileParam);
$fileParam = preg_replace('#/+#', '/', $fileParam);

if ($fileParam === '.' || $fileParam === '..' || preg_match('#(^|/)\.\.?(/|$)#', $fileParam)) {
    http_response_code(400);
    exit("Invalid path traversal.");
}

$basePath = QPLUS_STORAGE_DIR;
$filePath = $basePath . ltrim($fileParam, '/');

if (function_exists('mb_convert_encoding')) {
    $systemFilePath = mb_convert_encoding($filePath, 'CP949', 'UTF-8');
} else {
    $systemFilePath = $filePath;
}

if (is_link($systemFilePath)) {
    http_response_code(403);
    exit("Symlink deletion is not permitted.");
}

$baseRealPath = realpath($basePath);
$targetRealPath = realpath($systemFilePath);
if ($baseRealPath === false || $targetRealPath === false) {
    http_response_code(404);
    exit;
}

$baseRealPath = rtrim(str_replace('\\', '/', $baseRealPath), '/');
$targetRealPath = rtrim(str_replace('\\', '/', $targetRealPath), '/');
$basePrefix = $baseRealPath . '/';
if (strcasecmp($targetRealPath, $baseRealPath) === 0
    || strncasecmp($targetRealPath, $basePrefix, strlen($basePrefix)) !== 0) {
    http_response_code(403);
    exit("Cannot delete root storage directory.");
}

$systemFilePath = $targetRealPath;
$relativeTarget = ltrim(substr($targetRealPath, strlen($baseRealPath)), '/');
$topLevelName = strtolower(strtok($relativeTarget, '/'));
$protectedFolders = array('program', 'document', 'media', 'temp', 'api');

if (strpos($relativeTarget, '/') === false && in_array($topLevelName, $protectedFolders, true)) {
    http_response_code(403);
    exit("Protected system folder.");
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath) || is_link($dirPath)) return false;

    $files = scandir($dirPath);
    if ($files === false) return false;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $fullPath = $dirPath . DIRECTORY_SEPARATOR . $file;
        if (is_link($fullPath)) {
            return false;
        }
        if (is_dir($fullPath)) {
            if (!deleteDir($fullPath)) return false;
        } elseif (!unlink($fullPath)) {
            return false;
        }
    }

    return rmdir($dirPath);
}

if (is_dir($systemFilePath)) {
    http_response_code(deleteDir($systemFilePath) ? 200 : 500);
} elseif (is_file($systemFilePath)) {
    http_response_code(unlink($systemFilePath) ? 200 : 500);
} else {
    http_response_code(404);
}
?>
