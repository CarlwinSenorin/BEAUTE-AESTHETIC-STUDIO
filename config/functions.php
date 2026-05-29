<?php
/**
 * Core Functions for Beaute Aesthetic Studio
 * 
 * This file contains essential functions for authentication, authorization,
 * utility operations, and business logic.
 * 
 * @package BeauteAestheticStudio
 * @version 1.0.0
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

// Custom SMTP configuration if needed
// (Replacing PHPMailer with minimal socket-based SMTP)

// Load helper functions
if (file_exists(__DIR__ . '/../includes/helpers/ui-helpers.php')) {
    require_once __DIR__ . '/../includes/helpers/ui-helpers.php';
}

// Load recovery bin functions
if (file_exists(__DIR__ . '/recovery-functions.php')) {
    require_once __DIR__ . '/recovery-functions.php';
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isStaff() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'admin');
}

function isStaffLoggedIn() {
    return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
}

function requireStaffOrAdmin() {
    if (!isAdmin() && !isStaffLoggedIn()) {
        // Redirect to login
        $redirect = (strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../admin/login.php' : 'admin/login.php';
        header('Location: ' . $redirect);
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($current_url));
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        // Redirect non-admin users away from admin area
        $redirect_url = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'login.php' : 'admin/login.php';
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

// Get available time slots
function getAvailableTimeSlots($date, $duration, $staff_id = null, $pax = 1, $exclude_identifier = null, $include_busy = false, $service_category = null) {
    $conn = getDBConnection();
    $slots = [];
    
    // Get all active staff with their availability and specialization
    $stmt = $conn->query("SELECT staff.id, staff.availability, staff.specialization FROM staff JOIN users u ON staff.user_id = u.id WHERE u.status = 'active'");
    $staff_members = $stmt->fetchAll();
    
    // When "Any Available" and a service_category is specified, only count staff
    // whose specialization matches the category (or is 'All Services')
    if (!$staff_id && $service_category) {
        $svc_cat_lower = strtolower(trim($service_category));
        $qualified_staff = array_filter($staff_members, function($sm) use ($svc_cat_lower) {
            $spec = strtolower($sm['specialization'] ?? '');
            return ($spec === 'all services' || stripos($spec, $svc_cat_lower) !== false);
        });
        // Use only qualified staff count for availability, but keep full list for offline checks
        $total_active_staff = count($qualified_staff);
        // Build a set of non-qualified staff IDs to mark as offline
        $qualified_ids = array_column($qualified_staff, 'id');
        $non_qualified_ids = array_diff(array_column($staff_members, 'id'), $qualified_ids);
    } else {
        $total_active_staff = count($staff_members);
        $non_qualified_ids = [];
    }
    
    if ($total_active_staff < $pax) return []; // Cannot accommodate more pax than total staff
    
    // Get business hours as default
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['business_hours_start']);
    $business_start = $stmt->fetchColumn() ?: '09:00';
    
    $stmt->execute(['business_hours_end']);
    $business_end = $stmt->fetchColumn() ?: '18:00';
    
    // Get current day of week for availability check
    $day_of_week = strtolower(date('l', strtotime($date)));
    
    // If a specific staff is selected, use THEIR availability hours instead of global business hours
    if ($staff_id) {
        foreach ($staff_members as $sm) {
            if ((int)$sm['id'] === (int)$staff_id) {
                $avail = json_decode($sm['availability'], true);
                if ($avail && isset($avail[$day_of_week])) {
                    $day_avail = $avail[$day_of_week];
                    if (empty($day_avail['active'])) {
                        // Staff is not working this day — no slots at all
                        return $include_busy ? [] : [];
                    }
                    // Use staff's own working hours
                    $business_start = $day_avail['start'];
                    $business_end = $day_avail['end'];
                }
                break;
            }
        }
    }
    
    // Use the service duration as slot interval, capped at 30 minutes max
    // Short services (15min, 20min) get fine-grained slots matching their duration
    // Longer services (45min, 60min, 90min+) cap at 30-min intervals to keep the grid manageable
    $interval = min($duration, 30);
    
    // Get ALL existing appointments for the date (regardless of staff) for per-staff blocking
    // Include client_details so we can extract per-staff time ranges for multi-service bookings
    $sql = "SELECT appointment_time, end_time, staff_id, client_details FROM appointments 
            WHERE appointment_date = ? AND status NOT IN ('cancelled', 'no_show')";
    $params = [$date];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $booked_slots = $stmt->fetchAll();
    
    // Pre-fetch all service durations for efficient lookup (avoids N+1 queries in the loop)
    $svc_dur_stmt = $conn->query("SELECT id, duration FROM services");
    $svc_durations = $svc_dur_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Generate time slots based on staff or business hours
    $current = strtotime($date . ' ' . $business_start);
    $end = strtotime($date . ' ' . $business_end);
    
    // If end_time is 00:00 (Midnight), it should be interpreted as the start of the next day
    if ($business_end === '00:00' || $business_end === '24:00' || $end <= $current) {
        $end = strtotime($date . ' ' . $business_end . ' +1 day');
    }
    
    $duration_seconds = $duration * 60;
    
    // Get ALL temporary selections for this date (for overlap checking)
    $session_id = session_id();
    $sql_all_temp = "SELECT staff_id, appointment_time, duration FROM temporary_selections 
                     WHERE appointment_date = ? 
                     AND NOT (session_id = ? AND identifier = ?)
                     AND expires_at >= NOW()";
    $stmt_all_temp = $conn->prepare($sql_all_temp);
    $stmt_all_temp->execute([$date, $session_id, $exclude_identifier ?: 'NONE']);
    $all_temp_selections = $stmt_all_temp->fetchAll();
    
    while ($current + $duration_seconds <= $end) {
        $slot_start_time = date('H:i:s', $current);
        $slot_end_time = date('H:i:s', $current + $duration_seconds);
        $slot_start_ts = $current;
        $slot_end_ts = $current + $duration_seconds;
        
        $busy_staff = [];
        $offline_staff = [];
        
        // Mark non-qualified staff as offline (specialization mismatch)
        foreach ($non_qualified_ids as $nq_id) {
            $offline_staff[$nq_id] = true;
        }

        // 1. Check individual staff availability from their "availability" JSON
        foreach ($staff_members as $sm) {
            $avail = json_decode($sm['availability'], true);
            $sm_id = $sm['id'];
            
            if ($avail && isset($avail[$day_of_week])) {
                $day_avail = $avail[$day_of_week];
                if (empty($day_avail['active'])) {
                    $offline_staff[$sm_id] = true;
                    continue;
                }
                
                $sm_start = strtotime($date . ' ' . $day_avail['start']);
                $sm_end = strtotime($date . ' ' . $day_avail['end']);
                
                // If the service's full duration doesn't fit within staff's working hours
                if ($slot_start_ts < $sm_start || $slot_end_ts > $sm_end) {
                    $offline_staff[$sm_id] = true;
                }
            }
        }

        // 2. Check existing appointments — per-staff overlap using time ranges
        // For multi-service bookings, use client_details to get EACH staff's actual time range
        // instead of the appointment-level end_time which spans ALL services
        foreach ($booked_slots as $booked) {
            $details = !empty($booked['client_details']) ? json_decode($booked['client_details'], true) : null;
            
            if (is_array($details) && !empty($details)) {
                // Multi-service/multi-pax booking: check each individual staff assignment
                foreach ($details as $d) {
                    $d_staff = isset($d['staffId']) && $d['staffId'] ? $d['staffId'] : null;
                    $d_time = $d['time'] ?? null;
                    $d_svc_id = isset($d['service_id']) ? (int)$d['service_id'] : null;
                    
                    if ($d_staff && $d_time) {
                        // Get this specific service's duration from pre-fetched cache
                        $d_duration = 60; // default fallback
                        if ($d_svc_id && isset($svc_durations[$d_svc_id])) {
                            $d_duration = (int)$svc_durations[$d_svc_id];
                        }
                        $d_start = strtotime($date . ' ' . $d_time);
                        $d_end = $d_start + ($d_duration * 60);
                        
                        // Two ranges overlap if: NOT (slot_end <= d_start OR slot_start >= d_end)
                        if (!($slot_end_ts <= $d_start || $slot_start_ts >= $d_end)) {
                            $busy_staff[$d_staff] = true;
                        }
                    }
                }
            } else {
                // Simple single-service booking: use appointment-level staff_id and time range
                $booked_start = strtotime($date . ' ' . $booked['appointment_time']);
                $booked_end = strtotime($date . ' ' . $booked['end_time']);
                if (!($slot_end_ts <= $booked_start || $slot_start_ts >= $booked_end)) {
                    if ($booked['staff_id']) {
                        $busy_staff[$booked['staff_id']] = true;
                    }
                }
            }
        }

        // 3. Check temporary selections — duration-aware overlap
        foreach ($all_temp_selections as $t) {
            $temp_start = strtotime($date . ' ' . $t['appointment_time']);
            $temp_dur = (int)($t['duration'] ?: 60);
            $temp_end = $temp_start + ($temp_dur * 60);
            // Two ranges overlap if: NOT (slot_end <= temp_start OR slot_start >= temp_end)
            if (!($slot_end_ts <= $temp_start || $slot_start_ts >= $temp_end)) {
                if ($t['staff_id']) {
                    $busy_staff[$t['staff_id']] = true;
                }
                // Note: temp selections without staff_id don't globally block anymore — 
                // they only block when we're checking "Any Available" staff count
            }
        }
        
        $all_unavailable = array_merge($busy_staff, $offline_staff);
        // Only count staff that are part of the qualified pool as unavailable
        // When service_category is set, $total_active_staff only counts qualified staff,
        // so we must only subtract unavailable staff from that same qualified pool
        if (!$staff_id && $service_category && !empty($qualified_ids)) {
            $unavailable_qualified = array_intersect_key($all_unavailable, array_flip($qualified_ids));
            $available_staff_count = $total_active_staff - count($unavailable_qualified);
        } else {
            $available_staff_count = $total_active_staff - count($all_unavailable);
        }
        
        // Per-staff check: if a specific staff is requested, check if THEY are free
        $is_specific_staff_available = true;
        if ($staff_id) {
            if (isset($busy_staff[$staff_id]) || isset($offline_staff[$staff_id])) {
                $is_specific_staff_available = false;
            }
        }

        // Slot is available if: enough total specialists are free AND (if specified) the chosen specialist is free
        $is_available = ($available_staff_count >= $pax && $is_specific_staff_available);

        if ($is_available || $include_busy) {
            $slots[] = [
                'start' => $slot_start_time,
                'end' => $slot_end_time,
                'display' => date('g:i A', $current),
                'available_staff' => $available_staff_count,
                'is_available' => $is_available
            ];
        }
        
        $current += ($interval * 60);
    }
    
    return $slots;
}

/**
 * Manage Temporary Selections for Double-Booking Prevention
 */

