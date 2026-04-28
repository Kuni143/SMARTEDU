<?php
require_once '../config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get student_id
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success' => true, 'interests' => [], 'skills' => []]);
    exit;
}

$student_id = $student['id'];

// Get all "Strongly Agree" responses
$stmt = $pdo->prepare("
    SELECT question_no FROM responses
    WHERE student_id = ? AND sentiment = 'Strongly Agree'
    ORDER BY question_no ASC
");
$stmt->execute([$student_id]);
$strongly_agreed = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'question_no');

// ── Question-to-label mapping ──────────────────────────────────────────────
// A. Interests (Q1–Q15)
$interest_map = [
    1  => 'Mathematically inclined',
    2  => 'Science enthusiast',
    3  => 'Tech-savvy',
    4  => 'People-oriented',
    5  => 'Business-minded',
    6  => 'Creatively artistic',
    7  => 'Strong writer',
    8  => 'Health & medicine interested',
    9  => 'Analytical thinker',
    10 => 'Hands-on builder',
    11 => 'Public speaker',
    12 => 'Socially aware',
    13 => 'Natural organizer',
    14 => 'Fieldwork enthusiast',
    15 => 'Passionate educator',
];

// B. Skills (Q16–Q30)
$skill_map = [
    16 => 'Logical problem solver',
    17 => 'Effective communicator',
    18 => 'Team player',
    19 => 'Time manager',
    20 => 'Fast learner',
    21 => 'Digitally skilled',
    22 => 'Creative thinker',
    23 => 'Works well under pressure',
    24 => 'Decisive',
    25 => 'Organized',
    26 => 'Natural leader',
    27 => 'Detail-oriented',
    28 => 'Real-world problem solver',
    29 => 'Highly adaptable',
    30 => 'Research-driven',
];

// C. Academic Strengths (Q31–Q40) — shown under skills
$academic_map = [
    31 => 'Math achiever',
    32 => 'Science achiever',
    33 => 'English proficient',
    34 => 'Business subjects strength',
    35 => 'ICT/Technical strength',
    36 => 'Arts & Design strength',
    37 => 'Quick to grasp lessons',
    38 => 'Consistently high grades',
    39 => 'Academically confident',
    40 => 'Self-motivated learner',
];

// D. Strand Alignment (Q41–Q50) — shown under interests
$strand_map = [
    41 => 'Strand-interest aligned',
    42 => 'Skills-based strand choice',
    43 => 'College-ready',
    44 => 'Clear career path awareness',
    45 => 'Open to strand-related courses',
    46 => 'Flexible with course options',
    47 => 'Strand advantage aware',
    48 => 'Career opportunity explorer',
    49 => 'Confident in strand path',
    50 => 'Proactive course researcher',
];

// E. Career Preferences (Q51–Q60) — shown under interests
$career_map = [
    51 => 'Problem-solving career seeker',
    52 => 'People-helping career seeker',
    53 => 'High-income career seeker',
    54 => 'Creative career seeker',
    55 => 'Stability-focused',
    56 => 'Leadership career seeker',
    57 => 'Tech career seeker',
    58 => 'Flexible work seeker',
    59 => 'Lifelong learner',
    60 => 'Hardworking & driven',
];

// Build results
$interests = [];
$skills    = [];

foreach ($strongly_agreed as $qno) {
    if (isset($interest_map[$qno])) {
        $interests[] = $interest_map[$qno];
    } elseif (isset($strand_map[$qno])) {
        $interests[] = $strand_map[$qno];
    } elseif (isset($career_map[$qno])) {
        $interests[] = $career_map[$qno];
    } elseif (isset($skill_map[$qno])) {
        $skills[] = $skill_map[$qno];
    } elseif (isset($academic_map[$qno])) {
        $skills[] = $academic_map[$qno];
    }
}

echo json_encode([
    'success'   => true,
    'interests' => array_values(array_unique($interests)),
    'skills'    => array_values(array_unique($skills)),
]);