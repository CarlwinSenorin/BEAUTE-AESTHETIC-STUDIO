<?php
require_once 'config/functions.php';
$conn = getDBConnection();

// Get featured services
$stmt = $conn->query("SELECT * FROM services WHERE status = 'active' LIMIT 6");
$services = $stmt->fetchAll();

// Get active packages
$stmt = $conn->query("SELECT * FROM packages WHERE status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE()) LIMIT 3");
$packages = $stmt->fetchAll();

// Get approved testimonials
$stmt = $conn->query("SELECT t.*, u.first_name, u.last_name FROM testimonials t 
                      JOIN users u ON t.user_id = u.id 
                      WHERE t.status = 'approved' 
                      ORDER BY t.created_at DESC LIMIT 6");
$testimonials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Welcome to Beaute Aesthetic Studio</h1>
            <p>Your destination for premium nails, eyebrows, lashes, wax, massages, facials, and skin & slimming treatments</p>
            <div class="hero-buttons">
                <a href="booking.php" class="btn btn-primary">Book Appointment</a>
                <a href="#services" class="btn btn-secondary">Our Services</a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <p class="section-subtitle">Experience luxury and relaxation with our premium treatments</p>
            <div class="services-grid">

                <a href="booking.php?category=nails" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-hand-sparkles"></i></div>
                        <h3>Nails</h3>
                    </div>
                </a>

                <a href="booking.php?category=eyebrows" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-eye"></i></div>
                        <h3>Eyebrows</h3>
                    </div>
                </a>

                <a href="booking.php?category=lashes" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-star"></i></div>
                        <h3>Lashes</h3>
                    </div>
                </a>

                <a href="booking.php?category=wax" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-fire"></i></div>
                        <h3>Wax</h3>
                    </div>
                </a>

                <a href="booking.php?category=massages" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-spa"></i></div>
                        <h3>Massages</h3>
                    </div>
                </a>

                <a href="booking.php?category=facial" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-smile-beam"></i></div>
                        <h3>Facial</h3>
                    </div>
                </a>

                <a href="booking.php?category=skin_slimming" class="service-card-link">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-leaf"></i></div>
                        <h3>Skin &amp; Slimming</h3>
                    </div>
                </a>

            </div>
            <div class="text-center">
                <a href="services.php" class="btn btn-outline">View All Services</a>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="packages" class="packages-section">
        <div class="container">
            <h2 class="section-title">Special Packages & Offers</h2>
            <p class="section-subtitle">Save more with our exclusive bundles</p>
            <div class="packages-grid">
                <?php foreach ($packages as $package): 
                    $package_services = json_decode($package['services'], true);
                    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN (" . implode(',', array_fill(0, count($package_services), '?')) . ")");
                    $stmt->execute($package_services);
                    $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <div class="package-card">
                    <div class="package-badge"><?php echo number_format($package['discount_percentage'], 0); ?>% OFF</div>
                    <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                    <p><?php echo htmlspecialchars($package['description']); ?></p>
                    <ul class="package-services">
                        <?php foreach ($service_names as $name): ?>
                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="package-price">
                        <span class="old-price"><?php echo formatPrice($package['original_price']); ?></span>
                        <span class="new-price"><?php echo formatPrice($package['discounted_price']); ?></span>
                    </div>
                    <a href="booking.php?package=<?php echo $package['id']; ?>" class="btn btn-primary">Book Now</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About Beaute Aesthetic Studio</h2>
                    <p>At Beaute Aesthetic Studio, we believe in the power of self-care and the beauty of feeling your best. Our team of skilled professionals is dedicated to providing you with an exceptional experience that combines artistry, relaxation, and wellness.</p>
                    <p>With years of experience in nails, eyebrows, lashes, waxing, massages, facials, and skin & slimming treatments, we've created a sanctuary where you can escape the everyday and indulge in premium beauty and wellness services.</p>
                    <div class="about-features">
                        <div class="feature">
                            <i class="fas fa-certificate"></i>
                            <h4>Certified Professionals</h4>
                            <p>All our specialists are certified and continuously trained</p>
                        </div>
                        <div class="feature">
                            <i class="fas fa-gem"></i>
                            <h4>Premium Products</h4>
                            <p>We use only the finest quality products and tools</p>
                        </div>
                        <div class="feature">
                            <i class="fas fa-heart"></i>
                            <h4>Personalized Care</h4>
                            <p>Every service is tailored to your unique needs</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials-section">
        <div class="container">
            <h2 class="section-title">What Our Clients Say</h2>
            <p class="section-subtitle">Real experiences from our valued customers</p>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? 'active' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['review_text']); ?>"</p>
                    <div class="testimonial-author">
                        <strong><?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?></strong>
                        <span><?php echo formatDate($testimonial['created_at']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Address</h4>
                            <p>Casa Erin
Building, Cabagnan, Legazpi City</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Phone</h4>
                            <p>09171086478</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>info@beauteaesthetic.com</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>Hours</h4>
                            <p>Monday - Saturday: 9:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 4:00 PM</p>
                        </div>
                    </div>
                </div>
                <form class="contact-form" method="POST" action="api/contact.php">
                    <h3>Send us a Message</h3>
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Your Phone">
                    </div>
                    <div class="form-group">
                        <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