function addTemporarySelection($staff_id, $date, $time, $session_id, $identifier, $duration = 60) {
    if (!$date || !$time || !$session_id || !$identifier) return false;
    $conn = getDBConnection();
    cleanupTemporarySelections(); 

    // Remove any existing selection for this specific identifier in this session
    removeTemporarySelection(null, null, null, $session_id, $identifier);

    // Set expiration for 15 minutes
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $conn->prepare("INSERT INTO temporary_selections (staff_id, appointment_date, appointment_time, session_id, identifier, duration, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$staff_id ?: null, $date, $time, $session_id, $identifier, (int)$duration, $expires_at]);
}

function removeTemporarySelection($staff_id, $date, $time, $session_id, $identifier = null) {
    if (!$session_id) return false;
    $conn = getDBConnection();

    $sql = "DELETE FROM temporary_selections WHERE session_id = ?";
    $params = [$session_id];

    if ($identifier !== null) {
        $sql .= " AND identifier = ?";
        $params[] = $identifier;
    }
    if ($staff_id !== null) {
        $sql .= " AND staff_id = ?";
        $params[] = $staff_id;
    }
    if ($date !== null) {
        $sql .= " AND appointment_date = ?";
        $params[] = $date;
    }
    if ($time !== null) {
        $sql .= " AND appointment_time = ?";
        $params[] = $time;
    }

    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}

function cleanupTemporarySelections() {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM temporary_selections WHERE expires_at < NOW()");
    $stmt->execute();
}


/**
 * Check if a date is fully booked across all staff
 */
function isDateFullyBooked($date) {
    $conn = getDBConnection();
    
    // 1. Get total active staff count
    $stmt = $conn->query("SELECT COUNT(*) FROM staff");
    $staff_count = (int)$stmt->fetchColumn();
    
    if ($staff_count === 0) return true; // No staff means fully booked

    // 2. Get business hours and interval
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_hours_start', 'business_hours_end', 'appointment_interval')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $start_time = $settings['business_hours_start'] ?? '09:00';
    $end_time = $settings['business_hours_end'] ?? '18:00';
    $interval = (int)($settings['appointment_interval'] ?? 15);
    
    // 3. Generate all possible starting times
    $current = strtotime($date . ' ' . $start_time);
    $end = strtotime($date . ' ' . $end_time);
    if ($end <= $current) $end = strtotime($date . ' ' . $end_time . ' +1 day');

    $all_times = [];
    while ($current < $end) {
        $all_times[] = date('H:i:s', $current);
        $current += ($interval * 60);
    }

    if (empty($all_times)) return true;

    // 4. Count active appointments per time slot
    $placeholders = str_repeat('?,', count($all_times) - 1) . '?';
    $sql = "SELECT appointment_time, COUNT(*) as count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND status NOT IN ('cancelled', 'no_show')
            AND appointment_time IN ($placeholders)
            GROUP BY appointment_time";
    
    $stmt = $conn->prepare($sql);
    $params = array_merge([$date], $all_times);
    $stmt->execute($params);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 5. If ANY slot has count < staff_count, it's not fully booked
    foreach ($all_times as $time) {
        $booked_count = (int)($counts[$time] ?? 0);
        if ($booked_count < $staff_count) {
            return false; // Found an available slot
        }
    }

    return true; // All slots are at capacity
}

// AI-powered time slot suggestions (simple algorithm)
function getAITimeSlotSuggestions($preferred_date, $duration, $staff_id = null) {
    $slots = getAvailableTimeSlots($preferred_date, $duration, $staff_id);
    
    if (empty($slots)) {
        // Suggest next available dates
        $suggestions = [];
        for ($i = 1; $i <= 7; $i++) {
            $next_date = date('Y-m-d', strtotime($preferred_date . " +$i days"));
            $next_slots = getAvailableTimeSlots($next_date, $duration, $staff_id);
            if (!empty($next_slots)) {
                $suggestions[] = [
                    'date' => $next_date,
                    'slots' => array_slice($next_slots, 0, 3) // Top 3 slots
                ];
            }
            if (count($suggestions) >= 3) break;
        }
        return $suggestions;
    }
    
    // Return best slots (prefer prime/peak hours)
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['peak_hour_start']);
    $peak_start = $stmt->fetchColumn() ?: '11:00';
    $stmt->execute(['peak_hour_end']);
    $peak_end = $stmt->fetchColumn() ?: '14:00';
    
    $peak_start_val = strtotime($peak_start);
    $peak_end_val = strtotime($peak_end);
    
    $best_slots = [];
    foreach ($slots as $slot) {
        $slot_time = strtotime($slot['start']);
        $hour = (int)date('H', $slot_time);
        $score = 0;
        
        // 1. Prime/Peak Hour Scoring (Highest weight)
        if ($slot_time >= $peak_start_val && $slot_time < $peak_end_val) {
            $score = 4;
        } 
        // 2. Standard Business Hour Scoring
        elseif ($hour >= 9 && $hour < 17) {
            $score = 2;
        } 
        // 3. Early/Late slots
        else {
            $score = 1;
        }

        // 4. Weekend Bonus (Heuristic - users often prefer weekends)
        $dayOfWeek = date('N', strtotime($preferred_date)); // 1 (Mon) to 7 (Sun)
        if ($dayOfWeek >= 6) {
            $score += 0.5;
        }

        $slot['ai_score'] = $score;
        $best_slots[] = $slot;
    }
    
    usort($best_slots, function($a, $b) {
        if ($b['ai_score'] == $a['ai_score']) {
            return strtotime($a['start']) <=> strtotime($b['start']); // Earlier slots first if scores tie
        }
        return $b['ai_score'] <=> $a['ai_score']; // Descending score
    });
    
    // Return top 5 slots
    return array_map(function($s) { 
        unset($s['ai_score']); 
        return $s; 
    }, array_slice($best_slots, 0, 5));
}

