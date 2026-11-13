<?php
session_start();
require_once __DIR__ . '/session_presence.php';
$passiveActions = array('presence_heartbeat', 'active_count', 'session_presence');
$requestedAction = $_GET['action'] ?? $_POST['action'] ?? '';
$isSessionActivity = !in_array($requestedAction, $passiveActions, true)
    || ($requestedAction === 'presence_heartbeat' && ($_GET['activity'] ?? '') === '1');
enforceSessionIdleTimeout($isSessionActivity);
recordSessionPresence();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Password and base configuration initialization
$configFile = QPLUS_CONFIG_FILE;
if (!file_exists($configFile)) {
    $initConfig = "<?php return array('admin_pw' => 'admin', 'guest_pw' => 'guest', 'folder_pws' => array()); ?>";
    file_put_contents($configFile, $initConfig);
}
if (!is_dir(QPLUS_STORAGE_DIR)) {
    @mkdir(QPLUS_STORAGE_DIR, 0777, true);
}
if (!is_dir(QPLUS_TEMP_DIR)) {
    @mkdir(QPLUS_TEMP_DIR, 0777, true);
}
$config = include($configFile);
require_once __DIR__ . '/folder_access.php';

if (isset($config['password']) && !isset($config['admin_pw'])) {
    $config['admin_pw'] = $config['password'];
    $config['guest_pw'] = 'guest';
    unset($config['password']);
}
if (!isset($config['folder_pws']) || !is_array($config['folder_pws'])) {
    $config['folder_pws'] = array();
}

$configNeedsUpdate = false;
if (!empty($config['admin_pw']) && empty(password_get_info($config['admin_pw'])['algo'])) {
    $config['admin_pw'] = password_hash($config['admin_pw'], PASSWORD_DEFAULT);
    $configNeedsUpdate = true;
}
if (!empty($config['guest_pw']) && empty(password_get_info($config['guest_pw'])['algo'])) {
    $config['guest_pw'] = password_hash($config['guest_pw'], PASSWORD_DEFAULT);
    $configNeedsUpdate = true;
}
if (isset($config['folder_pws']) && is_array($config['folder_pws'])) {
    foreach ($config['folder_pws'] as $fPath => $fPw) {
        if (!empty($fPw) && empty(password_get_info($fPw)['algo'])) {
            $config['folder_pws'][$fPath] = password_hash($fPw, PASSWORD_DEFAULT);
            $configNeedsUpdate = true;
        }
    }
}
if (isset($config['background_file'])) {
    unset($config['background_file']);
    $configNeedsUpdate = true;
}
if ($configNeedsUpdate) {
    file_put_contents($configFile, "<?php return " . var_export($config, true) . "; ?>", LOCK_EX);
}

$admin_password = $config['admin_pw'];
$guest_password = $config['guest_pw'];
$folder_passwords = $config['folder_pws'];

function toUTF8($str) {
    if (function_exists('mb_detect_encoding') && mb_detect_encoding($str, 'UTF-8', true) === 'UTF-8') return $str;
    if (function_exists('iconv')) { $converted = @iconv('CP949', 'UTF-8//TRANSLIT//IGNORE', $str); if ($converted) return $converted; }
    if (function_exists('mb_convert_encoding')) return @mb_convert_encoding($str, 'UTF-8', 'CP949');
    return $str; 
}

function toSys($str) {
    if (function_exists('mb_detect_encoding') && mb_detect_encoding($str, 'UTF-8', true) !== 'UTF-8') return $str;
    if (function_exists('iconv')) { $converted = @iconv('UTF-8', 'CP949//TRANSLIT//IGNORE', $str); if ($converted) return $converted; }
    if (function_exists('mb_convert_encoding')) return @mb_convert_encoding($str, 'CP949', 'UTF-8');
    return $str;
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// --------------------------------------------------------
// Backend API Endpoints
// --------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'session_presence') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401);
        exit;
    }
    echo json_encode(getActiveSessionPresence(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'active_count') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        exit;
    }
    echo json_encode(array('count' => count(getActiveSessionPresence())));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'presence_heartbeat') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        exit;
    }
    recordSessionPresence();
    echo json_encode(array('success' => true));
    exit;
}

