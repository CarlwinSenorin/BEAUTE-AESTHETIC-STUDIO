<?php
/**
 * Appointment Reminder Cron Job
 * Run this script every hour via cron: 0 * * * * php /path/to/send-reminders.php
 */

require_once __DIR__ . '/../config/functions.php';

$conn = getDBConnection();

// Get settings
$stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'reminder_hours_before'");
$reminder_hours = (int)$stmt->fetchColumn() ?: 24;

$stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'sms_enabled'");
$sms_enabled = $stmt->fetchColumn() === 'true';

$stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'email_enabled'");
$email_enabled = $stmt->fetchColumn() === 'true';

// Calculate reminder time window
$reminder_time_start = date('Y-m-d H:i:s', strtotime("+$reminder_hours hours"));
$reminder_time_end = date('Y-m-d H:i:s', strtotime("+" . ($reminder_hours + 1) . " hours"));

// Get appointments that need reminders
$stmt = $conn->prepare("SELECT a.*, u.email, u.phone, u.first_name 
                        FROM appointments a 
                        JOIN users u ON a.user_id = u.id 
                        WHERE a.status IN ('pending', 'confirmed')
                        AND a.reminder_sent = 0
                        AND CONCAT(a.appointment_date, ' ', a.appointment_time) BETWEEN ? AND ?");
$stmt->execute([$reminder_time_start, $reminder_time_end]);
$appointments = $stmt->fetchAll();

$sent_count = 0;

foreach ($appointments as $appointment) {
    $sent = false;
    
    // Send email reminder
    if ($email_enabled) {
        if (sendEmailReminder($appointment['id'])) {
            $sent = true;
        }
    }
    
    // Send SMS reminder
    if ($sms_enabled) {
        if (sendSMSReminder($appointment['id'])) {
            $sent = true;
        }
    }
    
    if ($sent) {
        // Mark reminder as sent
        $stmt = $conn->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
        $stmt->execute([$appointment['id']]);
        $sent_count++;
    }
}

echo "Sent $sent_count reminders\n";

// ======================================================
// Follow-up notifications for recently completed appointments
// ======================================================
$stmt_setting = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'follow_up_hours_after'");
$follow_up_hours = (int)$stmt_setting->fetchColumn() ?: 2;

$follow_up_cutoff = date('Y-m-d H:i:s', strtotime("-$follow_up_hours hours"));

$stmt = $conn->prepare("SELECT id FROM appointments 
                         WHERE status = 'completed' 
                         AND follow_up_sent = 0
                         AND updated_at <= ?");
$stmt->execute([$follow_up_cutoff]);
$completed_appointments = $stmt->fetchAll();

$follow_up_count = 0;
foreach ($completed_appointments as $apt) {
    if (sendFollowUpNotification($apt['id'])) {
        $follow_up_count++;
    }
}

echo "Sent $follow_up_count follow-up notifications\n";
