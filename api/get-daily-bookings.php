<?php
/**
 * Get Daily Bookings API
 * Returns all booked time slots for a given date.
 * Own appointments show full details; others are shown as "Reserved" (privacy).
 */
require_once '../config/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

requireLogin();

$date = $_GET['date'] ?? null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(['error' => 'A valid date (YYYY-MM-DD) is required'], 400);
}

$conn = getDBConnection();
$user_id  = $_SESSION['user_id'];
$is_admin = isAdmin();

// Fetch all non-cancelled bookings for the date
$sql = "SELECT a.id, a.user_id, a.appointment_time, a.end_time, a.status, a.services, a.final_price
        FROM appointments a
        WHERE a.appointment_date = ?
          AND a.status NOT IN ('cancelled', 'no_show')
        ORDER BY a.appointment_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([$date]);
$appointments = $stmt->fetchAll();

$status_colors = [
    'pending'   => '#ffc107',
    'confirmed' => '#28a745',
    'completed' => '#17a2b8',
];

$bookings = [];

foreach ($appointments as $apt) {
    $is_own = ($apt['user_id'] == $user_id);

    // Format times
    $start_display = date('g:i A', strtotime($apt['appointment_time']));
    $end_display   = date('g:i A', strtotime($apt['end_time']));

    if ($is_admin || $is_own) {
        // Resolve service names
        $service_names = 'No services';
        $service_ids   = json_decode($apt['services'], true);
        if (!empty($service_ids)) {
            $placeholders  = str_repeat('?,', count($service_ids) - 1) . '?';
            $st = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
            $st->execute($service_ids);
            $service_names = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
        }

        $bookings[] = [
            'time_start'   => $start_display,
            'time_end'     => $end_display,
            'raw_start'    => $apt['appointment_time'],
            'status'       => $apt['status'],
            'color'        => $status_colors[$apt['status']] ?? '#6c757d',
            'is_own'       => $is_own,
            'masked'       => false,
            'label'        => $is_own ? 'My Appointment' : 'Reserved',
            'services'     => $service_names,
            'price'        => $is_own ? $apt['final_price'] : null,
        ];
    } else {
        // Privacy-masked view for other clients
        $bookings[] = [
            'time_start' => $start_display,
            'time_end'   => $end_display,
            'raw_start'  => $apt['appointment_time'],
            'status'     => $apt['status'],
            'color'      => '#9e9e9e',
            'is_own'     => false,
            'masked'     => true,
            'label'      => 'Reserved',
            'services'   => null,
            'price'      => null,
        ];
    }
}

// Build the response – also include whether the day is fully booked
$is_fully_booked = isDateFullyBooked($date);

jsonResponse([
    'date'            => $date,
    'bookings'        => $bookings,
    'total'           => count($bookings),
    'is_fully_booked' => $is_fully_booked,
]);
