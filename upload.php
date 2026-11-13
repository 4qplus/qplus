<?php
session_start();
require_once __DIR__ . '/session_presence.php';
enforceSessionIdleTimeout();
recordSessionPresence();
require_once __DIR__ . '/folder_access.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); 
    exit("권한이 없습니다."); 
}

$requestToken = $_POST['csrf_token'] ?? '';
if (!is_string($requestToken) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
    http_response_code(403);
    exit("잘못된 요청입니다.");
}

session_write_close(); // 세션 잠금 해제

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
        failMerge(400, "잘못된 병합 요청입니다.", $tempDir, $fileGuid);
    }

    // 경로 검증 및 표준화
    $targetDir = str_replace('\\', '/', $rawTargetDir);
    $targetDir = preg_replace('#/+#', '/', $targetDir);
    if ($targetDir === '.' || $targetDir === '..' || preg_match('#(^|/)\.\.?(/|$)#', $targetDir)) {
        failMerge(400, "잘못된 대상 경로입니다.", $tempDir, $fileGuid);
    }
    $cleanTargetDir = trim($targetDir, '/');

    $relPath = str_replace('\\', '/', $rawRelPath);
    $relPath = preg_replace('#/+#', '/', $relPath);
    if ($relPath === '.' || $relPath === '..' || preg_match('#(^|/)\.\.?(/|$)#', $relPath)) {
        failMerge(400, "잘못된 파일 상대 경로입니다.", $tempDir, $fileGuid);
    }
    $cleanRelPath = trim($relPath, '/');

    $basePath = QPLUS_STORAGE_DIR;
    if ($_SESSION['role'] !== 'admin') {
        $config = include QPLUS_CONFIG_FILE;
        $folderPasswords = $config['folder_pws'] ?? array();
        $lockedFolder = findLockedFolderPath($cleanTargetDir, $folderPasswords, $_SESSION['unlocked_folders'] ?? array());
        if ($lockedFolder !== null) {
            failMerge(403, "폴더 잠금을 먼저 해제하세요.", $tempDir, $fileGuid);
        }
    }

    $uploadDir = $basePath . ($cleanTargetDir === '' ? '' : $cleanTargetDir . '/');
    $fileName = !empty($cleanRelPath) ? basename($cleanRelPath) : "uploaded_file";
    $fileName = trim($fileName, ". ");

    if ($fileName === '' || strpos($fileName, "\0") !== false || preg_match('/[<>:"\/\\\\|?*]/', $fileName)) {
        failMerge(400, "사용할 수 없는 파일 이름입니다.", $tempDir, $fileGuid);
    }

    // 허용 확장자 검증 (서버 스크립트 .php, .phtml, .asp, .cgi 및 XSS 위험 .svg, .html 제외 모든 실사용 확장자 허용)
    $allowed = array(
        // 이미지
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'tif', 'tiff', 'heic', 'raw', 'cr2', 'nef',
        // 영상 / 비디오
        'mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'ts', 'mts', 'm2ts', 'vob', '3gp',
        // 음향 / 오디오
        'mp3', 'wav', 'flac', 'aac', 'ogg', 'wma', 'm4a', 'aiff', 'alac', 'mid', 'midi',
        // 문서 / 텍스트
        'pdf', 'txt', 'md', 'csv', 'rtf', 'hwp', 'hwpx', 'doc', 'docx',
        'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'log', 'json', 'xml', 'yaml', 'yml',
        // 압축 / 디스크 이미지
        'zip', '7z', 'rar', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'iso', 'img', 'dmg', 'wim',
        // 3D / 그래픽 / 디자인
        'blend', 'fbx', 'obj', 'max', 'c4d', 'mb', 'ma', 'psd', 'psb', 'ai', 'eps', 'clip',
        'prproj', 'aep', 'skp', 'dae', '3ds', 'stl', 'step', 'stp', 'iges', 'igs', 'dxf', 'dwg',
        // 프로그램 / 패키지 / 폰트 / 기타 리소스
        'apk', 'apks', 'xapk', 'exe', 'msi', 'cmd', 'bat', 'reg', 'ttf', 'otf', 'woff', 'woff2'
    );
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowed, true)) {
        failMerge(400, "허용되지 않는 파일 형식입니다. (." . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . ")", $tempDir, $fileGuid);
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

    // 업로드 경로가 스토리지(QPLUS_STORAGE_DIR) 하위인지 검증 (Path Traversal 차단)
    $baseRealPath = realpath($basePath);
    $uploadRealPath = realpath($uploadDir);
    if ($uploadRealPath === false) {
        $uploadRealPath = realpath($sysUploadDir);
    }
    if ($baseRealPath === false || $uploadRealPath === false) {
        failMerge(400, "유효하지 않은 디렉터리 경로입니다.", $tempDir, $fileGuid);
    }
    $baseNorm = rtrim(str_replace('\\', '/', $baseRealPath), '/') . '/';
    $uploadNorm = rtrim(str_replace('\\', '/', $uploadRealPath), '/') . '/';
    if (strncasecmp($uploadNorm, $baseNorm, strlen($baseNorm)) !== 0) {
        failMerge(403, "허용되지 않은 업로드 경로입니다.", $tempDir, $fileGuid);
    }

    $utf8TargetFilePath = $uploadDir . $fileName;
    $sysTargetFilePath = toSys($utf8TargetFilePath);

    if (file_exists($utf8TargetFilePath) || file_exists($sysTargetFilePath)) {
        failMerge(409, "같은 이름의 파일이 이미 존재합니다.", $tempDir, $fileGuid);
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
        failMerge(500, "파일 생성 권한 오류: 경로를 확인하세요 (" . $uploadDir . ")", $tempDir, $fileGuid);
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkFilePath = $tempDir . $fileGuid . "_" . $i . ".tmp";
        if (!file_exists($chunkFilePath)) {
            $chunkFilePath = toSys($tempDir . $fileGuid . "_" . $i . ".tmp");
        }
        
        // 조각이 하나라도 유실되었다면 즉시 병합 중지 및 불량 파일 삭제
        if (!file_exists($chunkFilePath)) {
            @fclose($out);
            @unlink($targetPathToUse); // 만들다 만 불량 파일 삭제
            failMerge(500, "치명적 오류: " . $i . "번째 파일 조각이 유실되었습니다. (네트워크 상태를 확인해주세요.)", $tempDir, $fileGuid);
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
exit("잘못된 요청입니다.");
?>
