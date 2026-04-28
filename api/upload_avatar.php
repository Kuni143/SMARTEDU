<?php
require_once '../config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file     = $_FILES['avatar'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB allowed']);
    exit;
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed']);
    exit;
}

$upload_dir = '../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$user_id   = (int) $_SESSION['user_id'];
$ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename  = 'avatar_' . $user_id . '_' . time() . '.' . strtolower($ext);
$dest_path = $upload_dir . $filename;

// Delete old avatar if exists
$stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$old = $stmt->fetchColumn();
if ($old) {
    $old_path = '../' . $old;
    if (file_exists($old_path)) {
        unlink($old_path);
    }
}

if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

$avatar_url = 'uploads/avatars/' . $filename;

$stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
$stmt->execute([$avatar_url, $user_id]);

echo json_encode(['success' => true, 'avatar_url' => $avatar_url]);