// Calculate appointment price (with peak-hour surcharge support)
function calculateAppointmentPrice($service_ids, $package_ids = null, $promotion_code = null, $appointment_time = null) {
    $conn = getDBConnection();
    $subtotal = 0;
    
    // Handle list of packages
    if ($package_ids) {
        if (!is_array($package_ids)) $package_ids = [$package_ids];
        $package_ids = array_filter(array_map('intval', $package_ids));
        
        if (!empty($package_ids)) {
            $placeholders = str_repeat('?,', count($package_ids) - 1) . '?';
            $stmt = $conn->prepare("SELECT discounted_price FROM packages WHERE id IN ($placeholders) AND status = 'active'");
            $stmt->execute($package_ids);
            $package_prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($package_prices as $price) {
                $subtotal += (float)$price;
            }
        }
    }
    
    // Calculate from services (those not part of a package, or all if no packages)
    if (!empty($service_ids)) {
        $service_ids = array_filter(array_map('intval', $service_ids));
        if (!empty($service_ids)) {
            $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
            $stmt = $conn->prepare("SELECT id, base_price FROM services WHERE id IN ($placeholders)");
            $stmt->execute($service_ids);
            $prices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($service_ids as $sid) {
                if (isset($prices[$sid])) {
                    $subtotal += (float)$prices[$sid];
                }
            }
        }
    }
    
    $discount = 0;
    $surcharge = 0;
    
    // Apply promotion if provided
    if ($promotion_code) {
        $stmt = $conn->prepare("SELECT * FROM promotions WHERE name = ? AND status = 'active' 
                                AND CURDATE() BETWEEN valid_from AND valid_until 
                                AND (usage_limit IS NULL OR used_count < usage_limit)");
        $stmt->execute([$promotion_code]);
        $promo = $stmt->fetch();
        
        if ($promo) {
            if ($promo['discount_type'] === 'percentage') {
                $discount = $subtotal * ($promo['discount_value'] / 100);
            } else {
                $discount = $promo['discount_value'];
            }
            
            if ($promo['min_purchase'] && $subtotal < $promo['min_purchase']) {
                $discount = 0;
            }
        }
    }
    
    // Apply peak-hour surcharge
    if ($appointment_time) {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['peak_hour_start']);
        $peak_start = $stmt->fetchColumn() ?: '11:00';
        
        $stmt->execute(['peak_hour_end']);
        $peak_end = $stmt->fetchColumn() ?: '14:00';
        
        $stmt->execute(['peak_hour_surcharge']);
        $surcharge_pct = (float)($stmt->fetchColumn() ?: 0);
        
        $time_val = strtotime($appointment_time);
        $peak_start_val = strtotime($peak_start);
        $peak_end_val = strtotime($peak_end);
        
        if ($surcharge_pct > 0 && $time_val >= $peak_start_val && $time_val < $peak_end_val) {
            $surcharge = ($subtotal - $discount) * ($surcharge_pct / 100);
        }
    }
    
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'surcharge' => round($surcharge, 2),
        'total' => max(0, round($subtotal - $discount + $surcharge, 2))
    ];
}

