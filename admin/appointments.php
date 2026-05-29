<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    
    // Handle start treatment action
    if (isset($_POST['start_treatment'])) {
        if ($id) {
            $stmt = $conn->prepare("UPDATE appointments SET status = 'in_progress', checked_in_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Treatment started! Monitor in Live View.';
        }
    } 
    // Handle regular status update
    elseif (isset($_POST['update_status'])) {
        $status = $_POST['status'] ?? '';
        
        if ($id && $status) {
            $stmt = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $old_status = $stmt->fetchColumn();

            $extraSql = "";
            if ($status === 'in_progress') {
                $extraSql = ", checked_in_at = NOW()";
            }
            
            $stmt = $conn->prepare("UPDATE appointments SET status = ? $extraSql WHERE id = ?");
            $stmt->execute([$status, $id]);

            if ($status === 'confirmed' && $old_status !== 'confirmed') {
                sendAppointmentNotification($id, 'confirmation');
            }

            $success = 'Appointment status updated';
        }
    }
}

// Check for messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = $success ?? 'Appointment moved to recovery bin.';
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Get appointments
$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'pending', 'reserved', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
if (!in_array($filter, $validFilters)) $filter = 'all';

$sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
               sf.first_name as staff_first, sf.last_name as staff_last
        FROM appointments a 
        JOIN users u ON a.user_id = u.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN users sf ON st.user_id = sf.id
        WHERE a.deleted_at IS NULL";

$params = [];
if ($filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}

