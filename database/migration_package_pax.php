<?php
require_once 'config/functions.php';
$conn = getDBConnection();

try {
    // Check if pax column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM packages LIKE 'pax'");
    if ($stmt->rowCount() === 0) {
        $conn->exec("ALTER TABLE packages ADD COLUMN pax INT NOT NULL DEFAULT 1 AFTER services");
        echo "✅ Added 'pax' column to packages table.\n";
    } else {
        echo "ℹ️ 'pax' column already exists.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
