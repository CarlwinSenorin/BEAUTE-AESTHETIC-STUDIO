<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$category_filter = $_GET['category'] ?? '';

// Revenue Statistics
$revenue_query = "SELECT 
    SUM(final_price) as total_revenue,
    COUNT(*) as total_appointments,
    AVG(final_price) as avg_price
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'";
$revenue_params = [$start_date, $end_date];

if ($category_filter) {
    $revenue_query .= " AND JSON_CONTAINS(services, CAST((SELECT id FROM services WHERE category = ? LIMIT 1) AS CHAR), '$')";
    // Note: The JSON_CONTAINS approach above is a bit simplified. 
    // A better way is to filter the appointments first and then check if any service in that appointment matches the category.
}

$stmt = $conn->prepare($revenue_query);
$stmt->execute($revenue_params);
$revenue_stats = $stmt->fetch();

// Appointments by Status
$status_query = "SELECT status, COUNT(*) as count 
                        FROM appointments 
                        WHERE appointment_date BETWEEN ? AND ?
                        GROUP BY status";
$stmt = $conn->prepare($status_query);
$stmt->execute([$start_date, $end_date]);
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Top Services - Get completed appointments
$appt_query = "SELECT services, final_price 
                        FROM appointments 
                        WHERE appointment_date BETWEEN ? AND ? 
                        AND status = 'completed'";
$stmt = $conn->prepare($appt_query);
$stmt->execute([$start_date, $end_date]);
$completed_appointments = $stmt->fetchAll();

// Process services in PHP
$service_stats = [];
foreach ($completed_appointments as $apt) {
    $service_ids = json_decode($apt['services'], true);
    if (!empty($service_ids)) {
        foreach ($service_ids as $service_id) {
            if (!isset($service_stats[$service_id])) {
                $service_stats[$service_id] = [
                    'booking_count' => 0,
                    'revenue' => 0
                ];
            }
            $service_stats[$service_id]['booking_count']++;
            $service_stats[$service_id]['revenue'] += $apt['final_price'] / count($service_ids); // Divide revenue by number of services
        }
    }
}

// Get service names and combine with stats
$top_services = [];
if (!empty($service_stats)) {
    $service_ids = array_keys($service_stats);
    $placeholders = str_repeat('?,', count($service_ids) - 1) . '?';
    
    $service_query = "SELECT id, name, category FROM services WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($service_query);
    $stmt->execute($service_ids);
    $services_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $services = [];
    $service_categories = [];
    foreach ($services_data as $row) {
        $services[$row['id']] = $row['name'];
        $service_categories[$row['id']] = $row['category'];
    }
    
    foreach ($service_stats as $service_id => $stats) {
        // Apply category filter if set
        if ($category_filter && ($service_categories[$service_id] ?? '') !== $category_filter) {
            continue;
        }
        
        $top_services[] = [
            'name' => $services[$service_id] ?? 'Unknown Service',
            'booking_count' => $stats['booking_count'],
            'revenue' => $stats['revenue']
        ];
    }
    
    // Sort by booking count
    usort($top_services, function($a, $b) {
        return $b['booking_count'] - $a['booking_count'];
    });
    
    // If category filter is active, override revenue_stats with sums from filtered top_services
    if ($category_filter) {
        $filtered_revenue = 0;
        $filtered_bookings = 0;
        foreach ($top_services as $s) {
            $filtered_revenue += $s['revenue'];
            $filtered_bookings += $s['booking_count'];
        }
        $revenue_stats['total_revenue'] = $filtered_revenue;
        $revenue_stats['total_appointments'] = $filtered_bookings;
        $revenue_stats['avg_price'] = $filtered_bookings > 0 ? $filtered_revenue / $filtered_bookings : 0;
    }

    // Limit to top 10
    $top_services = array_slice($top_services, 0, 10);
}

// Daily Revenue
$stmt = $conn->prepare("SELECT appointment_date, SUM(final_price) as daily_revenue, COUNT(*) as count
                        FROM appointments
                        WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'
                        GROUP BY appointment_date
                        ORDER BY appointment_date");
$stmt->execute([$start_date, $end_date]);
$daily_revenue = $stmt->fetchAll();
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-chart-bar"></i> Analytics & Reports</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <!-- Date Range Filter -->
            <div class="admin-card">
                <h2>Filters</h2>
                <form method="GET" class="date-range-form" id="filterForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quick Ranges</label>
                            <div class="quick-filters">
                                <button type="button" class="btn btn-sm btn-outline" onclick="setRange('today')">Today</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="setRange('week')">This Week</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="setRange('month')">This Month</button>
                                <button type="button" class="btn btn-sm btn-outline" onclick="setRange('last_month')">Last Month</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php
                                $categories = $conn->query("SELECT DISTINCT category FROM services")->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($categories as $cat) {
                                    $selected = ($category_filter === $cat) ? 'selected' : '';
                                    echo "<option value=\"$cat\" $selected>" . ucfirst($cat) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Revenue Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #28a745;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($revenue_stats['total_revenue'] ?? 0); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #17a2b8;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($revenue_stats['total_appointments'] ?? 0); ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ffc107;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($revenue_stats['avg_price'] ?? 0); ?></h3>
                        <p>Average Price</p>
                    </div>
                </div>
            </div>

            <!-- Appointments by Status -->
            <div class="admin-card">
                <h2>Appointments by Status</h2>
                <div class="status-chart">
                    <?php 
                    $total = array_sum($status_counts);
                    foreach ($status_counts as $status => $count): 
                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                    ?>
                        <div class="status-bar-item">
                            <div class="status-label">
                                <span><?php echo ucfirst($status); ?></span>
                                <span><?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-bar-fill" style="width: <?php echo $percentage; ?>%; background: <?php 
                                    echo $status === 'completed' ? '#28a745' : 
                                        ($status === 'confirmed' ? '#17a2b8' : 
                                        ($status === 'pending' ? '#ffc107' : '#dc3545')); 
                                ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Services -->
            <div class="admin-card">
                <h2>Top Services</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Bookings</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_services as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                    <td><?php echo number_format($service['booking_count']); ?></td>
                                    <td><?php echo formatPrice($service['revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daily Revenue -->
            <div class="admin-card">
                <h2>Daily Revenue</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Appointments</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_revenue as $day): ?>
                                <tr>
                                    <td><?php echo formatDate($day['appointment_date']); ?></td>
                                    <td><?php echo number_format($day['count']); ?></td>
                                    <td><?php echo formatPrice($day['daily_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        function setRange(range) {
            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');
            const now = new Date();
            let start, end;

            switch(range) {
                case 'today':
                    start = end = now;
                    break;
                case 'week':
                    start = new Date(now.setDate(now.getDate() - now.getDay()));
                    end = new Date();
                    break;
                case 'month':
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'last_month':
                    start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    end = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;
            }

            if (start && end) {
                startInput.value = start.toISOString().split('T')[0];
                endInput.value = end.toISOString().split('T')[0];
                document.getElementById('filterForm').submit();
            }
        }
    </script>
<?php include '../includes/admin-footer.php'; ?>
