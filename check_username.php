<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');

if (strlen($username) < 3 || strlen($username) > 50) {
    echo json_encode(['taken' => false]);
    exit;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    echo json_encode(['taken' => (bool) $stmt->fetch()]);
} catch (PDOException $e) {
    echo json_encode(['taken' => false]);
}