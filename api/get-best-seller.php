<?php
require_once '../config/functions.php';

$conn = getDBConnection();

// Get the most booked service in the last 30 days
$stmt = $conn->query("
    SELECT s.id, s.name, s.image_url, s.description, s.base_price, COUNT(a.id) as booking_count
    FROM services s
    JOIN appointments a ON JSON_CONTAINS(a.services, CAST(s.id AS CHAR), '$')
    WHERE a.status = 'completed' 
    AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id
    ORDER BY booking_count DESC
    LIMIT 1
");

$best_seller = $stmt->fetch(PDO::FETCH_ASSOC);

if ($best_seller) {
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $best_seller['id'],
            'name' => $best_seller['name'],
            'description' => substr($best_seller['description'], 0, 80) . '...',
            'image_url' => $best_seller['image_url'],
            'price' => formatPrice($best_seller['base_price']),
            'bookings' => $best_seller['booking_count']
        ]
    ]);
} else {
    // If no bookings in 30 days, just get any popular one
    $stmt = $conn->query("SELECT id, name, image_url, description, base_price FROM services WHERE status = 'active' LIMIT 1");
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($backup) {
        jsonResponse([
            'success' => true,
            'data' => [
                'id' => $backup['id'],
                'name' => $backup['name'],
                'description' => substr($backup['description'], 0, 80) . '...',
                'image_url' => $backup['image_url'],
                'price' => formatPrice($backup['base_price']),
                'bookings' => 'Popular'
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'No services found']);
    }
}
?>
