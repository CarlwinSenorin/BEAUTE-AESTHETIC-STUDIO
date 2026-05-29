<?php
require_once 'config/functions.php';
requireLogin();

$deleted = getDeletedAppointments($_SESSION['user_id'], false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Bin - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="dashboard-section">
        <div class="container">
            <div class="dashboard-header">
                <h1><i class="fas fa-trash-restore"></i> Recovery Bin</h1>
                <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (empty($deleted)): ?>
            <div class="dashboard-card">
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x"></i>
                    <h3>No deleted appointments</h3>
                    <p>Your deleted appointments will appear here and can be restored within 30 days.</p>
                </div>
            </div>
            <?php else: ?>
            
            <div class="dashboard-card">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Deleted appointments are automatically removed after 30 days.
                </div>
                
                <div class="appointments-list">
                    <?php foreach ($deleted as $apt): 
                        $services_data = json_decode($apt['services'], true);
                        $conn = getDBConnection();
                        $placeholders = str_repeat('?,', count($services_data) - 1) . '?';
                        $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
                        $stmt->execute($services_data);
                        $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $days_old = floor((time() - strtotime($apt['deleted_at'])) / 86400);
                    ?>
                    <div class="appointment-item <?php echo $days_old >= 25 ? 'warning-border' : ''; ?>">
                        <div class="appointment-info">
                            <div class="appointment-date">
                                <i class="far fa-calendar"></i>
                                <?php echo formatDate($apt['appointment_date']); ?>
                                <i class="far fa-clock"></i>
                                <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                            </div>
                            <div class="appointment-services">
                                <strong>Services:</strong>
                                <?php echo implode(', ', array_map('htmlspecialchars', $service_names)); ?>
                            </div>
                            <?php if ($apt['staff_name']): ?>
                            <div class="appointment-staff">
                                <i class="fas fa-user-tie"></i>
                                <?php echo htmlspecialchars($apt['staff_name']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="appointment-meta">
                                <span class="badge badge-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                                <span class="text-muted">
                                    Deleted <?php echo $days_old; ?> day(s) ago
                                </span>
                                <?php if ($days_old >= 25): ?>
                                    <span class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Will be purged in <?php echo 30 - $days_old; ?> day(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($apt['deletion_reason']): ?>
                            <div class="appointment-reason">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($apt['deletion_reason']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-actions">
                            <button class="btn btn-success btn-sm" 
                                    onclick="restoreAppointment(<?php echo $apt['id']; ?>)">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    
    <script>
    function restoreAppointment(id) {
        if (!confirm('Restore this appointment?')) {
            return;
        }
        
        fetch('api/appointments/restore.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ appointment_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Appointment restored successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to restore appointment');
            }
        })
        .catch(error => {
            alert('An error occurred');
            console.error(error);
        });
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
    
    .warning-border {
        border-left: 4px solid #ffc107 !important;
    }
    
    .appointment-reason {
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    
    .appointment-meta {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }
    
    .text-danger {
        color: #dc3545;
        font-weight: 600;
    }
    </style>
</body>
</html>
