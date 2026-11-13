<?php
if (!defined('QPLUS_ROOT')) {
    define('QPLUS_ROOT', rtrim(str_replace('\\', '/', __DIR__), '/'));
}
if (!defined('QPLUS_CONFIG_FILE')) {
    define('QPLUS_CONFIG_FILE', QPLUS_ROOT . '/config.php');
}
if (!defined('QPLUS_TEMP_DIR')) {
    define('QPLUS_TEMP_DIR', QPLUS_ROOT . '/temp/');
}
if (!defined('QPLUS_PRESENCE_DIR')) {
    define('QPLUS_PRESENCE_DIR', QPLUS_ROOT . '/qplus/');
}
if (!defined('QPLUS_TIPS_FILE')) {
    define('QPLUS_TIPS_FILE', QPLUS_ROOT . '/tips_data.php');
}
if (!defined('QPLUS_REQUESTS_FILE')) {
    define('QPLUS_REQUESTS_FILE', QPLUS_ROOT . '/requests_data.php');
}
if (!defined('QPLUS_STORAGE_DIR')) {
    $qplusStorageDir = QPLUS_ROOT . '/directory/';
    if (is_file(QPLUS_CONFIG_FILE)) {
        $qplusCfg = @include QPLUS_CONFIG_FILE;
        if (is_array($qplusCfg) && !empty($qplusCfg['storage_dir']) && is_string($qplusCfg['storage_dir'])) {
            $customDir = rtrim(str_replace('\\', '/', $qplusCfg['storage_dir']), '/');
            if ($customDir !== '') {
                $qplusStorageDir = $customDir . '/';
            }
        }
    }
    define('QPLUS_STORAGE_DIR', $qplusStorageDir);
}
if (!defined('QPLUS_STORAGE_DRIVE')) {
    $driveRoot = substr(QPLUS_STORAGE_DIR, 0, 2);
    if (preg_match('/^[A-Za-z]:$/', $driveRoot)) {
        define('QPLUS_STORAGE_DRIVE', $driveRoot . '\\');
    } else {
        define('QPLUS_STORAGE_DRIVE', QPLUS_ROOT);
    }
}

