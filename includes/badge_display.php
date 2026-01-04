<?php
function displayGroupBadges($group_id, $style = 'grid') {
    global $pdo;
    require_once 'includes/badges.php';
    $badgeSystem = new BadgeSystem($pdo);
    $badges = $badgeSystem->getGroupBadges($group_id);
    
    if (empty($badges)) {
        return '<div class="no-badges">
                    <span style="font-size: 2rem;">🏆</span>
                    <p>No badges earned yet</p>
                    <small>Complete tasks to earn badges!</small>
                </div>';
    }
    
    $html = '<div class="badges-' . $style . '">';
    foreach ($badges as $badge) {
        $icon = $badgeSystem->getBadgeIcon($badge['name']);
        $color = $badgeSystem->getBadgeColor($badge['name']);
        
        $html .= '
        <div class="badge-item" title="' . htmlspecialchars($badge['description']) . '">
            <div class="badge-icon" style="background: ' . $color['background'] . '; color: ' . $color['color'] . ';">
                ' . $icon . '
            </div>
            <div class="badge-info">
                <div class="badge-name">' . htmlspecialchars($badge['name']) . '</div>
                <div class="badge-date">Earned: ' . date('M j, Y', strtotime($badge['awarded_at'])) . '</div>
            </div>
        </div>';
    }
    $html .= '</div>';
    
    return $html;
}

function displayAvailableBadges($style = 'grid') {
    global $pdo;
    require_once 'includes/badges.php';
    $badgeSystem = new BadgeSystem($pdo);
    $badges = $badgeSystem->getAllBadges();
    
    $html = '<div class="available-badges ' . $style . '">';
    foreach ($badges as $badge) {
        $icon = $badgeSystem->getBadgeIcon($badge['name']);
        $color = $badgeSystem->getBadgeColor($badge['name']);
        
        $html .= '
        <div class="badge-preview" title="' . htmlspecialchars($badge['description']) . '">
            <div class="badge-icon-large" style="background: ' . $color['background'] . '; color: ' . $color['color'] . ';">
                ' . $icon . '
            </div>
            <div class="badge-details">
                <h4>' . htmlspecialchars($badge['name']) . '</h4>
                <p>' . htmlspecialchars($badge['description']) . '</p>
            </div>
        </div>';
    }
    $html .= '</div>';
    
    return $html;
}
?>