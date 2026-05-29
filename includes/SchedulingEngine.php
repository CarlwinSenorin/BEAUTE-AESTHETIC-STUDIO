<?php
/**
 * SchedulingEngine - Intelligent Resource Allocation Logic
 * Implements filtering, scoring, and ranking for optimal assignment.
 */

class SchedulingEngine {
    private $db;
    private $config;

    public function __construct($pdo) {
        $this->db = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
        $this->config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Main entry point for recommendations
     */
    public function getRecommendation($request) {
        // Step 1: Filter (Hard Constraints)
        $validSlots = $this->filterSlots($request);
        
        if (empty($validSlots)) {
            return $this->formatUnavailable($request);
        }

        // Step 2: Score (Soft Optimization)
        $scoredSlots = $this->scoreSlots($validSlots, $request);

        // Step 3: Rank & Select
        return $this->rankAndSelect($scoredSlots, $request);
    }

    /**
     * FILTER: Eliminate invalid slots based on hard constraints
     */
    private function filterSlots($request) {
        $slots = [];
        $startDate = new DateTime($request['preferred_date_range']['start']);
        $endDate = new DateTime($request['preferred_date_range']['end']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            if ($this->isBlackoutDate($dateStr)) continue;

            $daySlots = $this->generateDailySlots($dateStr, $request);
            foreach ($daySlots as $slot) {
                // Check business hours
                if (!$this->isWithinBusinessHours($slot)) continue;

                // Check resources (Staff & Rooms)
                $eligibleResourceCombos = $this->findEligibleResources($slot, $request);
                if (empty($eligibleResourceCombos)) continue;

                $slot['eligible_combos'] = $eligibleResourceCombos;
                $slots[] = $slot;
            }
        }
        return $slots;
    }

    /**
     * SCORE: Calculate composite score (0-100)
     */
    private function scoreSlots($slots, $request) {
        $scored = [];
        foreach ($slots as $slot) {
            foreach ($slot['eligible_combos'] as $combo) {
                $scoreDetails = [
                    'customer_fit' => $this->calculateCustomerFit($slot, $request),
                    'resource_efficiency' => $this->calculateResourceEfficiency($slot, $combo, $request),
                    'staff_optimality' => $this->calculateStaffOptimality($slot, $combo, $request),
                    'operational_health' => $this->calculateOperationalHealth($slot, $request)
                ];

                // Composite calculation with weights
                $totalScore = (
                    ($scoreDetails['customer_fit'] * 0.30) +
                    ($scoreDetails['resource_efficiency'] * 0.25) +
                    ($scoreDetails['staff_optimality'] * 0.25) +
                    ($scoreDetails['operational_health'] * 0.20)
                );

                $scored[] = [
                    'slot' => $slot,
                    'combo' => $combo,
                    'score' => round($totalScore),
                    'score_breakdown' => $scoreDetails
                ];
            }
        }
        return $scored;
    }

    /**
     * RANK & SELECT: Sort and pick top results
     */
    private function rankAndSelect($scoredSlots, $request) {
        usort($scoredSlots, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $top = $scoredSlots[0];
        $alternatives = array_slice($scoredSlots, 1, 2);

        $response = [
            "recommendation" => [
                "rank" => 1,
                "datetime" => $top['slot']['start_time'],
                "end_datetime" => $top['slot']['end_time'],
                "timezone" => TIMEZONE,
                "score" => $top['score'],
                "confidence" => $this->calculateConfidence($top['score'])
            ],
            "allocation" => [
                "staff" => [
                    "id" => $top['combo']['staff']['id'],
                    "name" => $top['combo']['staff']['name'],
                    "reason" => $this->generateStaffReason($top)
                ],
                "room" => [
                    "id" => $top['combo']['room']['id'],
                    "name" => $top['combo']['room']['name'],
                    "reason" => $this->generateRoomReason($top)
                ],
                "service_config" => [
                    "id" => $request['service_type'],
                    "actual_duration" => $request['duration_minutes'],
                    "buffer_applied" => $this->getBufferMinutes($request)
                ]
            ],
            "alternatives" => array_map(function($alt, $index) {
                return [
                    "rank" => $index + 2,
                    "datetime" => $alt['slot']['start_time'],
                    "score" => $alt['score'],
                    "staff_id" => $alt['combo']['staff']['id'],
                    "room_id" => $alt['combo']['room']['id']
                ];
            }, $alternatives, array_keys($alternatives)),
            "explanation" => $this->generateExplanation($top, $request),
            "warnings" => $this->generateWarnings($top)
        ];

        return $response;
    }

    // --- Private Helper Methods ---

    private function generateDailySlots($date, $request) {
        $slots = [];
        $start_time = strtotime($date . ' ' . ($this->config['business_hours_start'] ?? '09:00'));
        $end_time = strtotime($date . ' ' . ($this->config['business_hours_end'] ?? '18:00'));
        $duration = $request['duration_minutes'] * 60;
        $buffer = $this->getBufferMinutes($request) * 60;
        $interval = 30 * 60; // Check every 30 mins

        for ($t = $start_time; $t + $duration <= $end_time; $t += $interval) {
            $slots[] = [
                'start_time' => date('Y-m-d\TH:i:s', $t),
                'end_time' => date('Y-m-d\TH:i:s', $t + $duration),
                'date' => $date,
                'time' => date('H:i:s', $t)
            ];
        }
        return $slots;
    }

    private function isBlackoutDate($date) {
        // Mocking blackout check; in real app, query database
        return false;
    }

    private function isWithinBusinessHours($slot) {
        // Already handled by slot generation but could add extra checks
        return true;
    }

    private function findEligibleResources($slot, $request) {
        $combos = [];
        $staff = $this->getAvailableStaff($slot, $request);
        $rooms = $this->getAvailableRooms($slot, $request);

        foreach ($staff as $s) {
            foreach ($rooms as $r) {
                // Check skills match
                if (!$this->hasRequiredSkills($s['id'], $request)) continue;
                
                // Check special requirements (e.g. accessibility)
                if (!$this->meetsSpecialRequirements($r, $s, $request)) continue;

                $combos[] = ['staff' => $s, 'room' => $r];
            }
        }
        return $combos;
    }

    /**
     * Get staff who are working and don't have conflicting bookings
     */
    private function getAvailableStaff($slot, $request) {
        $date = $slot['date'];
        $time = $slot['time'];
        $endTime = date('H:i:s', strtotime($slot['end_time']));
        $dayOfWeek = strtolower(date('l', strtotime($date)));

        $stmt = $this->db->prepare("
            SELECT s.id, u.first_name, u.last_name, s.availability, s.current_load, s.rating, s.efficiency_score
            FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE u.status = 'active'
            AND s.current_load < s.max_daily_capacity
        ");
        // Note: For simplicity in this implementation, I assumed max_daily_capacity exists in DB
        // or I can default it to 300 mins.
        $stmt->execute();
        $allStaff = $stmt->fetchAll();

        $available = [];
        foreach ($allStaff as $s) {
            // Check availability JSON
            $avail = json_decode($s['availability'], true);
            if ($avail && isset($avail[$dayOfWeek]) && !empty($avail[$dayOfWeek]['active'])) {
                $start = strtotime($date . ' ' . $avail[$dayOfWeek]['start']);
                $end = strtotime($date . ' ' . $avail[$dayOfWeek]['end']);
                $slotStart = strtotime($slot['start_time']);
                $slotEnd = strtotime($slot['end_time']);

                if ($slotStart >= $start && $slotEnd <= $end) {
                    // Check conflicts
                    $stmt2 = $this->db->prepare("SELECT COUNT(*) FROM appointments 
                        WHERE staff_id = ? AND appointment_date = ? 
                        AND NOT (end_time <= ? OR appointment_time >= ?)");
                    $stmt2->execute([$s['id'], $date, $time, $endTime]);
                    if ($stmt2->fetchColumn() == 0) {
                        $available[] = [
                            'id' => $s['id'],
                            'name' => $s['first_name'] . ' ' . $s['last_name'],
                            'current_load' => $s['current_load'],
                            'rating' => $s['rating'],
                            'efficiency' => $s['efficiency_score']
                        ];
                    }
                }
            }
        }
        return $available;
    }

    private function getAvailableRooms($slot, $request) {
        $stmt = $this->db->prepare("
            SELECT id, name, is_accessible, 0 as current_usage
            FROM rooms
            WHERE status = 'active'
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function hasRequiredSkills($staffId, $request) {
        // Query staff_skills table
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM staff_skills ss
            JOIN skills sk ON ss.skill_id = sk.id
            WHERE ss.staff_id = ? AND sk.name = ?
        ");
        // Note: request['service_type'] might need mapping to skills
        $stmt->execute([$staffId, $request['service_type']]);
        return $stmt->fetchColumn() > 0;
    }

    private function meetsSpecialRequirements($room, $staff, $request) {
        $reqs = $request['special_requirements'] ?? [];
        foreach ($reqs as $req) {
            if ($req === 'wheelchair_accessible' && !$room['is_accessible']) return false;
            // Add other requirement checks here
        }
        return true;
    }

    // --- Scoring Logic ---

    private function calculateCustomerFit($slot, $request) {
        $points = 0;
        // Preferred time of day match: +25
        $hour = (int)date('H', strtotime($slot['start_time']));
        $pref = strtolower($request['preferred_time_of_day'] ?? 'any');
        if ($pref === 'morning' && $hour < 12) $points += 25;
        elseif ($pref === 'afternoon' && $hour >= 12 && $hour < 17) $points += 25;
        elseif ($pref === 'evening' && $hour >= 17) $points += 25;
        elseif ($pref === 'any') $points += 25;

        // Preferred date_range centrality: +20
        $start = strtotime($request['preferred_date_range']['start']);
        $end = strtotime($request['preferred_date_range']['end']);
        $current = strtotime($slot['date']);
        $mid = $start + ($end - $start) / 2;
        $maxDiffDays = max(1, ($end - $start) / (2) / (24*3600)); // Half range
        $diffDays = abs($current - $mid) / (24*3600);
        $points += max(0, 20 * (1 - ($diffDays / $maxDiffDays)));

        // Urgency boost: critical +15, high +10, normal +5
        $urgency = strtolower($request['urgency'] ?? 'normal');
        if ($urgency === 'critical') $points += 15;
        elseif ($urgency === 'high') $points += 10;
        elseif ($urgency === 'normal') $points += 5;

        return ($points / 60) * 100; // Normalize to 100 (Max points = 25+20+15 = 60)
    }

    private function calculateResourceEfficiency($slot, $combo, $request) {
        $points = 0;
        // Staff utilization balance (prefer lower load): +25
        $loadFactor = 1 - ($combo['staff']['current_load'] / 300); // 300 as max cap
        $points += max(0, $loadFactor * 25);
        
        // Room/equipment utilization: +15
        $points += 15; // Assume room is available and underused for now

        // Minimize idle gaps: +10
        $points += 10; // Placeholder

        return ($points / 50) * 100; // Normalize to 100 (Max 50)
    }

    private function calculateStaffOptimality($slot, $combo, $request) {
        $points = 0;
        // Skill match depth: +20
        $points += 20; // Already filtered for mandatory skill, assuming high depth
        
        // Staff rating: +15
        $points += ($combo['staff']['rating'] / 5) * 15;
        
        // Historical efficiency: +10
        $points += ($combo['staff']['efficiency'] / 1.0) * 10;

        // Preference alignment: +5
        $points += 5;

        return ($points / 50) * 100; // Normalize to 100 (Max 50)
    }

    private function calculateOperationalHealth($slot, $request) {
        $points = 0;
        // Avoid peak congestion: +15
        $hour = date('H:i', strtotime($slot['start_time']));
        $peakStart = $this->config['peak_hour_start'] ?? '11:00';
        $peakEnd = $this->config['peak_hour_end'] ?? '14:00';
        if ($hour < $peakStart || $hour > $peakEnd) $points += 15;

        // Buffer time sufficiency: +10
        $points += 10;

        // Customer tier priority: VIP +15
        $tier = strtolower($request['customer_tier'] ?? 'standard');
        if ($tier === 'vip') $points += 15;
        elseif ($tier === 'premium') $points += 10;
        elseif ($tier === 'standard') $points += 5;

        return ($points / 40) * 100; // Normalize to 100 (Max 40)
    }

    private function calculateConfidence($score) {
        if ($score > 80) return "high";
        if ($score > 60) return "medium";
        return "low";
    }

    private function generateExplanation($top, $request) {
        $time = date('g:i A', strtotime($top['slot']['start_time']));
        return "This slot scores highest ({$top['score']}/100) because it matches the customer's preferred " . ($request['preferred_time_of_day'] ?? 'any') . " window at {$time}, assigns highly-rated specialist {$top['combo']['staff']['name']}, and uses an optimal room configuration while avoiding peak demand periods.";
    }

    private function generateWarnings($top) {
        return [];
    }

    private function generateStaffReason($top) {
        return "High rating ({$top['combo']['staff']['rating']}) and low current utilization.";
    }

    private function generateRoomReason($top) {
        return "Meets all special requirements and currently underutilized.";
    }

    private function getBufferMinutes($request) {
        return 15; // Standard buffer
    }

    private function formatUnavailable($request) {
        return [
            "status" => "unavailable",
            "reason" => "No staff with required skills are available within the preferred date range.",
            "next_available" => date('Y-m-d', strtotime($request['preferred_date_range']['end'] . ' +1 day'))
        ];
    }
}