/**
 * Find available staff members for a given slot, ensuring enough are available for the requested pax
 */
function getAvailableStaffForPax($date, $time, $duration, $pax = 1) {
    $available = getAvailableStaffForSlot($date, $time, $duration);
    if (count($available) >= $pax) {
        return array_slice($available, 0, $pax);
    }
    return [];
}

/**
 * Get all available staff members for a specific slot
 */
function getAvailableStaffForSlot($date, $time, $duration) {
    $conn = getDBConnection();

    $slot_start = strtotime($date . ' ' . $time);
    $slot_end = $slot_start + ($duration * 60);
    $endTime = date('H:i:s', $slot_end);
    $day_of_week = strtolower(date('l', strtotime($date)));

    // Get all active staff with their availability JSON
    $stmt = $conn->query("
        SELECT s.id, s.availability, u.first_name, u.last_name 
        FROM staff s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.status = 'active'
    ");
    $staffMembers = $stmt->fetchAll();

    $availableStaff = [];
    
    // Pre-fetch appointments and service durations ONCE (outside the per-staff loop)
    $appt_stmt = $conn->prepare(
        "SELECT staff_id, appointment_time, end_time, client_details FROM appointments 
         WHERE appointment_date = ? AND status NOT IN ('cancelled', 'no_show')"
    );
    $appt_stmt->execute([$date]);
    $all_appts = $appt_stmt->fetchAll();
    
    $svc_dur_map = [];
    $svc_stmt = $conn->query("SELECT id, duration FROM services");
    while ($sr = $svc_stmt->fetch()) {
        $svc_dur_map[(int)$sr['id']] = (int)$sr['duration'];
    }
    
    // Build per-staff busy intervals from all existing appointments
    $staff_busy_intervals = []; // staffId => [[start_ts, end_ts], ...]
    foreach ($all_appts as $appt) {
        $appt_details = !empty($appt['client_details']) ? json_decode($appt['client_details'], true) : null;
        
        if (is_array($appt_details) && !empty($appt_details)) {
            foreach ($appt_details as $ad) {
                $ad_staff = isset($ad['staffId']) && $ad['staffId'] ? (int)$ad['staffId'] : null;
                $ad_time = $ad['time'] ?? null;
                $ad_svc_id = isset($ad['service_id']) ? (int)$ad['service_id'] : null;
                
                if ($ad_staff && $ad_time) {
                    $ad_dur = ($ad_svc_id && isset($svc_dur_map[$ad_svc_id])) 
                              ? $svc_dur_map[$ad_svc_id] : 60;
                    $ad_start = strtotime($date . ' ' . $ad_time);
                    $ad_end = $ad_start + ($ad_dur * 60);
                    $staff_busy_intervals[$ad_staff][] = [$ad_start, $ad_end];
                }
            }
        } else {
            if ($appt['staff_id']) {
                $appt_start = strtotime($date . ' ' . $appt['appointment_time']);
                $appt_end = strtotime($date . ' ' . $appt['end_time']);
                $staff_busy_intervals[(int)$appt['staff_id']][] = [$appt_start, $appt_end];
            }
        }
    }
    
    foreach ($staffMembers as $staff) {
        // 1. Check staff's own weekly availability
        $avail = json_decode($staff['availability'] ?? '{}', true);
        if ($avail && isset($avail[$day_of_week])) {
            $day_avail = $avail[$day_of_week];
            if (empty($day_avail['active'])) {
                continue; // Staff is not working this day
            }
            $sm_start = strtotime($date . ' ' . $day_avail['start']);
            $sm_end   = strtotime($date . ' ' . $day_avail['end']);
            if ($slot_start < $sm_start || $slot_end > $sm_end) {
                continue; // Slot is outside staff's working hours
            }
        }

        // 2. Check for conflicting bookings using pre-built per-staff busy intervals
        $is_busy = false;
        $staff_id_check = (int)$staff['id'];
        
        if (isset($staff_busy_intervals[$staff_id_check])) {
            foreach ($staff_busy_intervals[$staff_id_check] as $busy) {
                if (!($slot_end <= $busy[0] || $slot_start >= $busy[1])) {
                    $is_busy = true;
                    break;
                }
            }
        }

        if (!$is_busy) {
            $availableStaff[] = [
                'id' => $staff['id'],
                'first_name' => $staff['first_name'],
                'last_name' => $staff['last_name']
            ];
        }
    }
    return $availableStaff;
}


/**
 * Log a notification event
 */
function logNotification($appointment_id, $type, $recipient, $message, $status = 'success') {
    $logFile = __DIR__ . '/../notifications.log';
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'appointment_id' => $appointment_id,
        'type' => $type,
        'recipient' => $recipient,
        'message' => $message,
        'status' => $status
    ];
    
    $existingLogs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $existingLogs = json_decode($content, true) ?: [];
    }
    
    array_unshift($existingLogs, $logEntry); // Newest first
    // Keep only last 100 logs
    $existingLogs = array_slice($existingLogs, 0, 100);
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
}

