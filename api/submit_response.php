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

    // ════════════════════════════════════════════════════════════════════
    // SCIENCE & HEALTH
    // ════════════════════════════════════════════════════════════════════
    'BS Biology' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 8, 9, 51, 57, 59],
        'strand_kw' => 'Science',
    ],
    'BS Nursing' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 8, 15, 52, 55, 59],
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
    'BS Physical Therapy' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 8, 15, 52, 55, 59],
        'strand_kw' => 'Health',
    ],
    'BS Radiologic Technology' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [2, 8, 9, 51, 55, 59],
        'strand_kw' => 'Health',
    ],
    'BS Nutrition and Dietetics' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 25, 26, 27],
        'interest_q'=> [2, 8, 9, 51, 55, 59],
        'strand_kw' => 'Health',
    ],
    'BS Psychology' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 27],
        'interest_q'=> [4, 8, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'BS Chemistry' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 9, 51, 57, 59],
        'strand_kw' => 'Science',
    ],
    'BS Biochemistry' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 8, 9, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Applied Physics' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 9, 10, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Physics' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 9, 10, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Mathematics' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 9, 51, 57, 59],
        'strand_kw' => 'Science',
    ],
    'BS Statistics' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 9, 51, 57, 59],
        'strand_kw' => 'Science',
    ],
    'BS Astronomy' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Molecular Biology and Biotechnology' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 2, 8, 9, 51, 57],
        'strand_kw' => 'Science',
    ],
    'Doctor of Medicine' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 17, 20, 23, 25],
        'interest_q'=> [2, 8, 9, 51, 55, 59],
        'strand_kw' => 'Medicine',
    ],
    'Doctor of Dental Medicine' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [17, 18, 20, 23, 25],
        'interest_q'=> [2, 8, 9, 51, 55],
        'strand_kw' => 'Medicine',
    ],
    'Doctor of Optometry' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [2, 8, 9, 51, 55],
        'strand_kw' => 'Medicine',
    ],
    'Doctor of Veterinary Medicine' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 17, 20, 23, 25],
        'interest_q'=> [2, 8, 9, 51, 55],
        'strand_kw' => 'Medicine',
    ],
    'BS Midwifery' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 8, 15, 52, 55],
        'strand_kw' => 'Health',
    ],
    'BS-MD Integrated Program' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 17, 20, 23, 25],
        'interest_q'=> [2, 8, 9, 51, 55, 59],
        'strand_kw' => 'Medicine',
    ],
    'BS Cosmetic Science' => [
        'field'     => 'Health & Medicine',
        'skills_q'  => [16, 20, 25, 26, 27],
        'interest_q'=> [2, 8, 9, 51, 55],
        'strand_kw' => 'Science',
    ],

    // ════════════════════════════════════════════════════════════════════
    // TECHNOLOGY & IT
    // ════════════════════════════════════════════════════════════════════
    'BS Computer Science' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27, 30],
        'interest_q'=> [1, 3, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Information Technology' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Information Systems' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 13, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Information Management' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 13, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Cybersecurity' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 9, 10, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Artificial Intelligence' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Data Science' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 3, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Blockchain Technology' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 9, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Computer Science and Information Engineering' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Computer Applications' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Math with Computer' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Applied Mathematics Major in Data Science' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [1, 3, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Entertainment and Multimedia Computing' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 7, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Entertainment & Multimedia Computing' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 7, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Game Development' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 7, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Animation' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BS Multimedia Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BS Data Science and Business Administration' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 3, 9, 13, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Business Analytics with AI' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 3, 9, 13, 51, 57],
        'strand_kw' => 'Technology',
    ],

    // ════════════════════════════════════════════════════════════════════
    // ENGINEERING & TECHNOLOGY
    // ════════════════════════════════════════════════════════════════════
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
    'BS Electronics & Communications Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Electronics and Communications Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 10, 51, 57],
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
    'BS Mechanical Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
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
    'BS Aeronautical Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Aerospace Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Construction Engineering and Management' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 24, 25],
        'interest_q'=> [1, 10, 13, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Environmental Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 9, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Robotics Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Mechatronics' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Geodetic Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 9, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Materials Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Metallurgical Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Mining Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Petroleum Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 2, 9, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Railway Engineering' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Instrumentation & Control' => [
        'field'     => 'Engineering & Technology',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 9, 10, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Environmental Science' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 9, 10, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Food Technology' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 26, 27],
        'interest_q'=> [2, 8, 9, 51, 55],
        'strand_kw' => 'Science',
    ],
    'BS Animal Science' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 20, 25, 27, 30],
        'interest_q'=> [2, 8, 9, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Geology' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 9, 10, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BS Geography' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 9, 10, 51, 57],
        'strand_kw' => 'Science',
    ],

    // ════════════════════════════════════════════════════════════════════
    // ARCHITECTURE & DESIGN
    // ════════════════════════════════════════════════════════════════════
    'BS Architecture' => [
        'field'     => 'Design & Architecture',
        'skills_q'  => [20, 22, 25, 26, 27],
        'interest_q'=> [6, 7, 10, 51, 54, 59],
        'strand_kw' => 'Architecture',
    ],
    'BS Interior Design' => [
        'field'     => 'Design & Architecture',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 10, 51, 54],
        'strand_kw' => 'Design',
    ],
    'BS Landscape Architecture' => [
        'field'     => 'Design & Architecture',
        'skills_q'  => [20, 22, 25, 26, 27],
        'interest_q'=> [6, 7, 10, 51, 54],
        'strand_kw' => 'Architecture',
    ],
    'BS Industrial Design' => [
        'field'     => 'Design & Architecture',
        'skills_q'  => [20, 22, 25, 26, 27],
        'interest_q'=> [6, 7, 10, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'Bachelor of Fine Arts – Painting' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Fine Arts',
    ],
    'Bachelor of Fine Arts – Visual Communication' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Fine Arts',
    ],
    'Bachelor of Fine Arts Major in Studio Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Fine Arts',
    ],
    'Bachelor of Multimedia Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'Bachelor in Multimedia Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BA Multimedia Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BA Multimedia Arts and Design' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BA Fashion Design and Technology' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Design',
    ],
    'BA Fashion Design and Marketing' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [5, 6, 7, 11, 51, 54],
        'strand_kw' => 'Design',
    ],
    'BA Fashion Design and Merchandising' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [5, 6, 7, 11, 51, 54],
        'strand_kw' => 'Design',
    ],
    'BA Film and Visual Effects' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Media',
    ],
    'BA Digital Film' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54, 57],
        'strand_kw' => 'Media',
    ],
    'BA Music Production' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Arts',
    ],
    'BA Music Production and Sound Design' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Arts',
    ],
    'BA Production Design' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 11, 51, 54],
        'strand_kw' => 'Design',
    ],
    'BA Theater Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 26, 27, 28, 29],
        'interest_q'=> [7, 11, 15, 51, 54],
        'strand_kw' => 'Arts',
    ],
    'Bachelor of Performing Arts Major in Dance' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 26, 27, 28, 29],
        'interest_q'=> [7, 11, 15, 51, 54],
        'strand_kw' => 'Arts',
    ],
    'BA Broadcast Media' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Media',
    ],
    'BA Broadcasting' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],

    // ════════════════════════════════════════════════════════════════════
    // BUSINESS & FINANCE
    // ════════════════════════════════════════════════════════════════════
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
    'BS Management Accounting' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Accounting Information System' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 21, 24, 25],
        'interest_q'=> [3, 5, 9, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Accounting Technology' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Entrepreneurship' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 22, 23, 26],
        'interest_q'=> [5, 13, 14, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Entrepreneurial Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 22, 23, 26],
        'interest_q'=> [5, 13, 14, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Economics' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 24, 26],
        'interest_q'=> [5, 9, 12, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Economics' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 25, 26],
        'interest_q'=> [5, 9, 12, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Legal Management' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Law',
    ],
    'Legal Management' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Law',
    ],
    'Juris Doctor' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Law',
    ],
    'BS Internal Auditing' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Real Estate Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 22, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Marketing' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Financial Management and Technology' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 21, 24],
        'interest_q'=> [3, 5, 9, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS International Business' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 22, 23, 26],
        'interest_q'=> [5, 12, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Customs Administration' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 12, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Supply Chain Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Human Resource Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Human Resource and Organizational Development' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Human Resources and Organizational Development' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Social Innovation and Entrepreneurship' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 5, 13, 15, 51, 53],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Financial Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Marketing Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Human Resource Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Operations Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Management Information System' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 21, 24],
        'interest_q'=> [3, 5, 9, 13, 51, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Business Economics' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 24, 26],
        'interest_q'=> [5, 9, 12, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Supply Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Building and Property Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 22, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Technology Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 21, 24],
        'interest_q'=> [3, 5, 9, 13, 51, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Technopreneurship' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 21, 24],
        'interest_q'=> [3, 5, 9, 13, 51, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration Major in Sustainability' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [2, 5, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Business Administration with Specialization in Hospitality' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [5, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],
    'BS Office Administration Major in Legal Transcription' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 24, 25, 26],
        'interest_q'=> [5, 13, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Office Administration Major in Airline Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [5, 13, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BSBA' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 13, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA (Management Analytics)' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 18, 19, 24, 26],
        'interest_q'=> [5, 9, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA (Marketing Finance, HR)' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 19, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA (Marketing Finance, HR, Operations)' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 19, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA Business Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 13, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA Financial Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [16, 19, 24, 25, 26],
        'interest_q'=> [5, 9, 51, 53, 55, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA Marketing' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BSBA Marketing Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [5, 11, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BSHM' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Disaster Risk Management' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 12, 13, 15, 52, 56],
        'strand_kw' => 'Public',
    ],
    'BS Mass Communication' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BS Retail Technology and Consumer Science' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 21, 23, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],
    'BS Secretarial Education' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 22, 24, 26],
        'interest_q'=> [5, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Cooperatives' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 5, 13, 15, 51, 56],
        'strand_kw' => 'Business',
    ],
    'BS Transportation Management' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 56],
        'strand_kw' => 'Business',
    ],

    // ════════════════════════════════════════════════════════════════════
    // COMMUNICATION & MEDIA
    // ════════════════════════════════════════════════════════════════════
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
    'AB Communication' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Communication Arts' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Communication Research' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Mass Communication' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'AB Mass Communication' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Journalism' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 12, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA English Language Studies' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'AB English' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'Bachelor of Arts in English' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'BA Filipinology' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 15, 52, 54, 59],
        'strand_kw' => 'Communication',
    ],
    'AB Integrated Marketing' => [
        'field'     => 'Communication & Media',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [5, 7, 11, 13, 51, 54],
        'strand_kw' => 'Communication',
    ],
    'BSIT (Regular & Business Analytics)' => [
        'field'     => 'Technology & IT',
        'skills_q'  => [16, 19, 21, 25, 27],
        'interest_q'=> [3, 5, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],

    // ════════════════════════════════════════════════════════════════════
    // SOCIAL SCIENCES & HUMANITIES
    // ════════════════════════════════════════════════════════════════════
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
    'AB Political Economy' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [9, 12, 13, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'BA Political Science Major in Local Government Administration' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'BA Political Science Major in Paralegal Studies' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'BA Political Science Major in Policy Management' => [
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
    'Bachelor of Public Administration' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 23, 26, 29],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Public',
    ],
    'AB Psychology' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 27],
        'interest_q'=> [4, 8, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'AB Humanities' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'AB Economics' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [16, 18, 19, 25, 26],
        'interest_q'=> [5, 9, 12, 51, 53, 56],
        'strand_kw' => 'Social',
    ],
    'AB History' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'AB Philosophy' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'AB Behavioral Science' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 27],
        'interest_q'=> [4, 8, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'BA International Studies' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Social',
    ],
    'BA Diplomacy and International Affairs' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Political',
    ],
    'BA Governance and Public Affairs' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 23, 26, 29],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Public',
    ],
    'BS Social Work' => [
        'field'     => 'Public Service',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 12, 13, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'BS Criminology' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [12, 13, 15, 52, 56, 59],
        'strand_kw' => 'Law',
    ],
    'BS Forensic Science' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [16, 17, 20, 23, 25],
        'interest_q'=> [2, 9, 12, 51, 56, 59],
        'strand_kw' => 'Science',
    ],
    'BS Industrial Security Management' => [
        'field'     => 'Law & Social Sciences',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [10, 12, 13, 52, 56, 59],
        'strand_kw' => 'Law',
    ],
    'BA History' => [
        'field'     => 'Social Sciences',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 11, 12, 15, 52, 59],
        'strand_kw' => 'Social',
    ],
    'BA Performing Arts' => [
        'field'     => 'Design & Arts',
        'skills_q'  => [22, 26, 27, 28, 29],
        'interest_q'=> [7, 11, 15, 51, 54],
        'strand_kw' => 'Arts',
    ],

    // ════════════════════════════════════════════════════════════════════
    // EDUCATION
    // ════════════════════════════════════════════════════════════════════
    'BS Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Elementary Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor in Elementary Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'BEEd' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Secondary Education Major in English' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Secondary Education Major in Mathematics' => [
        'field'     => 'Education',
        'skills_q'  => [16, 17, 18, 26, 27],
        'interest_q'=> [1, 4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Secondary Education Major in Social Studies' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 7, 12, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Secondary Education in English Filipino, and Math' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'BSED' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'BSEd' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'BSED (English Math, Science, Social Studies, Filipino)' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'BS General Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [4, 7, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Physical Education' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 7, 15, 52, 55, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Library and Information Science' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 26, 27],
        'interest_q'=> [7, 9, 11, 15, 52, 59],
        'strand_kw' => 'Education',
    ],
    'Bachelor of Arts in Ministry' => [
        'field'     => 'Education',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 7, 12, 15, 52, 59],
        'strand_kw' => 'Education',
    ],

    // ════════════════════════════════════════════════════════════════════
    // HOSPITALITY & TOURISM
    // ════════════════════════════════════════════════════════════════════
    'BS Hospitality Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Hospitality & Tourism Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Tourism Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Tourism' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Hotel & Restaurant Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS Hotel and Restaurant Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'BS International Hospitality Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 12, 13, 15, 51, 58],
        'strand_kw' => 'Business',
    ],
    'BS International Tourism and Travel Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 12, 13, 15, 51, 58],
        'strand_kw' => 'Business',
    ],
    'BS Culinary Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 13, 14, 15, 51, 58],
        'strand_kw' => 'Business',
    ],
    'BS Culinary Arts Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 13, 14, 15, 51, 58],
        'strand_kw' => 'Business',
    ],
    'BS Hospitality and Luxury Management' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [4, 12, 13, 15, 51, 58],
        'strand_kw' => 'Business',
    ],

    // ════════════════════════════════════════════════════════════════════
    // AVIATION & MARITIME
    // ════════════════════════════════════════════════════════════════════
    'BS Aircraft Maintenance Technology' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Avionics Technology' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Electronics Technology' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'Aviation Electronics Technology' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Air Transportation' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [10, 12, 13, 51, 57, 58],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Major in Flying' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 24, 25],
        'interest_q'=> [10, 14, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Management' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 19, 23, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Unmanned Aerial Systems' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Communication' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 21, 22, 26],
        'interest_q'=> [3, 7, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Logistics' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [18, 19, 23, 24, 26],
        'interest_q'=> [5, 10, 13, 51, 53, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Safety and Security Management' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 19, 23, 24],
        'interest_q'=> [10, 12, 13, 51, 56, 57],
        'strand_kw' => 'Technology',
    ],
    'BS Aviation Tourism' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 22, 23, 26],
        'interest_q'=> [10, 13, 15, 51, 55, 58],
        'strand_kw' => 'Technology',
    ],
    'BS Marine Transportation' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [10, 13, 14, 51, 57, 59],
        'strand_kw' => 'Technology',
    ],
    'BS Marine Engineering' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],
    'BS Naval Architecture and Marine Engineering' => [
        'field'     => 'Aviation & Maritime',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Engineering',
    ],

    // ════════════════════════════════════════════════════════════════════
    // TECHNICAL-VOCATIONAL / TECHNOLOGY PROGRAMS
    // ════════════════════════════════════════════════════════════════════
    'BTVTE' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BTVTED (Animation Hardware, Graphics, Electronics, Welding)' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BTVTEd in Electrical Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Electrical',
    ],
    'BTVTEd in Electronics Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 6, 10, 51, 57],
        'strand_kw' => 'Electronics',
    ],
    'BTVTEd in ICT' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'Bachelor in Automotive Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57, 59],
        'strand_kw' => 'Technical',
    ],
    'Bachelor of Engineering Technology in Electrical Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Electrical',
    ],
    'Bachelor of Engineering Technology in Electronics Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 6, 10, 51, 57],
        'strand_kw' => 'Electronics',
    ],
    'Bachelor of Graphics Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [22, 25, 26, 27, 29],
        'interest_q'=> [6, 7, 10, 51, 54, 57],
        'strand_kw' => 'Design',
    ],
    'BET in Automotive Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Chemical Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 9, 10, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Civil Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Computer Engineering Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'BET in Dies and Moulds Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Electrical Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Electrical',
    ],
    'BET in Electromechanical Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Electronics Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 6, 10, 51, 57],
        'strand_kw' => 'Electronics',
    ],
    'BET in HVAC/R Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Instrumentation and Control Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Mechanical & Production Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Mechatronics Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 20, 21, 25, 27],
        'interest_q'=> [3, 10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Non-Destructive Testing Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 10, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'BET in Power Plant Engineering Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [1, 3, 10, 51, 57],
        'strand_kw' => 'Electrical',
    ],
    'Diploma in Information Communication Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'Diploma in Mechanical Engineering Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [10, 14, 51, 57],
        'strand_kw' => 'Technical',
    ],
    'Diploma in Office Management Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [17, 18, 22, 24, 26],
        'interest_q'=> [5, 13, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'Associate in Computer Technology' => [
        'field'     => 'Technical-Vocational',
        'skills_q'  => [16, 21, 22, 25, 27],
        'interest_q'=> [3, 6, 9, 51, 57],
        'strand_kw' => 'Technology',
    ],
    'Associate in Hotel and Restaurant Technology' => [
        'field'     => 'Hospitality & Tourism',
        'skills_q'  => [17, 18, 23, 24, 28],
        'interest_q'=> [4, 13, 15, 51, 55, 58],
        'strand_kw' => 'Business',
    ],
    'Associate in Human Resource Development' => [
        'field'     => 'Business & Finance',
        'skills_q'  => [17, 18, 23, 24, 26],
        'interest_q'=> [4, 13, 15, 51, 53, 55],
        'strand_kw' => 'Business',
    ],

    // ════════════════════════════════════════════════════════════════════
    // AGRICULTURE & NATURAL RESOURCES
    // ════════════════════════════════════════════════════════════════════
    'BS Agriculture' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 8, 9, 51, 57],
        'strand_kw' => 'Science',
    ],
    'BA Agriculture' => [
        'field'     => 'Science & Research',
        'skills_q'  => [16, 19, 20, 25, 27],
        'interest_q'=> [2, 8, 9, 51, 57],
        'strand_kw' => 'Science',
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