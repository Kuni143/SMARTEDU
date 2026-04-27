<?php
// ── api/submit_response.php ────────────────────────────────────────────────
// Receives JSON POST from studform.php, saves student + 60 responses,
// computes top-5 course recommendations, stores them in student_results,
// then returns { success: true, student_id: N }.
//
// Scoring formula (mirrors scoring_engine image):
//   score = (skills_match  × 0.40)
//         + (interest_match × 0.30)
//         + (strand_align   × 0.20)
//         + (grades_factor  × 0.10)
//
// Each component is normalised to [0–1] before weighting.
// ──────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// ── Parse JSON body ────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

// ── Allowed values ─────────────────────────────────────────────────────────
$allowedGrades  = ['Grade 11', 'Grade 12'];
$allowedStrands = ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'Arts and Design'];

$scaleMap = [
    5 => 'Strongly Agree',
    4 => 'Agree',
    3 => 'Neutral',
    2 => 'Disagree',
    1 => 'Strongly Disagree',
];

// ── Extract & validate fields ──────────────────────────────────────────────
$grade   = $body['grade']  ?? null;
$strand  = $body['strand'] ?? null;
$gpa     = (isset($body['gpa']) && $body['gpa'] !== '') ? (float)$body['gpa'] : null;
$answers = $body['answers'] ?? [];

