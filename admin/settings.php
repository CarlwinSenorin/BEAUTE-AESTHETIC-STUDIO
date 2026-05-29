<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    $settings = [
        'sms_enabled' => $_POST['sms_enabled'] ?? 'false',
        'sms_api_key' => trim($_POST['sms_api_key'] ?? ''),
        'sms_from_number' => trim($_POST['sms_from_number'] ?? ''),
        'email_enabled' => $_POST['email_enabled'] ?? 'false',
        'peak_hour_start' => $_POST['peak_hour_start'] ?? '11:00',
        'peak_hour_end' => $_POST['peak_hour_end'] ?? '14:00',
        'peak_hour_surcharge' => $_POST['peak_hour_surcharge'] ?? '0',
        'reminder_hours_before' => $_POST['reminder_hours_before'] ?? '24',
        'follow_up_hours_after' => $_POST['follow_up_hours_after'] ?? '2',
        'smtp_host' => $_POST['smtp_host'] ?? 'smtp.gmail.com',
        'smtp_port' => $_POST['smtp_port'] ?? '587',
        'smtp_secure' => $_POST['smtp_secure'] ?? 'tls',
        'smtp_user' => $_POST['smtp_user'] ?? '',
        'smtp_pass' => $_POST['smtp_pass'] ?? ''
    ];

    // Validate API key — reject JSON blobs accidentally pasted
    if (!empty($settings['sms_api_key']) && (substr($settings['sms_api_key'], 0, 1) === '{' || substr($settings['sms_api_key'], 0, 1) === '[' || strlen($settings['sms_api_key']) > 200)) {
        $error = 'Invalid API Key. It should be a short token starting with "pk_" (about 67 characters). Please copy only the API key from httpsms.com/settings, not the entire JSON response.';
        $settings['sms_api_key'] = ''; // Clear the invalid key
    }

    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        $success = 'Settings updated successfully';
    } catch (PDOException $e) {
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Fetch all settings
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
$raw_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Default settings if not in DB
$current_settings = array_merge([
    'sms_enabled' => 'false',
    'sms_api_key' => '',
    'sms_from_number' => '',
    'email_enabled' => 'false',
    'peak_hour_start' => '11:00',
    'peak_hour_end' => '14:00',
    'peak_hour_surcharge' => '0',
    'reminder_hours_before' => '24',
    'follow_up_hours_after' => '2',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_secure' => 'tls',
    'smtp_user' => '',
    'smtp_pass' => ''
], $raw_settings);

// Force clear API key if it looks like the JSON blob from previous session
if (isset($current_settings['sms_api_key']) && (substr($current_settings['sms_api_key'], 0, 1) === '{' || substr($current_settings['sms_api_key'], 0, 1) === '[')) {
    $conn->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'sms_api_key'")->execute();
    $current_settings['sms_api_key'] = '';
}

include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
        <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="admin-form">
        <?php csrfTokenField(); ?>
        
        <div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
            
            <!-- SMS Settings -->
            <div class="admin-card">
                <h2><i class="fas fa-sms"></i> SMS Configuration (httpSMS)</h2>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="switch-label" style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="sms_enabled" value="true" <?php echo ($current_settings['sms_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?> style="margin-right: 10px;">
                        Enable SMS Notifications
                    </label>
                </div>
                <div class="form-group">
                    <label>httpSMS API Key</label>
                    <input type="password" name="sms_api_key" value="<?php echo htmlspecialchars($current_settings['sms_api_key'] ?? ''); ?>" placeholder="Enter API Key">
                    <small style="color: #666;">Get your API key from <a href="https://httpsms.com" target="_blank">httpsms.com</a></small>
                </div>
                <div class="form-group">
                    <label>SMS From Number (Android Device)</label>
                    <input type="text" name="sms_from_number" value="<?php echo htmlspecialchars($current_settings['sms_from_number'] ?? ''); ?>" placeholder="+639XXXXXXXXX">
                    <small style="color: #666;">The phone number of your Android device in international format.</small>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <button type="button" id="test-sms-btn" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Test SMS Connection
                    </button>
                    <div id="test-sms-msg" style="margin-top: 10px; font-size: 0.85rem; padding: 10px; border-radius: 4px; display: none;"></div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="admin-card">
                <h2><i class="fas fa-envelope"></i> Email Configuration</h2>
                <div class="form-group">
                    <label class="switch-label" style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="email_enabled" value="true" <?php echo $current_settings['email_enabled'] === 'true' ? 'checked' : ''; ?> style="margin-right: 10px;">
                        Enable Email Notifications
                    </label>
                </div>
                <div class="alert alert-info" style="font-size: 0.85rem; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Gmail SMTP requires an <strong>App Password</strong> if 2FA is enabled.
                </div>
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>" placeholder="587">
                    </div>
                    <div class="form-group">
                        <label>SMTP Encryption</label>
                        <select name="smtp_secure">
                            <option value="tls" <?php echo ($current_settings['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($current_settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>SMTP Username (Gmail)</label>
                    <input type="email" name="smtp_user" value="<?php echo htmlspecialchars($current_settings['smtp_user']); ?>" placeholder="example@gmail.com">
                </div>
                <div class="form-group">
                    <label>SMTP Password (App Password)</label>
                    <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($current_settings['smtp_pass']); ?>" placeholder="Enter App Password">
                </div>
            </div>

            <!-- Appointment Settings -->
            <div class="admin-card">
                <h2><i class="fas fa-calendar-check"></i> Appointment Settings</h2>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Reminder (hours before)</label>
                        <input type="number" name="reminder_hours_before" value="<?php echo htmlspecialchars($current_settings['reminder_hours_before']); ?>" min="1" max="72">
                    </div>
                    <div class="form-group">
                        <label>Follow-up (hours after)</label>
                        <input type="number" name="follow_up_hours_after" value="<?php echo htmlspecialchars($current_settings['follow_up_hours_after']); ?>" min="1" max="24">
                    </div>
                </div>
            </div>

            <!-- Peak-Hour Settings -->
            <div class="admin-card">
                <h2><i class="fas fa-chart-line"></i> Peak-Hour Pricing</h2>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="peak_hour_start" value="<?php echo htmlspecialchars($current_settings['peak_hour_start']); ?>">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="peak_hour_end" value="<?php echo htmlspecialchars($current_settings['peak_hour_end']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Surcharge (%)</label>
                        <input type="number" name="peak_hour_surcharge" value="<?php echo htmlspecialchars($current_settings['peak_hour_surcharge']); ?>" min="0" max="100">
                    </div>
                </div>
                <p style="color: #666; font-size: 0.85rem; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Higher demand period surcharge.
                </p>
            </div>

        </div>

        <div class="form-actions" style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
        </div>
    </form>
</div>


<script>
document.getElementById('test-sms-btn').addEventListener('click', function() {
    const btn = this;
    const msgDiv = document.getElementById('test-sms-msg');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    msgDiv.style.display = 'none';
    
    const formData = new FormData();
    formData.append('phone', document.querySelector('input[name="sms_from_number"]').value);
    
    fetch('test-sms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msgDiv.style.display = 'block';
        msgDiv.innerHTML = data.message;
        msgDiv.style.backgroundColor = data.success ? '#d4edda' : '#f8d7da';
        msgDiv.style.color = data.success ? '#155724' : '#721c24';
        msgDiv.style.border = '1px solid ' + (data.success ? '#c3e6cb' : '#f5c6cb');
    })
    .catch(error => {
        msgDiv.style.display = 'block';
        msgDiv.innerHTML = 'Error communicating with server.';
        msgDiv.style.backgroundColor = '#f8d7da';
        msgDiv.style.color = '#721c24';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>
