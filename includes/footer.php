<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-spa"></i> Beaute Aesthetic Studio</h3>
                <p>Your premier destination for beauty and wellness services. Experience luxury, relaxation, and transformation.</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/HoneysBeautyLounge.ph"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="<?php echo $current_page === 'index.php' ? '#packages' : 'index.php#packages'; ?>">Packages</a></li>
                    <li><a href="<?php echo $current_page === 'index.php' ? '#about' : 'index.php#about'; ?>">About Us</a></li>
                    <li><a href="<?php echo $current_page === 'index.php' ? '#testimonials' : 'index.php#testimonials'; ?>">Testimonials</a></li>
                    <li><a href="<?php echo $current_page === 'index.php' ? '#contact' : 'index.php#contact'; ?>">Contact</a></li>
                    <?php if (!isLoggedIn()): ?>
                        <li><a href="login.php" class="btn btn-primary btn-sm" style="display: inline-block; margin-top: 0.5rem;">Login / Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Info</h4>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i>Casa Erin
Building, Cabagnan, Legazpi City</li>
                    <li><i class="fas fa-phone"></i> 09171086478</li>
                    <li><i class="fas fa-envelope"></i> info@beauteaesthetic.com</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Beaute Aesthetic Studio. All rights reserved.</p>
        </div>
    </div>
    </div>
</footer>

<!-- Best Seller Suggestion Popup -->
<div id="suggestion-overlay" class="suggestion-overlay" style="display: none;">
    <div id="suggestion-popup" class="suggestion-popup">
        <button class="close-suggestion" onclick="closeSuggestion()">&times;</button>
        <div class="suggestion-content">
            <div class="suggestion-header">
                <span class="badge">🔥 Best Seller</span>
            </div>
            <div class="suggestion-body">
                <div class="suggestion-img">
                    <img id="suggestion-image" src="" alt="">
                </div>
                <div class="suggestion-info">
                    <h3 id="suggestion-name"></h3>
                    <p id="suggestion-desc"></p>
                    <div class="suggestion-footer">
                        <span class="price" id="suggestion-price"></span>
                        <a id="suggestion-link" href="#" class="btn btn-suggestion">Book Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.suggestion-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
    animation: fadeIn 0.3s ease-out;
}

.suggestion-popup {
    width: 90%;
    max-width: 500px;
    background: var(--white, #ffffff);
    color: var(--text-color, #333);
    border-radius: 20px;
    box-shadow: 0 15px 40px rgba(212, 165, 116, 0.2);
    overflow: hidden;
    position: relative;
    padding: 35px;
    border: 1px solid var(--accent-color, #f5e6d3);
    animation: zoomIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes zoomIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.close-suggestion {
    position: absolute;
    top: 15px;
    right: 20px;
    border: none;
    background: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s, transform 0.2s;
}

.close-suggestion:hover {
    color: var(--primary-color, #d4a574);
    transform: rotate(90deg);
}

.suggestion-header .badge {
    background: var(--primary-color, #d4a574);
    color: white;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: inline-block;
    margin-bottom: 20px;
    box-shadow: 0 4px 10px rgba(212, 165, 116, 0.3);
}

.suggestion-body {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

@media (min-width: 768px) {
    .suggestion-body {
        flex-direction: row;
        align-items: center;
    }
}

.suggestion-img {
    width: 100%;
    aspect-ratio: 1/1;
    max-width: 160px;
    flex-shrink: 0;
    margin: 0 auto;
}

.suggestion-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 15px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.suggestion-info {
    flex-grow: 1;
}

.suggestion-info h3 {
    margin: 0 0 10px 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--dark-color, #2c2c2c);
}

.suggestion-info p {
    margin: 0 0 20px 0;
    font-size: 14px;
    color: #666;
    line-height: 1.6;
}

.suggestion-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

.suggestion-footer .price {
    font-weight: 700;
    color: var(--primary-color, #d4a574);
    font-size: 22px;
}

.btn-suggestion {
    background: var(--primary-color, #d4a574);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 30px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    box-shadow: 0 5px 15px rgba(212, 165, 116, 0.3);
}

.btn-suggestion:hover {
    background: var(--secondary-color, #8b6f47);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(212, 165, 116, 0.4);
    color: white;
}
</style>

<script>
function closeSuggestion() {
    document.getElementById('suggestion-overlay').style.display = 'none';
    sessionStorage.setItem('suggestion_closed', 'true');
}

document.addEventListener('DOMContentLoaded', function() {
    if (sessionStorage.getItem('suggestion_closed')) return;

    fetch('api/get-best-seller.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const overlay = document.getElementById('suggestion-overlay');
                document.getElementById('suggestion-name').textContent = data.data.name;
                document.getElementById('suggestion-desc').textContent = data.data.description;
                document.getElementById('suggestion-price').textContent = data.data.price;
                document.getElementById('suggestion-image').src = data.data.image_url || 'assets/img/placeholder.jpg';
                document.getElementById('suggestion-link').href = 'booking.php?service=' + data.data.id;
                
                // Show after 3 seconds
                setTimeout(() => {
                    overlay.style.display = 'flex';
                }, 3000);
            }
        })
        .catch(err => console.error('Error fetching best seller:', err));
});

// Close when clicking overlay
document.getElementById('suggestion-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSuggestion();
    }
});
</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/chatbot.css?v=<?php echo time(); ?>">
<script>window.BEAUTEBOT_LOGGED_IN = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
window.BEAUTEBOT_PENDING_BOOKING = <?php echo (isLoggedIn() && isset($_SESSION['chat_state']['step']) && $_SESSION['chat_state']['step'] === 'CONFIRM') ? 'true' : 'false'; ?>;</script>
<script src="<?php echo BASE_URL; ?>assets/js/chatbot.js?v=<?php echo time(); ?>"></script>