if (!in_array($grade, $allowedGrades, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid grade level.']);
    exit;
}
if (!in_array($strand, $allowedStrands, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid strand.']);
    exit;
}
if ($gpa !== null && ($gpa < 0 || $gpa > 100)) {
    http_response_code(422);
    echo json_encode(['error' => 'GPA must be between 0 and 100.']);
    exit;
}
if (empty($answers) || !is_array($answers)) {
    http_response_code(422);
    echo json_encode(['error' => 'No answers provided.']);
    exit;
}

// Clean & validate each answer
$cleanAnswers = [];
foreach ($answers as $qNo => $scaleVal) {
    $qNo      = (int)$qNo;
    $scaleVal = (int)$scaleVal;
    if ($qNo < 1 || $qNo > 60) continue;
    if (!array_key_exists($scaleVal, $scaleMap)) {
        http_response_code(422);
        echo json_encode(['error' => "Invalid scale value ($scaleVal) for Q$qNo. Must be 1–5."]);
        exit;
    }
    $cleanAnswers[$qNo] = ['scale_value' => $scaleVal, 'sentiment' => $scaleMap[$scaleVal]];
}

if (count($cleanAnswers) < 60) {
    http_response_code(422);
    echo json_encode(['error' => 'Incomplete submission. All 60 questions must be answered.', 'received' => count($cleanAnswers)]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
//  SCORING ENGINE
//  score = (skills_match × 0.40) + (interest_match × 0.30)
//        + (strand_align × 0.20) + (grades_factor  × 0.10)
//
//  Section question ranges:
//    A  Interests        Q1  – Q15   (15 questions)
//    B  Skills           Q16 – Q30   (15 questions)
//    C  Academic Str.    Q31 – Q40   (10 questions)
//    D  Strand Align.    Q41 – Q50   (10 questions)
//    E  Career Prefs.    Q51 – Q60   (10 questions)
//
//  Component definitions per course (which section questions are relevant):
//    skills_match   = avg of relevant B questions, normalised to [0-1]
//    interest_match = avg of relevant A + E questions, normalised to [0-1]
//    strand_align   = avg of relevant D questions + strand bonus, to [0-1]
//    grades_factor  = normalised GPA (0 if not provided, uses C section avg)
// ══════════════════════════════════════════════════════════════════════════

// ── Helper: average of given question numbers, normalised 0→1 ─────────────
function avgNorm(array $qNums, array $answers): float {
    if (empty($qNums)) return 0.0;
    $sum = 0;
    foreach ($qNums as $n) {
        $sum += $answers[$n]['scale_value'] ?? 3; // default neutral
    }
    // scale_value range 1-5 → normalise to 0-1
    $avg = $sum / count($qNums);
    return ($avg - 1) / 4;
}

// ── Grades factor ──────────────────────────────────────────────────────────
// Use GPA if provided (0-100 → 0-1), otherwise fall back to Academic section avg
function gradesFactor(?float $gpa, array $answers): float {
    if ($gpa !== null) {
        return min(1.0, max(0.0, $gpa / 100));
    }
    // Academic Strengths Q31-Q40 as proxy
    return avgNorm(range(31, 40), $answers);
}

// ── Strand bonus: does the course align with the student's SHS strand? ─────
// Returns 0.0 – 1.0 additional boost folded into strand_align
function strandBonus(string $studentStrand, string $courseStrand): float {
    $map = [
        'STEM'           => ['STEM', 'Science', 'Engineering', 'Technology', 'Health', 'Medicine', 'Architecture'],
        'ABM'            => ['Business', 'Accountancy', 'Management', 'Finance', 'Commerce'],
        'HUMSS'          => ['Communication', 'Political', 'Education', 'Social', 'Public', 'Law', 'Journalism'],
        'GAS'            => ['General', 'Business', 'Education', 'Public', 'Communication'],
        'TVL'            => ['Technology', 'Industrial', 'Technical', 'Electrical', 'Electronics', 'Engineering'],
        'Arts and Design'=> ['Architecture', 'Design', 'Fine Arts', 'Communication', 'Media'],
    ];
    $keywords = $map[$studentStrand] ?? [];
    foreach ($keywords as $kw) {
        if (stripos($courseStrand, $kw) !== false) return 1.0;
    }
    return 0.3; // partial credit — any course is possible
}

// ── Course profiles ────────────────────────────────────────────────────────
// Each entry defines which question numbers drive each scoring component.
// skills_q   → Section B questions most relevant to the course
// interest_q → Section A + E questions most relevant
// strand_q   → Section D questions (generic strand alignment)
// strand_kw  → keyword used for strandBonus lookup
$COURSE_PROFILES = [

    // ── STEM / Science ──────────────────────────────────────────────────
    'BS Biology' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],          // analytical, understand, computers, lead, research
        'interest_q'=> [1, 2, 8, 9, 51, 57, 59],      // math, science, health, data, problem-solving, tech, learning
        'strand_kw' => 'Science',
    ],
    'BS Nursing' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [17, 18, 23, 24, 28],           // communication, teamwork, decisions, organise, pressure
        'interest_q'=> [4, 8, 15, 52, 55, 59],         // helping people, health, teaching, helping, stable, learning
        'strand_kw' => 'Health',
    ],
    'BS Medical Technology' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [2, 8, 9, 51, 55, 59],
        'strand_kw' => 'Health',
    ],
    'BS Pharmacy' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 25, 26, 27],
        'interest_q'=> [1, 2, 8, 9, 55, 59],
        'strand_kw' => 'Health',
    ],

    // ── Technology / IT ─────────────────────────────────────────────────
    'BS Computer Science' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27, 30],       // analytical, understand, computers, research
        'interest_q'=> [1, 3, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Information Technology' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'AB Communications' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Communication' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],

    // ── Engineering ─────────────────────────────────────────────────────
    'BS Computer Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 10, 51, 57, 59],
        'strand_kw' => 'Engineering',
    ],
    'BS Electronics Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 2, 3, 9, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Civil Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57, 59],
        'strand_kw' => 'Engineering',
    ],
    'BS Electrical Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Chemical Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Industrial Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 24, 25],
        'interest_q'=> [1, 9, 10, 13, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Mechanical Technology' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Electronics Technology' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 6, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Electrical Technology' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Industrial Technology' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 24, 25],
        'interest_q'=> [10, 13, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],

    // ── Architecture / Design ────────────────────────────────────────────
    'BS Architecture' => [
        'field'     => 'Design & Architecture',
        'skills_q'  => [20, 22, 25, 26, 27],
        'interest_q'=> [6, 7, 10, 51, 54, 59],
        'strand_kw' => 'Architecture',
    ],

    // ── Business ────────────────────────────────────────────────────────
    'BS Accountancy' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 13, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Management Engineering' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 24, 26],
        'interest_q'=> [5, 9, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 13, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Office Administration' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 24, 25, 26],
        'interest_q'=> [5, 13, 51, 55, 58],
        'strand_kw' => 'Business',
    ],

    // ── Social / Humanities ──────────────────────────────────────────────
    'BA Political Science' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'AB Political Science' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'BS Public Administration' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 23, 26, 29],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Public',
    ],
    'BS Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
];