// 1. Storage disk space query API
if (isset($_GET['action']) && $_GET['action'] === 'sysinfo') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }
    
    $targetDrive = QPLUS_STORAGE_DRIVE; 
    $totalSpace = @disk_total_space($targetDrive);
    $freeSpace = @disk_free_space($targetDrive);
    $usedSpace = $totalSpace - $freeSpace;
    $usedPercent = ($totalSpace > 0) ? round(($usedSpace / $totalSpace) * 100, 1) : 0;
    
    echo json_encode([
        'total' => formatBytes($totalSpace),
        'used' => formatBytes($usedSpace),
        'free' => formatBytes($freeSpace),
        'percent' => $usedPercent
    ]);
    exit;
}

// 2. Temporary cache purge API
if (isset($_POST['action']) && $_POST['action'] === 'clear_temp') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }
    
    $tempDir = QPLUS_TEMP_DIR;
    $deletedCount = 0;
    $freedSpace = 0;

    $files = glob($tempDir . '*');
    if ($files !== false) {
        foreach ($files as $file) {
            if (is_file($file)) {
                $freedSpace += filesize($file);
                if (@unlink($file)) {
                    $deletedCount++;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'msg' => "Deleted {$deletedCount} temporary file(s) and freed " . formatBytes($freedSpace) . "."
    ]);
    exit;
}

// 3. Admin / Guest password change API
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') { http_response_code(401); exit; }
    
    $targetRole = $_POST['target_role'] ?? 'admin';
    $oldPw = $_POST['old_pw'] ?? '';
    $newPw = $_POST['new_pw'] ?? '';
    
    if (!verifyPassword($oldPw, $admin_password)) { echo json_encode(['success' => false, 'msg' => 'Current admin password does not match.']); exit; }
    if (empty($newPw)) { echo json_encode(['success' => false, 'msg' => 'Please enter a new password.']); exit; }
    
    if ($targetRole === 'admin') $config['admin_pw'] = password_hash($newPw, PASSWORD_DEFAULT);
    else if ($targetRole === 'guest') $config['guest_pw'] = password_hash($newPw, PASSWORD_DEFAULT);
    
    if (file_put_contents($configFile, "<?php return " . var_export($config, true) . "; ?>", LOCK_EX)) {
        echo json_encode(['success' => true, 'msg' => ($targetRole === 'admin' ? 'Admin' : 'Guest') . ' password changed successfully.']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to save configuration.']);
    }
    exit;
}

// 4. Folder password set / clear API
if (isset($_POST['action']) && $_POST['action'] === 'set_folder_password') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') { http_response_code(401); exit; }

    $folderInput = trim($_POST['folder_name'] ?? '');
    $folderPass = trim($_POST['folder_pw'] ?? '');

    $folderValidation = resolveDirectoryFolderPath($folderInput, toSys(QPLUS_STORAGE_DIR));
    if ($folderValidation['path'] === '') {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => $folderValidation['error']]); exit;
    }
    $folderName = $folderValidation['path'];

    if ($folderPass === '') {
        unset($config['folder_pws'][$folderName]);
        $msg = "Password lock removed for folder [{$folderName}].";
    } else {
        $config['folder_pws'][$folderName] = password_hash($folderPass, PASSWORD_DEFAULT);
        $msg = "Password lock enabled for folder [{$folderName}].";
    }

    if (file_put_contents($configFile, "<?php return " . var_export($config, true) . "; ?>", LOCK_EX)) {
        echo json_encode(['success' => true, 'msg' => $msg]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Failed to save configuration.']);
    }
    exit;
}

