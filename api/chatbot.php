<?php
require_once '../config/functions.php';
require_once '../config/intent-logic.php';
$knowledge = require_once '../config/chatbot-knowledge.php';
header('Content-Type: application/json');

// Get request body
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = [];
}
$message = strtolower(trim($input['message'] ?? ''));
$action  = $input['action']  ?? '';
$payload = $input['payload'] ?? '';

// Start session if not started (for conversation state)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize chat state if empty
if (!isset($_SESSION['chat_state'])) {
    $_SESSION['chat_state'] = [
        'step' => null,
        'data' => []
    ];
}

$response = [
    'text'    => "I'm not sure how to help with that. Try asking about our services or booking an appointment!",
    'options' => []
];

try {
    // Handle specific actions (button clicks)
    if ($action) {
        handleAction($action, $payload);
    }
    // Handle booking/cancel/reschedule flow based on state
    elseif ($_SESSION['chat_state']['step']) {
        // Priority check: Is the user trying to cancel or go back?
        $interrupts = ['cancel', 'back', 'stop', 'restart', 'menu', 'reschedule'];
        reset($interrupts);
        $isInterrupt = false;
        foreach($interrupts as $stopWord) {
            if (strpos($message, $stopWord) !== false) {
                $isInterrupt = true;
                break;
            }
        }

        if ($isInterrupt) {
            // Clear booking state if user wants to cancel or reschedule
            if (strpos($message, 'cancel') !== false || strpos($message, 'reschedule') !== false || strpos($message, 'restart') !== false || strpos($message, 'menu') !== false) {
                resetChatState();
            }
            handleIntent($message);
        } elseif (!handleBookingFlow($message)) {
            handleIntent($message);
        }
    }
    // Handle natural language intents
    else {
        handleIntent($message);
    }
} catch (Exception $e) {
    $response['text'] = "I encountered an error: " . $e->getMessage() . " on line " . $e->getLine();
} catch (Error $e) {
    $response['text'] = "I encountered a system error: " . $e->getMessage() . " on line " . $e->getLine();
}

