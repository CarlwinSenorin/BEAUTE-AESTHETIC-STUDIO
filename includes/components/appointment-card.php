<?php
/**
 * Appointment Card Component
 * Renders a consistent appointment card display
 * 
 * @param array $appointment Appointment data array
 * @param bool $showActions Whether to show action buttons
 * @param string $context Context ('user' or 'admin')
 */
function renderAppointmentCard($appointment, $showActions = true, $context = 'user') {
    // Ensure ui-helpers is loaded
    if (!function_exists('getStatusBadge')) {
        require_once __DIR__ . '/../helpers/ui-helpers.php';
    }
    
    $isPast = strtotime($appointment['appointment_date']) < strtotime('today');
    $canReschedule = !$isPast && in_array($appointment['status'], ['pending', 'confirmed']);
    $canCancel = !$isPast && in_array($appointment['status'], ['pending', 'confirmed']);
    
    ?>
    <div class="appointment-card <?php echo $isPast ? 'past-appointment' : ''; ?>" data-appointment-id="<?php echo $appointment['id']; ?>">
        <div class="appointment-header">
            <div class="appointment-date-time">
                <div class="appointment-date">
                    <i class="fas fa-calendar"></i>
                    <?php echo formatDate($appointment['appointment_date']); ?>
                </div>
                <div class="appointment-time">
                    <i class="fas fa-clock"></i>
                    <?php echo formatTime($appointment['appointment_time']); ?>
                </div>
            </div>
            <div class="appointment-status">
                <?php echo getStatusBadge($appointment['status'], 'appointment'); ?>
            </div>
        </div>
        
        <div class="appointment-body">
            <?php if (!empty($appointment['service_names'])): ?>
                <div class="appointment-services">
                    <h4><i class="fas fa-spa"></i> Services</h4>
                    <p><?php echo htmlspecialchars($appointment['service_names']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($context === 'admin' && !empty($appointment['client_name'])): ?>
                <div class="appointment-client">
                    <i class="fas fa-user"></i>
                    <strong>Client:</strong> <?php echo htmlspecialchars($appointment['client_name']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['staff_name'])): ?>
                <div class="appointment-staff">
                    <i class="fas fa-user-tie"></i>
                    <strong>Specialist:</strong> <?php echo htmlspecialchars($appointment['staff_name']); ?>
                </div>
            <?php endif; ?>
            
            <div class="appointment-price">
                <i class="fas fa-dollar-sign"></i>
                <strong>Total:</strong> <?php echo formatPrice($appointment['final_price']); ?>
            </div>
            
            <?php if (!empty($appointment['notes'])): ?>
                <div class="appointment-notes">
                    <i class="fas fa-note-sticky"></i>
                    <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($showActions): ?>
            <div class="appointment-actions">
                <?php if ($context === 'user'): ?>
                    <?php if ($canReschedule): ?>
                        <a href="reschedule.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($canCancel): ?>
                        <a href="cancel-appointment.php?id=<?php echo $appointment['id']; ?>" 
                           class="btn btn-sm btn-outline btn-danger"
                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($appointment['status'] === 'completed' && empty($appointment['has_review'])): ?>
                        <a href="review.php?appointment=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-star"></i> Leave Review
                        </a>
                    <?php endif; ?>
                <?php else: // Admin context ?>
                    <a href="appointment-details.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
