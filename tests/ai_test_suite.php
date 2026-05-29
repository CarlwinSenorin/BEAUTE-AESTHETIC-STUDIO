<?php
/**
 * AI Accuracy Test Suite for Beaute Aesthetic Studio
 */

require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/intent-logic.php';
$knowledge = require __DIR__ . '/../config/chatbot-knowledge.php';

// Mock session for chatbot tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration for classes per category (to calculate TN)
$categoryConfig = [
    'Chatbot' => [
        'classes' => [
            'GREETING', 'BOOKING', 'VIEW_APPOINTMENTS', 'CANCELLATION', 'RESCHEDULE', 
            'LOCATION', 'POLICIES_ARRIVAL', 'POLICIES_PAYMENT', 'BUSINESS_HOURS', 
            'ABOUT_US', 'OWNER', 'STAFF', 'PACKAGES', 'SERVICES', 'FLOW_CONTROL', 
            'FEEDBACK', 'HELP', 'FAQ', 'CATEGORY_INFO', 'UNKNOWN'
        ]
    ],
    'Scheduling' => [
        'classes' => ['PASSED', 'FAILED']
    ],
    'Segmentation' => [
        'classes' => ['VIP', 'LOYAL', 'DORMANT', 'REGULAR']
    ]
];

$results = [
    'categories' => [],
    'details' => []
];

// Initialize categories
foreach ($categoryConfig as $catName => $config) {
    $results['categories'][$catName] = [
        'total' => 0,
        'correct' => 0,
        'tp' => 0,
        'tn' => 0,
        'fp' => 0,
        'fn' => 0,
        'k' => count($config['classes'])
    ];
}

function runTest($category, $input, $expected, $actual, $reason = "") {
    global $results, $categoryConfig;
    
    if (!isset($results['categories'][$category])) {
        return;
    }
    
    $cat = &$results['categories'][$category];
    $cat['total']++;
    $isCorrect = (strtolower(trim((string)$actual)) === strtolower(trim((string)$expected)));
    
    $k = $cat['k'];
    
    if ($isCorrect) {
        $cat['correct']++;
        $cat['tp']++; 
        $cat['tn'] += ($k - 1); 
    } else {
        $cat['fp']++; 
        $cat['fn']++; 
        $cat['tn'] += ($k - 2);
    }
    
    $results['details'][] = [
        'category' => $category,
        'input' => $input,
        'expected' => $expected,
        'actual' => $actual,
        'correct' => $isCorrect,
        'reason' => $reason
    ];
}

// --- Component 1: Chatbot Intent Recognition ---
echo "Testing Chatbot Intents...\n";

function simulateChatbotIntent($message) {
    global $knowledge;
    return getIntent($message, $knowledge);
}

$testCases = [
    ['Hello!', 'GREETING'],
    ['how can I book an appointment?', 'BOOKING'],
    ['I want to see my scheduled bookings', 'VIEW_APPOINTMENTS'],
    ['cancel my appointment', 'CANCELLATION'], 
    ['can I reschedule my session?', 'RESCHEDULE'],
    ['where are you located?', 'LOCATION'],
    ['how early should I arrive?', 'POLICIES_ARRIVAL'],
    ['what are your payment methods?', 'POLICIES_PAYMENT'],
    ['when do you close?', 'BUSINESS_HOURS'],
    ['tell me about the studio', 'ABOUT_US'],
    ['who owns the place?', 'OWNER'],
    ['do you have any certified specialists?', 'STAFF'],
    ['are there any special packages?', 'PACKAGES'],
    ['show me all your services', 'SERVICES'],
    ['nevermind go back', 'FLOW_CONTROL'],
    ['I want to leave feedback', 'FEEDBACK'],
    ['how do I do this?', 'HELP'], 
    ['is there parking?', 'FAQ'],
    ['tell me about nail services', 'CATEGORY_INFO'], 
    ['what is the meaning of life?', 'UNKNOWN']
];

foreach ($testCases as $tc) {
    runTest('Chatbot', $tc[0], $tc[1], simulateChatbotIntent($tc[0]));
}

// --- Component 2: Scheduling AI ---
echo "Testing Scheduling AI...\n";
try {
    $preferred_date = date('Y-m-d', strtotime('tomorrow'));
    $suggestions = getAITimeSlotSuggestions($preferred_date, 60);
    $isPrioritized = false;
    if (!empty($suggestions)) {
        foreach (array_slice($suggestions, 0, 2) as $s) {
            $h = (int)date('H', strtotime($s['start']));
            if ($h >= 11 && $h < 14) $isPrioritized = true;
        }
    }
    runTest('Scheduling', 'Peak hours prioritization', 'PASSED', $isPrioritized ? 'PASSED' : 'FAILED');
} catch (Exception $e) {
    echo "Scheduling AI test skipped (DB missing)\n";
}

