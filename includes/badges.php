<?php
require_once 'config/database.php';

class BadgeSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Check and award badges for a group
    public function checkAndAwardBadges($group_id) {
        $awarded_badges = [];
        
        // Check for First Clean badge
        if ($this->awardFirstCleanBadge($group_id)) {
            $awarded_badges[] = 'First Clean';
        }
        
        // Check for Streak Master badge
        if ($this->awardStreakMasterBadge($group_id)) {
            $awarded_badges[] = 'Streak Master';
        }
        
        // Check for Perfect Week badge
        if ($this->awardPerfectWeekBadge($group_id)) {
            $awarded_badges[] = 'Perfect Week';
        }
        
        // Check for Team Player badge
        if ($this->awardTeamPlayerBadge($group_id)) {
            $awarded_badges[] = 'Team Player';
        }
        
        // Check for Early Bird badge
        if ($this->awardEarlyBirdBadge($group_id)) {
            $awarded_badges[] = 'Early Bird';
        }
        
        return $awarded_badges;
    }
    
    // Award First Clean badge
    private function awardFirstCleanBadge($group_id) {
        // Check if group already has this badge
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ? AND b.name = 'First Clean'
        ");
        $stmt->execute([$group_id]);
        
        if ($stmt->fetch()) {
            return false; // Already has the badge
        }
        
        // Check if group has at least one approved submission
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM submissions 
            WHERE group_id = ? AND status = 'approved'
        ");
        $stmt->execute([$group_id]);
        $approved_count = $stmt->fetchColumn();
        
        if ($approved_count >= 1) {
            return $this->awardBadge($group_id, 'First Clean');
        }
        
        return false;
    }
    
    // Award Streak Master badge
    private function awardStreakMasterBadge($group_id) {
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ? AND b.name = 'Streak Master'
        ");
        $stmt->execute([$group_id]);
        
        if ($stmt->fetch()) {
            return false;
        }
        
        // Check for 7-day streak
        $stmt = $this->pdo->prepare("
            SELECT streak FROM points WHERE group_id = ?
        ");
        $stmt->execute([$group_id]);
        $streak = $stmt->fetchColumn();
        
        if ($streak >= 7) {
            return $this->awardBadge($group_id, 'Streak Master');
        }
        
        return false;
    }
    
    // Award Perfect Week badge
    private function awardPerfectWeekBadge($group_id) {
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ? AND b.name = 'Perfect Week'
        ");
        $stmt->execute([$group_id]);
        
        if ($stmt->fetch()) {
            return false;
        }
        
        // Check if group completed all tasks in the current week
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT t.id) as total_tasks,
                   COUNT(DISTINCT s.task_id) as completed_tasks
            FROM tasks t
            LEFT JOIN submissions s ON s.task_id = t.id AND s.group_id = ? AND s.status = 'approved'
            WHERE t.class_id = (SELECT class_id FROM groups WHERE id = ?)
            AND YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $stmt->execute([$group_id, $group_id]);
        $result = $stmt->fetch();
        
        if ($result && $result['total_tasks'] > 0 && $result['total_tasks'] == $result['completed_tasks']) {
            return $this->awardBadge($group_id, 'Perfect Week');
        }
        
        return false;
    }
    
    // Award Team Player badge (updated to match current attendance schema)
    private function awardTeamPlayerBadge($group_id) {
        // Already awarded?
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ? AND b.name = 'Team Player'
        ");
        $stmt->execute([$group_id]);
        
        if ($stmt->fetch()) {
            return false;
        }
        
        // Get total group members
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $total_members = (int)$stmt->fetchColumn();
        if ($total_members <= 0) {
            return false;
        }
        
        // Get distinct approved submission dates for this group in the last 7 days
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT DATE(submitted_at) AS sub_date
            FROM submissions
            WHERE group_id = ? AND status = 'approved' AND submitted_at >= CURDATE() - INTERVAL 7 DAY
            ORDER BY sub_date DESC
        ");
        $stmt->execute([$group_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($dates)) {
            return false;
        }
        
        // For each submission date, check if all group members were present (attendance.attendance_date)
        foreach ($dates as $sub_date) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT a.student_id) as present_count
                FROM attendance a
                WHERE a.attendance_date = ? AND a.status = 'present'
                AND a.student_id IN (SELECT student_id FROM group_members WHERE group_id = ?)
            ");
            $stmt->execute([$sub_date, $group_id]);
            $present_count = (int)$stmt->fetchColumn();
            
            if ($present_count === $total_members) {
                // All members present on this submission date -> award
                return $this->awardBadge($group_id, 'Team Player');
            }
        }
        
        return false;
    }
    
    // Award Early Bird badge
    private function awardEarlyBirdBadge($group_id) {
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ? AND b.name = 'Early Bird'
        ");
        $stmt->execute([$group_id]);
        
        if ($stmt->fetch()) {
            return false;
        }
        
        // Check for submission before 8 AM
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM submissions 
            WHERE group_id = ? AND status = 'approved' 
            AND HOUR(submitted_at) < 8
        ");
        $stmt->execute([$group_id]);
        $early_submissions = $stmt->fetchColumn();
        
        if ($early_submissions > 0) {
            return $this->awardBadge($group_id, 'Early Bird');
        }
        
        return false;
    }
    
    // Award a specific badge to a group (internal)
    private function awardBadge($group_id, $badge_name) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO group_badges (group_id, badge_id) 
                SELECT ?, id FROM badges WHERE name = ?
            ");
            $stmt->execute([$group_id, $badge_name]);
            return true;
        } catch (PDOException $e) {
            error_log("Error awarding badge: " . $e->getMessage());
            return false;
        }
    }
    
    // Public helper to award a badge by name (validates and prevents duplicates)
    public function awardBadgeByName($group_id, $badge_name) {
        // validate badge exists
        $stmt = $this->pdo->prepare("SELECT id FROM badges WHERE name = ? LIMIT 1");
        $stmt->execute([$badge_name]);
        $badge = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$badge) {
            return false;
        }
        // ensure not already awarded
        $stmt = $this->pdo->prepare("
            SELECT gb.id FROM group_badges gb
            WHERE gb.group_id = ? AND gb.badge_id = ?
            LIMIT 1
        ");
        $stmt->execute([$group_id, $badge['id']]);
        if ($stmt->fetch()) {
            return false; // already has it
        }
        return $this->awardBadge($group_id, $badge_name);
    }
    
    // Get all badges for a group
    public function getGroupBadges($group_id) {
        $stmt = $this->pdo->prepare("
            SELECT b.name, b.description, b.icon, gb.awarded_at
            FROM group_badges gb
            JOIN badges b ON b.id = gb.badge_id
            WHERE gb.group_id = ?
            ORDER BY gb.awarded_at DESC
        ");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll();
    }
    
    // Get all available badges
    public function getAllBadges() {
        $stmt = $this->pdo->prepare("SELECT * FROM badges ORDER BY id");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Standardized badge icon getter
    public function getBadgeIcon($badge_name, $use_unicode = true) {
        if ($use_unicode) {
            switch($badge_name) {
                case 'First Clean': return '⭐';
                case 'Streak Master': return '🔥';
                case 'Perfect Week': return '🏆';
                case 'Team Player': return '👥';
                case 'Early Bird': return '🌅';
                default: return '🎖️';
            }
        } else {
            // Fallback to Font Awesome
            switch($badge_name) {
                case 'First Clean': return 'fas fa-star';
                case 'Streak Master': return 'fas fa-fire';
                case 'Perfect Week': return 'fas fa-trophy';
                case 'Team Player': return 'fas fa-users';
                case 'Early Bird': return 'fas fa-sun';
                default: return 'fas fa-award';
            }
        }
    }
    
    // Get badge color scheme
    public function getBadgeColor($badge_name) {
        switch($badge_name) {
            case 'First Clean': return ['background' => 'linear-gradient(135deg, #f59e0b, #d97706)', 'color' => 'white'];
            case 'Streak Master': return ['background' => 'linear-gradient(135deg, #dc2626, #b91c1c)', 'color' => 'white'];
            case 'Perfect Week': return ['background' => 'linear-gradient(135deg, #16a34a, #15803d)', 'color' => 'white'];
            case 'Team Player': return ['background' => 'linear-gradient(135deg, #7c3aed, #6d28d9)', 'color' => 'white'];
            case 'Early Bird': return ['background' => 'linear-gradient(135deg, #ea580c, #c2410c)', 'color' => 'white'];
            default: return ['background' => 'linear-gradient(135deg, #6b7280, #4b5563)', 'color' => 'white'];
        }
    }
}
?>