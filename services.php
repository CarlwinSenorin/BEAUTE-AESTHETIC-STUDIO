<?php
require_once 'config/functions.php';
$conn = getDBConnection();

$category = isset($_GET['category']) ? sanitize($_GET['category']) : null;
$sql = "SELECT * FROM services WHERE status = 'active'";
$params = [];

if ($category) {
    // Validate category against allowed values
    $allowed_categories = ['nails', 'eyebrows', 'lashes', 'wax', 'massages', 'facial', 'skin_slimming'];
    if (in_array($category, $allowed_categories)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
}

$sql .= " ORDER BY category, name";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Group by category
$services_by_category = [];
foreach ($services as $service) {
    $services_by_category[$service['category']][] = $service;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Our Services</h1>
            <p>Explore our comprehensive range of beauty and wellness treatments</p>
        </div>
    </section>

    <section class="services-page">
        <div class="container">
            <div class="category-filters">
                <a href="services.php" class="filter-btn <?php echo !$category ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> All
                </a>
                <a href="services.php?category=nails" class="filter-btn <?php echo $category === 'nails' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-sparkles"></i> Nails
                </a>
                <a href="services.php?category=eyebrows" class="filter-btn <?php echo $category === 'eyebrows' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i> Eyebrows
                </a>
                <a href="services.php?category=lashes" class="filter-btn <?php echo $category === 'lashes' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i> Lashes
                </a>
                <a href="services.php?category=wax" class="filter-btn <?php echo $category === 'wax' ? 'active' : ''; ?>">
                    <i class="fas fa-fire"></i> Wax
                </a>
                <a href="services.php?category=massages" class="filter-btn <?php echo $category === 'massages' ? 'active' : ''; ?>">
                    <i class="fas fa-spa"></i> Massages
                </a>
                <a href="services.php?category=facial" class="filter-btn <?php echo $category === 'facial' ? 'active' : ''; ?>">
                    <i class="fas fa-smile-beam"></i> Facial
                </a>
                <a href="services.php?category=skin_slimming" class="filter-btn <?php echo $category === 'skin_slimming' ? 'active' : ''; ?>">
                    <i class="fas fa-magic"></i> Skin & Slimming
                </a>
            </div>

            <?php foreach ($services_by_category as $cat => $cat_services): ?>
                <div class="category-section">
                    <h2 class="category-title">
                        <?php 
                        $icons = [
                            'nails' => 'hand-sparkles',
                            'eyebrows' => 'eye',
                            'lashes' => 'eye',
                            'wax' => 'fire',
                            'massages' => 'spa',
                            'facial' => 'smile',
                            'skin_slimming' => 'leaf'
                        ];
                        $icon = $icons[$cat] ?? 'spa';
                        $display_name = [
                            'nails' => 'Nails',
                            'eyebrows' => 'Eyebrows',
                            'lashes' => 'Lashes',
                            'wax' => 'Wax',
                            'massages' => 'Massages',
                            'facial' => 'Facial',
                            'skin_slimming' => 'Skin & Slimming Treatments'
                        ];
                        ?>
                        <i class="fas fa-<?php echo $icon; ?>"></i>
                        <?php echo $display_name[$cat] ?? ucfirst($cat); ?>
                    </h2>
                    <div class="services-grid">
                        <?php foreach ($cat_services as $service): ?>
                            <div class="service-card-detailed">
                                <div class="service-image">
                                    <?php if (!empty($service['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="service-details">
                                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                                    <div class="service-meta">
                                        <span><i class="far fa-clock"></i> <?php echo $service['duration']; ?> minutes</span>
                                        <span class="service-price"><?php echo formatPrice($service['base_price']); ?></span>
                                    </div>
                                    <a href="booking.php?service=<?php echo $service['id']; ?>" class="btn btn-primary">Book Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
