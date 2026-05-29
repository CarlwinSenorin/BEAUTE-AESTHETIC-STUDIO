<?php
require_once '../config/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

$conn = getDBConnection();

$date = sanitize($_POST['date'] ?? '');
$assignments_raw = $_POST['assignments'] ?? '[]';
$assignments = json_decode($assignments_raw, true);

if (!$date || !is_array($assignments) || empty($assignments)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

try {
    // Get all active staff with availability & specialization
    $all_staff_stmt = $conn->query(
        "SELECT s.id, s.availability, s.specialization, u.first_name, u.last_name 
         FROM staff s JOIN users u ON s.user_id = u.id 
         WHERE u.status = 'active'"
    );
    $all_active_staff = $all_staff_stmt->fetchAll();

    // Pre-fetch all service durations & categories
    $svc_stmt = $conn->query("SELECT id, duration, category FROM services");
    $svc_data = [];
    while ($row = $svc_stmt->fetch()) {
        $svc_data[(int)$row['id']] = [
            'duration' => (int)$row['duration'],
            'category' => strtolower(trim($row['category'] ?? ''))
        ];
    }

    // Build per-staff busy intervals from existing appointments on this date
    $existing_stmt = $conn->prepare(
        "SELECT id, staff_id, appointment_time, end_time, client_details 
         FROM appointments 
         WHERE appointment_date = ? AND status NOT IN ('cancelled', 'no_show')"
    );
    $existing_stmt->execute([$date]);
    $existing_appts = $existing_stmt->fetchAll();

    $staff_busy_intervals = []; // staffId => [[start_ts, end_ts], ...]

    foreach ($existing_appts as $ea) {
        $ea_details = !empty($ea['client_details']) ? json_decode($ea['client_details'], true) : null;

        if (is_array($ea_details) && !empty($ea_details)) {
            foreach ($ea_details as $ed) {
                $ed_staff = isset($ed['staffId']) && $ed['staffId'] ? (int)$ed['staffId'] : null;
                $ed_time = $ed['time'] ?? null;
                $ed_svc_id = isset($ed['service_id']) ? (int)$ed['service_id'] : null;

                if ($ed_staff && $ed_time) {
                    $ed_dur = ($ed_svc_id && isset($svc_data[$ed_svc_id]))
                              ? $svc_data[$ed_svc_id]['duration'] : 60;
                    $ed_start = strtotime($date . ' ' . $ed_time);
                    $ed_end = $ed_start + ($ed_dur * 60);
                    $staff_busy_intervals[$ed_staff][] = [$ed_start, $ed_end];
                }
            }
        } else {
            if ($ea['staff_id']) {
                $ea_start = strtotime($date . ' ' . $ea['appointment_time']);
                $ea_end = strtotime($date . ' ' . $ea['end_time']);
                $staff_busy_intervals[(int)$ea['staff_id']][] = [$ea_start, $ea_end];
            }
        }
    }

    // Also add already-assigned staff from this booking (specific staff selections)
    // to prevent intra-booking double-booking
    foreach ($assignments as $a) {
        $a_staff = isset($a['staffId']) && $a['staffId'] ? (int)$a['staffId'] : null;
        $a_time = $a['time'] ?? null;
        $a_svc_id = isset($a['service_id']) ? (int)$a['service_id'] : null;
        
        if ($a_staff && $a_time) {
            $a_dur = ($a_svc_id && isset($svc_data[$a_svc_id]))
                     ? $svc_data[$a_svc_id]['duration'] : (int)($a['duration'] ?? 60);
            $a_start = strtotime($date . ' ' . $a_time);
            $a_end = $a_start + ($a_dur * 60);
            $staff_busy_intervals[$a_staff][] = [$a_start, $a_end];
        }
    }

    $day_of_week = strtolower(date('l', strtotime($date)));
    $results = [];
    
    // Process each assignment that needs auto-assign (empty staffId)
    foreach ($assignments as $a) {
        $a_staff = isset($a['staffId']) && $a['staffId'] ? (int)$a['staffId'] : null;
        $a_time = $a['time'] ?? null;
        $a_svc_id = isset($a['service_id']) ? (int)$a['service_id'] : null;
        $a_item_id = $a['itemId'] ?? '';
        
        if ($a_staff) {
            // Already has a specific staff, keep it
            $results[] = [
                'itemId' => $a_item_id,
                'staffId' => (string)$a_staff,
                'staffName' => $a['staffName'] ?? '',
                'assigned' => false
            ];
            continue;
        }
        
        if (!$a_time) {
            // No time selected — can't assign
            $results[] = [
                'itemId' => $a_item_id,
                'staffId' => '',
                'staffName' => '',
                'assigned' => false,
                'error' => 'No time selected'
            ];
            continue;
        }
        
        // "Any Available" — find a free staff member
        $svc_dur = ($a_svc_id && isset($svc_data[$a_svc_id]))
                   ? $svc_data[$a_svc_id]['duration'] : (int)($a['duration'] ?? 60);
        $slot_start = strtotime($date . ' ' . $a_time);
        $slot_end = $slot_start + ($svc_dur * 60);
        
        $svc_category = ($a_svc_id && isset($svc_data[$a_svc_id]))
                        ? $svc_data[$a_svc_id]['category'] : '';
        
        $candidates = [];
        foreach ($all_active_staff as $s) {
            $s_id = (int)$s['id'];
            
            // Check working hours
            $avail = json_decode($s['availability'] ?? '{}', true);
            if ($avail && isset($avail[$day_of_week])) {
                if (empty($avail[$day_of_week]['active'])) continue;
                $sm_start = strtotime($date . ' ' . $avail[$day_of_week]['start']);
                $sm_end = strtotime($date . ' ' . $avail[$day_of_week]['end']);
                if ($slot_start < $sm_start || $slot_end > $sm_end) continue;
            }
            
            // Check busy intervals
            $is_busy = false;
            if (isset($staff_busy_intervals[$s_id])) {
                foreach ($staff_busy_intervals[$s_id] as $busy) {
                    if (!($slot_end <= $busy[0] || $slot_start >= $busy[1])) {
                        $is_busy = true;
                        break;
                    }
                }
            }
            if (!$is_busy) $candidates[] = $s;
        }
        
        if (!empty($candidates)) {
            // Prefer specialty-matched staff
            $specialty_matched = [];
            if ($svc_category) {
                foreach ($candidates as $c) {
                    $spec = strtolower($c['specialization'] ?? '');
                    if ($spec === 'all services' || stripos($spec, $svc_category) !== false) {
                        $specialty_matched[] = $c;
                    }
                }
            }
            $pool = !empty($specialty_matched) ? $specialty_matched : $candidates;
            $picked = $pool[array_rand($pool)];
            
            $results[] = [
                'itemId' => $a_item_id,
                'staffId' => (string)$picked['id'],
                'staffName' => $picked['first_name'] . ' ' . $picked['last_name'],
                'assigned' => true
            ];
            
            // Add to busy intervals to prevent next assignments from double-booking
            $staff_busy_intervals[(int)$picked['id']][] = [$slot_start, $slot_end];
        } else {
            $results[] = [
                'itemId' => $a_item_id,
                'staffId' => '',
                'staffName' => '',
                'assigned' => false,
                'error' => 'No available staff for this time slot'
            ];
        }
    }
    
    echo json_encode(['success' => true, 'assignments' => $results]);
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
