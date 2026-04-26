<?php
// ── api/submit_response.php ───────────────────────────
// Receives a student questionnaire submission from studform.html
// and saves it to the `responses` table.
//
// Expected POST body (JSON):
// {
//   "grade":    "Grade 11",
//   "strand":   "STEM",
//   "gpa":      90.5,           // optional, null if not provided
//   "answers": {
//     "1":  5,   // Strongly Agree    = 5
//     "2":  4,   // Agree             = 4
//     "3":  3,   // Neutral           = 3
//     "4":  2,   // Disagree          = 2
//     "5":  1,   // Strongly Disagree = 1
//     ...
//     "60": 4
//   }
// }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── Parse JSON body ───────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

// ── Allowed values ────────────────────────────────────
$allowedGrades  = ['Grade 11', 'Grade 12'];
$allowedStrands = ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'Arts and Design', 'Sports'];

// Numeric scale value → sentiment label (mirrors SCALE_VALS in studform.js)
$scaleMap = [
    5 => 'Strongly Agree',
    4 => 'Agree',
    3 => 'Neutral',
    2 => 'Disagree',
    1 => 'Strongly Disagree',
];

// ── Extract fields ────────────────────────────────────
$grade   = $body['grade']  ?? null;
$strand  = $body['strand'] ?? null;
$gpa     = (isset($body['gpa']) && $body['gpa'] !== '') ? (float) $body['gpa'] : null;
$answers = $body['answers'] ?? [];

// Validate grade
if (!in_array($grade, $allowedGrades, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid grade level.']);
    exit;
}

// Validate strand
if (!in_array($strand, $allowedStrands, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid strand.']);
    exit;
}

// Validate GPA
if ($gpa !== null && ($gpa < 0 || $gpa > 100)) {
    http_response_code(422);
    echo json_encode(['error' => 'GPA must be between 0 and 100.']);
    exit;
}

// Validate answers array
if (empty($answers) || !is_array($answers)) {
    http_response_code(422);
    echo json_encode(['error' => 'No answers provided.']);
    exit;
}

$cleanAnswers = [];
foreach ($answers as $qNo => $scaleVal) {
    $qNo      = (int) $qNo;
    $scaleVal = (int) $scaleVal;

    if ($qNo < 1 || $qNo > 60) continue; // skip out-of-range

    if (!array_key_exists($scaleVal, $scaleMap)) {
        http_response_code(422);
        echo json_encode(['error' => "Invalid scale value ($scaleVal) for question $qNo. Must be 1–5."]);
        exit;
    }

    $cleanAnswers[$qNo] = [
        'scale_value' => $scaleVal,
        'sentiment'   => $scaleMap[$scaleVal],
    ];
}

// All 60 questions are required
if (count($cleanAnswers) < 60) {
    http_response_code(422);
    echo json_encode([
        'error'    => 'Incomplete submission. All 60 questions must be answered.',
        'received' => count($cleanAnswers),
    ]);
    exit;
}

// ── Persist ───────────────────────────────────────────
try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 1. Insert student record (grade + strand + optional GPA)
    $stmt = $pdo->prepare("
        INSERT INTO students (grade, strand, gpa)
        VALUES (:grade, :strand, :gpa)
    ");
    $stmt->execute([
        ':grade'  => $grade,
        ':strand' => $strand,
        ':gpa'    => $gpa,
    ]);
    $studentId = (int) $pdo->lastInsertId();

    // 2. Insert responses — stores both raw scale value AND sentiment label
    $stmt = $pdo->prepare("
        INSERT INTO responses (student_id, question_no, scale_value, sentiment)
        VALUES (:student_id, :question_no, :scale_value, :sentiment)
        ON DUPLICATE KEY UPDATE
            scale_value = VALUES(scale_value),
            sentiment   = VALUES(sentiment)
    ");

    foreach ($cleanAnswers as $qNo => $ans) {
        $stmt->execute([
            ':student_id'  => $studentId,
            ':question_no' => $qNo,
            ':scale_value' => $ans['scale_value'],
            ':sentiment'   => $ans['sentiment'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'student_id' => $studentId,
        'saved'      => count($cleanAnswers),
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}