<?php
require_once '../config/functions.php';
requireAdmin();

$logFile = __DIR__ . '/../notifications.log';
$logs = [];

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $logs = json_decode($content, true) ?: [];
}

// Handle clearing logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    file_put_contents($logFile, json_encode([]));
    header('Location: notification-logs.php?msg=cleared');
    exit;
}

$msg = $_GET['msg'] ?? '';
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-history"></i> Notification Logs</h1>
                <div class="header-actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all notification logs?')">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Clear Logs
                        </button>
                    </form>
                    <a href="index.php" class="btn btn-sm btn-outline">Back to Dashboard</a>
                </div>
            </div>

            <?php if ($msg === 'cleared'): ?>
                <div class="alert alert-success">Logs cleared successfully.</div>
            <?php endif; ?>

            <div class="admin-card">
                <p class="form-description">These logs record all Email and SMS notifications (placeholders) triggered by the system or sent manually. This is for verification purposes.</p>
                
                <?php if (empty($logs)): ?>
                    <p class="empty-state">No notification activity recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Appt ID</th>
                                    <th>Type</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Message Preview</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><small><?php echo htmlspecialchars($log['timestamp']); ?></small></td>
                                        <td><a href="appointment-details.php?id=<?php echo $log['appointment_id']; ?>">#<?php echo $log['appointment_id']; ?></a></td>
                                        <td>
                                            <span class="status-badge" style="background: <?php echo $log['type'] === 'email' ? '#17a2b8' : '#6f42c1'; ?>; color: white;">
                                                <?php echo strtoupper($log['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $log['status']; ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="message-preview" title="<?php echo htmlspecialchars($log['message']); ?>">
                                                <?php echo htmlspecialchars(substr($log['message'], 0, 50)) . (strlen($log['message']) > 50 ? '...' : ''); ?>
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
.message-preview {
    font-size: 0.85rem;
    color: #666;
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
</style>
<?php include '../includes/admin-footer.php'; ?>
