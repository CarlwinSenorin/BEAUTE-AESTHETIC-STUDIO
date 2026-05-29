<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    header('Location: appointments.php');
    exit;
}

// Fetch appointment with client info
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                                sf.first_name as staff_first, sf.last_name as staff_last
                        FROM appointments a
                        JOIN users u ON a.user_id = u.id
                        LEFT JOIN staff st ON a.staff_id = st.id
                        LEFT JOIN users sf ON st.user_id = sf.id
                        WHERE a.id = ?");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: appointments.php');
    exit;
}

$error = '';
$success = '';

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_reminder') {
        $type = $_POST['reminder_type'] ?? 'both';
        $sent_email = false;
        $sent_sms = false;
        
        if ($type === 'email' || $type === 'both') {
            if (sendEmailReminder($appointment_id)) $sent_email = true;
        }
        if ($type === 'sms' || $type === 'both') {
            if (sendSMSReminder($appointment_id)) $sent_sms = true;
        }
        
        if ($sent_email || $sent_sms) {
            $success = 'Manual reminder sent' . ($sent_email && $sent_sms ? ' (Email & SMS)' : ($sent_email ? ' (Email)' : ' (SMS)'));
        } else {
            $error = 'Failed to send manual reminder.';
        }
    } else {
        $new_date = $_POST['appointment_date'] ?? $appointment['appointment_date'];
        $new_time = $_POST['appointment_time'] ?? $appointment['appointment_time'];
        $status   = $_POST['status'] ?? $appointment['status'];

        if ($new_date && $new_time && $status) {
            $today = date('Y-m-d');
            if ($new_date < $today) {
                $error = 'You cannot schedule an appointment in the past.';
            } else {
                $services = json_decode($appointment['services'], true);
                if (empty($services) || !is_array($services)) {
                    $duration = 60;
                } else {
                    $placeholders = str_repeat('?,', count($services) - 1) . '?';
                    $stmt = $conn->prepare("SELECT SUM(duration) as duration FROM services WHERE id IN ($placeholders)");
                    $stmt->execute($services);
                    $result = $stmt->fetch();
                    $duration = $result['duration'] ?? 60;
                }

                $start_ts = strtotime($new_date . ' ' . $new_time);
                $end_time = date('H:i:s', $start_ts + ($duration * 60));

                $old_status = $appointment['status'];
                $stmt = $conn->prepare("UPDATE appointments 
                                        SET appointment_date = ?, appointment_time = ?, end_time = ?, status = ?, updated_at = NOW()
                                        WHERE id = ?");
                $stmt->execute([$new_date, $new_time, $end_time, $status, $appointment_id]);

                if ($status === 'confirmed' && $old_status !== 'confirmed') {
                    sendAppointmentNotification($appointment_id, 'confirmation');
                }

                $success = 'Appointment updated successfully.';

                // Refresh appointment data
                $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                                               sf.first_name as staff_first, sf.last_name as staff_last
                                        FROM appointments a
                                        JOIN users u ON a.user_id = u.id
                                        LEFT JOIN staff st ON a.staff_id = st.id
                                        LEFT JOIN users sf ON st.user_id = sf.id
                                        WHERE a.id = ?");
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch();
            }
        } else {
            $error = 'Please provide date, time, and status.';
        }
    }
}

