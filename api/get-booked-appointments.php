<?php
require_once __DIR__ . '/../config/functions.php';

// Ensure user is logged in (client or admin/staff)
if (!isLoggedIn() && !isAdmin() && !isStaffLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$conn = getDBConnection();

// Get date range from FullCalendar
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');

// Fix: Use GROUP_CONCAT to avoid duplicate events from JSON_CONTAINS service join
// Fetch one row per appointment, with all service names concatenated
$sql = "SELECT a.id, a.appointment_date, a.appointment_time, a.end_time, a.status,
               a.services, a.pax, a.final_price, a.payment_method, a.payment_status,
               u.first_name, u.last_name, u.phone,
               sf.first_name as staff_first, sf.last_name as staff_last
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN users sf ON st.user_id = sf.id
        WHERE a.appointment_date BETWEEN ? AND ?
        AND a.status NOT IN ('cancelled', 'no_show')
        ORDER BY a.appointment_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([$start, $end]);
$appointments = $stmt->fetchAll();

$events = [];
$is_admin = isAdmin();

// Pre-fetch service name map for efficiency
$svcStmt = $conn->query("SELECT id, name FROM services");
$serviceMap = [];
while ($row = $svcStmt->fetch()) {
    $serviceMap[(int)$row['id']] = $row['name'];
}

foreach ($appointments as $app) {
    // Resolve service names from the JSON services column
    $serviceIds = json_decode($app['services'] ?? '[]', true);
    $svcNames = [];
    if (is_array($serviceIds)) {
        foreach ($serviceIds as $sid) {
            if (isset($serviceMap[(int)$sid])) {
                $svcNames[] = $serviceMap[(int)$sid];
            }
        }
    }
    $serviceDisplay = !empty($svcNames) ? implode(', ', array_unique($svcNames)) : 'No services';

    $start_datetime = $app['appointment_date'] . 'T' . $app['appointment_time'];
    $end_datetime = $app['appointment_date'] . 'T' . $app['end_time'];
    
    $staffName = $app['staff_first'] ? $app['staff_first'] . ' ' . $app['staff_last'] : 'Unassigned';
    $pax = $app['pax'] ?? 1;

    if ($is_admin) {
        $title = $app['first_name'] . ' ' . $app['last_name'];
        if ($pax > 1) $title .= " ({$pax} pax)";
        
        $events[] = [
            'id' => $app['id'],
            'title' => $title,
            'start' => $start_datetime,
            'end' => $end_datetime,
            'className' => 'status-' . $app['status'],
            'extendedProps' => [
                'client_name' => $app['first_name'] . ' ' . $app['last_name'],
                'phone' => $app['phone'],
                'service' => $serviceDisplay,
                'staff' => $staffName,
                'pax' => (int)$pax,
                'price' => $app['final_price'],
                'payment_method' => ucwords(str_replace('_', ' ', $app['payment_method'] ?? 'N/A')),
                'payment_status' => ucfirst($app['payment_status'] ?? 'N/A'),
                'status' => $app['status']
            ]
        ];
    } else {
        $events[] = [
            'title' => 'Booked',
            'start' => $start_datetime,
            'end' => $end_datetime,
            'rendering' => 'background',
            'color' => '#ff9f89',
            'extendedProps' => [
                'status' => 'booked'
            ]
        ];
    }
}

jsonResponse($events);
?>