// 5. Folder access password verification API
if (isset($_POST['action']) && $_POST['action'] === 'verify_folder') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }

    $folderName = normalizeFolderPath($_POST['folder'] ?? '');
    $inputPw = trim($_POST['password'] ?? '');

    if ($_SESSION['role'] === 'admin' || !isset($folder_passwords[$folderName]) || $folder_passwords[$folderName] === '') {
        echo json_encode(['success' => true]);
        exit;
    }

    if (!empty($_SESSION['unlocked_folders'][$folderName])) {
        echo json_encode(['success' => true]);
        exit;
    }

    $now = time();
    $folderFailKey = 'folder_fail_' . md5($folderName);
    $failInfo = $_SESSION[$folderFailKey] ?? array('count' => 0, 'last' => 0);
    if ($failInfo['count'] >= 5 && ($now - $failInfo['last']) < 60) {
        $remaining = 60 - ($now - $failInfo['last']);
        echo json_encode(['success' => false, 'msg' => "Too many attempts. Please try again after {$remaining}s."]);
        exit;
    }

    if (verifyPassword($inputPw, $folder_passwords[$folderName])) {
        unset($_SESSION[$folderFailKey]);
        $_SESSION['unlocked_folders'][$folderName] = true;
        echo json_encode(['success' => true]);
    } else {
        $newCount = ($failInfo['count'] >= 5 && ($now - $failInfo['last']) >= 60) ? 1 : ($failInfo['count'] + 1);
        $_SESSION[$folderFailKey] = array('count' => $newCount, 'last' => $now);
        echo json_encode(['success' => false, 'msg' => 'Invalid folder password.']);
    }
    exit;
}

// 6. Directory creation API
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }
    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }
    $targetDir = isset($_POST['path']) ? $_POST['path'] : '';
    $folderName = isset($_POST['folder_name']) ? trim($_POST['folder_name']) : '';

    if ($folderName === '') { echo json_encode(['success' => false, 'msg' => 'Folder name is required.']); exit; }
    if (preg_match('/[<>:"\/\\\\|?*]/', $folderName)) { echo json_encode(['success' => false, 'msg' => 'Folder name contains invalid characters.']); exit; }

    $basePath = QPLUS_STORAGE_DIR;
    $targetDir = str_replace(array('..', '\\'), array('', '/'), $targetDir);
    $lockedFolder = findLockedFolderPath($targetDir, $folder_passwords, $_SESSION['unlocked_folders'] ?? array(), $_SESSION['role'] === 'admin');
    if ($lockedFolder !== null) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Please unlock the parent folder first.']); exit;
    }
    $fullPath = rtrim($basePath . ltrim($targetDir, '/'), '/') . '/' . $folderName;
    $sysPath = toSys($fullPath);

    if (file_exists($sysPath)) { echo json_encode(['success' => false, 'msg' => 'A folder with this name already exists.']); exit; }

    if (mkdir($sysPath, 0777, true)) { echo json_encode(['success' => true]); } 
    else { echo json_encode(['success' => false, 'msg' => 'Failed to create folder.']); }
    exit;
}

