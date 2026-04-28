// get_profile.php
<?php
require_once '../config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

try {
    $pdo = getDB();

    // Get user info + latest student record
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.avatar_url, u.username_changed_at,
               s.grade, s.strand, s.id AS student_id
        FROM users u
        LEFT JOIN students s ON s.user_id = u.id
        WHERE u.id = ?
        ORDER BY s.id DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Compute days until username can be changed
    $can_change_username = true;
    $days_remaining = 0;
    if (!empty($user['username_changed_at'])) {
        $changed     = new DateTime($user['username_changed_at']);
        $now         = new DateTime();
        $diff        = $now->diff($changed);
        $days_passed = (int) $diff->days;
        if ($days_passed < 30) {
            $can_change_username = false;
            $days_remaining      = 30 - $days_passed;
        }
    }

    echo json_encode([
        'success'             => true,
        'username'            => $user['username'],
        'avatar_url'          => $user['avatar_url'],
        'grade'               => $user['grade']  ?? null,
        'strand'              => $user['strand'] ?? null,
        'can_change_username' => $can_change_username,
        'days_remaining'      => $days_remaining,
        'student_id'          => $user['student_id'],
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}