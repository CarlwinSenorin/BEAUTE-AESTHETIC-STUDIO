<?php
/**
 * Package Card Component
 * Renders a consistent package card display
 * 
 * @param array $package Package data array
 * @param array $services Array of service details included in package
 * @param bool $showBookButton Whether to show the book now button
 */
function renderPackageCard($package, $services = [], $showBookButton = true) {
    $discountPercent = round($package['discount_percentage'] ?? 0);
    $savings = $package['original_price'] - $package['discounted_price'];
    
    ?>
    <div class="package-card" data-package-id="<?php echo $package['id']; ?>">
        <?php if ($discountPercent > 0): ?>
            <div class="package-badge">
                Save <?php echo $discountPercent; ?>%
            </div>
        <?php endif; ?>
        
        <h3><?php echo htmlspecialchars($package['name']); ?></h3>
        
        <?php if (!empty($package['description'])): ?>
            <p class="package-description">
                <?php echo htmlspecialchars($package['description']); ?>
            </p>
        <?php endif; ?>
        
        <?php $pkg_pax = $package['pax'] ?? 1; ?>
        <?php if ($pkg_pax > 1): ?>
            <div class="package-pax-info" style="margin: 0.5rem 0; color: #2a9d8f; font-weight: 600;">
                <i class="fas fa-users"></i> <?php echo $pkg_pax; ?> Persons
            </div>
        <?php endif; ?>
        
        <?php if (!empty($services)): ?>
            <div class="package-services">
                <h4><i class="fas fa-list-check"></i> Includes:</h4>
                <ul>
                    <?php foreach ($services as $service): ?>
                        <li>
                            <i class="fas fa-check"></i>
                            <?php echo htmlspecialchars($service['name']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="package-price">
            <?php if ($package['original_price'] > $package['discounted_price']): ?>
                <span class="old-price"><?php echo formatPrice($package['original_price']); ?></span>
            <?php endif; ?>
            <span class="new-price"><?php echo formatPrice($package['discounted_price']); ?></span>
        </div>
        
        <?php if ($savings > 0): ?>
            <div class="package-savings">
                <i class="fas fa-tag"></i> You save <?php echo formatPrice($savings); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showBookButton): ?>
            <a href="booking.php?package=<?php echo $package['id']; ?>" class="btn btn-primary">
                <i class="fas fa-calendar-check"></i> Book Package
            </a>
        <?php endif; ?>
    </div>
    <?php
}
?>