// 7. Admin item move API
if (isset($_POST['action']) && $_POST['action'] === 'move_items') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401); echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }

    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }

    $sources = json_decode($_POST['sources'] ?? '[]', true);
    $destination = trim(str_replace('\\', '/', $_POST['destination'] ?? ''), '/');
    if (!is_array($sources) || !$sources || preg_match('#(^|/)\.\.?(/|$)#', $destination)) {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Invalid move path.']); exit;
    }

    $basePath = realpath(QPLUS_STORAGE_DIR);
    $destinationPath = realpath(toSys(QPLUS_STORAGE_DIR . $destination));
    if ($basePath === false || $destinationPath === false || !is_dir($destinationPath)) {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Destination folder not found.']); exit;
    }

    $basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
    $destinationPath = rtrim(str_replace('\\', '/', $destinationPath), '/');
    if (stripos($destinationPath, $basePath) !== 0) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Path not allowed.']); exit;
    }

    $moved = 0;
    $folderAccessChanged = false;
    foreach ($sources as $source) {
        if (!is_string($source)) continue;
        $source = trim(str_replace('\\', '/', $source), '/');
        if ($source === '' || preg_match('#(^|/)\.\.?(/|$)#', $source)) continue;

        $sourcePath = realpath(toSys(QPLUS_STORAGE_DIR . $source));
        if ($sourcePath === false) continue;
        $sourcePath = rtrim(str_replace('\\', '/', $sourcePath), '/');
        if (strcasecmp($sourcePath, rtrim($basePath, '/')) === 0 || stripos($sourcePath, $basePath) !== 0) continue;

        $sourcePrefix = $sourcePath . '/';
        if (stripos($destinationPath . '/', $sourcePrefix) === 0) continue;

        $targetPath = $destinationPath . '/' . basename($sourcePath);
        if (file_exists($targetPath)) continue;
        if (@rename($sourcePath, $targetPath)) {
            $moved++;
            if (is_dir($targetPath)) {
                $oldFolderPath = normalizeFolderPath($source);
                $newFolderPath = normalizeFolderPath($destination . '/' . basename($sourcePath));
                $config['folder_pws'] = remapFolderAccessPath($config['folder_pws'], $oldFolderPath, $newFolderPath);
                $_SESSION['unlocked_folders'] = remapFolderAccessPath($_SESSION['unlocked_folders'] ?? array(), $oldFolderPath, $newFolderPath);
                $folderAccessChanged = true;
            }
        }
    }

    if ($folderAccessChanged && @file_put_contents($configFile, "<?php return " . var_export($config, true) . "; ?>", LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'moved' => $moved, 'msg' => 'Items moved, but folder password settings could not be updated.']);
        exit;
    }

    echo json_encode(['success' => $moved > 0, 'moved' => $moved, 'msg' => $moved > 0 ? 'Moved successfully.' : 'No items moved (items may not exist or destination has same name).']);
    exit;
}

// 8. Tips & Notice board API
$tipsFile = QPLUS_TIPS_FILE;
if (!file_exists($tipsFile)) {
    @file_put_contents($tipsFile, "<?php return array(); ?>");
}

if (isset($_GET['action']) && $_GET['action'] === 'tips') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401); exit;
    }
    $tips = include $tipsFile;
    echo json_encode(is_array($tips) ? $tips : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_tip') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401); echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }
    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($title === '' || $body === '') {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Title and body are required.']); exit;
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($title, 'UTF-8') > 200 || mb_strlen($body, 'UTF-8') > 10000) {
            http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Content exceeds maximum length.']); exit;
        }
    }

    $tips = include $tipsFile;
    if (!is_array($tips)) $tips = array();
    array_unshift($tips, array(
        'id' => bin2hex(random_bytes(8)),
        'title' => $title,
        'body' => $body,
        'created_at' => date('Y-m-d H:i')
    ));
    if (@file_put_contents($tipsFile, "<?php return " . var_export($tips, true) . "; ?>", LOCK_EX) === false) {
        http_response_code(500); echo json_encode(['success' => false, 'msg' => 'Failed to save TIP.']); exit;
    }
    echo json_encode(['success' => true, 'msg' => 'TIP posted successfully.']);
    exit;
}

