<?php
require_once '../config/functions.php';
requireAdmin();

$deleted = getDeletedAppointments(null, true);
$purge_count = purgeOldDeletedAppointments(); // Auto-purge old items
?>
<?php include '../includes/admin-header.php'; ?>

<div class="admin-content">
    <div class="admin-header">
        <h1><i class="fas fa-trash-restore"></i> Recovery Bin</h1>
        <div>
            <?php if ($purge_count > 0): ?>
                <small class="text-muted">Auto-purged <?php echo $purge_count; ?> old appointment(s)</small>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
        </div>
    </div>

    <?php if (empty($deleted)): ?>
    <div class="admin-card">
        <div class="empty-state">
            <i class="fas fa-inbox fa-3x"></i>
            <h3>Recovery bin is empty</h3>
            <p>Deleted appointments will appear here and be automatically purged after 30 days.</p>
        </div>
    </div>
    <?php else: ?>
    
    <div class="admin-card">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Appointments are automatically deleted after 30 days in the recovery bin.
        </div>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Date & Time</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th>Deleted By</th>
                        <th>Deleted At</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deleted as $apt): 
                        $days_old = floor((time() - strtotime($apt['deleted_at'])) / 86400);
                    ?>
                    <tr class="<?php echo $days_old >= 25 ? 'warning-row' : ''; ?>">
                        <td>#<?php echo $apt['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?>
                            <br><small><?php echo htmlspecialchars($apt['email']); ?></small>
                        </td>
                        <td>
                            <?php echo formatDate($apt['appointment_date']); ?><br>
                            <small><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not assigned'); ?></td>
                        <td><span class="badge badge-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($apt['deleted_by_name'] ?? 'System'); ?></td>
                        <td>
                            <?php echo date('M d, Y g:i A', strtotime($apt['deleted_at'])); ?>
                            <br><small class="text-muted"><?php echo $days_old; ?> days ago</small>
                            <?php if ($days_old >= 25): ?>
                                <br><small class="text-danger">Purging in <?php echo 30 - $days_old; ?> days</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($apt['deletion_reason'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" 
                                    onclick="restoreAppointment(<?php echo $apt['id']; ?>)"
                                    title="Restore appointment">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="permanentDelete(<?php echo $apt['id']; ?>)"
                                    title="Delete permanently">
                                <i class="fas fa-trash"></i> Delete Forever
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/admin-footer.php'; ?>

<script>
function restoreAppointment(id) {
    if (!confirm('Are you sure you want to restore this appointment?')) {
        return;
    }
    
    fetch('../api/appointments/restore.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ appointment_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Appointment restored successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to restore appointment', 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred', 'error');
        console.error(error);
    });
}

function permanentDelete(id) {
    if (!confirm('⚠️ WARNING: This will PERMANENTLY delete this appointment. This action cannot be undone. Are you sure?')) {
        return;
    }
    
    fetch('../api/appointments/permanent-delete.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ appointment_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Appointment permanently deleted', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to delete appointment', 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred', 'error');
        console.error(error);
    });
}

function showToast(message, type) {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 25px;background:#fff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;';
    
    if (type === 'success') toast.style.borderLeft = '4px solid #28a745';
    if (type === 'error') toast.style.borderLeft = '4px solid #dc3545';
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<style>
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.empty-state i {
    margin-bottom: 1rem;
    color: #ddd;
}

.warning-row {
    background-color: #fff3cd;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background-color: #d4edda;
    color: #155724;
}

.badge-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.badge-warning {
    background-color: #fff3cd;
    color: #856404;
}

.badge-pending,
.badge-confirmed {
    background-color: #d1ecf1;
    color: #0c5460;
}

.text-muted {
    color: #6c757d;
    font-size: 0.875rem;
}

.text-danger {
    color: #dc3545;
    font-weight: 600;
}
</style>