// Prepare service names
$service_names = [];
$service_ids = json_decode($appointment['services'], true);
if (!empty($service_ids) && is_array($service_ids)) {
    $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
    $stmt->execute($service_ids);
    $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Decode client_details
$clientDetails = !empty($appointment['client_details']) ? json_decode($appointment['client_details'], true) : null;
$pax = $appointment['pax'] ?? 1;
$paymentMethod = $appointment['payment_method'] ?? 'N/A';
$paymentStatus = $appointment['payment_status'] ?? 'N/A';
$paymentMethodDisplay = ucwords(str_replace('_', ' ', $paymentMethod));
$paymentStatusDisplay = ucfirst($paymentStatus);
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
                <a href="appointments.php" class="btn btn-outline">Back to Appointments</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Appointment Overview -->
            <div class="admin-card">
                <h2><i class="fas fa-clipboard-list"></i> Appointment Overview</h2>
                <div class="detail-grid">
                    <div>
                        <h3>Client</h3>
                        <p><strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong></p>
                        <p><i class="fas fa-envelope" style="color:#aaa; width:16px;"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                        <p><i class="fas fa-phone" style="color:#aaa; width:16px;"></i> <?php echo htmlspecialchars($appointment['phone']); ?></p>
                    </div>
                    <div>
                        <h3>Schedule</h3>
                        <p><strong><?php echo formatDate($appointment['appointment_date']); ?></strong></p>
                        <p><?php echo formatTime($appointment['appointment_time']); ?>
                        <?php if (!empty($appointment['end_time'])): ?>
                            – <?php echo formatTime($appointment['end_time']); ?>
                        <?php endif; ?></p>
                    </div>
                    <div>
                        <h3>Services</h3>
                        <p><?php echo !empty($service_names) ? htmlspecialchars(implode(', ', $service_names)) : 'No services'; ?></p>
                    </div>
                    <div>
                        <h3>Staff</h3>
                        <p><?php echo htmlspecialchars($appointment['staff_first'] ? $appointment['staff_first'] . ' ' . $appointment['staff_last'] : 'Unassigned'); ?></p>
                    </div>
                    <div>
                        <h3>Pricing</h3>
                        <p><strong>Total:</strong> <?php echo formatPrice($appointment['final_price']); ?></p>
                        <?php if ($appointment['discount_applied'] > 0): ?>
                            <p><strong>Discount:</strong> -<?php echo formatPrice($appointment['discount_applied']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>Status</h3>
                        <p><span class="status-badge status-<?php echo $appointment['status']; ?>"><?php echo ucwords(str_replace('_',' ',$appointment['status'])); ?></span></p>
                        <p style="margin-top:4px;"><strong>Pax:</strong> <?php echo (int)$pax; ?> person(s)</p>
                        <p><strong>Payment:</strong> <?php echo htmlspecialchars($paymentMethodDisplay); ?> (<?php echo htmlspecialchars($paymentStatusDisplay); ?>)</p>
                    </div>
                </div>
                <?php if (!empty($appointment['notes'])): ?>
                <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid #eee;">
                    <h3>Notes</h3>
                    <p style="color:#555;"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                </div>
                <?php endif; ?>
                <div style="margin-top:0.75rem; color:#999; font-size:0.8rem;">
                    Booked on <?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?>
                </div>
            </div>

            <!-- Per-Person Breakdown -->
            <?php if (is_array($clientDetails) && !empty($clientDetails)): ?>
            <div class="admin-card">
                <h2><i class="fas fa-users"></i> Per-Person Service Breakdown</h2>
                <div class="person-breakdown">
                    <?php
                    $byPerson = [];
                    foreach ($clientDetails as $cd) {
                        $pIdx = $cd['person_index'] ?? 1;
                        $byPerson[$pIdx][] = $cd;
                    }
                    ksort($byPerson);
                    foreach ($byPerson as $personIdx => $personServices):
                    ?>
                    <div class="person-card">
                        <div class="person-card-header">
                            <i class="fas fa-user-circle"></i> Person <?php echo (int)$personIdx; ?>
                        </div>
                        <div class="person-card-body">
                            <?php foreach ($personServices as $ps): ?>
                            <div class="person-service-row">
                                <div class="person-service-name">
                                    <i class="fas fa-spa"></i>
                                    <?php echo htmlspecialchars($ps['service_name'] ?? 'Service'); ?>
                                </div>
                                <div class="person-service-details">
                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($ps['staffName'] ?? 'Any'); ?></span>
                                    <span><i class="fas fa-calendar-day"></i> <?php echo !empty($ps['date']) ? formatDate($ps['date']) : '—'; ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo !empty($ps['time']) ? formatTime($ps['time']) : '—'; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Update Appointment -->
            <div class="admin-card">
                <h2><i class="fas fa-edit"></i> Update Appointment</h2>
                <form method="POST" class="admin-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="appointment_date" value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="appointment_time" value="<?php echo htmlspecialchars($appointment['appointment_time']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <?php foreach (['pending','reserved','confirmed','in_progress','completed','cancelled','no_show'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $appointment['status'] === $s ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_',' ',$s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Notifications -->
            <div class="admin-card">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <p class="form-description">Send a manual reminder to this client via email or SMS.</p>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 1rem;">
                    <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="hidden" name="action" value="send_reminder">
                        <select name="reminder_type" class="status-select">
                            <option value="both">Both Email & SMS</option>
                            <option value="email">Email Only</option>
                            <option value="sms">SMS Only</option>
                        </select>
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-paper-plane"></i> Send Reminder
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }
        .detail-grid h3 {
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
        }
        .detail-grid p {
            margin: 0.25rem 0;
            color: #555;
        }
        /* Person Breakdown */
        .person-breakdown { display: flex; flex-direction: column; gap: 0.75rem; }
        .person-card { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff; }
        .person-card-header {
            background: linear-gradient(135deg, var(--primary-color, #d4a574) 0%, #c48b5c 100%);
            color: #fff; padding: 0.5rem 1rem; font-weight: 600; font-size: 0.88rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .person-card-body { padding: 0.75rem 1rem; }
        .person-service-row {
            display: flex; flex-direction: column; gap: 4px;
            padding: 0.5rem 0; border-bottom: 1px solid #f0f4f8;
        }
        .person-service-row:last-child { border-bottom: none; }
        .person-service-name {
            font-weight: 600; font-size: 0.9rem; color: #2d3748;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .person-service-name i { color: var(--primary-color, #d4a574); font-size: 0.85rem; }
        .person-service-details {
            display: flex; flex-wrap: wrap; gap: 1rem;
            font-size: 0.82rem; color: #718096; padding-left: 1.5rem;
        }
        .person-service-details i { color: #a0aec0; margin-right: 3px; }
        .status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
<?php include '../includes/admin-footer.php'; ?>
