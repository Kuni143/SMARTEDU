<?php
require_once '../config/db.php';
$pdo = getDB();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$new_username = trim($data['username'] ?? '');

if (empty($new_username)) {
    echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
    exit;
}

if (strlen($new_username) < 3 || strlen($new_username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3–50 characters']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
    echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Check 30-day restriction
$stmt = $pdo->prepare("SELECT username_changed_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($row['username_changed_at'])) {
    $changed     = new DateTime($row['username_changed_at']);
    $now         = new DateTime();
    $diff        = $now->diff($changed);
    $days_passed = (int) $diff->days;
    if ($days_passed < 30) {
        $remaining = 30 - $days_passed;
        echo json_encode([
            'success' => false,
            'message' => "You can only change your username once every 30 days. Please wait {$remaining} more day(s)."
        ]);
        exit;
    }
}

// Check uniqueness
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$new_username, $user_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Username is already taken']);
    exit;
}

// Update
$stmt = $pdo->prepare("UPDATE users SET username = ?, username_changed_at = NOW() WHERE id = ?");
$stmt->execute([$new_username, $user_id]);

$_SESSION['username'] = $new_username;

echo json_encode(['success' => true, 'username' => $new_username]);