/**
 * Send appointment notification (Email/SMS)
 */
function sendAppointmentNotification($appointment_id, $trigger = 'confirmation') {
    error_log("sendAppointmentNotification triggered for ID: $appointment_id | Trigger: $trigger");
    $conn = getDBConnection();
    
    // Fetch appointment details with client and staff info
    $stmt = $conn->prepare("
        SELECT a.*, 
               u.email, u.phone, u.first_name as client_name,
               su.first_name as staff_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN staff s ON a.staff_id = s.id
        LEFT JOIN users su ON s.user_id = su.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) return false;

    // Get service names
    $service_ids = json_decode($appointment['services'], true);
    $service_names = [];
    if (is_array($service_ids) && !empty($service_ids)) {
        $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
        $stmt->execute($service_ids);
        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $services_list = !empty($service_names) ? implode(', ', $service_names) : 'Scheduled Services';
    $staff_display = $appointment['staff_name'] ?: 'Any Available Specialist';
    $date_display = formatDate($appointment['appointment_date']);
    $time_display = formatTime($appointment['appointment_time']);
    $price_display = formatPrice($appointment['final_price']);

    // Check settings
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['email_enabled']);
    $email_enabled = ($stmt->fetchColumn() === 'true');
    
    $stmt->execute(['sms_enabled']);
    $sms_enabled = ($stmt->fetchColumn() === 'true');

    $subject = "Appointment Confirmation - " . APP_NAME;
    $message = "Hi {$appointment['client_name']},\n\n";
    
    if ($trigger === 'confirmation') {
        $message .= "Great news! Your appointment has been confirmed.\n\n";
    } elseif ($trigger === 'reservation') {
        $message .= "Your appointment reservation has been received and is now pending admin confirmation.\n\n";
    } else {
        $message .= "Here are the details of your appointment:\n\n";
    }

    $message .= "Booking Details:\n";
    $message .= "- Services: {$services_list}\n";
    $message .= "- Specialist: {$staff_display}\n";
    $message .= "- Date: {$date_display}\n";
    $message .= "- Time: {$time_display}\n";
    $message .= "- Total Price: {$price_display}\n\n";
    $message .= "Thank you for choosing " . APP_NAME . "!";

    $status = 'success';
    $sent_count = 0;

    if ($email_enabled) {
        $email_result = sendSMTPPHPMail($appointment['email'], $subject, $message);
        if ($email_result) {
            logNotification($appointment_id, 'email', $appointment['email'], $message, 'sent');
            $sent_count++;
        } else {
            logNotification($appointment_id, 'email', $appointment['email'], $message, 'failed');
        }
    }

    if ($sms_enabled) {
        if ($trigger === 'reservation') {
            $sms_message = "Hi {$appointment['client_name']}, your reservation for {$services_list} on {$date_display} at {$time_display} is received by " . APP_NAME . ". We will notify you once confirmed.";
        } else {
            $sms_message = "Hi {$appointment['client_name']}, your appointment for {$services_list} on {$date_display} at {$time_display} is confirmed by " . APP_NAME . ". Price: {$price_display}.";
        }
        $sms_result = sendHttpSMS($appointment['phone'], $sms_message);
        
        // Handle simulation/real result
        if ($sms_result === 'config_missing') {
            logNotification($appointment_id, 'sms', $appointment['phone'], $sms_message, 'config_missing');
        } else {
            logNotification($appointment_id, 'sms', $appointment['phone'], $sms_message, $sms_result ? 'sent' : 'failed');
            if ($sms_result) $sent_count++;
        }
    }

    return $sent_count > 0;
}