if (isset($_POST['action']) && in_array($_POST['action'], ['update_tip', 'delete_tip'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401); echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }
    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }

    $tipId = trim($_POST['id'] ?? '');
    $tips = include $tipsFile;
    if (!is_array($tips)) $tips = array();
    $tipIndex = null;
    foreach ($tips as $index => $tip) {
        if (isset($tip['id']) && hash_equals((string)$tip['id'], $tipId)) {
            $tipIndex = $index;
            break;
        }
    }
    if ($tipIndex === null) {
        http_response_code(404); echo json_encode(['success' => false, 'msg' => 'TIP not found.']); exit;
    }

    if ($_POST['action'] === 'delete_tip') {
        array_splice($tips, $tipIndex, 1);
        $message = 'TIP deleted.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Title and body are required.']); exit;
        }
        if (function_exists('mb_strlen')) {
            if (mb_strlen($title, 'UTF-8') > 200 || mb_strlen($body, 'UTF-8') > 10000) {
                http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Content exceeds maximum length.']); exit;
            }
        }
        $tips[$tipIndex]['title'] = $title;
        $tips[$tipIndex]['body'] = $body;
        $tips[$tipIndex]['updated_at'] = date('Y-m-d H:i');
        $message = 'TIP updated.';
    }

    if (@file_put_contents($tipsFile, "<?php return " . var_export($tips, true) . "; ?>", LOCK_EX) === false) {
        http_response_code(500); echo json_encode(['success' => false, 'msg' => 'Failed to save TIP.']); exit;
    }
    echo json_encode(['success' => true, 'msg' => $message]);
    exit;
}

// 9. Request board API
$requestsFile = QPLUS_REQUESTS_FILE;
if (!file_exists($requestsFile)) {
    @file_put_contents($requestsFile, "<?php return array(); ?>");
}

if (isset($_GET['action']) && $_GET['action'] === 'requests') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401); exit;
    }
    $requests = include $requestsFile;
    echo json_encode(is_array($requests) ? $requests : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_request') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401); echo json_encode(['success' => false, 'msg' => 'Login required.']); exit;
    }
    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($title === '' || $body === '') {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Title and body are required.']); exit;
    }
    if (function_exists('mb_strlen') && (mb_strlen($title, 'UTF-8') > 200 || mb_strlen($body, 'UTF-8') > 10000)) {
        http_response_code(400); echo json_encode(['success' => false, 'msg' => 'Content exceeds maximum length.']); exit;
    }

    $requests = include $requestsFile;
    if (!is_array($requests)) $requests = array();
    array_unshift($requests, array(
        'id' => bin2hex(random_bytes(8)),
        'title' => $title,
        'body' => $body,
        'role' => $_SESSION['role'],
        'created_at' => date('Y-m-d H:i'),
        'status' => 'Received'
    ));
    if (@file_put_contents($requestsFile, "<?php return " . var_export($requests, true) . "; ?>", LOCK_EX) === false) {
        http_response_code(500); echo json_encode(['success' => false, 'msg' => 'Failed to save request.']); exit;
    }
    echo json_encode(['success' => true, 'msg' => 'Request submitted successfully.']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_request') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401); echo json_encode(['success' => false, 'msg' => 'Unauthorized.']); exit;
    }
    $requestToken = $_POST['csrf_token'] ?? '';
    if (!is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
        http_response_code(403); echo json_encode(['success' => false, 'msg' => 'Invalid request.']); exit;
    }

    $requestId = trim($_POST['id'] ?? '');
    $requests = include $requestsFile;
    if (!is_array($requests)) $requests = array();
    $requestIndex = null;
    foreach ($requests as $index => $request) {
        if (isset($request['id']) && hash_equals((string)$request['id'], $requestId)) {
            $requestIndex = $index;
            break;
        }
    }
    if ($requestIndex === null) {
        http_response_code(404); echo json_encode(['success' => false, 'msg' => 'Request not found.']); exit;
    }

    array_splice($requests, $requestIndex, 1);
    if (@file_put_contents($requestsFile, "<?php return " . var_export($requests, true) . "; ?>", LOCK_EX) === false) {
        http_response_code(500); echo json_encode(['success' => false, 'msg' => 'Failed to delete request.']); exit;
    }
    echo json_encode(['success' => true, 'msg' => 'Request deleted.']);
    exit;
}