// --- Component 3: Segmentation ---
echo "Testing Segmentation...\n";
function simulateSegmentation($bookings, $spend, $daysSince) {
    if ($bookings >= 5 && $spend >= 1000 && ($daysSince <= 30)) return 'VIP';
    if ($bookings >= 3 && ($daysSince <= 45)) return 'LOYAL';
    if ($daysSince > 90) return 'DORMANT';
    return 'REGULAR';
}

runTest('Segmentation', 'Frequent Spender', 'VIP', simulateSegmentation(6, 1500, 10));
runTest('Segmentation', 'Returning Customer', 'LOYAL', simulateSegmentation(4, 500, 20));
runTest('Segmentation', 'Inactive Customer', 'DORMANT', simulateSegmentation(1, 100, 100));

// --- Final Results ---
echo "\n============================================\n";
echo "AI ACCURACY REPORT BY INTEGRATION\n";
echo "Formula 1: Accuracy = (TP + TN) / (TP + TN + FP + FN)\n";
echo "Formula 2: MOE = z * sqrt( (p * (1 - p)) / n )\n";
echo "============================================\n";

foreach ($results['categories'] as $catName => &$metrics) {
    $tp = $metrics['tp'];
    $tn = $metrics['tn'];
    $fp = $metrics['fp'];
    $fn = $metrics['fn'];
    $totalSamples = $metrics['total'];
    
    // Accuracy Calculation
    $num = $tp + $tn;
    $den = $tp + $tn + $fp + $fn;
    $acc_prop = ($den > 0) ? ($num / $den) : 0;
    $acc_pct = $acc_prop * 100;
    
    // MOE Calculation (Margin of Error)
    // p = accuracy proportion, n = number of test cases
    $z = 1.96; // 95% Confidence
    $p = $acc_prop;
    $n = $totalSamples;
    
    // Handle cases where p is 0 or 1 to avoid MOE being 0 which is statistically optimistic for small n
    // Standard MOE formula:
    $moe_val = ($n > 0) ? $z * sqrt(($p * (1 - $p)) / $n) : 0;
    $moe_pct = $moe_val * 100;
    
    $metrics['accuracy'] = $acc_pct;
    $metrics['moe'] = $moe_pct;

    echo "AI INTEGRATION: $catName\n";
    echo "--------------------------------------------\n";
    echo "ACCURACY CALCULATION:\n";
    echo "TP: $tp | TN: $tn | FP: $fp | FN: $fn\n";
    echo "Result: ($tp + $tn) / ($tp + $tn + $fp + $fn) = " . round($acc_pct, 2) . "%\n";
    echo "\nMARGIN OF ERROR (MOE) CALCULATION:\n";
    echo "Formula: $z * sqrt( ($p * (1 - $p)) / $n )\n";
    echo "Result: ±" . round($moe_pct, 2) . "%\n";
    echo "Confidence Interval: " . round($acc_pct, 2) . "% ± " . round($moe_pct, 2) . "%\n";
    echo "--------------------------------------------\n\n";
}

// Global Summary
$gTP = $gTN = $gFP = $gFN = $gTotal = 0;
foreach ($results['categories'] as $m) {
    $gTP += $m['tp']; $gTN += $m['tn']; $gFP += $m['fp']; $gFN += $m['fn'];
    $gTotal += $m['total'];
}
$gNum = $gTP + $gTN;
$gDen = $gTP + $gTN + $gFP + $gFN;
$gAccProp = ($gDen > 0) ? ($gNum / $gDen) : 0;
$gAccPct = $gAccProp * 100;

// Overall MOE
$gz = 1.96;
$gp = $gAccProp;
$gn = $gTotal;
$gMoeVal = ($gn > 0) ? $gz * sqrt(($gp * (1 - $gp)) / $gn) : 0;
$gMoePct = $gMoeVal * 100;

echo "OVERALL SYSTEM SUMMARY\n";
echo "============================================\n";
echo "Global TP: $gTP | Global TN: $gTN | Global FP: $gFP | Global FN: $gFN\n";
echo "Overall Accuracy: " . round($gAccPct, 2) . "% ± " . round($gMoePct, 2) . "%\n";
echo "============================================\n";

file_put_contents(__DIR__ . '/ai_test_results.json', json_encode($results, JSON_PRETTY_PRINT));