/**
 * Send SMS via httpSMS API
 */
function sendHttpSMS($phone, $message) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    
    $stmt->execute(['sms_api_key']);
    $api_key = $stmt->fetchColumn();
    
    $stmt->execute(['sms_from_number']);
    $from_number = $stmt->fetchColumn();
    
    // Trim the API key
    $api_key = trim($api_key);
    
    if (empty($api_key) || empty($from_number) || strlen(trim($from_number)) < 5) {
        error_log("httpSMS Error: API Key or From Number missing or invalid. From: " . ($from_number ?: 'EMPTY'));
        return 'config_missing';
    }
    
    // Clean recipient phone number format for E.164
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
        $phone = '+63' . $phone;
    } elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
        $phone = '+63' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) === '63' && strlen($phone) === 12) {
        $phone = '+' . $phone;
    } elseif (strlen($phone) > 5 && substr($phone, 0, 1) !== '+') {
        $phone = '+' . $phone;
    }
    
    // Clean from_number format — ensure E.164 with leading +
    $from_number = preg_replace('/[^0-9+]/', '', trim($from_number));
    if (substr($from_number, 0, 1) !== '+') {
        $from_number = '+' . $from_number;
    }

    $url = 'https://api.httpsms.com/v1/messages/send';
    
    $data = [
        'from' => $from_number,
        'to' => $phone,
        'content' => $message,
        'encrypted' => false,
        'sim' => 'DEFAULT'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $api_key,
        'Content-Type: application/json',
        'Accept: */*'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Debug logging
    $request_json = json_encode($data);
    error_log("httpSMS Request to $url: " . $request_json);
    error_log("httpSMS Sent to: $phone (From: $from_number) | HTTP: $http_code | Response: $response");
    
    if ($http_code === 200 || $http_code === 201) {
        $resData = json_decode($response, true);
        // httpSMS returns status "pending" when message is queued for delivery
        return isset($resData['status']) && in_array($resData['status'], ['success', 'pending']);
    }
    
    return false;
}


// Send email reminder
function sendEmailReminder($appointment_id) {
    return sendAppointmentNotification($appointment_id, 'reminder');
}

// Send SMS reminder
function sendSMSReminder($appointment_id) {
    return sendAppointmentNotification($appointment_id, 'reminder');
}

/**
 * Send follow-up notification (thank-you + review prompt)
 */