// ─────────────────────────────────────────────────────────────────
// BOOKING ACTION HANDLER
// ─────────────────────────────────────────────────────────────────
function handleBookingActions($action, $payload) {
    global $response;

    switch ($action) {
        case 'start_booking':
            resetChatState();
            startBooking();
            break;

        case 'book_service':
            $_SESSION['chat_state']['step'] = 'SELECT_PAX';
            $response['text'] = "🌸 How many persons would you like to book for?";
            $response['options'] = [
                ['label' => '1 Person',  'action' => 'select_pax', 'payload' => '1'],
                ['label' => '2 Persons', 'action' => 'select_pax', 'payload' => '2'],
                ['label' => '3 Persons', 'action' => 'select_pax', 'payload' => '3']
            ];
            break;

        case 'select_pax':
            $pax = (int)$payload;
            $_SESSION['chat_state']['data']['pax'] = $pax;
            $_SESSION['chat_state']['data']['current_pax_index'] = 0;
            $_SESSION['chat_state']['data']['pax_details'] = [];
            askForCategory();
            break;

        case 'select_category_pax':
            askForServiceInCategory($payload);
            break;

        case 'select_service_pax':
            $index  = $_SESSION['chat_state']['data']['current_pax_index'];
            $svcId  = $payload;
            $conn   = getDBConnection();
            $stmt   = $conn->prepare("SELECT name, base_price, duration, category FROM services WHERE id = ?");
            $stmt->execute([$svcId]);
            $service = $stmt->fetch();

            if (!$service) {
                $response['text'] = "I'm sorry, I couldn't find that service. Let's try picking a category again.";
                askForCategory();
                break;
            }

            $_SESSION['chat_state']['data']['pax_details'][$index] = [
                'services'      => [[
                    'id'       => $svcId,
                    'name'     => $service['name'],
                    'price'    => $service['base_price'],
                    'duration' => $service['duration'],
                    'category' => $service['category']
                ]],
                'totalDuration' => $service['duration']
            ];
            askForStaff();
            break;

        case 'select_staff_pax':
            $index    = $_SESSION['chat_state']['data']['current_pax_index'];
            $staffId  = $payload;

            $staffName = 'Any Available Specialist';
            if ($staffId) {
                $conn  = getDBConnection();
                $stmt  = $conn->prepare("SELECT u.first_name FROM staff s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                $stmt->execute([$staffId]);
                $staffName = $stmt->fetchColumn() ?: $staffName;
            }

            $_SESSION['chat_state']['data']['pax_details'][$index]['staff_id']  = $staffId;
            $_SESSION['chat_state']['data']['pax_details'][$index]['staffName'] = $staffName;

            $_SESSION['chat_state']['step'] = 'SELECT_DATE_PAX';
            $response['text'] = "When would you like the appointment for **Person " . ($index + 1) . "**?\n(e.g., *tomorrow*, *next Monday*, *May 25*)";
            break;

        case 'select_slot_pax':
            $index = $_SESSION['chat_state']['data']['current_pax_index'];
            $time  = $payload;
            $_SESSION['chat_state']['data']['pax_details'][$index]['time'] = $time;

            $paxCount = $_SESSION['chat_state']['data']['pax'];
            if ($index < $paxCount - 1) {
                $_SESSION['chat_state']['data']['current_pax_index']++;
                askForCategory();
            } else {
                // Skip payment selection — default to pay on arrival
                $_SESSION['chat_state']['data']['payment_method'] = 'pay_on_arrival';
                $_SESSION['chat_state']['step'] = 'CONFIRM';
                showBookingSummary();
            }
            break;



        case 'confirm_booking':
            if (!isLoggedIn()) {
                // Keep chat state so booking data persists after login/register
                $response['text'] = "✨ To complete your booking, please **log in** or **create an account** first. Don't worry — your booking details will be saved!";
                $response['options'] = [
                    ['label' => '🔑 Login',    'action' => 'redirect_login',    'payload' => ''],
                    ['label' => '📝 Register', 'action' => 'redirect_register', 'payload' => ''],
                    ['label' => '❌ Cancel',    'action' => 'cancel_booking',    'payload' => '', 'danger' => true]
                ];
            } else {
                createBooking();
            }
            break;

        case 'redirect_login':
            $response['text'] = "Redirecting you to the login page... 🔑";
            $response['redirect'] = 'login.php';
            break;

        case 'redirect_register':
            $response['text'] = "Redirecting you to create an account... 📝";
            $response['redirect'] = 'register.php';
            break;

        case 'resume_booking':
            // Resume a pending booking after login/register
            if ($_SESSION['chat_state']['step'] === 'CONFIRM' && !empty($_SESSION['chat_state']['data']['pax_details'])) {
                showBookingSummary();
            } else {
                $response['text'] = "It looks like your previous booking has expired. Let's start fresh! 🌸";
                $response['options'] = buildMainMenuOptions();
                resetChatState();
            }
            break;

        case 'cancel_booking':
            resetChatState();
            $response['text'] = "Booking cancelled. Is there anything else I can help you with? 😊";
            $response['options'] = buildMainMenuOptions();
            break;
    }
}

// ─────────────────────────────────────────────────────────────────
// ACTION HANDLER
// ─────────────────────────────────────────────────────────────────
function handleAction($action, $payload) {
    global $response;

    switch ($action) {

        // ── Booking flow ──────────────────────────────────────────
        case 'start_booking':
        case 'book_service':
        case 'select_pax':
        case 'select_category_pax':
        case 'select_service_pax':
        case 'select_staff_pax':
        case 'select_slot_pax':
        case 'confirm_booking':
        case 'cancel_booking':
        case 'redirect_login':
        case 'redirect_register':
        case 'resume_booking':
            handleBookingActions($action, $payload);
            break;

        // ── Appointment management ─────────────────────────────
        case 'view_appointments':
        case 'cancel_appointment_select':
        case 'cancel_appointment_confirm':
        case 'reschedule_select':
        case 'reschedule_slot':
            handleAppointmentActions($action, $payload);
            break;

        case 'list_services':
            listServices();
            break;

        case 'list_staff':
            listStaff();
            break;

        case 'main_menu':
        case 'reset_chat':
            resetChatState();
            $firstName = $_SESSION['user_name'] ?? '';
            $name = $firstName ? " $firstName" : '';
            $response['text'] = "Hi$name! 👋 What can I help you with today?";
            $response['options'] = buildMainMenuOptions();
            break;
    }
}

// ─────────────────────────────────────────────────────────────────
// APPOINTMENT ACTION HANDLER
// ─────────────────────────────────────────────────────────────────
function handleAppointmentActions($action, $payload) {
    global $response;

    switch ($action) {
        case 'view_appointments':
            viewAppointments();
            break;

        case 'cancel_appointment_select':
            $apptId = (int)$payload;
            $_SESSION['chat_state']['data']['target_appointment_id'] = $apptId;

            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE id = ? AND user_id = ?");
            $stmt->execute([$apptId, $_SESSION['user_id']]);
            $appt = $stmt->fetch();

            if (!$appt) {
                $response['text'] = "I couldn't find that appointment. Let's look at your bookings again.";
                viewAppointments();
                break;
            }

            $dateStr = date('F j, Y', strtotime($appt['appointment_date']));
            $timeStr = date('g:i A',  strtotime($appt['appointment_time']));
            $response['text'] = "Are you sure you want to **cancel** your appointment on **$dateStr at $timeStr**?\n\nThis action cannot be undone.";
            $response['options'] = [
                ['label' => '✅ Yes, Cancel It',   'action' => 'cancel_appointment_confirm', 'payload' => $apptId, 'danger' => true],
                ['label' => '← Go Back',           'action' => 'view_appointments',          'payload' => '']
            ];
            break;

        case 'cancel_appointment_confirm':
            $apptId = (int)$payload;
            $conn   = getDBConnection();
            $stmt   = $conn->prepare("SELECT appointment_date, appointment_time, created_at FROM appointments WHERE id = ? AND user_id = ? AND status NOT IN ('cancelled','completed')");
            $stmt->execute([$apptId, $_SESSION['user_id']]);
            $appt = $stmt->fetch();
            
            if ($appt) {
                // Check policy: 24h advance OR within 1h of booking
                $appt_ts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
                $booked_ts = strtotime($appt['created_at']);
                $hours_until = ($appt_ts - time()) / 3600;
                $mins_since_booking = (time() - $booked_ts) / 60;

                if ($hours_until < 24 && $mins_since_booking > 60) {
                    $response['text'] = "⚠️ I'm sorry, appointments can only be cancelled at least 24 hours in advance (unless booked within the last hour). Please contact us for assistance.";
                } else {
                    $upd = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $upd->execute([$apptId]);
                    $response['text'] = "✅ Your appointment has been **cancelled**. We hope to see you again soon!";
                }
            } else {
                $response['text'] = "I'm sorry, that appointment cannot be cancelled at this time.";
            }
            $response['options'] = buildMainMenuOptions();
            resetChatState();
            break;

        case 'reschedule_select':
            $apptId = (int)$payload;
            $_SESSION['chat_state']['data']['reschedule_appointment_id'] = $apptId;
            $_SESSION['chat_state']['step'] = 'RESCHEDULE_DATE';
            $response['text'] = "What new date would you like? (e.g., *tomorrow*, *next Friday*, *May 30*)";
            break;

        case 'reschedule_slot':
            $apptId  = $_SESSION['chat_state']['data']['reschedule_appointment_id'] ?? null;
            $newDate = $_SESSION['chat_state']['data']['reschedule_date'] ?? null;
            $newTime = $payload;

            if ($apptId && $newDate) {
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT duration FROM appointments a JOIN services s ON JSON_CONTAINS(a.services, JSON_QUOTE(CAST(s.id AS CHAR))) WHERE a.id = ? LIMIT 1");
                $stmt->execute([$apptId]);
                $dur = $stmt->fetchColumn() ?: 60;
                $endTime = date('H:i:s', strtotime($newTime) + ($dur * 60));

                $upd = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, end_time = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND user_id = ?");
                $upd->execute([$newDate, date('H:i:s', strtotime($newTime)), $endTime, $apptId, $_SESSION['user_id']]);

                $response['text'] = "✅ Done! Your appointment has been **rescheduled** to **" . date('F j', strtotime($newDate)) . " at " . date('g:i A', strtotime($newTime)) . "**. See you then!";
            } else {
                $response['text'] = "Something went wrong. Let's try again.";
            }
            $response['options'] = buildMainMenuOptions();
            resetChatState();
            break;
    }
}

// ─────────────────────────────────────────────────────────────────
// BOOKING FLOW HANDLER
// ─────────────────────────────────────────────────────────────────
function handleBookingFlow($message) {
    global $response;
    $step = $_SESSION['chat_state']['step'];

    // ── Pax selection ──────────────────────────────────────────
    if ($step === 'SELECT_PAX') {
        $pax = (int)filter_var($message, FILTER_SANITIZE_NUMBER_INT);
        if ($pax > 0 && $pax <= 5) {
            $_SESSION['chat_state']['data']['pax'] = $pax;
            $_SESSION['chat_state']['data']['current_pax_index'] = 0;
            $_SESSION['chat_state']['data']['pax_details'] = [];
            askForCategory();
            return true;
        } else {
            $response['text'] = "I can book for up to 5 persons at a time. How many people will be coming?";
            $response['options'] = [
                ['label' => '1 Person',  'action' => 'select_pax', 'payload' => '1'],
                ['label' => '2 Persons', 'action' => 'select_pax', 'payload' => '2'],
                ['label' => '❌ Cancel Booking', 'action' => 'cancel_booking', 'payload' => '']
            ];
            return true;
        }
    }

    // ── Category selection ──────────────────────────────────────
    elseif ($step === 'SELECT_CATEGORY_PAX') {
        global $knowledge;
        foreach ($knowledge['categories'] as $cat => $desc) {
            if (strpos($message, str_replace('_', ' ', $cat)) !== false) {
                askForServiceInCategory($cat);
                return true;
            }
        }
    }

    // ── Service selection ───────────────────────────────────────
    elseif ($step === 'SELECT_SERVICE_PAX') {
        if (searchServicesForPax($message)) return true;
    }

    // ── Date selection ──────────────────────────────────────────
    elseif ($step === 'SELECT_DATE_PAX') {
        // Quick way to say "same date"
        if (strpos(strtolower($message), 'same') !== false || strpos(strtolower($message), 'today') !== false) {
            $index = $_SESSION['chat_state']['data']['current_pax_index'];
            if ($index > 0 && isset($_SESSION['chat_state']['data']['pax_details'][0]['date'])) {
                $date = $_SESSION['chat_state']['data']['pax_details'][0]['date'];
            } else {
                $date = parseDate($message);
            }
        } else {
            $date = parseDate($message);
        }

        if ($date) {
            $index = $_SESSION['chat_state']['data']['current_pax_index'];
            $_SESSION['chat_state']['data']['pax_details'][$index]['date'] = $date;
            $_SESSION['chat_state']['step'] = 'SELECT_SLOT_PAX';

            $paxDetail = $_SESSION['chat_state']['data']['pax_details'][$index];
            $duration  = $paxDetail['totalDuration'];
            $staffId   = $paxDetail['staff_id'];
            $personNum = $index + 1;

            // Pass service category so "Any Available" only shows slots where a qualified specialist is free
            $serviceCategory = $paxDetail['services'][0]['category'] ?? null;
            $slots = getAvailableTimeSlots($date, $duration, $staffId, 1, null, false, $serviceCategory);

            if (empty($slots)) {
                $response['text'] = "I'm sorry, no availability on " . date('F j', strtotime($date)) . " for this. Please choose another date.";
                $_SESSION['chat_state']['step'] = 'SELECT_DATE_PAX';
            } else {
                $response['text'] = "Here are the available times for **Person $personNum** on **" . date('F j, Y', strtotime($date)) . "**:";
                $options = [];
                // Allow ALL available hours
                foreach ($slots as $slot) {
                    $time = date('g:i A', strtotime($slot['start']));
                    $options[] = ['label' => $time, 'action' => 'select_slot_pax', 'payload' => $slot['start']];
                }
                $response['options'] = $options;
            }
            return true;
        }
    }

    // ── Reschedule date selection ────────────────────────────────
    elseif ($step === 'RESCHEDULE_DATE') {
        $date = parseDate($message);
        if ($date) {
            $_SESSION['chat_state']['data']['reschedule_date'] = $date;
            $_SESSION['chat_state']['step'] = 'RESCHEDULE_SLOT';

            $apptId = $_SESSION['chat_state']['data']['reschedule_appointment_id'];
            // Get duration of service from appointment
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT final_price, pax FROM appointments WHERE id = ?");
            $stmt->execute([$apptId]);
            $appt = $stmt->fetch();
            $pax  = $appt['pax'] ?? 1;

            $slots = getAvailableTimeSlots($date, 60, null, $pax);

            if (empty($slots)) {
                $response['text'] = "No available slots on " . date('F j, Y', strtotime($date)) . " for $pax person(s). Please try a different date.";
                $_SESSION['chat_state']['step'] = 'RESCHEDULE_DATE';
            } else {
                $response['text'] = "Available times on **" . date('F j, Y', strtotime($date)) . "**:";
                $options = [];
                foreach (array_slice($slots, 0, 8) as $slot) {
                    $time = date('g:i A', strtotime($slot['start']));
                    $options[] = ['label' => $time, 'action' => 'reschedule_slot', 'payload' => $slot['start']];
                }
                $response['options'] = $options;
            }
            return true;
        }
        // Not a recognizable date — gentle nudge
        $response['text'] = "I couldn't understand that date. Please try something like *next Monday*, *May 25*, or *2026-05-25*.";
        return true;
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────
// INTENT HANDLER
// ─────────────────────────────────────────────────────────────────
function handleIntent($message) {
    global $response, $knowledge;

    $bestIntent   = getIntent($message, $knowledge);
    
    // Final check: if intent is still weak, try FAQs/Categories
    if ($bestIntent === 'UNKNOWN') {
        // Try FAQs
        foreach ($knowledge['faqs'] as $faq) {
            foreach ($faq['keywords'] as $kw) {
                if (strpos($message, $kw) !== false) {
                    $response['text'] = $faq['answer'];
                    return;
                }
            }
        }

        // Try categories
        foreach ($knowledge['categories'] as $cat => $desc) {
            if (strpos($message, str_replace('_', ' ', $cat)) !== false) {
                $response['text'] = $desc . " Would you like to book one of these?";
                $response['options'] = [['label' => 'Book ' . ucfirst($cat), 'action' => 'start_booking', 'payload' => '']];
                return;
            }
        }

        // Try exact DB service search
        if (searchServices($message)) return;

        // Fuzzy service match
        if (fuzzyServiceSearch($message)) return;

        // Final fallback
        $response['text'] = "I apologize, but I'm not quite sure I understand. I am still learning! 😊 You can ask me to book an appointment, view your schedules, or inquire about our studio.";
        $response['options'] = buildMainMenuOptions();
        return;
    }

    switch ($bestIntent) {
        case 'GREETING':
            $firstName = $_SESSION['user_name'] ?? '';
            $name = $firstName ? " $firstName" : "";
            $response['text'] = "Hello{$name}! Welcome to Beaute Aesthetic Studio. ✨ I'm your personal assistant BeauteBot. How can I help you today?";
            $response['options'] = buildMainMenuOptions();
            break;

        case 'BOOKING':
            startBooking();
            break;

        case 'VIEW_APPOINTMENTS':
            if (!isLoggedIn()) {
                $response['text'] = "Redirecting to login page... ✨";
                $response['redirect'] = 'login.php';
            } else {
                viewAppointments();
            }
            break;

        case 'CANCELLATION':
            if (!isLoggedIn()) {
                $response['text'] = "Redirecting to login page... ✨";
                $response['redirect'] = 'login.php';
            } else {
                $response['text'] = $knowledge['policies']['cancellation'] . "\n\nWould you like to cancel an appointment?";
                $response['options'] = [
                    ['label' => '📋 View My Appointments', 'action' => 'view_appointments', 'payload' => '']
                ];
            }
            break;

        case 'RESCHEDULE':
            if (!isLoggedIn()) {
                $response['text'] = "Redirecting to login page... ✨";
                $response['redirect'] = 'login.php';
            } else {
                $response['text'] = $knowledge['policies']['rescheduling'] . "\n\nWhich appointment would you like to reschedule?";
                $response['options'] = [
                    ['label' => '📋 View My Appointments', 'action' => 'view_appointments', 'payload' => '']
                ];
            }
            break;

        case 'LOCATION':
            $response['text'] = "📍 **" . $knowledge['business_info']['address'] . "**\n\n" . $knowledge['business_info']['location'];
            $response['options'] = [
                ['label' => '📞 Contact Us', 'action' => 'main_menu', 'payload' => ''],
                ['label' => '🏠 Home',       'action' => 'main_menu', 'payload' => '']
            ];
            break;

        case 'POLICIES_ARRIVAL':
            $response['text'] = $knowledge['policies']['arrival'];
            break;

        case 'POLICIES_PAYMENT':
            if (strpos($message, 'service') !== false || strpos($message, 'list') !== false) {
                listServices();
            } else {
                $response['text'] = $knowledge['policies']['payment'] . "\n\nWould you like to see our service prices?";
                $response['options'] = [['label' => 'View Services', 'action' => 'list_services', 'payload' => '']];
            }
            break;

        case 'BUSINESS_HOURS':
            $response['text'] = "⏰ **Business Hours**\n\n" .
                $knowledge['business_info']['hours']['general'] . "\n\n" .
                $knowledge['business_info']['hours']['timeslots'] . "\n\n" .
                $knowledge['business_info']['peak_hours'];
            break;

        case 'PACKAGES':
            $text = "🎁 **Special Packages & Offers**\n\n";
            foreach ($knowledge['packages'] as $name => $desc) {
                $text .= "• **{$name}**: {$desc}\n";
            }
            $text .= "\nWould you like to book one of these packages?";
            $response['text'] = $text;
            $response['options'] = [
                ['label' => '📅 Book Package',  'action' => 'start_booking', 'payload' => ''],
                ['label' => '💆 View Services', 'action' => 'list_services', 'payload' => '']
            ];
            break;

        case 'ABOUT_US':
            // Check for contact specifics
            if (strpos($message, 'phone') !== false || strpos($message, 'contact') !== false || strpos($message, 'email') !== false) {
                $response['text'] = "📞 **Contact Information**\n\nPhone: **" . $knowledge['business_info']['contact']['phone'] . "**\nEmail: **" . $knowledge['business_info']['contact']['email'] . "**\n\nYou can also message us on Facebook!";
            } else {
                $owner = $knowledge['studio_info']['owner'] ?? 'Franz Edgie Firaza';
                $response['text'] = "✨ **About Beaute Aesthetic Studio**\n\n" . $knowledge['studio_info']['about'] . "\n\n**Mission:** " . $knowledge['studio_info']['mission'] . "\n\n**Owner:** $owner";
            }
            break;

        case 'OWNER':
            $owner = $knowledge['studio_info']['owner'] ?? 'Franz Edgie Firaza';
            $response['text'] = "👑 Beaute Aesthetic Studio is owned and led by **$owner**. Under their leadership, the studio has become a premier wellness destination in Legazpi City.";
            $response['options'] = [
                ['label' => '✨ About the Studio', 'action' => 'main_menu', 'payload' => ''],
                ['label' => '📅 Book Appointment', 'action' => 'start_booking', 'payload' => '']
            ];
            break;

        case 'STAFF':
            listStaff();
            break;

        case 'SERVICES':
            listServices();
            break;

        case 'HELP':
            $response['text'] = $knowledge['help']['general'];
            $response['options'] = buildMainMenuOptions();
            break;

        case 'FEEDBACK':
            $response['text'] = $knowledge['feedback']['info'];
            break;

        case 'FLOW_CONTROL':
            if (strpos($message, 'back') !== false) {
                $response['text'] = "Going back to the main menu. How else can I help?";
                $response['options'] = buildMainMenuOptions();
                resetChatState();
            } elseif (strpos($message, 'confirm') !== false) {
                if ($_SESSION['chat_state']['step'] === 'CONFIRM') {
                    createBooking();
                } else {
                    $response['text'] = "What would you like to confirm? If you are booking, I will guide you through the steps!";
                }
            } else {
                $response['text'] = "I'm listening! 😊 What would you like to do next? You can say *back*, *book*, or ask a question.";
                $response['options'] = buildMainMenuOptions();
            }
            break;
    }
}

// ─────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────

/**
 * Parse natural-language date strings including "next Monday", "this Friday", etc.
 */
function parseDate($message) {
    // Direct strtotime attempt
    $t = strtotime($message);
    if ($t && $t >= strtotime('today')) {
        return date('Y-m-d', $t);
    }

    // Pattern: "next <weekday>"
    if (preg_match('/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $m)) {
        $t = strtotime('next ' . $m[1]);
        if ($t) return date('Y-m-d', $t);
    }

    // Pattern: "this <weekday>"
    if (preg_match('/this\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $m)) {
        $t = strtotime('this ' . $m[1]);
        if ($t && $t >= strtotime('today')) return date('Y-m-d', $t);
        // If "this Monday" already passed this week, use next one
        if ($t) return date('Y-m-d', strtotime('next ' . $m[1]));
    }

    // Pattern: "tomorrow"
    if (strpos($message, 'tomorrow') !== false) {
        return date('Y-m-d', strtotime('tomorrow'));
    }

    // Pattern: "today"
    if (strpos($message, 'today') !== false) {
        return date('Y-m-d', strtotime('today'));
    }

    return null;
}

function searchServices($query) {
    global $response;
    $conn = getDBConnection();

    $keywords    = explode(' ', $query);
    $fillers     = ['do', 'you', 'have', 'provide', 'offer', 'the', 'a', 'an', 'any', 'me', 'about', 'i', 'want'];
    $clean       = array_filter($keywords, fn($k) => !in_array($k, $fillers) && strlen($k) > 2);

    if (empty($clean)) return false;

    $search = '%' . implode('%', $clean) . '%';
    $stmt   = $conn->prepare("SELECT * FROM services WHERE name LIKE ? AND status = 'active' LIMIT 1");
    $stmt->execute([$search]);
    $service = $stmt->fetch();

    if ($service) {
        $response['text'] = "Yes, we offer **{$service['name']}**! It takes about {$service['duration']} minutes and costs " . formatPrice($service['base_price']) . ". " . ($service['description'] ?? '');
        $response['options'] = [
            ['label' => 'Book ' . $service['name'],  'action' => 'book_service',   'payload' => $service['id']],
            ['label' => 'View Other Services',        'action' => 'list_services',  'payload' => '']
        ];
        return true;
    }

    return false;
}

/**
 * Fuzzy match: find a service whose name is close to the user's query.
 */
function fuzzyServiceSearch($query) {
    global $response;
    $conn  = getDBConnection();
    $stmt  = $conn->query("SELECT id, name, base_price, duration FROM services WHERE status = 'active'");
    $all   = $stmt->fetchAll();

    $bestMatch = null;
    $bestScore = 0;

    foreach ($all as $svc) {
        similar_text(strtolower($query), strtolower($svc['name']), $pct);
        if ($pct > $bestScore) {
            $bestScore = $pct;
            $bestMatch = $svc;
        }
    }

    if ($bestMatch && $bestScore > 50) {
        $response['text'] = "Did you mean **{$bestMatch['name']}**? It costs " . formatPrice($bestMatch['base_price']) . " and takes {$bestMatch['duration']} minutes.";
        $response['options'] = [
            ['label' => 'Book ' . $bestMatch['name'], 'action' => 'book_service',  'payload' => $bestMatch['id']],
            ['label' => 'View All Services',           'action' => 'list_services', 'payload' => '']
        ];
        return true;
    }

    return false;
}

function searchServicesForPax($query) {
    global $response;
    $conn  = getDBConnection();
    $index = $_SESSION['chat_state']['data']['current_pax_index'];

    $search = '%' . trim($query) . '%';
    $stmt   = $conn->prepare("SELECT * FROM services WHERE name LIKE ? AND status = 'active' LIMIT 1");
    $stmt->execute([$search]);
    $service = $stmt->fetch();

    if ($service) {
        $_SESSION['chat_state']['data']['pax_details'][$index] = [
            'services'      => [[
                'id'       => $service['id'],
                'name'     => $service['name'],
                'price'    => $service['base_price'],
                'duration' => $service['duration'],
                'category' => $service['category']
            ]],
            'totalDuration' => $service['duration']
        ];
        askForStaff();
        return true;
    }
    return false;
}

function startBooking() {
    global $response;
    $_SESSION['chat_state']['step'] = 'SELECT_PAX';
    $response['text'] = "I'd be happy to help! 🌸 How many persons will be coming?";
    $response['options'] = [
        ['label' => '1 Person',  'action' => 'select_pax', 'payload' => '1'],
        ['label' => '2 Persons', 'action' => 'select_pax', 'payload' => '2'],
        ['label' => '3 Persons', 'action' => 'select_pax', 'payload' => '3'],
        ['label' => '4 Persons', 'action' => 'select_pax', 'payload' => '4'],
        ['label' => '5 Persons', 'action' => 'select_pax', 'payload' => '5']
    ];
}

function askForCategory() {
    global $response, $knowledge;
    $index     = $_SESSION['chat_state']['data']['current_pax_index'];
    $personNum = $index + 1;

    $_SESSION['chat_state']['step'] = 'SELECT_CATEGORY_PAX';

    $response['text'] = "What service category would you like for **Person $personNum**?";
    $options = [];
    foreach ($knowledge['categories'] as $cat => $desc) {
        $label     = str_replace('_', ' ', $cat);
        $options[] = ['label' => ucwords($label), 'action' => 'select_category_pax', 'payload' => $cat];
    }
    $response['options'] = $options;
}

function askForServiceInCategory($category) {
    global $response;
    $index     = $_SESSION['chat_state']['data']['current_pax_index'];
    $personNum = $index + 1;

    $_SESSION['chat_state']['step'] = 'SELECT_SERVICE_PAX';

    $conn  = getDBConnection();
    $stmt  = $conn->prepare("SELECT id, name, base_price, duration FROM services WHERE category = ? AND status = 'active'");
    $stmt->execute([$category]);
    $services = $stmt->fetchAll();

    $displayCategory = ucwords(str_replace('_', ' ', $category));
    $response['text'] = "Which **$displayCategory** service would you like for **Person $personNum**?";
    $options = [];
    foreach ($services as $service) {
        $options[] = [
            'label'   => $service['name'] . ' — ' . formatPrice($service['base_price']),
            'action'  => 'select_service_pax',
            'payload' => $service['id']
        ];
    }
    $response['options'] = $options;
}

function askForStaff() {
    global $response;
    $index     = $_SESSION['chat_state']['data']['current_pax_index'];
    $personNum = $index + 1;
    $paxDetail = $_SESSION['chat_state']['data']['pax_details'][$index] ?? null;

    if (!$paxDetail || empty($paxDetail['services'])) {
        $response['text'] = "I'm sorry, I lost track of your selection. Let's try again.";
        startBooking();
        return;
    }
    $category = $paxDetail['services'][0]['category'];

    $_SESSION['chat_state']['step'] = 'SELECT_STAFF_PAX';

    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT s.id, u.first_name, u.last_name FROM staff s JOIN users u ON s.user_id = u.id
         WHERE u.status = 'active' AND (s.specialization LIKE ? OR s.specialization = 'All Services')"
    );
    $stmt->execute(['%' . $category . '%']);
    $staff = $stmt->fetchAll();

    $response['text'] = "Great! Would you like a specific specialist for **" . ucwords(str_replace('_', ' ', $category)) . "** (Person $personNum)?";
    $options = [
        ['label' => 'Any Available Specialist', 'action' => 'select_staff_pax', 'payload' => '']
    ];
    foreach ($staff as $s) {
        $options[] = ['label' => $s['first_name'] . ' ' . $s['last_name'], 'action' => 'select_staff_pax', 'payload' => $s['id']];
    }
    $response['options'] = $options;
}



function showBookingSummary() {
    global $response;
    $pax           = $_SESSION['chat_state']['data']['pax'];
    $paxDetails    = $_SESSION['chat_state']['data']['pax_details'];

    $text = "📋 **Booking Summary**\n\n";

    $earliestTS = PHP_INT_MAX;
    $earliestTime = null;
    $earliestDateStr = null;
    $earliestTimeStr = null;

    foreach ($paxDetails as $i => $p) {
        $num      = $i + 1;
        $svcName  = $p['services'][0]['name'];
        $staffName = $p['staffName'];

        $dateStr = '';
        $timeStr = '';
        if (isset($p['date']) && isset($p['time'])) {
            $dateStr = date('M j, Y', strtotime($p['date']));
            $timeStr = date('g:i A',  strtotime($p['time']));

            $ts = strtotime($p['date'] . ' ' . $p['time']);
            if ($ts < $earliestTS) {
                 $earliestTS = $ts;
                 $earliestTime = $p['time'];
                 $earliestDateStr = date('F j, Y', strtotime($p['date']));
                 $earliestTimeStr = $timeStr;
            }
        }
        
        $text .= "👤 **Person $num:** $svcName — *$staffName* ($dateStr at $timeStr)\n";
    }

    $serviceIds = array_map(fn($p) => $p['services'][0]['id'], $paxDetails);
    $priceData  = calculateAppointmentPrice($serviceIds, null, null, $earliestTime);
    $finalPrice = $priceData['total'];

    $text .= "\n💰 **Total:** " . formatPrice($finalPrice);
    if ($priceData['surcharge'] > 0) {
        $text .= " *(includes peak-hour surcharge)*";
    }

    $response['text']    = $text;
    $response['options'] = [
        ['label' => '✅ Confirm Booking', 'action' => 'confirm_booking', 'payload' => 'yes'],
        ['label' => '❌ Cancel',          'action' => 'cancel_booking',  'payload' => 'no', 'danger' => true]
    ];
}

function listServices() {
    global $response;
    $conn  = getDBConnection();
    $stmt  = $conn->query("SELECT name, base_price, category FROM services WHERE status = 'active' ORDER BY category LIMIT 10");
    $services = $stmt->fetchAll();

    $text = "🌸 **Our Services**\n\n";
    $currentCat = '';
    foreach ($services as $s) {
        $cat = ucfirst($s['category']);
        if ($cat !== $currentCat) {
            $text .= "\n**$cat**\n";
            $currentCat = $cat;
        }
        $text .= "• {$s['name']} — " . formatPrice($s['base_price']) . "\n";
    }

    $response['text']    = $text;
    $response['options'] = [
        ['label' => '📅 Book Now', 'action' => 'start_booking', 'payload' => '']
    ];
}

function listStaff() {
    global $response;
    $conn = getDBConnection();
    $stmt = $conn->query(
        "SELECT u.first_name, u.last_name, s.specialization
         FROM staff s JOIN users u ON s.user_id = u.id
         WHERE u.status = 'active'
         ORDER BY u.first_name"
    );
    $staff = $stmt->fetchAll();

    if (empty($staff)) {
        $response['text'] = "Our specialists information is currently being updated. Please contact us directly for details!";
        return;
    }

    $text = "👥 **Our Specialist Team**\n\n";
    foreach ($staff as $s) {
        $text .= "• **{$s['first_name']} {$s['last_name']}** — " . ucwords(str_replace('_', ' ', $s['specialization'])) . "\n";
    }
    $text .= "\nYou can choose your preferred specialist when booking!";

    $response['text']    = $text;
    $response['options'] = [
        ['label' => '📅 Book Appointment', 'action' => 'start_booking', 'payload' => '']
    ];
}

function viewAppointments() {
    global $response;

    if (!isLoggedIn()) {
        $response['text'] = "Redirecting to login page... ✨";
        $response['redirect'] = 'login.php';
        return;
    }

    $conn  = getDBConnection();
    $stmt  = $conn->prepare(
        "SELECT id, appointment_date, appointment_time, services, pax, status, final_price
         FROM appointments
         WHERE user_id = ? AND status NOT IN ('cancelled','completed') AND appointment_date >= CURDATE()
         ORDER BY appointment_date ASC, appointment_time ASC
         LIMIT 5"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $appointments = $stmt->fetchAll();

    if (empty($appointments)) {
        $response['text'] = "You have no upcoming appointments. Would you like to book one?";
        $response['options'] = [
            ['label' => '📅 Book Appointment', 'action' => 'start_booking', 'payload' => '']
        ];
        return;
    }

    $text = "📋 **Your Upcoming Appointments**\n\n";
    $options = [];

    foreach ($appointments as $i => $appt) {
        $num     = $i + 1;
        $dateStr = date('F j, Y', strtotime($appt['appointment_date']));
        $timeStr = date('g:i A',  strtotime($appt['appointment_time']));
        $status  = ucfirst($appt['status']);
        $price   = formatPrice($appt['final_price']);

        $text .= "**[$num]** 📅 $dateStr at $timeStr\n";
        $text .= "     👥 {$appt['pax']} person(s) • $status • $price\n\n";

        // Only offer cancel/reschedule for pending/confirmed
        if (in_array($appt['status'], ['pending', 'confirmed'])) {
            $options[] = ['label' => "🔄 Reschedule #$num", 'action' => 'reschedule_select',          'payload' => $appt['id']];
            $options[] = ['label' => "❌ Cancel #$num",     'action' => 'cancel_appointment_select',   'payload' => $appt['id'], 'danger' => true];
        }
    }

    $options[] = ['label' => '📅 Book New Appointment', 'action' => 'start_booking', 'payload' => ''];

    $response['text']    = $text;
    $response['options'] = $options;
}

function createBooking() {
    global $response;
    $conn = getDBConnection();

    $userId        = $_SESSION['user_id'];
    $pax           = $_SESSION['chat_state']['data']['pax'];
    $paxDetails    = $_SESSION['chat_state']['data']['pax_details'];
    $paymentMethod = $_SESSION['chat_state']['data']['payment_method'] ?? 'pay_on_arrival';

    $maxDuration             = 0;
    $serviceIdsForCalculation = [];
    $servicesArray           = [];

    // Find the earliest date and time for the overarching appointment record
    $earliestTS = PHP_INT_MAX;
    $earliestDate = null;
    $earliestTime = null;

    foreach ($paxDetails as $p) {
        $svc                       = $p['services'][0];
        $serviceIdsForCalculation[] = $svc['id'];
        $servicesArray[]            = (string)$svc['id'];
        $maxDuration               = max($maxDuration, $p['totalDuration']);

        if (isset($p['date']) && isset($p['time'])) {
            $ts = strtotime($p['date'] . ' ' . $p['time']);
            if ($ts < $earliestTS) {
                 $earliestTS = $ts;
                 $earliestDate = $p['date'];
                 $earliestTime = $p['time'];
            }
        }
    }

    $priceData  = calculateAppointmentPrice($serviceIdsForCalculation, null, null, $earliestTime);
    $subtotal   = $priceData['subtotal'];
    $discount   = $priceData['discount'];
    $surcharge  = $priceData['surcharge'];
    $finalPrice = $priceData['total'];

    $endTime = date('H:i:s', strtotime($earliestTime) + ($maxDuration * 60));

    // Pre-fetch all service durations and active staff for random assignment
    $svc_dur_stmt = $conn->query("SELECT id, duration FROM services");
    $all_svc_durations = $svc_dur_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $all_staff_stmt = $conn->query("SELECT s.id, s.availability, s.specialization, u.first_name, u.last_name 
                                     FROM staff s JOIN users u ON s.user_id = u.id 
                                     WHERE u.status = 'active'");
    $all_active_staff = $all_staff_stmt->fetchAll();
    
    // Pre-fetch service categories for specialty matching
    $svc_cat_stmt = $conn->query("SELECT id, category FROM services");
    $svc_categories = $svc_cat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Build busy intervals from existing appointments
    $existing_appts_stmt = $conn->prepare(
        "SELECT staff_id, appointment_time, end_time, client_details FROM appointments 
         WHERE appointment_date = ? AND status NOT IN ('cancelled', 'no_show')"
    );
    $existing_appts_stmt->execute([$earliestDate]);
    $existing_appts = $existing_appts_stmt->fetchAll();
    
    $staff_busy_intervals = [];
    foreach ($existing_appts as $ea) {
        $ea_details = !empty($ea['client_details']) ? json_decode($ea['client_details'], true) : null;
        if (is_array($ea_details) && !empty($ea_details)) {
            foreach ($ea_details as $ed) {
                $ed_staff = isset($ed['staffId']) && $ed['staffId'] ? (int)$ed['staffId'] : null;
                $ed_time = $ed['time'] ?? null;
                $ed_svc_id = isset($ed['service_id']) ? (int)$ed['service_id'] : null;
                if ($ed_staff && $ed_time) {
                    $ed_dur = ($ed_svc_id && isset($all_svc_durations[$ed_svc_id])) ? (int)$all_svc_durations[$ed_svc_id] : 60;
                    $ed_start = strtotime($earliestDate . ' ' . $ed_time);
                    $ed_end = $ed_start + ($ed_dur * 60);
                    $staff_busy_intervals[$ed_staff][] = [$ed_start, $ed_end];
                }
            }
        } else if ($ea['staff_id']) {
            $ea_start = strtotime($earliestDate . ' ' . $ea['appointment_time']);
            $ea_end = strtotime($earliestDate . ' ' . $ea['end_time']);
            $staff_busy_intervals[(int)$ea['staff_id']][] = [$ea_start, $ea_end];
        }
    }

    $detailedAssignments = [];
    foreach ($paxDetails as $i => $p) {
        $svcId = $p['services'][0]['id'];
        $staffId = $p['staff_id'] ?: null;
        $staffName = $p['staffName'] ?: 'Any Available Specialist';
        $pDate = $p['date'];
        $pTime = date('H:i:s', strtotime($p['time']));
        
        // Randomly assign staff if "Any Available" — prefer specialty match
        if (!$staffId && $pDate && $pTime) {
            $svc_dur = isset($all_svc_durations[$svcId]) ? (int)$all_svc_durations[$svcId] : 60;
            $slot_start = strtotime($pDate . ' ' . $pTime);
            $slot_end = $slot_start + ($svc_dur * 60);
            $check_day = strtolower(date('l', strtotime($pDate)));
            
            // Get service category for specialty matching
            $svc_category = isset($svc_categories[$svcId]) ? strtolower(trim($svc_categories[$svcId])) : '';
            
            $candidates = [];
            foreach ($all_active_staff as $s) {
                $s_id = (int)$s['id'];
                $avail = json_decode($s['availability'] ?? '{}', true);
                if ($avail && isset($avail[$check_day])) {
                    if (empty($avail[$check_day]['active'])) continue;
                    $sm_start = strtotime($pDate . ' ' . $avail[$check_day]['start']);
                    $sm_end = strtotime($pDate . ' ' . $avail[$check_day]['end']);
                    if ($slot_start < $sm_start || $slot_end > $sm_end) continue;
                }
                $is_busy = false;
                if (isset($staff_busy_intervals[$s_id])) {
                    foreach ($staff_busy_intervals[$s_id] as $busy) {
                        if (!($slot_end <= $busy[0] || $slot_start >= $busy[1])) {
                            $is_busy = true;
                            break;
                        }
                    }
                }
                if (!$is_busy) $candidates[] = $s;
            }
            
            if (!empty($candidates)) {
                // Prefer staff whose specialization matches the service category
                $specialty_matched = [];
                if ($svc_category) {
                    foreach ($candidates as $c) {
                        $spec = strtolower($c['specialization'] ?? '');
                        if ($spec === 'all services' || stripos($spec, $svc_category) !== false) {
                            $specialty_matched[] = $c;
                        }
                    }
                }
                $pool = !empty($specialty_matched) ? $specialty_matched : $candidates;
                $picked = $pool[array_rand($pool)];
                $staffId = (int)$picked['id'];
                $staffName = $picked['first_name'] . ' ' . $picked['last_name'];
                $staff_busy_intervals[$staffId][] = [$slot_start, $slot_end];
            }
        }
        
        $detailedAssignments[] = [
            'service_id'   => $svcId,
            'service_name' => $p['services'][0]['name'],
            'staffId'      => $staffId ? (string)$staffId : null,
            'staffName'    => $staffName,
            'date'         => $pDate,
            'time'         => $pTime
        ];
    }

    $clientDetailsJson = json_encode($detailedAssignments);
    $servicesJson      = json_encode($servicesArray);

    try {
        $mainStaffId = $detailedAssignments[0]['staffId'] ? (int)$detailedAssignments[0]['staffId'] : null;

        // Pass earliestDate and earliestTime as the main appointment date/time for the DB
        $stmt = $conn->prepare(
            "INSERT INTO appointments
             (user_id, staff_id, appointment_date, appointment_time, end_time, services, pax, client_details, total_price, discount_applied, final_price, notes, status, payment_status, payment_method, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reserved', 'pending', ?, NOW())"
        );
        $stmt->execute([
            $userId, $mainStaffId, $earliestDate, date('H:i:s', strtotime($earliestTime)), $endTime,
            $servicesJson, $pax, $clientDetailsJson,
            $subtotal, $discount, $finalPrice,
            "Booked via BeauteBot AI",
            $paymentMethod
        ]);

        $appointmentId = $conn->lastInsertId();
        sendAppointmentNotification($appointmentId, 'confirmation');

        $response['text'] = "🎉 **Booking Confirmed!**\n\nYour appointment for **$pax " . ($pax > 1 ? "persons" : "person") . "** is set for **" . date('l, F j', strtotime($earliestDate)) . " at " . date('g:i A', strtotime($earliestTime)) . "**.\n\n**Total:** " . formatPrice($finalPrice) . "\n\nWe look forward to seeing you! 💆‍♀️";
        resetChatState();
        $response['options'] = [
            ['label' => '📋 View My Bookings', 'action' => 'view_appointments', 'payload' => '']
        ];

    } catch (Exception $e) {
        $response['text'] = "I apologize, but something went wrong: " . $e->getMessage();
        resetChatState();
    }
}

function buildMainMenuOptions() {
    $opts = [
        ['label' => '📅 Book Appointment', 'action' => 'start_booking',    'payload' => ''],
        ['label' => '💆 View Services',    'action' => 'list_services',    'payload' => ''],
        ['label' => '👩‍⚕️ Our Team',       'action' => 'list_staff',       'payload' => '']
    ];
    if (isLoggedIn()) {
        $opts[] = ['label' => '📋 My Appointments', 'action' => 'view_appointments', 'payload' => ''];
    }
    return $opts;
}

function resetChatState() {
    $_SESSION['chat_state'] = ['step' => null, 'data' => []];
}

echo json_encode($response);
