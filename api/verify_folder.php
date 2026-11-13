<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../folder_access.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => '로그인이 필요합니다.'));
    exit;
}

$config = include __DIR__ . '/../config.php';
$folderPasswords = $config['folder_pws'] ?? array();

$input = json_decode(file_get_contents('php://input'), true);
$folderName = normalizeFolderPath($input['folder'] ?? '');
$password = trim($input['password'] ?? '');

if ($_SESSION['role'] === 'admin' || !isset($folderPasswords[$folderName]) || $folderPasswords[$folderName] === '') {
    echo json_encode(array('success' => true));
    exit;
}

if (!empty($_SESSION['unlocked_folders'][$folderName])) {
    echo json_encode(array('success' => true));
    exit;
}

if (verifyPassword($password, $folderPasswords[$folderName])) {
    $_SESSION['unlocked_folders'][$folderName] = true;
    echo json_encode(array('success' => true));
} else {
    echo json_encode(array('success' => false, 'message' => '비밀번호가 일치하지 않습니다.'));
}
exit;