function sendFollowUpNotification($appointment_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT a.*, u.email, u.phone, u.first_name as client_name
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.status = 'completed' AND a.follow_up_sent = 0
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) return false;
    
    $message = "Hi {$appointment['client_name']}, thank you for visiting " . APP_NAME . "! ";
    $message .= "We hope you loved your experience. We would appreciate it if you could leave us a review. ";
    $message .= "Book your next appointment at " . BASE_URL . "booking.php — See you soon!";
    
    $sent = false;
    
    // Send email follow-up
    $stmt_setting = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt_setting->execute(['email_enabled']);
    if ($stmt_setting->fetchColumn() === 'true') {
        $email_result = sendSMTPPHPMail($appointment['email'], 'Thank You! - ' . APP_NAME, $message);
        if ($email_result) {
            logNotification($appointment_id, 'email', $appointment['email'], $message, 'sent');
            $sent = true;
        } else {
            logNotification($appointment_id, 'email', $appointment['email'], $message, 'failed');
        }
    }
    
    // Send SMS follow-up
    $stmt_setting->execute(['sms_enabled']);
    if ($stmt_setting->fetchColumn() === 'true') {
        $sms_msg = "Hi {$appointment['client_name']}, thank you for visiting " . APP_NAME . "! Leave us a review and book again soon!";
        sendHttpSMS($appointment['phone'], $sms_msg);
        logNotification($appointment_id, 'sms', $appointment['phone'], $sms_msg, 'sent');
        $sent = true;
    }
    
    if ($sent) {
        $stmt = $conn->prepare("UPDATE appointments SET follow_up_sent = 1 WHERE id = ?");
        $stmt->execute([$appointment_id]);
    }
    
    return $sent;
}

// JSON Response Helper
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
// AI Smart Recommendations
function getAIRecommendations($user_id) {
    $conn = getDBConnection();
    
    // 1. Analyze user's past bookings to find favorite category
    $stmt = $conn->prepare("
        SELECT s.category, COUNT(*) as count 
        FROM appointments a 
        JOIN services s ON JSON_CONTAINS(a.services, CAST(s.id AS CHAR), '$')
        WHERE a.user_id = ? AND a.status = 'completed'
        GROUP BY s.category 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $favorite = $stmt->fetch();
    
    $recommendations = [];
    $reason = "";
    
    if ($favorite) {
        // User has history: Recommend top rated services in their favorite category 
        // that they haven't booked recently (simple logic)
        $category = $favorite['category'];
        $reason = "Based on your love for " . ucfirst($category);
        
        $stmt = $conn->prepare("SELECT * FROM services WHERE category = ? AND status = 'active' ORDER BY base_price DESC LIMIT 3");
        $stmt->execute([$category]);
        $recommendations = $stmt->fetchAll();
    } else {
        // No history: Recommend "Trending" (random popular ones)
        $reason = "Trending Treatments";
        $stmt = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY RAND() LIMIT 3");
        $recommendations = $stmt->fetchAll();
    }
    
    return ['services' => $recommendations, 'reason' => $reason];
}

// =============================================
// AI-Driven Customer Segmentation (RFM Analysis)
// =============================================

/**
 * Get customer segments using RFM (Recency, Frequency, Monetary) analysis
 */
function getCustomerSegments() {
    $conn = getDBConnection();
    
    $stmt = $conn->query("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.created_at as registered_at,
            COUNT(a.id) as booking_count,
            COALESCE(SUM(a.final_price), 0) as total_spend,
            MAX(a.appointment_date) as last_visit,
            DATEDIFF(CURDATE(), MAX(a.appointment_date)) as days_since_last_visit,
            DATEDIFF(CURDATE(), u.created_at) as days_since_registration
        FROM users u
        LEFT JOIN appointments a ON u.id = a.user_id 
            AND a.status = 'completed'
            AND (a.deleted_at IS NULL)
        WHERE u.role = 'client' AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at
        ORDER BY total_spend DESC
    ");
    $clients = $stmt->fetchAll();
    
    $segments = [
        'VIP' => [],
        'Loyal' => [],
        'Regular' => [],
        'At-Risk' => [],
        'New' => [],
        'Dormant' => []
    ];
    
    foreach ($clients as $client) {
        $bookings = (int)$client['booking_count'];
        $spend = (float)$client['total_spend'];
        $days_since = $client['days_since_last_visit'];
        $days_reg = (int)$client['days_since_registration'];
        
        // 1. VIP: High spend, high frequency, recent visit
        if ($bookings >= 5 && $spend >= 1000 && ($days_since === null || $days_since <= 30)) {
            $client['segment'] = 'VIP';
            $segments['VIP'][] = $client;
        } 
        // 2. Loyal: Frequent visitors (3+) even with lower spend
        elseif ($bookings >= 3 && ($days_since === null || $days_since <= 45)) {
            $client['segment'] = 'Loyal';
            $segments['Loyal'][] = $client;
        }
        // 3. New: Registered recently with few bookings
        elseif ($days_reg <= 30 && $bookings < 2) {
            $client['segment'] = 'New';
            $segments['New'][] = $client;
        } 
        // 4. Dormant: No visit for a long time (> 90 days)
        elseif ($days_since !== null && $days_since > 90) {
            $client['segment'] = 'Dormant';
            $segments['Dormant'][] = $client;
        } 
        // 5. At-Risk: Stopped visiting recently (> 60 days)
        elseif ($days_since !== null && $days_since > 60) {
            $client['segment'] = 'At-Risk';
            $segments['At-Risk'][] = $client;
        } 
        // 6. Regular: Everyone else
        else {
            $client['segment'] = 'Regular';
            $segments['Regular'][] = $client;
        }
    }
    
    return $segments;
}

// =============================================
// Inventory Management Functions
// =============================================

/**
 * Deduct inventory stock and log the change
 */
function deductInventory($inventory_id, $qty, $reason = '', $user_id = null) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch();
    
    if (!$item) return false;
    
    $new_qty = max(0, $item['quantity'] - $qty);
    $status = ($new_qty <= 0) ? 'out_of_stock' : 'in_stock';
    
    // Check reorder level
    $stmt = $conn->prepare("SELECT reorder_level FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $reorder = (int)$stmt->fetchColumn();
    if ($new_qty > 0 && $new_qty <= $reorder) {
        $status = 'low_stock';
    }
    
    $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_qty, $status, $inventory_id]);
    
    // Log the change
    $stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, change_type, quantity_change, quantity_after, notes, created_by) VALUES (?, 'deduct', ?, ?, ?, ?)");
    $stmt->execute([$inventory_id, -$qty, $new_qty, $reason, $user_id]);
    
    return true;
}

