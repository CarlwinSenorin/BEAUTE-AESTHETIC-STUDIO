<?php
require_once 'config/functions.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
removeTemporarySelection(null, null, null, session_id());

// Get all services
$stmt = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY category, name");
$all_services = $stmt->fetchAll();

// Get staff members
$stmt = $conn->query("SELECT s.*, u.first_name, u.last_name FROM staff s JOIN users u ON s.user_id = u.id WHERE u.status = 'active'");
$staff_members = $stmt->fetchAll();

// Get active packages with duration (sum of service durations)
$stmt = $conn->query("SELECT * FROM packages WHERE status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE())");
$packages = $stmt->fetchAll();
foreach ($packages as &$pkg) {
    $svc_ids = json_decode($pkg['services'], true);
    $pkg['duration'] = 60;
    $pkg['service_details'] = [];
    if (!empty($svc_ids)) {
        $ph = implode(',', array_fill(0, count($svc_ids), '?'));
        $st = $conn->prepare("SELECT id, name, duration, category, base_price as price FROM services WHERE id IN ($ph)");
        $st->execute($svc_ids);
        $pkg['service_details'] = $st->fetchAll(PDO::FETCH_ASSOC);
        $pkg['duration'] = array_sum(array_column($pkg['service_details'], 'duration')) ?: 60;
    }
}
unset($pkg);

