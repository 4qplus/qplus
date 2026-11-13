<?php

require_once __DIR__ . '/paths.php';
define('QPLUS_SESSION_IDLE_TIMEOUT_SECONDS', 1800);

function enforceSessionIdleTimeout($isActivity = true)
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['logged_in'])) return false;

    $now = time();
    if (!isset($_SESSION['last_activity_at'])) {
        $_SESSION['last_activity_at'] = $now;
    } elseif ($now - (int)$_SESSION['last_activity_at'] >= QPLUS_SESSION_IDLE_TIMEOUT_SECONDS) {
        removeSessionPresence();
        $_SESSION = array();
        session_regenerate_id(true);
        return false;
    }

    if ($isActivity) $_SESSION['last_activity_at'] = $now;
    return true;
}

function withSessionPresenceLock($callback)
{
    $dataDirectory = QPLUS_PRESENCE_DIR;
    if (!is_dir($dataDirectory) && !@mkdir($dataDirectory, 0777, true)) return null;

    $lock = @fopen($dataDirectory . 'session_presence.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        return null;
    }

    $dataFile = $dataDirectory . 'session_presence.php';
    $sessions = array();
    if (is_file($dataFile)) {
        if (!defined('QPLUS_SESSION_PRESENCE_ACCESS')) define('QPLUS_SESSION_PRESENCE_ACCESS', true);
        $storedSessions = include $dataFile;
        if (is_array($storedSessions)) $sessions = $storedSessions;
    }

    $result = $callback($sessions);
    $contents = "<?php\nif (!defined('QPLUS_SESSION_PRESENCE_ACCESS')) { http_response_code(404); exit; }\nreturn "
        . var_export($sessions, true) . ";\n";
    $saved = @file_put_contents($dataFile, $contents, LOCK_EX) !== false;
    if (function_exists('opcache_invalidate')) @opcache_invalidate($dataFile, true);

    flock($lock, LOCK_UN);
    fclose($lock);
    return $saved ? $result : null;
}

function recordSessionPresence($newLogin = false)
{
    if (empty($_SESSION['logged_in']) || empty($_SESSION['role']) || session_status() !== PHP_SESSION_ACTIVE) return;

    $sessionKey = hash('sha256', session_id());
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($ipAddress, array('127.0.0.1', '::1'), true)) {
        $forwardedIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if (filter_var($forwardedIp, FILTER_VALIDATE_IP)) $ipAddress = $forwardedIp;
    }
    $now = time();
    withSessionPresenceLock(function (&$sessions) use ($sessionKey, $now, $newLogin, $ipAddress) {
        foreach ($sessions as $key => $session) {
            if (!isset($session['last_seen']) || $session['last_seen'] < $now - 86400) unset($sessions[$key]);
        }

        $existing = $sessions[$sessionKey] ?? array();
        $sessions[$sessionKey] = array(
            'role' => $_SESSION['role'],
            'ip' => $ipAddress,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            'login_at' => $newLogin ? $now : ($existing['login_at'] ?? $now),
            'last_seen' => $now
        );
    });
}

function removeSessionPresence()
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $sessionKey = hash('sha256', session_id());
    withSessionPresenceLock(function (&$sessions) use ($sessionKey) {
        unset($sessions[$sessionKey]);
    });
}

function getActiveSessionPresence($activeWithinSeconds = 1200)
{
    return withSessionPresenceLock(function (&$sessions) use ($activeWithinSeconds) {
        $cutoff = time() - $activeWithinSeconds;
        $active = array();
        foreach ($sessions as $session) {
            if (($session['last_seen'] ?? 0) >= $cutoff) $active[] = $session;
        }
        usort($active, function ($left, $right) {
            return ($right['last_seen'] ?? 0) <=> ($left['last_seen'] ?? 0);
        });
        return $active;
    });
}
