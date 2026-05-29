<?php
/**
 * Stat Card Component
 * Renders a consistent statistics card for dashboards
 * 
 * @param string $icon Font Awesome icon class
 * @param string $value Statistic value
 * @param string $label Statistic label
 * @param string $color Background color (CSS color or class)
 * @param string $trend Optional trend indicator ('+10%', '-5%', etc.)
 * @param string $link Optional link URL
 */
function renderStatCard($icon, $value, $label, $color = '#17a2b8', $trend = '', $link = '') {
    $cardContent = '
    <div class="stat-card">
        <div class="stat-icon" style="background: ' . htmlspecialchars($color) . ';">
            <i class="fas fa-' . htmlspecialchars($icon) . '"></i>
        </div>
        <div class="stat-content">
            <h3>' . htmlspecialchars($value) . '</h3>
            <p>' . htmlspecialchars($label) . '</p>';
    
    if (!empty($trend)) {
        $trendClass = (strpos($trend, '+') === 0) ? 'trend-up' : 'trend-down';
        $trendIcon = (strpos($trend, '+') === 0) ? 'arrow-up' : 'arrow-down';
        $cardContent .= '
            <div class="stat-trend ' . $trendClass . '">
                <i class="fas fa-' . $trendIcon . '"></i> ' . htmlspecialchars($trend) . '
            </div>';
    }
    
    $cardContent .= '
        </div>
    </div>';
    
    if (!empty($link)) {
        echo '<a href="' . htmlspecialchars($link) . '" class="stat-card-link">' . $cardContent . '</a>';
    } else {
        echo $cardContent;
    }
}
?>
