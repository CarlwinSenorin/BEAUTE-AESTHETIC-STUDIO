<?php
/**
 * UI Helper Functions
 * Provides reusable functions for generating consistent UI elements
 */

/**
 * Get Font Awesome icon class for service category
 * 
 * @param string $category Service category
 * @return string Font Awesome icon class
 */
function getServiceIcon($category) {
    $icons = [
        'nails' => 'hand-sparkles',
        'eyebrows' => 'eye',
        'lashes' => 'eyelash',
        'wax' => 'spa',
        'massages' => 'hands',
        'facial' => 'face-smile',
        'skin_slimming' => 'heart-pulse'
    ];
    
    return $icons[$category] ?? 'spa';
}

/**
 * Get status badge HTML
 * 
 * @param string $status Status value
 * @param string $type Type of status (appointment, user, service, etc.)
 * @return string HTML for status badge
 */
function getStatusBadge($status, $type = 'appointment') {
    $color = getStatusColor($status, $type);
    $label = ucfirst(str_replace('_', ' ', $status));
    
    return sprintf(
        '<span class="badge badge-%s">%s</span>',
        $color,
        htmlspecialchars($label)
    );
}

/**
 * Get color class for status
 * 
 * @param string $status Status value
 * @param string $type Type of status
 * @return string Color class name
 */
function getStatusColor($status, $type = 'appointment') {
    $colors = [
        'appointment' => [
            'pending' => 'warning',
            'confirmed' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            'no_show' => 'secondary'
        ],
        'user' => [
            'active' => 'success',
            'inactive' => 'secondary'
        ],
        'service' => [
            'active' => 'success',
            'inactive' => 'secondary'
        ],
        'testimonial' => [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger'
        ]
    ];
    
    return $colors[$type][$status] ?? 'secondary';
}

/**
 * Format phone number consistently
 * 
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    // Format based on length
    if (strlen($cleaned) == 10) {
        return sprintf('(%s) %s-%s',
            substr($cleaned, 0, 3),
            substr($cleaned, 3, 3),
            substr($cleaned, 6)
        );
    }
    
    return $phone; // Return original if not standard format
}

/**
 * Get "time ago" format for timestamps
 * 
 * @param string $datetime Datetime string
 * @return string Human-readable time ago
 */
function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Generate alert HTML
 * 
 * @param string $message Alert message
 * @param string $type Alert type (success, error, warning, info)
 * @param bool $dismissible Whether alert can be dismissed
 * @return string HTML for alert
 */
function renderAlert($message, $type = 'info', $dismissible = true) {
    $icons = [
        'success' => 'check-circle',
        'error' => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    
    $icon = $icons[$type] ?? 'info-circle';
    $dismissBtn = $dismissible ? '<button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>' : '';
    
    return sprintf(
        '<div class="alert alert-%s">
            <i class="fas fa-%s"></i>
            <span>%s</span>
            %s
        </div>',
        $type,
        $icon,
        htmlspecialchars($message),
        $dismissBtn
    );
}

/**
 * Generate loading spinner HTML
 * 
 * @param string $text Loading text
 * @param string $size Size (small, medium, large)
 * @return string HTML for loading spinner
 */
function renderLoadingSpinner($text = 'Loading...', $size = 'medium') {
    return sprintf(
        '<div class="loading-spinner loading-%s">
            <i class="fas fa-spinner fa-spin"></i>
            <span>%s</span>
        </div>',
        $size,
        htmlspecialchars($text)
    );
}

/**
 * Generate empty state HTML
 * 
 * @param string $icon Font Awesome icon class
 * @param string $title Empty state title
 * @param string $message Empty state message
 * @param string $actionButton Optional action button HTML
 * @return string HTML for empty state
 */
function renderEmptyState($icon, $title, $message, $actionButton = '') {
    return sprintf(
        '<div class="empty-state">
            <i class="fas fa-%s"></i>
            <h3>%s</h3>
            <p>%s</p>
            %s
        </div>',
        $icon,
        htmlspecialchars($title),
        htmlspecialchars($message),
        $actionButton
    );
}

/**
 * Truncate text with ellipsis
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add (default: '...')
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate pagination HTML
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML for pagination
 */
function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= sprintf(
            '<a href="%s?page=%d" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>',
            $baseUrl,
            $currentPage - 1
        );
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= sprintf('<a href="%s?page=1" class="pagination-number">1</a>', $baseUrl);
        if ($start > 2) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $activeClass = $i == $currentPage ? ' active' : '';
        $html .= sprintf(
            '<a href="%s?page=%d" class="pagination-number%s">%d</a>',
            $baseUrl,
            $i,
            $activeClass,
            $i
        );
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
        $html .= sprintf('<a href="%s?page=%d" class="pagination-number">%d</a>', $baseUrl, $totalPages, $totalPages);
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= sprintf(
            '<a href="%s?page=%d" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>',
            $baseUrl,
            $currentPage + 1
        );
    }
    
    $html .= '</div>';
    
    return $html;
}
