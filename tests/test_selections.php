<?php
require_once 'config/functions.php';

echo "Testing Temporary Selection Logic...\n";

$session1 = "test_session_1";
$session2 = "test_session_2";
$staff_id = 1;
$date = "2026-05-01";
$time = "10:00:00";
$identifier = "item_1";

// 1. Cleanup
cleanupTemporarySelections();
$conn = getDBConnection();
$conn->query("DELETE FROM temporary_selections");

// 2. Add selection for session 1
echo "Session 1 selecting Staff $staff_id at $time...\n";
addTemporarySelection($staff_id, $date, $time, $session1, $identifier);

// 3. Check availability for session 1 (should see it as available since it's their own)
session_id($session1);
$slots1 = getAvailableTimeSlots($date, 60, $staff_id);
$is_available1 = false;
foreach ($slots1 as $s) {
    if ($s['start'] === $time) $is_available1 = true;
}
echo "Session 1 sees slot as " . ($is_available1 ? "AVAILABLE" : "UNAVAILABLE") . " (Expected: AVAILABLE)\n";

// 4. Check availability for session 2 (should see it as unavailable)
session_id($session2);
$slots2 = getAvailableTimeSlots($date, 60, $staff_id);
$is_available2 = false;
foreach ($slots2 as $s) {
    if ($s['start'] === $time) $is_available2 = true;
}
echo "Session 2 sees slot as " . ($is_available2 ? "AVAILABLE" : "UNAVAILABLE") . " (Expected: UNAVAILABLE)\n";

// 5. Release selection
session_id($session1);
removeTemporarySelection(null, null, null, $session1, $identifier);
echo "Session 1 released selection.\n";

// 6. Check availability for session 2 again (should be available now)
session_id($session2);
$slots3 = getAvailableTimeSlots($date, 60, $staff_id);
$is_available3 = false;
foreach ($slots3 as $s) {
    if ($s['start'] === $time) $is_available3 = true;
}
echo "Session 2 sees slot as " . ($is_available3 ? "AVAILABLE" : "UNAVAILABLE") . " (Expected: AVAILABLE)\n";

if ($is_available1 && !$is_available2 && $is_available3) {
    echo "\nTEST PASSED successfully!\n";
} else {
    echo "\nTEST FAILED!\n";
}
