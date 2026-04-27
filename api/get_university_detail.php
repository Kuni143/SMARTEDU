<?php
// ── api/get_university_detail.php ─────────────────────────────────────────
// GET ?name=Ateneo+de+Manila+University
// Returns full details of a single university by name.
// ─────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$name = trim($_GET['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing name parameter.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.type,
            u.location,
            u.description,
            u.campus_branches,
            u.tuition_fees,
            u.exam,
            u.requirements,
            u.enrollment_requirements,
            u.contact_links
        FROM universities u
        WHERE u.name = :name
        LIMIT 1
    ");
    $stmt->execute([':name' => $name]);
    $university = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$university) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'University not found.']);
        exit;
    }

    // Fetch all courses offered by this university
    $cStmt = $pdo->prepare("
        SELECT course_name
        FROM university_courses
        WHERE university_id = :id
        ORDER BY course_name ASC
    ");
    $cStmt->execute([':id' => $university['id']]);
    $university['courses'] = $cStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success'    => true,
        'university' => $university,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage(),
    ]);
}