// Pre-select service or package if provided
$preselected_service = $_GET['service'] ?? null;
$preselected_package = $_GET['package'] ?? null;
$preselected_date = $_GET['date'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/booking.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Book Your Appointment</h1>
            <p>Select your services and preferred time slot</p>
        </div>
    </section>

    <section class="booking-section">
        <div class="container">
            <form id="bookingForm" class="booking-form">
                <!-- Step 0: Pax Selection -->
                <div class="booking-step active" id="step0">
                    <h2><i class="fas fa-users"></i> Number of Persons</h2>
                    <p class="step-description">How many people will be coming for this appointment?</p>
                    <div class="pax-selector">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <label class="pax-option">
                                <input type="radio" name="pax_radio" value="<?php echo $i; ?>" <?php echo $i == 1 ? 'checked' : ''; ?>>
                                <div class="pax-card">
                                    <span class="pax-number"><?php echo $i; ?></span>
                                    <span class="pax-label"><?php echo $i == 1 ? 'Person' : 'Persons'; ?></span>
                                </div>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="btn btn-primary btn-next" onclick="handleNextAction()">Next: Select Date</button>
                </div>

                <!-- Step 1: Global Date Selection -->
                <div class="booking-step" id="step1">
                    <h2><i class="fas fa-calendar-day"></i> Select Appointment Date</h2>
                    <p class="step-description">Choose the date for your visit</p>
                    <div class="date-selection-container">
                        <div id="globalDatePickerContainer">
                            <input type="text" id="globalDateInput" name="global_date" placeholder="Select Date" readonly class="form-control">
                            <div id="globalDatePickerInline"></div>
                        </div>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="handlePrevAction()">Back</button>
                        <button type="button" class="btn btn-primary btn-next" onclick="handleNextAction()">Next: Select Services</button>
                    </div>
                </div>

                <!-- Step 2: Service Selection -->
                <div class="booking-step" id="step2">
                    <h2 id="step2Heading"><i class="fas fa-spa"></i> Select Services</h2>
                    
                    <p class="step-description">Choose one or multiple services for your appointment</p>

                    <div class="packages-option" id="packagesOption">
                        <h3>Available Packages</h3>
                        <div class="packages-mini-grid">
                            <?php foreach ($packages as $package): ?>
                                <div class="package-mini-card <?php echo $preselected_package == $package['id'] ? 'selected' : ''; ?>" 
                                     data-package-id="<?php echo $package['id']; ?>" 
                                     data-duration="<?php echo $package['duration']; ?>"
                                     data-price="<?php echo $package['discounted_price']; ?>"
                                     data-pax="<?php echo $package['pax'] ?? 1; ?>"
                                     data-services='<?php echo htmlspecialchars(json_encode($package['service_details']), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <h4><?php echo htmlspecialchars($package['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($package['description']); ?></p>
                                    <div class="package-price-mini">
                                        <span class="old-price"><?php echo formatPrice($package['original_price']); ?></span>
                                        <span class="new-price"><?php echo formatPrice($package['discounted_price']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h3>Services</h3>
                    <div class="services-selection-grouped">
                        <?php 
                        $current_category = null;
                        foreach ($all_services as $service): 
                            if ($current_category !== $service['category']):
                                if ($current_category !== null) echo '</div>';
                                $current_category = $service['category'];
                                $display_category = str_replace('_', ' & ', $current_category);
                                if ($display_category === 'skin & slimming') $display_category = 'Skin & Slimming';
                                echo '<div class="service-category-group-header"><h4>' . ucfirst($display_category) . '</h4></div>';
                                echo '<div class="service-category-items">';
                            endif;
                        ?>
                            <label class="service-checkbox" data-service-id="<?php echo $service['id']; ?>">
                                <input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                       data-duration="<?php echo $service['duration']; ?>"
                                       data-price="<?php echo $service['base_price']; ?>"
                                       data-category="<?php echo strtolower(htmlspecialchars($service['category'])); ?>"
                                       data-name="<?php echo htmlspecialchars($service['name']); ?>"
                                       <?php echo $preselected_service == $service['id'] ? 'checked' : ''; ?>>
                                <div class="service-checkbox-content">
                                    <span class="service-name"><?php echo htmlspecialchars($service['name']); ?></span>
                                    <span class="service-duration"><?php echo $service['duration']; ?> min</span>
                                    <span class="service-price"><?php echo formatPrice($service['base_price']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($current_category !== null) echo '</div>'; ?>
                    </div>

                    <div class="selected-services-summary" id="selectedServicesSummary" style="display: none;">
                        <h4>Selected:</h4>
                        <div id="selectedServicesList"></div>
                        <div class="total-duration">
                            <strong>Total Duration: <span id="totalDuration">0</span> minutes</strong>
                        </div>
                        <div class="total-price-display">
                            <strong>Total Price: <span id="totalPrice">&#8369;0.00</span></strong>
                        </div>
                    </div>

                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="handlePrevAction()">Back</button>
                        <button type="button" class="btn btn-primary btn-next" onclick="handleNextAction()">Next: Assign Staff & Time</button>
                    </div>

                </div>

                <!-- Step 3: Staff and Date & Time Assignment -->
                <div class="booking-step" id="step3">
                    <h2 id="step3Heading"><i class="fas fa-calendar-check"></i> Assign Staff & Time</h2>
                    <p class="step-description">Select your preferred specialist and time for each service</p>
                    
                    <div id="serviceAssignmentsContainer" class="service-assignments-container">
                        <!-- Dynamic assignment cards will be inserted here by JavaScript -->
                    </div>

                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="handlePrevAction()">Back</button>
                        <button type="button" class="btn btn-primary btn-next" id="btnNextAction" onclick="handleNextAction()">Next: Review & Confirm</button>
                    </div>
                </div>

                <!-- Step 3: Global Review (Step 3 is skipped or used for final checks if needed, but we keep the ID sequence if possible or just skip to 4) -->
                <!-- We'll remove original step 3 and move to step 4 -->

                <!-- Step 4: Review & Payment -->
                <div class="booking-step" id="step4">
                    <h2><i class="fas fa-check-circle"></i> Review Your Appointment</h2>
                    <p class="step-description">Please double-check your appointment details before confirming.</p>
                    
                    <div class="booking-summary">
                        <div class="summary-services-wrapper">
                            <h3><i class="fas fa-spa"></i> Selected Services</h3>
                            <div id="summaryServices"></div>
                        </div>
                        
                        <div class="summary-details-pricing-wrapper">
                            <div class="summary-info-section">
                                <h3><i class="fas fa-info-circle"></i> Appointment Info</h3>
                                <div id="summaryAppointment"></div>
                            </div>

                            <div class="summary-pricing-section">
                                <h3><i class="fas fa-receipt"></i> Pricing Summary</h3>
                                <div id="summaryPricing"></div>
                            </div>
                        </div>

                        <div class="summary-extra-section">
                            <div class="form-group">
                                <label><i class="fas fa-credit-card"></i> Payment Method</label>
                                <select name="payment_method">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-comment-alt"></i> Special Requests (Optional)</label>
                                <textarea name="notes" rows="3" placeholder="Any special requests or notes for your appointment..."></textarea>
                            </div>


                        </div>
                    </div>

                    <div class="step-buttons">
                        <button type="button" class="btn btn-outline" onclick="handlePrevAction()">Back</button>
                        <button type="submit" class="btn btn-primary btn-large btn-next">Confirm Booking</button>
                    </div>
                </div>

                <input type="hidden" name="selected_package" id="selectedPackage">
                <input type="hidden" name="appointment_time" id="selectedTime">
                <input type="hidden" name="pax" id="paxInput" value="1">

            </form>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script>
        // Global data for booking.js
        window.staffData = <?php echo json_encode($staff_members); ?>;
    </script>
    <script src="assets/js/booking.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