// ── Compute score per course ───────────────────────────────────────────────
$courseScores = [];

foreach ($COURSE_PROFILES as $courseName => $profile) {

    // 1. Skills match  (weight 0.40)
    $skillsMatch   = avgNorm($profile['skills_q'],   $cleanAnswers);

    // 2. Interest match  (weight 0.30)
    $interestMatch = avgNorm($profile['interest_q'], $cleanAnswers);

    // 3. Strand alignment  (weight 0.20)
    //    = avg of ALL Section D questions × strand bonus
    $strandBase    = avgNorm(range(41, 50), $cleanAnswers);
    $bonus         = strandBonus($strand, $courseName . ' ' . $profile['field'] . ' ' . $profile['strand_kw']);
    $strandAlign   = min(1.0, $strandBase * 0.6 + $bonus * 0.4);

    // 4. Grades factor  (weight 0.10)
    $gradesFact    = gradesFactor($gpa, $cleanAnswers);

    // Final weighted score  (always sums to 1.0 weight)
    $score = ($skillsMatch   * 0.40)
           + ($interestMatch * 0.30)
           + ($strandAlign   * 0.20)
           + ($gradesFact    * 0.10);

    $courseScores[$courseName] = [
        'field'          => $profile['field'],
        'score'          => round($score, 6),
        'skills_match'   => round($skillsMatch,   4),
        'interest_match' => round($interestMatch, 4),
        'strand_align'   => round($strandAlign,   4),
        'grades_factor'  => round($gradesFact,    4),
    ];
}

// Sort descending by final score
uasort($courseScores, fn($a, $b) => $b['score'] <=> $a['score']);

// ── Persist ────────────────────────────────────────────────────────────────
try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // logged-in user from session
    $userId = $_SESSION['user_id'] ?? null;

    // 1. Insert student record
    $stmt = $pdo->prepare("
        INSERT INTO students (user_id, grade, strand, gpa)
        VALUES (:user_id, :grade, :strand, :gpa)
    ");
    $stmt->execute([':user_id' => $userId, ':grade' => $grade, ':strand' => $strand, ':gpa' => $gpa]);
    $studentId = (int)$pdo->lastInsertId();

    // 2. Insert all 60 responses
    $rStmt = $pdo->prepare("
        INSERT INTO responses (student_id, question_no, scale_value, sentiment)
        VALUES (:sid, :qno, :scale, :sentiment)
        ON DUPLICATE KEY UPDATE scale_value = VALUES(scale_value), sentiment = VALUES(sentiment)
    ");
    foreach ($cleanAnswers as $qNo => $ans) {
        $rStmt->execute([':sid' => $studentId, ':qno' => $qNo, ':scale' => $ans['scale_value'], ':sentiment' => $ans['sentiment']]);
    }

    // 3. Insert top-5 results into student_results
    //    Only insert courses that exist in university_courses table
    $existStmt = $pdo->query('SELECT DISTINCT course_name FROM university_courses');
    $existingCourses = array_flip($existStmt->fetchAll(PDO::FETCH_COLUMN));

    $resStmt = $pdo->prepare("
        INSERT INTO student_results (student_id, `rank`, course_name, field_name, score)
        VALUES (:sid, :rank, :course, :field, :score)
    ");

    $rank = 1;
    foreach ($courseScores as $courseName => $data) {
        if (!isset($existingCourses[$courseName])) continue;
        if ($rank > 5) break;
        $resStmt->execute([
            ':sid'    => $studentId,
            ':rank'   => $rank,
            ':course' => $courseName,
            ':field'  => $data['field'],
            ':score'  => $data['score'],
        ]);
        $rank++;
    }

    $pdo->commit();

    // Store in session for results page
    $_SESSION['student_id'] = $studentId;

    echo json_encode([
        'success'    => true,
        'student_id' => $studentId,
        'saved'      => count($cleanAnswers),
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}