// Sort oldest to newest (first booked first)
$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-calendar-check"></i> Manage Appointments</h1>
                <div>
                    <span style="color:#888; font-size:0.9rem; margin-right:15px;"><?php echo count($appointments); ?> record(s)</span>
                    <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="admin-card">
                <div class="filter-buttons">
                    <?php
                    $tabs = [
                        'all' => 'All', 'pending' => 'Pending', 'reserved' => 'Reserved',
                        'confirmed' => 'Confirmed', 'in_progress' => 'In Progress',
                        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'
                    ];
                    foreach ($tabs as $key => $label):
                    ?>
                        <a href="appointments.php?filter=<?php echo $key; ?>" class="filter-btn <?php echo $filter === $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="admin-card">
                <h2>Appointments</h2>
                <?php if (empty($appointments)): ?>
                    <p class="empty-state"><i class="fas fa-calendar-times" style="font-size:2rem; display:block; margin-bottom:10px; color:#ddd;"></i>No appointments found<?php echo $filter !== 'all' ? " with status <strong>{$filter}</strong>" : ''; ?>.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Client</th>
                                <th>Staff</th>
                                <th>Services</th>
                                <th>Pax</th>
                                <th>Price</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): 
                                $services = json_decode($apt['services'], true);
                                $serviceNames = 'N/A';
                                if (!empty($services)) {
                                    $placeholders = str_repeat('?,', count($services) - 1) . '?';
                                    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
                                    $stmt->execute($services);
                                    $serviceNames = implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
                                }

                                $clientDetails = !empty($apt['client_details']) ? json_decode($apt['client_details'], true) : null;
                                $pax = $apt['pax'] ?? 1;
                                $paymentMethod = $apt['payment_method'] ?? 'N/A';
                                $paymentStatus = $apt['payment_status'] ?? 'N/A';
                                $paymentMethodDisplay = ucwords(str_replace('_', ' ', $paymentMethod));
                                $paymentStatusDisplay = ucfirst($paymentStatus);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($apt['appointment_date']); ?></strong><br>
                                        <small><?php echo formatTime($apt['appointment_time']); ?>
                                        <?php if (!empty($apt['end_time'])): ?>
                                            – <?php echo formatTime($apt['end_time']); ?>
                                        <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></strong><br>
                                        <small style="color:#999;"><?php echo htmlspecialchars($apt['phone']); ?></small><br>
                                        <small style="color:#999;"><?php echo htmlspecialchars($apt['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($apt['staff_first'] ? $apt['staff_first'] . ' ' . $apt['staff_last'] : 'Unassigned'); ?>
                                    </td>
                                    <td style="max-width:180px; white-space:normal; font-size:0.85rem;"><?php echo htmlspecialchars($serviceNames); ?></td>
                                    <td>
                                        <span class="pax-badge"><?php echo (int)$pax; ?> <i class="fas fa-user<?php echo $pax > 1 ? 's' : ''; ?>"></i></span>
                                    </td>
                                    <td><?php echo formatPrice($apt['final_price']); ?></td>
                                    <td>
                                        <span class="payment-method-badge"><?php echo htmlspecialchars($paymentMethodDisplay); ?></span><br>
                                        <small class="payment-status-<?php echo strtolower($paymentStatus); ?>"><?php echo htmlspecialchars($paymentStatusDisplay); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select">
                                                <?php foreach (['pending','reserved','confirmed','in_progress','completed','cancelled','no_show'] as $s): ?>
                                                    <option value="<?php echo $s; ?>" <?php echo $apt['status'] === $s ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_',' ',$s)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        
                                        <?php if ($apt['status'] === 'confirmed'): ?>
                                            <form method="POST" style="margin-top: 5px;">
                                                <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                                <input type="hidden" name="start_treatment" value="1">
                                                <button type="submit" class="btn btn-sm btn-success" title="Start Treatment">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                            </form>
                                        <?php elseif ($apt['status'] === 'in_progress'): ?>
                                            <a href="live-monitor.php" class="btn btn-sm btn-info" style="margin-top: 5px;">
                                                <i class="fas fa-heartbeat"></i> Monitor
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" class="btn btn-sm btn-outline toggle-details-btn" onclick="toggleDetails(<?php echo $apt['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="appointment-details.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary" title="Full Details">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="delete-appointment.php" style="display: inline-block;" onsubmit="return confirm('Move this appointment to recovery bin?');">
                                            <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Expandable Detail Row -->
                                <tr class="detail-row" id="detail-<?php echo $apt['id']; ?>" style="display:none;">
                                    <td colspan="9" style="padding:0; background:#f8f9ff;">
                                        <div class="detail-panel">
                                            <div class="detail-section">
                                                <h4><i class="fas fa-info-circle"></i> Booking Information</h4>
                                                <div class="detail-info-grid">
                                                    <div class="detail-info-item">
                                                        <span class="detail-label">Assigned Staff</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($apt['staff_first'] ? $apt['staff_first'] . ' ' . $apt['staff_last'] : 'Unassigned'); ?></span>
                                                    </div>
                                                    <div class="detail-info-item">
                                                        <span class="detail-label">Pax</span>
                                                        <span class="detail-value"><?php echo (int)$pax; ?> person(s)</span>
                                                    </div>
                                                    <div class="detail-info-item">
                                                        <span class="detail-label">Payment</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($paymentMethodDisplay); ?> (<?php echo htmlspecialchars($paymentStatusDisplay); ?>)</span>
                                                    </div>
                                                    <div class="detail-info-item">
                                                        <span class="detail-label">Booked On</span>
                                                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($apt['created_at'])); ?></span>
                                                    </div>
                                                    <?php if (!empty($apt['notes'])): ?>
                                                    <div class="detail-info-item" style="grid-column: 1 / -1;">
                                                        <span class="detail-label">Notes</span>
                                                        <span class="detail-value"><?php echo nl2br(htmlspecialchars($apt['notes'])); ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (is_array($clientDetails) && !empty($clientDetails)): ?>
                                            <div class="detail-section">
                                                <h4><i class="fas fa-users"></i> Per-Person Service Breakdown</h4>
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
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 20px;
            border: 2px solid #ddd;
            border-radius: 20px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .filter-btn.active,
        .filter-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        .status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Pax Badge */
        .pax-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(212, 165, 116, 0.15);
            color: #8b6914;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        /* Payment */
        .payment-method-badge {
            display: inline-block;
            background: #edf2f7;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .payment-status-pending { color: #d69e2e; font-weight: 600; }
        .payment-status-paid    { color: #38a169; font-weight: 600; }
        .payment-status-failed  { color: #e53e3e; font-weight: 600; }

        /* Detail Row */
        .detail-row td {
            border-bottom: 2px solid var(--primary-color, #d4a574) !important;
        }
        .detail-panel {
            padding: 1.25rem 1.5rem;
        }
        .detail-section {
            margin-bottom: 1.25rem;
        }
        .detail-section:last-child { margin-bottom: 0; }
        .detail-section h4 {
            margin: 0 0 0.75rem 0;
            font-size: 0.95rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-section h4 i { color: var(--primary-color, #d4a574); }
        .detail-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        .detail-info-item { display: flex; flex-direction: column; gap: 2px; }
        .detail-info-item .detail-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
        }
        .detail-info-item .detail-value { font-size: 0.9rem; color: #2d3748; }

        /* Person Breakdown Cards */
        .person-breakdown { display: flex; flex-direction: column; gap: 0.75rem; }
        .person-card { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #fff; }
        .person-card-header {
            background: linear-gradient(135deg, var(--primary-color, #d4a574) 0%, #c48b5c 100%);
            color: #fff;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        .toggle-details-btn { margin-right: 4px; }
    </style>

    <script>
    function toggleDetails(id) {
        const row = document.getElementById('detail-' + id);
        if (!row) return;
        if (row.style.display === 'none' || !row.style.display) {
            row.style.display = 'table-row';
            row.previousElementSibling.style.background = '#faf6f1';
        } else {
            row.style.display = 'none';
            row.previousElementSibling.style.background = '';
        }
    }
    </script>

<?php include '../includes/admin-footer.php'; ?>
