<?php

function verifyPassword($input, $hashOrPlain) {
    if ($hashOrPlain === null || $hashOrPlain === '') return false;
    $input = (string)$input;
    $hashOrPlain = (string)$hashOrPlain;

    $info = password_get_info($hashOrPlain);
    if (!empty($info['algo'])) {
        return password_verify($input, $hashOrPlain);
    }

    return hash_equals($hashOrPlain, $input);
}

function normalizeFolderPath($path) {
    $path = trim(str_replace('\\', '/', (string)$path), '/');
    $path = preg_replace('#/+#', '/', $path);
    if ($path === '' || preg_match('#(^|/)\.\.?(/|$)#', $path)) return '';
    return $path;
}

function resolveDirectoryFolderPath($path, $basePath) {
    $normalizedPath = normalizeFolderPath($path);
    if ($normalizedPath === '') {
        return array('path' => '', 'error' => '폴더 경로를 입력하세요.');
    }

    $baseSystemPath = $basePath;
    $targetSystemPath = $basePath . '/' . $normalizedPath;
    if (function_exists('mb_convert_encoding')) {
        $baseSystemPath = mb_convert_encoding($baseSystemPath, 'CP949', 'UTF-8');
        $targetSystemPath = mb_convert_encoding($targetSystemPath, 'CP949', 'UTF-8');
    }
    $baseRealPath = realpath($baseSystemPath);
    $targetRealPath = realpath($targetSystemPath);
    if ($baseRealPath === false || $targetRealPath === false) {
        return array('path' => '', 'error' => '존재하지 않는 폴더 경로입니다.');
    }

    $baseRealPath = rtrim(str_replace('\\', '/', $baseRealPath), '/');
    $targetRealPath = rtrim(str_replace('\\', '/', $targetRealPath), '/');
    if (strcasecmp($targetRealPath, $baseRealPath) === 0
        || strncasecmp($targetRealPath, $baseRealPath . '/', strlen($baseRealPath) + 1) !== 0) {
        return array('path' => '', 'error' => 'directory 안에 있는 폴더만 지정할 수 있습니다.');
    }
    if (!is_dir($targetRealPath)) {
        return array('path' => '', 'error' => '파일이 아닌 폴더 경로를 입력하세요.');
    }

    return array('path' => $normalizedPath, 'error' => '');
}

function findLockedFolderPath($path, $folderPasswords, $unlockedFolders, $isAdmin = false) {
    if ($isAdmin) return null;

    $path = normalizeFolderPath($path);
    if ($path === '') return null;

    $parts = explode('/', $path);
    $prefix = array();
    foreach ($parts as $part) {
        $prefix[] = $part;
        $folderPath = implode('/', $prefix);
        if (!empty($folderPasswords[$folderPath]) && empty($unlockedFolders[$folderPath])) {
            return $folderPath;
        }
    }

    return null;
}

function isConfiguredFolderPath($path, $folderPasswords) {
    $path = normalizeFolderPath($path);
    return $path !== '' && !empty($folderPasswords[$path]);
}

function remapFolderAccessPath($accessMap, $oldPath, $newPath) {
    $oldPath = normalizeFolderPath($oldPath);
    $newPath = normalizeFolderPath($newPath);
    if ($oldPath === '' || $newPath === '' || !is_array($accessMap)) return $accessMap;

    $updatedMap = array();
    foreach ($accessMap as $path => $value) {
        $path = normalizeFolderPath($path);
        if ($path === '') continue;

        if ($path === $oldPath || strpos($path, $oldPath . '/') === 0) {
            $relativePath = substr($path, strlen($oldPath));
            $path = $newPath . $relativePath;
        }
        $updatedMap[$path] = $value;
    }

    return $updatedMap;
}