/**
 * Restock inventory and log the change
 */
function restockInventory($inventory_id, $qty, $reason = '', $user_id = null) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT quantity, reorder_level FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch();
    
    if (!$item) return false;
    
    $new_qty = $item['quantity'] + $qty;
    $status = ($new_qty <= 0) ? 'out_of_stock' : (($new_qty <= $item['reorder_level']) ? 'low_stock' : 'in_stock');
    
    $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_qty, $status, $inventory_id]);
    
    // Log the change
    $stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, change_type, quantity_change, quantity_after, notes, created_by) VALUES (?, 'restock', ?, ?, ?, ?)");
    $stmt->execute([$inventory_id, $qty, $new_qty, $reason, $user_id]);
    
    return true;
}

/**
 * Get low stock items
 */
function getLowStockItems($threshold = null) {
    $conn = getDBConnection();
    
    if ($threshold !== null) {
        $stmt = $conn->prepare("SELECT i.*, s.name as service_name FROM inventory i LEFT JOIN services s ON i.linked_service_id = s.id WHERE i.quantity <= ? ORDER BY i.quantity ASC");
        $stmt->execute([$threshold]);
    } else {
        $stmt = $conn->query("SELECT i.*, s.name as service_name FROM inventory i LEFT JOIN services s ON i.linked_service_id = s.id WHERE i.quantity <= i.reorder_level ORDER BY i.quantity ASC");
    }
    
    return $stmt->fetchAll();
}

/**
 * Send email using custom minimal SMTP implementation
 * This replaces PHPMailer to reduce codebase size.
 */
function sendSMTPPHPMail($to, $subject, $message, $is_html = false) {
    $conn = getDBConnection();
    
    // Get SMTP settings
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass')");
    $stmt->execute();
    $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $host = $s['smtp_host'] ?: 'smtp.gmail.com';
    $port = $s['smtp_port'] ?: 587;
    $secure = $s['smtp_secure'] ?: 'tls';
    $user = $s['smtp_user'] ?: '';
    $pass = $s['smtp_pass'] ?: '';

    if (empty($user) || empty($pass)) return false;

    // Helper to send command and get response
    $talk = function($socket, $cmd = null) {
        if ($cmd) fputs($socket, $cmd . "\r\n");
        $resp = "";
        while ($line = fgets($socket, 515)) {
            $resp .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        return $resp;
    };

    try {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        $transport = ($secure === 'ssl') ? "ssl://$host" : "tcp://$host";
        $socket = stream_socket_client("$transport:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) throw new Exception("Connection failed: $errstr");

        $talk($socket); // Initial greeting
        $talk($socket, "EHLO " . $_SERVER['HTTP_HOST']);

        if ($secure === 'tls') {
            $talk($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
                throw new Exception("TLS encryption failed");
            }
            $talk($socket, "EHLO " . $_SERVER['HTTP_HOST']);
        }

        $talk($socket, "AUTH LOGIN");
        $talk($socket, base64_encode($user));
        $talk($socket, base64_encode($pass));

        $talk($socket, "MAIL FROM: <$user>");
        $talk($socket, "RCPT TO: <$to>");
        
        $talk($socket, "DATA");
        
        $headers = [
            "MIME-Version: 1.0",
            "Content-type: " . ($is_html ? "text/html" : "text/plain") . "; charset=UTF-8",
            "From: " . APP_NAME . " <$user>",
            "To: <$to>",
            "Subject: $subject",
            "Date: " . date('r')
        ];

        fputs($socket, implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n");
        $resp = $talk($socket);
        
        $talk($socket, "QUIT");
        fclose($socket);

        return strpos($resp, '250') === 0;
    } catch (Exception $e) {
        error_log("TinyMailer Error: " . $e->getMessage());
        return false;
    }
}
