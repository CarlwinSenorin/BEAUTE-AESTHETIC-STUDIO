<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Get customer segments
$segments = getCustomerSegments();

$segment_colors = [
    'VIP'     => ['bg' => '#ffd700', 'text' => '#333', 'icon' => 'fa-crown'],
    'Loyal'   => ['bg' => '#6f42c1', 'text' => '#fff', 'icon' => 'fa-heart'],
    'Regular' => ['bg' => '#4caf50', 'text' => '#fff', 'icon' => 'fa-user-check'],
    'At-Risk' => ['bg' => '#ff9800', 'text' => '#fff', 'icon' => 'fa-exclamation-triangle'],
    'New'     => ['bg' => '#2196f3', 'text' => '#fff', 'icon' => 'fa-user-plus'],
    'Dormant' => ['bg' => '#9e9e9e', 'text' => '#fff', 'icon' => 'fa-user-clock'],
];
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-layer-group"></i> Customer Segments</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <p style="color: #aaa; margin-bottom: 20px;">
                <i class="fas fa-robot"></i> AI-driven RFM analysis (Recency, Frequency, Monetary) automatically classifies your clients into actionable segments.
            </p>

            <!-- Segment Summary Cards -->
            <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <?php foreach ($segments as $name => $clients): ?>
                    <?php 
                        $color = $segment_colors[$name] ?? ['bg' => '#eee', 'text' => '#000', 'icon' => 'fa-user']; 
                    ?>
                    <div class="admin-card" style="text-align: center; padding: 20px; border-left: 4px solid <?php echo $color['bg']; ?>;">
                        <div style="font-size: 2rem; margin-bottom: 5px;">
                            <i class="fas <?php echo $color['icon']; ?>" style="color: <?php echo $color['bg']; ?>;"></i>
                        </div>
                        <div style="font-size: 2rem; font-weight: 700;"><?php echo count($clients); ?></div>
                        <div style="color: #888; font-size: 0.9rem; font-weight: 500;"><?php echo $name; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Segment Criteria Legend -->
            <div class="admin-card" style="margin-bottom: 25px;">
                <h3 style="margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Segmentation Criteria</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
                    <div><span style="display:inline-block; width:12px; height:12px; background:#ffd700; border-radius:50%; margin-right:8px;"></span><strong>VIP</strong> — 5+ bookings, ₱1000+ spend, active in last 30 days</div>
                    <div><span style="display:inline-block; width:12px; height:12px; background:#6f42c1; border-radius:50%; margin-right:8px;"></span><strong>Loyal</strong> — 3+ bookings, active in last 45 days</div>
                    <div><span style="display:inline-block; width:12px; height:12px; background:#2196f3; border-radius:50%; margin-right:8px;"></span><strong>New</strong> — Registered within 30 days, &lt;2 bookings</div>
                    <div><span style="display:inline-block; width:12px; height:12px; background:#4caf50; border-radius:50%; margin-right:8px;"></span><strong>Regular</strong> — Moderate activity, doesn't fit other segments</div>
                    <div><span style="display:inline-block; width:12px; height:12px; background:#ff9800; border-radius:50%; margin-right:8px;"></span><strong>At-Risk</strong> — No bookings in 60–90 days</div>
                    <div><span style="display:inline-block; width:12px; height:12px; background:#9e9e9e; border-radius:50%; margin-right:8px;"></span><strong>Dormant</strong> — No bookings in 90+ days</div>
                </div>
            </div>

            <!-- Client Tables by Segment -->
            <?php foreach ($segments as $name => $clients): ?>
                <?php 
                    $color = $segment_colors[$name] ?? ['bg' => '#eee', 'text' => '#000', 'icon' => 'fa-user']; 
                ?>
                <div class="admin-card" style="margin-bottom: 20px;">
                    <h2 style="margin-bottom: 15px;">
                        <i class="fas <?php echo $color['icon']; ?>" style="color: <?php echo $color['bg']; ?>; margin-right: 8px;"></i>
                        <?php echo $name; ?> Clients 
                        <span style="font-size: 0.85rem; font-weight: 400; color: #888;">(<?php echo count($clients); ?>)</span>
                    </h2>
                    <?php if (empty($clients)): ?>
                        <p style="color: #888; text-align: center; padding: 20px;">No clients in this segment.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Bookings</th>
                                        <th>Total Spend</th>
                                        <th>Last Visit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                            <td>
                                                <span style="font-weight: 600;"><?php echo $client['booking_count']; ?></span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--primary-color, #e91e63);">
                                                    <?php echo formatPrice($client['total_spend']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($client['last_visit']): ?>
                                                    <?php echo formatDate($client['last_visit']); ?>
                                                    <br><small style="color: #999;"><?php echo $client['days_since_last_visit']; ?> days ago</small>
                                                <?php else: ?>
                                                    <span style="color: #999;">Never</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
<?php include '../includes/admin-footer.php'; ?>
