<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
} elseif (isset($_SESSION['student_id'])) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $_SESSION['student_id']]);
        $row = $stmt->fetch();
        if ($row && $row['user_id']) {
            $user_id = (int) $row['user_id'];
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
        exit;
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Use $_POST instead of php://input since we're sending FormData
$university_id = (int) ($_POST['university_id'] ?? 0);

if (!$university_id) {
    echo json_encode([
        'success'  => false,
        'message'  => 'Invalid university ID',
        'post'     => $_POST   // debug: shows what arrived
    ]);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND university_id = ?");
    $stmt->execute([$user_id, $university_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE user_id = ? AND university_id = ?");
        $stmt->execute([$user_id, $university_id]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, university_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $university_id]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}