// 10. Directory & file listing API
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    ini_set('display_errors', 0); error_reporting(0);
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }

    $dirParam = isset($_GET['dir']) ? $_GET['dir'] : '';
    $dirParam = ltrim(str_replace('\\', '/', $dirParam), '/'); 
    
    $lockedFolder = findLockedFolderPath($dirParam, $folder_passwords, $_SESSION['unlocked_folders'] ?? array(), $_SESSION['role'] === 'admin');
    if ($lockedFolder !== null) {
        http_response_code(403);
        echo json_encode(['error' => 'AUTH_REQUIRED', 'folder' => $lockedFolder]);
        exit;
    }

    $basePath = realpath(QPLUS_STORAGE_DIR);
    $targetPath = realpath($basePath . '/' . $dirParam);
    if ($targetPath === false) {
        $targetPath = realpath(toSys($basePath . '/' . $dirParam));
    }

    if ($basePath === false || $targetPath === false || stripos($targetPath, $basePath) !== 0) {
        http_response_code(403); echo json_encode(['error' => '접근이 거부된 경로입니다.']); exit;
    }

    $sysPath = $targetPath;

    $hidden_items = array(
        '.', '..', 'php', 'qplus', 'temp', 'config', 'api', 'assets', 'installer', '_backup_security',
        'index.php', 'upload.php', 'download.php', 'delete.php', 'check.php', 'config.php', 'paths.php',
        'tips_data.php', 'requests_data.php', 'sales_data.php', 'app_ui.php',
        'duckdns.ps1', 'duckdns', 'web.config', 'system volume information', '$recycle.bin', 
        'index_backup.html', 'index_backup.php', 'folder_access.php'
    );

    $items = array();
    if (@file_exists($sysPath) && @is_dir($sysPath)) {
        $files = scandir($sysPath);
        foreach ($files as $file) {
            $utf8_file = toUTF8($file);
            if (in_array(strtolower($utf8_file), $hidden_items)) continue;
            if (strpos($utf8_file, 'temp_download_') === 0) continue; 
            
            $sysItemPath = $sysPath . DIRECTORY_SEPARATOR . $file;
            $isDir = @is_dir($sysItemPath);
            $parentPath = rtrim($dirParam, '/');
            $itemHref = $parentPath . ($parentPath === '' ? '' : '/') . $utf8_file . ($isDir ? '/' : '');

            $isProtected = false;
            $isUnlocked = false;
            if ($isDir) {
                $folderPath = normalizeFolderPath($itemHref);
                if (isConfiguredFolderPath($folderPath, $folder_passwords)) {
                    $isProtected = true;
                    $isUnlocked = ($_SESSION['role'] === 'admin') || !empty($_SESSION['unlocked_folders'][$folderPath]);
                }
            }

            $fileSize = $isDir ? 0 : (@filesize($sysItemPath) ?: 0);
            $fileMtime = @filemtime($sysItemPath) ?: 0;

            $items[] = array(
                'name' => htmlspecialchars($utf8_file, ENT_QUOTES, 'UTF-8'),
                'rawName' => $utf8_file,
                'isDir' => $isDir,
                'isProtected' => $isProtected,
                'isUnlocked' => $isUnlocked,
                'size' => $fileSize,
                'sizeFormatted' => $isDir ? '-' : formatBytes($fileSize),
                'mtime' => $fileMtime,
                'mtimeFormatted' => $fileMtime ? date('Y-m-d H:i', $fileMtime) : '-',
                'href' => ltrim($itemHref, '/')
            );
        }
        usort($items, function ($a, $b) {
            if ($a['isDir'] !== $b['isDir']) return $a['isDir'] ? -1 : 1;
            return strnatcasecmp($a['rawName'], $b['rawName']);
        });
    } else {
        http_response_code(404); echo json_encode(['error' => 'Not found']); exit;
    }
    
    $jsonResult = json_encode($items, JSON_UNESCAPED_UNICODE);
    if ($jsonResult === false) echo json_encode(['error' => 'Encoding Error']); else echo $jsonResult;
    exit;
}

if (isset($_GET['logout'])) { removeSessionPresence(); session_destroy(); header("Location: /"); exit; }

