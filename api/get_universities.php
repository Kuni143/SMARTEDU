<?php
// ── api/get_universities.php ──────────────────────────────────────────────
// GET ?course=BS+Computer+Science
// Returns all universities that offer the requested course, with their
// full info from the universities table.
// ─────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

$course = trim($_GET['course'] ?? '');

if ($course === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing course parameter.']);
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
        INNER JOIN university_courses uc
            ON uc.university_id = u.id
           AND uc.course_name   = :course
        ORDER BY u.name ASC
    ");
    $stmt->execute([':course' => $course]);
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'      => true,
        'course'       => $course,
        'universities' => $universities,
        'count'        => count($universities),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage(),
    ]);
}