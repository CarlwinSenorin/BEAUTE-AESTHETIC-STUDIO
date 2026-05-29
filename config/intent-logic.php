<?php
/**
 * Shared Intent Detection Logic
 */

function getIntent($message, $knowledge) {
    if (empty($message)) return 'UNKNOWN';
    $message = strtolower(trim($message));
    
    $intents = [
        'GREETING' => [
            'keywords' => ['hello' => 1, 'hi' => 1, 'hey' => 1, 'greetings' => 1, 'good morning' => 1.5, 'good afternoon' => 1.5, 'good evening' => 1.5, 'yo' => 0.5],
            'regex'    => '/\b(hello|hi|hey|greetings)\b/i'
        ],
        'BOOKING' => [
            'keywords' => ['book' => 2, 'booking' => 2, 'appointment' => 2, 'reserve' => 2, 'reservation' => 2, 'schedule' => 2, 'sched' => 1.5, 'appt' => 1.5, 'slot' => 1, 'available time' => 2, 'available times' => 2, 'time slot' => 1.5, 'date selection' => 2, 'today' => 1, 'tomorrow' => 1, 'morning' => 0.5, 'afternoon' => 0.5, 'evening' => 0.5, 'pax' => 1, 'number of pax' => 2],
            'regex'    => '/\b(book|booking|appointment|reserve|reservation|schedule|sched|appt|pax)\b/i'
        ],
        'VIEW_APPOINTMENTS' => [
            'keywords' => ['my appointment' => 3, 'my booking' => 3, 'my schedule' => 3, 'view appointment' => 3, 'show appointment' => 3, 'upcoming' => 2, 'scheduled' => 2, 'see my' => 1.5, 'check my' => 1.5, 'booking status' => 3, 'review appointment' => 3],
            'regex'    => '/\b(my\s+(appointment|booking|schedule)|view|show|upcoming|scheduled|status|review)\b/i'
        ],
        'CANCELLATION' => [
            'keywords' => ['cancel' => 5, 'cancellation' => 4, 'remove' => 2, 'delete' => 1, 'cancel booking' => 5],
            'regex'    => '/\b(cancel|cancellation)\b/i'
        ],
        'RESCHEDULE' => [
            'keywords' => ['reschedule' => 5, 'change date' => 4, 'move appointment' => 4, 'change appointment' => 3, 'change time' => 3, 'different date' => 3, 'postpone' => 2, 'reschedule booking' => 5],
            'regex'    => '/\b(reschedule|postpone)\b/i'
        ],
        'LOCATION' => [
            'keywords' => ['location' => 2, 'address' => 2, 'where' => 1.5, 'located' => 2, 'place' => 1, 'legazpi' => 1, 'branch' => 2],
            'regex'    => '/\b(location|address|where|branch)\b/i'
        ],
        'POLICIES_ARRIVAL' => [
            'keywords' => ['arrive' => 2, 'arrival' => 2, 'early' => 1.5, 'late' => 1, 'check in' => 1.5, 'reminder' => 1, 'notifications' => 1],
            'regex'    => '/\b(arrive|arrival|early|reminder|notification)\b/i'
        ],
        'POLICIES_PAYMENT' => [
            'keywords' => ['pay' => 2, 'payment' => 2, 'cost' => 1, 'price' => 1, 'gcash' => 2, 'cash' => 2, 'card' => 1, 'rate' => 1, 'total cost' => 3, 'payment method' => 3],
            'regex'    => '/\b(pay|payment|cost|price|gcash|cash|rate)\b/i'
        ],
        'BUSINESS_HOURS' => [
            'keywords' => ['hour' => 2, 'open' => 2, 'close' => 2, 'operating' => 2, 'timeslots' => 2, 'session' => 1, 'operating hours' => 3],
            'regex'    => '/\b(hour|open|close|time|operating|timeslot|session)\b/i'
        ],
        'ABOUT_US' => [
            'keywords' => ['about' => 2, 'mission' => 2, 'vision' => 2, 'studio' => 1.5, 'identity' => 2, 'goal' => 1.5, 'contact' => 1, 'phone' => 1, 'number' => 0.5, 'email' => 1],
            'regex'    => '/\b(about|mission|vision|identity|goal|contact|phone|email)\b/i'
        ],
        'OWNER' => [
            'keywords' => ['owner' => 3, 'founder' => 3, 'who owns' => 3, 'who started' => 3, 'who is the owner' => 3, 'franz' => 3, 'firaza' => 3, 'edgie' => 3],
            'regex'    => '/\b(owner|founder|franz|firaza|edgie)\b/i'
        ],
        'STAFF' => [
            'keywords' => ['staff' => 3, 'specialist' => 2, 'team' => 2, 'therapist' => 2, 'who works' => 2, 'expert' => 1, 'artist' => 1, 'available specialist' => 3, 'choose specialist' => 3, 'any specialist' => 3],
            'regex'    => '/\b(staff|specialist|team|therapist)\b/i'
        ],
        'PACKAGES' => [
            'keywords' => ['package' => 3, 'combo' => 2, 'bundle' => 2, 'deal' => 1, 'promo' => 2, 'offer' => 1, 'discount' => 2],
            'regex'    => '/\b(package|combo|bundle|deal|promo|offer|discount)\b/i'
        ],
        'SERVICES' => [
            'keywords' => ['service' => 3, 'services' => 3, 'offer' => 1, 'list' => 1, 'nail' => 1, 'eyebrow' => 1, 'lash' => 1, 'wax' => 1, 'massage' => 1, 'facial' => 1, 'skin' => 1, 'service list' => 3, 'service category' => 3, 'available services' => 3],
            'regex'    => '/\b(service|services|nail|eyebrow|lash|wax|massage|facial|skin)\b/i'
        ],
        'FLOW_CONTROL' => [
            'keywords' => ['next' => 2, 'back' => 2, 'yes' => 1, 'no' => 1, 'confirm' => 2, 'confirm booking' => 3],
            'regex'    => '/\b(next|back|yes|no|confirm)\b/i'
        ],
        'FEEDBACK' => [
            'keywords' => ['feedback' => 3, 'rate' => 2, 'rate service' => 3, 'review' => 1],
            'regex'    => '/\b(feedback|rate|review)\b/i'
        ],
        'HELP' => [
            'keywords' => ['help' => 3, 'guide' => 2, 'support' => 1, 'what can you' => 2, 'how to' => 2, 'how do' => 2, 'how can' => 2, 'use' => 1, 'assistant' => 1],
            'regex'    => '/\b(help|guide|support|how|use)\b/i'
        ]
    ];

    foreach ($knowledge['categories'] as $cat => $desc) {
        $catName = str_replace('_', ' ', $cat);
        $singular = rtrim($catName, 's');
        if (strpos($message, $catName) !== false || strpos($message, $singular) !== false) {
            // Check if it's accompanied by info keywords like "about", "what is", "info"
            if (strpos($message, 'about') !== false || strpos($message, 'what') !== false || strpos($message, 'info') !== false || strpos($message, 'list') === false) {
                // If not booking, assume info
                if (strpos($message, 'book') === false && strpos($message, 'reserve') === false) {
                    return 'CATEGORY_INFO';
                }
            }
        }
    }

    $bestIntent   = 'UNKNOWN';
    $highestScore = 0;

    foreach ($intents as $intent => $data) {
        $score = 0;
        // Regex match
        if (isset($data['regex']) && preg_match($data['regex'], $message)) {
            $score += 2.0; 
        }
        // Keyword match
        foreach ($data['keywords'] as $kw => $weight) {
            if (strpos($message, $kw) !== false) {
                $score += $weight;
            }
        }

        // --- CONFLICT RESOLUTION ---
        // If "cancel" is present, boost CANCELLATION and penalize VIEW_APPOINTMENTS
        if ($intent === 'CANCELLATION' && strpos($message, 'cancel') !== false) {
            $score += 3; // Boosted further
        }
        if ($intent === 'VIEW_APPOINTMENTS' && strpos($message, 'cancel') !== false) {
            $score -= 4; // Penalized further
        }
        
        // If "how do/can" is present, boost HELP
        if ($intent === 'HELP' && (strpos($message, 'how do') !== false || strpos($message, 'how can') !== false)) {
            $score += 2;
        }

        if ($score > $highestScore) {
            $highestScore = $score;
            $bestIntent   = $intent;
        }
    }

    // Secondary check: FAQ and Categories if score is low
    if ($highestScore < 1.5) {
        foreach ($knowledge['faqs'] as $faq) {
            foreach ($faq['keywords'] as $kw) {
                if (strpos($message, $kw) !== false) return 'FAQ';
            }
        }
        foreach ($knowledge['categories'] as $cat => $desc) {
            if (strpos($message, str_replace('_', ' ', $cat)) !== false) return 'CATEGORY_INFO';
        }
    }

    return ($highestScore >= 1.5) ? $bestIntent : 'UNKNOWN';
}
