<?php
/**
 * Service Card Component
 * Renders a consistent service card display
 * 
 * @param array $service Service data array with keys: id, name, category, description, duration, base_price
 * @param bool $showBookButton Whether to show the book now button
 * @param string $linkType Type of link ('booking' or 'details')
 */
function renderServiceCard($service, $showBookButton = true, $linkType = 'booking') {
    // Ensure ui-helpers is loaded
    if (!function_exists('getServiceIcon')) {
        require_once __DIR__ . '/../helpers/ui-helpers.php';
    }
    
    $icon = getServiceIcon($service['category']);
    $categoryLabel = ucfirst(str_replace('_', ' & ', $service['category']));
    
    ?>
    <div class="service-card" data-service-id="<?php echo $service['id']; ?>">
        <div class="service-icon">
            <i class="fas fa-<?php echo $icon; ?>"></i>
        </div>
        <div class="service-category"><?php echo htmlspecialchars($categoryLabel); ?></div>
        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
        <p class="service-description">
            <?php echo htmlspecialchars(truncateText($service['description'] ?? '', 100)); ?>
        </p>
        <div class="service-info">
            <span class="service-duration">
                <i class="fas fa-clock"></i> 
                <?php echo $service['duration']; ?> min
            </span>
            <span class="service-price">
                <i class="fas fa-dollar-sign"></i> 
                <?php echo formatPrice($service['base_price']); ?>
            </span>
        </div>
        <?php if ($showBookButton): ?>
            <div class="service-actions">
                <?php if ($linkType === 'booking'): ?>
                    <a href="booking.php?service=<?php echo $service['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-calendar-plus"></i> Book Now
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline btn-sm" onclick="showServiceDetails(<?php echo $service['id']; ?>)">
                        <i class="fas fa-info-circle"></i> Learn More
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