// Authentication login handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $now = time();
    $loginFails = $_SESSION['login_fail_count'] ?? 0;
    $lastFailTime = $_SESSION['login_last_fail_time'] ?? 0;

    if ($loginFails >= 5 && ($now - $lastFailTime) < 60) {
        $remaining = 60 - ($now - $lastFailTime);
        $error_msg = "Too many login attempts. Please try again after {$remaining}s.";
    } else {
        if ($now - $lastFailTime >= 60) {
            $_SESSION['login_fail_count'] = 0;
        }

        $role = (($_POST['role'] ?? '') === 'admin' && isset($_GET['admin'])) ? 'admin' : 'guest';
        $pw = $_POST['password'] ?? '';
        
        $loginSuccess = false;
        if ($role === 'admin' && verifyPassword($pw, $admin_password)) {
            $loginSuccess = true;
        } elseif ($role === 'guest' && verifyPassword($pw, $guest_password)) {
            $loginSuccess = true;
        }

        if ($loginSuccess) {
            unset($_SESSION['login_fail_count'], $_SESSION['login_last_fail_time']);
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = $role;
            $_SESSION['last_activity_at'] = time();
            recordSessionPresence(true);
            header("Location: /");
            exit;
        } else {
            $_SESSION['login_fail_count'] = ($_SESSION['login_fail_count'] ?? 0) + 1;
            $_SESSION['login_last_fail_time'] = $now;
            $error_msg = "Invalid password.";
        }
    }
}

// Render login view
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $isAdminLogin = isset($_GET['admin']) && $_GET['admin'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>LOGIN - qplus</title>
    <link rel="icon" type="image/png" href="/assets/qplus_symbol.png">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", "Apple SD Gothic Neo", sans-serif; background: #f5f5f5; color: #141414; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; -webkit-font-smoothing: antialiased; }
        .login-box { background: #ffffff; padding: 44px 38px; border-radius: 16px; border: 1px solid #e4e4e4; box-shadow: 0 8px 28px rgba(0,0,0,0.05); text-align: center; width: 90%; max-width: 344px; box-sizing: border-box; }
        .login-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 28px; }
        input[type="password"] { width: 100%; padding: 14px 15px; margin-bottom: 16px; border: none; border-radius: 10px; box-sizing: border-box; font-size: 14px; color: #141414; background: #efefef; transition: all 0.15s; outline: none; }
        input[type="password"]:focus { background: #ffffff; box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.35); }
        button { width: 100%; padding: 14px; background: #0284c7; color: #ffffff; border: none; border-radius: 10px; font-weight: 700; font-size: 14.5px; cursor: pointer; transition: background 0.15s; }
        button:hover { background: #0369a1; }
        .error { color: #dc2626; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
        .admin-entry { position: fixed; right: 24px; bottom: 24px; width: auto; padding: 9px 15px; background: #ffffff; color: #4a4a4a; border: 1px solid #e4e4e4; border-radius: 9px; font-size: 12.5px; font-weight: 600; text-decoration: none; transition: all 0.15s; }
        .admin-entry:hover { background: #e0f2fe; color: #0284c7; border-color: #e0f2fe; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-brand">
            <img src="/assets/qplus_symbol.png" alt="" style="height:28px;width:auto;">
            <img src="/assets/qplus_logo.png" alt="qplus" style="height:23px;width:auto;">
        </div>
        <?php if (isset($error_msg)) echo "<div class='error'>{$error_msg}</div>"; ?>
        <form method="POST">
            <input type="hidden" name="role" value="<?php echo $isAdminLogin ? 'admin' : 'guest'; ?>">
            <input type="password" name="password" placeholder="<?php echo $isAdminLogin ? 'Admin password' : 'Password'; ?>" required autofocus>
            <button type="submit">LOGIN</button>
        </form>
    </div>
    <?php if ($isAdminLogin): ?>
        <a class="admin-entry" href="/">Guest Login</a>
    <?php else: ?>
        <a class="admin-entry" href="?admin=1">Admin Login</a>
    <?php endif; ?>
</body>
</html>
<?php exit; } ?>

<?php include __DIR__ . '/app_ui.php'; ?>
