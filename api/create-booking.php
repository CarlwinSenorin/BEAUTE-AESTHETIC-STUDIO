<?php
require_once '../config/functions.php';
header('Content-Type: application/json');
// Custom requireLogin for API
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'] ?? null;
$services = json_decode($_POST['services'] ?? '[]', true);

try {
    if (!$user_id) {
        throw new Exception("User not authenticated.");
    }
    
    $package_id = isset($_POST['selected_package']) && $_POST['selected_package'] ? (int)$_POST['selected_package'] : null;
    $package_ids = json_decode($_POST['package_ids'] ?? '[]', true) ?: ($package_id ? [$package_id] : []);
    $staff_id = isset($_POST['staff_id']) && $_POST['staff_id'] ? (int)$_POST['staff_id'] : null;
    $appointment_date = sanitize($_POST['appointment_date'] ?? '');
    $appointment_time = sanitize($_POST['appointment_time'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? 'pay_on_arrival');
    $allowed_payment = ['pay_on_arrival', 'cash', 'card', 'gcash'];
    if (!in_array($payment_method, $allowed_payment)) $payment_method = 'pay_on_arrival';

    $client_details = $_POST['client_details'] ?? '';
    $pax = (int)($_POST['pax'] ?? 1);

    // Normalize services: frontend might send array of IDs or array of objects with id property
    $service_ids = [];
    if (!empty($services)) {
        foreach ($services as $svc) {
            if (is_array($svc) && isset($svc['id'])) {
                $service_ids[] = (int)$svc['id'];
            } elseif (is_numeric($svc)) {
                $service_ids[] = (int)$svc;
            }
        }
    }

    if (empty($service_ids) && empty($package_ids)) {
        throw new Exception("No services selected.");
    }

    // If service_ids is empty but a package is selected, expand package services server-side
    if (empty($service_ids) && !empty($package_ids)) {
        foreach ($package_ids as $pid) {
            $pkg_stmt = $conn->prepare("SELECT services FROM packages WHERE id = ? AND status = 'active'");
            $pkg_stmt->execute([$pid]);
            $pkg_svcs = json_decode($pkg_stmt->fetchColumn() ?: '[]', true);
            if (is_array($pkg_svcs)) {
                $service_ids = array_merge($service_ids, array_map('intval', $pkg_svcs));
            }
        }
    }

    // ====== PACKAGE DATE OVERLAP PREVENTION ======
    // Prevent the same package from being booked on the same date by the same user
    if (!empty($package_ids) && $appointment_date) {
        foreach ($package_ids as $pid) {
            // Get the package's service list to match against existing bookings
            $pkg_svc_stmt = $conn->prepare("SELECT services FROM packages WHERE id = ?");
            $pkg_svc_stmt->execute([$pid]);
            $pkg_services_raw = $pkg_svc_stmt->fetchColumn();
            
            if ($pkg_services_raw) {
                $pkg_svc_list = json_decode($pkg_services_raw, true);
                $pkg_services_sorted = array_map('intval', $pkg_svc_list);
                sort($pkg_services_sorted);
                
                // Check existing appointments on the same date for this user
                $existing_stmt = $conn->prepare(
                    "SELECT services FROM appointments 
                     WHERE appointment_date = ? 
                     AND user_id = ?
                     AND status NOT IN ('cancelled', 'no_show')"
                );
                $existing_stmt->execute([$appointment_date, $user_id]);
                
                while ($row = $existing_stmt->fetch()) {
                    $existing_svcs = json_decode($row['services'], true);
                    if (is_array($existing_svcs)) {
                        $existing_sorted = array_map('intval', $existing_svcs);
                        sort($existing_sorted);
                        // If the service sets match (or the existing contains all package services), it's an overlap
                        if ($existing_sorted == $pkg_services_sorted || 
                            empty(array_diff($pkg_services_sorted, $existing_sorted))) {
                            throw new Exception("You already have this package booked on " . formatDate($appointment_date) . ". Please choose a different date.");
                        }
                    }
                }
            }
        }
    }

    // Initialize price variables
    $total_price = 0;
    $discount = 0;
    $final_price = 0;

    // Use central calculation function
    $price_data = calculateAppointmentPrice($service_ids, $package_ids);
    $total_price = $price_data['subtotal'];
    $discount = $price_data['discount'];
    $final_price = $price_data['total'];

    // Calculate initial duration from services (considering duplicates/quantities)
    $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT id, duration FROM services WHERE id IN ($placeholders)");
    $stmt->execute($service_ids);
    $durations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $duration = 0;
    foreach ($service_ids as $sid) {
        if (isset($durations[$sid])) {
            $duration += $durations[$sid];
        }
    }
    if ($duration <= 0) $duration = 60;

    // Calculate duration and end_time more intelligently for multi-pax/overlapping services
    $start_timestamp = strtotime($appointment_date . ' ' . $appointment_time);
    $latest_end_timestamp = $start_timestamp + ($duration * 60);

    if ($client_details) {
        $details = json_decode($client_details, true);
        if (is_array($details)) {
            $latest_time = 0;
            foreach ($details as $d) {
                if (isset($d['date']) && isset($d['time'])) {
                    // We need service duration here too. Let's fetch it for all services in one go if not already known.
                    $svc_id = (int)$d['service_id'];
                    $st_dur = $conn->prepare("SELECT duration FROM services WHERE id = ?");
                    $st_dur->execute([$svc_id]);
                    $svc_dur = (int)$st_dur->fetchColumn();
                    
                    $this_end = strtotime($d['date'] . ' ' . $d['time']) + ($svc_dur * 60);
                    if ($this_end > $latest_time) $latest_time = $this_end;
                }
            }
            if ($latest_time > 0) {
                $latest_end_timestamp = $latest_time;
                $duration = ($latest_end_timestamp - $start_timestamp) / 60;
            }
        }
    }

    $end_time = date('H:i:s', $latest_end_timestamp);

    // Create appointment
    $services_json = json_encode($service_ids);

    $conn->beginTransaction();
    
    // ====== SERVER-SIDE DOUBLE-BOOKING PREVENTION ======
    // Before inserting, verify no conflicting appointments exist for each staff+time combination.
    // This prevents race conditions when two clients submit at the same time.
    //
    // IMPORTANT: We must check BOTH the appointment-level staff_id AND the per-staff
    // assignments inside client_details JSON, because multi-pax/multi-service bookings
    // store one staff_id at the appointment level but the full time range spans ALL services.
    // Only client_details has the actual per-staff time windows.
    
    // Pre-fetch all service durations for conflict checking
    $all_svc_durations = [];
    $svc_dur_stmt = $conn->query("SELECT id, duration FROM services");
    while ($row = $svc_dur_stmt->fetch()) {
        $all_svc_durations[(int)$row['id']] = (int)$row['duration'];
    }
    
    // Build a helper function to check if a given staff+time+duration conflicts with existing appointments
    // by parsing client_details from each existing appointment (same approach as getAvailableTimeSlots)
    $existing_appts_stmt = $conn->prepare(
        "SELECT id, staff_id, appointment_time, end_time, client_details FROM appointments 
         WHERE appointment_date = ? AND status NOT IN ('cancelled', 'no_show')"
    );
    $existing_appts_stmt->execute([$appointment_date]);
    $existing_appts = $existing_appts_stmt->fetchAll();
    
    // Build per-staff busy intervals from existing appointments
    $staff_busy_intervals = []; // staffId => [[start_ts, end_ts], ...]
    foreach ($existing_appts as $ea) {
        $ea_details = !empty($ea['client_details']) ? json_decode($ea['client_details'], true) : null;
        
        if (is_array($ea_details) && !empty($ea_details)) {
            // Multi-service/multi-pax: use each staff's actual time from client_details
            foreach ($ea_details as $ed) {
                $ed_staff = isset($ed['staffId']) && $ed['staffId'] ? (int)$ed['staffId'] : null;
                $ed_time = $ed['time'] ?? null;
                $ed_svc_id = isset($ed['service_id']) ? (int)$ed['service_id'] : null;
                
                if ($ed_staff && $ed_time) {
                    $ed_dur = ($ed_svc_id && isset($all_svc_durations[$ed_svc_id])) 
                              ? $all_svc_durations[$ed_svc_id] : 60;
                    $ed_start = strtotime($appointment_date . ' ' . $ed_time);
                    $ed_end = $ed_start + ($ed_dur * 60);
                    $staff_busy_intervals[$ed_staff][] = [$ed_start, $ed_end];
                }
            }
        } else {
            // Simple single-service booking: use appointment-level data
            if ($ea['staff_id']) {
                $ea_start = strtotime($appointment_date . ' ' . $ea['appointment_time']);
                $ea_end = strtotime($appointment_date . ' ' . $ea['end_time']);
                $staff_busy_intervals[(int)$ea['staff_id']][] = [$ea_start, $ea_end];
            }
        }
    }
    
    // ====== RANDOM STAFF ASSIGNMENT FOR "ANY AVAILABLE SPECIALIST" ======
    // Get all active staff with specialization for specialty-based random assignment
    $all_staff_stmt = $conn->query("SELECT s.id, s.availability, s.specialization, u.first_name, u.last_name 
                                     FROM staff s JOIN users u ON s.user_id = u.id 
                                     WHERE u.status = 'active'");
    $all_active_staff = $all_staff_stmt->fetchAll();
    $day_of_week = strtolower(date('l', strtotime($appointment_date)));
    
    // Pre-fetch service categories for specialty matching
    $svc_cat_stmt = $conn->query("SELECT id, category FROM services");
    $svc_categories = $svc_cat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if ($client_details) {
        $details = json_decode($client_details, true);
        if (is_array($details)) {
            // First pass: randomly assign staff to entries with empty staffId
            foreach ($details as &$d) {
                $check_staff = isset($d['staffId']) && $d['staffId'] ? (int)$d['staffId'] : null;
                $check_date = $d['date'] ?? $appointment_date;
                $check_time = $d['time'] ?? $appointment_time;
                $check_svc_id = isset($d['service_id']) ? (int)$d['service_id'] : null;
                
                if (!$check_staff && $check_date && $check_time) {
                    // "Any Available" — find all staff free at this time, prefer specialty match
                    $svc_dur = ($check_svc_id && isset($all_svc_durations[$check_svc_id]))
                               ? $all_svc_durations[$check_svc_id] : 60;
                    $slot_start = strtotime($check_date . ' ' . $check_time);
                    $slot_end = $slot_start + ($svc_dur * 60);
                    
                    // Get the service category for specialty matching
                    $svc_category = ($check_svc_id && isset($svc_categories[$check_svc_id])) 
                                    ? strtolower(trim($svc_categories[$check_svc_id])) : '';
                    
                    $candidates = [];
                    foreach ($all_active_staff as $s) {
                        $s_id = (int)$s['id'];
                        // Check working hours
                        $avail = json_decode($s['availability'] ?? '{}', true);
                        $check_day = strtolower(date('l', strtotime($check_date)));
                        if ($avail && isset($avail[$check_day])) {
                            if (empty($avail[$check_day]['active'])) continue;
                            $sm_start = strtotime($check_date . ' ' . $avail[$check_day]['start']);
                            $sm_end = strtotime($check_date . ' ' . $avail[$check_day]['end']);
                            if ($slot_start < $sm_start || $slot_end > $sm_end) continue;
                        }
                        // Check busy intervals (existing appointments)
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
                        // Prefer staff whose specialization matches the service category
                        $specialty_matched = [];
                        if ($svc_category) {
                            foreach ($candidates as $c) {
                                $spec = strtolower($c['specialization'] ?? '');
                                if ($spec === 'all services' || stripos($spec, $svc_category) !== false) {
                                    $specialty_matched[] = $c;
                                }
                            }
                        }
                        // Pick from specialty-matched first, fallback to all candidates
                        $pool = !empty($specialty_matched) ? $specialty_matched : $candidates;
                        $picked = $pool[array_rand($pool)];
                        $d['staffId'] = (string)$picked['id'];
                        $d['staffName'] = $picked['first_name'] . ' ' . $picked['last_name'];
                        // Add to busy intervals so the next entry won't double-book this staff
                        $staff_busy_intervals[(int)$picked['id']][] = [$slot_start, $slot_end];
                    }
                }
            }
            unset($d); // break reference
            
            // Update client_details and re-derive staff_id from first entry
            $client_details = json_encode($details);
            $first_staff = isset($details[0]['staffId']) && $details[0]['staffId'] ? (int)$details[0]['staffId'] : null;
            if (!$staff_id && $first_staff) {
                $staff_id = $first_staff;
            }
            
            // Second pass: conflict check for all entries (now with assigned staff)
            foreach ($details as $d) {
                $check_staff = isset($d['staffId']) && $d['staffId'] ? (int)$d['staffId'] : null;
                $check_date = $d['date'] ?? $appointment_date;
                $check_time = $d['time'] ?? $appointment_time;
                $check_svc_id = isset($d['service_id']) ? (int)$d['service_id'] : null;
                
                if ($check_staff && $check_date && $check_time) {
                    $svc_dur = ($check_svc_id && isset($all_svc_durations[$check_svc_id]))
                               ? $all_svc_durations[$check_svc_id] : 60;
                    
                    $new_start = strtotime($check_date . ' ' . $check_time);
                    $new_end = $new_start + ($svc_dur * 60);
                    
                    // Check against per-staff busy intervals from EXISTING appointments only
                    // (skip the intervals we just added above for this booking's own assignments)
                    foreach ($existing_appts as $ea) {
                        $ea_details = !empty($ea['client_details']) ? json_decode($ea['client_details'], true) : null;
                        if (is_array($ea_details) && !empty($ea_details)) {
                            foreach ($ea_details as $ed) {
                                $ed_staff = isset($ed['staffId']) && $ed['staffId'] ? (int)$ed['staffId'] : null;
                                if ($ed_staff === $check_staff) {
                                    $ed_time = $ed['time'] ?? null;
                                    $ed_svc_id = isset($ed['service_id']) ? (int)$ed['service_id'] : null;
                                    if ($ed_time) {
                                        $ed_dur = ($ed_svc_id && isset($all_svc_durations[$ed_svc_id])) ? $all_svc_durations[$ed_svc_id] : 60;
                                        $ed_start = strtotime($check_date . ' ' . $ed_time);
                                        $ed_end = $ed_start + ($ed_dur * 60);
                                        if (!($new_end <= $ed_start || $new_start >= $ed_end)) {
                                            $conn->rollBack();
                                            throw new Exception("Time conflict: the selected specialist is already booked at " . formatTime($check_time) . " on " . formatDate($check_date) . ". Please go back and choose a different time.");
                                        }
                                    }
                                }
                            }
                        } else if ($ea['staff_id'] && (int)$ea['staff_id'] === $check_staff) {
                            $ea_start = strtotime($check_date . ' ' . $ea['appointment_time']);
                            $ea_end = strtotime($check_date . ' ' . $ea['end_time']);
                            if (!($new_end <= $ea_start || $new_start >= $ea_end)) {
                                $conn->rollBack();
                                throw new Exception("Time conflict: the selected specialist is already booked at " . formatTime($check_time) . " on " . formatDate($check_date) . ". Please go back and choose a different time.");
                            }
                        }
                    }
                }
            }
        }
    } else if (!$staff_id) {
        // Simple single-service booking with "Any Available" — randomly assign by specialty
        $slot_start = strtotime($appointment_date . ' ' . $appointment_time);
        $slot_end = strtotime($appointment_date . ' ' . $end_time);
        
        // Get category of the first service for specialty matching
        $simple_svc_category = '';
        if (!empty($service_ids) && isset($svc_categories[$service_ids[0]])) {
            $simple_svc_category = strtolower(trim($svc_categories[$service_ids[0]]));
        }
        
        $candidates = [];
        foreach ($all_active_staff as $s) {
            $s_id = (int)$s['id'];
            $avail = json_decode($s['availability'] ?? '{}', true);
            if ($avail && isset($avail[$day_of_week])) {
                if (empty($avail[$day_of_week]['active'])) continue;
                $sm_start = strtotime($appointment_date . ' ' . $avail[$day_of_week]['start']);
                $sm_end = strtotime($appointment_date . ' ' . $avail[$day_of_week]['end']);
                if ($slot_start < $sm_start || $slot_end > $sm_end) continue;
            }
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
            // Prefer staff whose specialization matches the service category
            $specialty_matched = [];
            if ($simple_svc_category) {
                foreach ($candidates as $c) {
                    $spec = strtolower($c['specialization'] ?? '');
                    if ($spec === 'all services' || stripos($spec, $simple_svc_category) !== false) {
                        $specialty_matched[] = $c;
                    }
                }
            }
            $pool = !empty($specialty_matched) ? $specialty_matched : $candidates;
            $picked = $pool[array_rand($pool)];
            $staff_id = (int)$picked['id'];
        }
    } else if ($staff_id) {
        // Single-service booking with a specific staff: check conflicts
        $new_start = strtotime($appointment_date . ' ' . $appointment_time);
        $new_end = strtotime($appointment_date . ' ' . $end_time);
        
        if (isset($staff_busy_intervals[$staff_id])) {
            foreach ($staff_busy_intervals[$staff_id] as $busy) {
                if (!($new_end <= $busy[0] || $new_start >= $busy[1])) {
                    $conn->rollBack();
                    throw new Exception("Time conflict: the selected specialist is already booked at this time. Please go back and choose a different time.");
                }
            }
        }
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO appointments 
                                (user_id, staff_id, appointment_date, appointment_time, end_time, 
                                 services, pax, client_details, total_price, discount_applied, final_price, notes, status, payment_status, payment_method) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reserved', 'pending', ?)");
        $stmt->execute([
            $user_id,
            $staff_id ?: null,
            $appointment_date,
            $appointment_time,
            $end_time,
            $services_json,
            $pax,
            $client_details ?: null,
            $total_price,
            $discount,
            $final_price,
            $notes,
            $payment_method
        ]);
    } catch (PDOException $e) {
        // Fallback if payment columns don't exist yet (though we verified they do)
        if (strpos($e->getMessage(), 'payment_status') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            $stmt = $conn->prepare("INSERT INTO appointments 
                                    (user_id, staff_id, appointment_date, appointment_time, end_time, 
                                     services, total_price, discount_applied, final_price, notes, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reserved')");
            $stmt->execute([
                $user_id,
                $staff_id ?: null,
                $appointment_date,
                $appointment_time,
                $end_time,
                $services_json,
                $total_price,
                $discount,
                $final_price,
                $notes
            ]);
        } else {
            throw $e;
        }
    }
    
    $appointment_id = $conn->lastInsertId();
    $conn->commit();
    
    // Trigger notification for new reservation
    error_log("Triggering auto-notification for new reservation ID: $appointment_id");
    sendAppointmentNotification($appointment_id, 'reservation');
    
    jsonResponse([
        'success' => true,
        'appointment_id' => $appointment_id,
        'message' => 'Appointment booked successfully!'
    ]);
    
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $log_file = dirname(__FILE__) . '/debug.log';
    $log_msg = "[" . date('Y-m-d H:i:s') . "] Booking Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    $log_msg .= "[" . date('Y-m-d H:i:s') . "] Posted Data: " . json_encode($_POST) . "\n";
    file_put_contents($log_file, $log_msg, FILE_APPEND);
    
    jsonResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], 400);
}
?>
