<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

// Get student data
$user_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$role = $_SESSION['role'];

// Initialize variables
$group = null;
$tasks = [];
$recent_submissions = [];
$group_members = [];

try {
    if ($role == 'student') {
        // Get student's group and points
        $stmt = $pdo->prepare("
            SELECT g.*, p.points, p.streak, c.name as class_name, c.id as class_id
            FROM group_members gm
            JOIN groups g ON g.id = gm.group_id
            JOIN classes c ON c.id = g.class_id
            LEFT JOIN points p ON p.group_id = g.id
            WHERE gm.student_id = ?
        ");
        $stmt->execute([$user_id]);
        $group = $stmt->fetch();

        if ($group) {
            // Get today's tasks
            $stmt = $pdo->prepare("
                SELECT t.* 
                FROM tasks t
                WHERE t.class_id = ? AND DATE(t.created_at) = CURDATE()
            ");
            $stmt->execute([$group['class_id']]);
            $tasks = $stmt->fetchAll() ?: [];

            // Get recent submissions
            $stmt = $pdo->prepare("
                SELECT s.*, t.name as task_name
                FROM submissions s
                JOIN tasks t ON t.id = s.task_id
                WHERE s.group_id = ?
                ORDER BY s.submitted_at DESC
                LIMIT 5
            ");
            $stmt->execute([$group['id']]);
            $recent_submissions = $stmt->fetchAll() ?: [];

            // Get group members
            $stmt = $pdo->prepare("
                SELECT u.id, u.name 
                FROM group_members gm
                JOIN users u ON u.id = gm.student_id
                WHERE gm.group_id = ?
            ");
            $stmt->execute([$group['id']]);
            $group_members = $stmt->fetchAll() ?: [];
        }
    } else {
        header('Location: teacher_dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $database_error = "System is initializing. Some features may not be available yet.";
}

require_once 'includes/header.php';
?>

<div class="page-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2 class="sidebar-title">Menu</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="join_class.php" class="sidebar-link">
                <i class="fas fa-users"></i> Classes
            </a>
            <a href="leaderboard.php" class="sidebar-link">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <a href="submit_task.php" class="sidebar-link">
                <i class="fas fa-camera"></i> Submit
            </a>
            <a href="student_profile.php" class="sidebar-link">
                <i class="fas fa-user"></i> Profile
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Header -->
            <div class="page-header">
                <div class="header-content">
                    <h1 class="page-title">Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>
                    <p class="page-subtitle">
                        <?php if($group): ?>
                            Team <?php echo htmlspecialchars($group['name']); ?> • <?php echo htmlspecialchars($group['class_name']); ?>
                        <?php else: ?>
                            Join a class to start earning XP
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="header-stats">
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $group['points'] ?? 0; ?></div>
                            <div class="stat-label">XP</div>
                        </div>
                    </div>
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $group['streak'] ?? 0; ?></div>
                            <div class="stat-label">Day Streak</div>
                        </div>
                    </div>
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo count($tasks); ?></div>
                            <div class="stat-label">Missions</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if(!$group): ?>
                <!-- No Group State -->
                <div class="empty-state pixel-border">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h2>Ready to Start Your Quest?</h2>
                    <p>Join a class to begin your cleaning adventure and earn XP with your squad!</p>
                    <a href="join_class.php" class="btn btn-primary pixel-button">
                        <i class="fas fa-sign-in-alt"></i>
                        Join a Class
                    </a>
                </div>

            <?php else: ?>
                <!-- Student Dashboard Content -->
                <div class="dashboard-grid">
                    
                    <!-- Today's Missions -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-tasks"></i>
                                Today's Missions
                            </h2>
                            <span class="card-badge pixel-border"><?php echo count($tasks); ?></span>
                        </div>
                        
                        <div class="card-body">
                            <?php if(!empty($tasks)): ?>
                                <div class="missions-list">
                                    <?php foreach($tasks as $task): ?>
                                    <div class="mission-item pixel-border">
                                        <div class="mission-main">
                                            <div class="mission-icon pixel-border">
                                                <i class="fas fa-broom"></i>
                                            </div>
                                            <div class="mission-details">
                                                <h4 class="mission-title"><?php echo htmlspecialchars($task['name']); ?></h4>
                                                <p class="mission-location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($task['cleaning_area']); ?>
                                                </p>
                                                <?php if(!empty($task['description'])): ?>
                                                    <p class="mission-desc"><?php echo htmlspecialchars($task['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mission-xp">
                                                +<?php echo $task['points']; ?> XP
                                            </div>
                                        </div>
                                        <div class="mission-actions">
                                            <a href="submit_task.php?task_id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary pixel-button">
                                                <i class="fas fa-camera"></i>
                                                Submit Proof
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-card pixel-border">
                                    <i class="fas fa-clipboard-check"></i>
                                    <p>All missions completed! 🎉</p>
                                    <small>Great work, champion!</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Squad Info -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-users"></i>
                                Your Squad
                            </h2>
                            <span class="card-badge pixel-border"><?php echo count($group_members); ?></span>
                        </div>
                        
                        <div class="card-body">
                            <div class="squad-header">
                                <div class="squad-avatar pixel-border">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="squad-info">
                                    <h4><?php echo htmlspecialchars($group['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($group['class_name']); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($group_members)): ?>
                            <div class="members-list">
                                <h5 class="section-label">Squad Members</h5>
                                <div class="members-grid">
                                    <?php foreach($group_members as $member): ?>
                                    <div class="member-item pixel-border <?php echo $member['id'] == $user_id ? 'you' : ''; ?>">
                                        <div class="member-avatar pixel-border">
                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                        </div>
                                        <span class="member-name">
                                            <?php echo $member['id'] == $user_id ? 'You' : htmlspecialchars($member['name']); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Squad Achievements -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-award"></i>
                                Squad Achievements
                            </h2>
                        </div>
                        
                        <div class="card-body">
                            <?php
                            require_once 'includes/badge_display.php';
                            echo displayGroupBadges($group['id']);
                            ?>
                        </div>
                    </div>

                    <!-- Leaderboard Preview -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-trophy"></i>
                                Global Rankings
                            </h2>
                        </div>
                        
                        <div class="card-body">
                            <div class="ranking-preview">
                                <div class="ranking-icon pixel-border">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="ranking-info">
                                    <h4>Section Wars</h4>
                                    <p>See how your class ranks in the school competition!</p>
                                    
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT c.name, COALESCE(SUM(p.points), 0) as total_points
                                            FROM classes c
                                            LEFT JOIN groups g ON g.class_id = c.id
                                            LEFT JOIN points p ON p.group_id = g.id
                                            GROUP BY c.id, c.name
                                            ORDER BY total_points DESC
                                            LIMIT 5
                                        ");
                                        $stmt->execute();
                                        $top_sections = $stmt->fetchAll();
                                        
                                        $current_rank = null;
                                        foreach($top_sections as $index => $section) {
                                            if($section['name'] == $group['class_name']) {
                                                $current_rank = $index + 1;
                                                break;
                                            }
                                        }
                                        
                                        if($current_rank): ?>
                                            <div class="current-ranking pixel-border">
                                                <div class="rank-badge rank-<?php echo min($current_rank, 3); ?> pixel-border">
                                                    #<?php echo $current_rank; ?>
                                                </div>
                                                <div class="rank-details">
                                                    <strong>Your Section Rank</strong>
                                                    <span>Top <?php echo $current_rank; ?> in school</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                    <?php } catch (PDOException $e) {
                                        // Silently fail
                                    } ?>
                                </div>
                            </div>
                            <a href="leaderboard.php" class="btn btn-outline btn-block pixel-button">
                                View Full Leaderboard
                            </a>
                        </div>
                    </div>

                    <!-- Mission Log -->
                    <?php if(!empty($recent_submissions)): ?>
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-history"></i>
                                Mission Log
                            </h2>
                        </div>
                        
                        <div class="card-body">
                            <div class="activity-list">
                                <?php foreach($recent_submissions as $activity): ?>
                                <div class="activity-item pixel-border">
                                    <div class="activity-icon status-<?php echo $activity['status']; ?> pixel-border">
                                        <i class="fas fa-<?php echo $activity['status'] == 'approved' ? 'check' : ($activity['status'] == 'rejected' ? 'times' : 'clock'); ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p class="activity-text"><?php echo htmlspecialchars($activity['task_name']); ?></p>
                                        <div class="activity-meta">
                                            <span class="status-badge status-<?php echo $activity['status']; ?> pixel-border">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                            <span class="activity-time">
                                                <?php echo date('M j, g:i A', strtotime($activity['submitted_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Reset */
* {
    box-sizing: border-box;
}
html, body {
    height: 100%;
    margin: 0;
    font-family: 'Courier New', monospace;
    background-color: var(--bg-primary);
}

/* Page wrapper: flex container for sidebar + main content */
.page-wrapper {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

/* ================== SIDEBAR STYLES FROM STUDENT_PROFILE ================== */
.sidebar {
    width: 220px;
    background: #fff;
    border-right: 3px solid #000;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 999;
    padding-top: 1rem;
}
.sidebar-header {
    padding: 0 1rem;
    margin-bottom: 1rem;
}
.sidebar-title {
    font-size: 1.2rem;
    font-weight: bold;
    font-family: 'Courier New', monospace;
}
.sidebar-nav {
    display: flex;
    flex-direction: column;
}
.sidebar-link {
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    font-size: 1rem;
    color: #333;
    text-decoration: none;
    gap: 0.5rem;
    transition: background 0.2s;
    font-family: 'Courier New', monospace;
}
.sidebar-link i {
    font-size: 1.2rem;
}
.sidebar-link:hover {
    background-color: #f1f3f5;
    border-radius: 4px;
}
.sidebar-link.active {
    background-color: #e0f7fa;
    font-weight: 600;
}
.sidebar-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Main content styles */
.main-content {
    margin-left: 220px; /* same as sidebar width */
    padding: 20px;
    width: calc(100% - 220px);
    min-height: 100vh;
}

/* Pixel Styling Variables */
:root {
    --pixel-size: 2px;
    --primary: #3a86ff;
    --primary-dark: #1e6feb;
    --primary-light: #6b84ff;
    --secondary: #ff6b4a;
    --success: #4caf50;
    --warning: #ff9800;
    --danger: #f44336;
    --light: #f8f9fa;
    --dark: #2d3748;
    --border: #e2e8f0;
    --text: #2d3748;
    --text-muted: #64748b;
    --card-bg: #ffffff;
    --bg-primary: #f0f4ff;
    --bg-secondary: #e8edff;
}

/* Pixel Borders */
.pixel-border {
    border: var(--pixel-size) solid #000;
    border-radius: 0;
    box-shadow: 
        calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
    position: relative;
}

.pixel-border::before {
    content: '';
    position: absolute;
    top: calc(var(--pixel-size) * -1);
    left: calc(var(--pixel-size) * -1);
    right: calc(var(--pixel-size) * -1);
    bottom: calc(var(--pixel-size) * -1);
    border: var(--pixel-size) solid #000;
    pointer-events: none;
}

.pixel-button {
    border: var(--pixel-size) solid #000;
    border-radius: 0;
    box-shadow: 
        calc(var(--pixel-size) * 1) calc(var(--pixel-size) * 1) 0 #000;
    transition: all 0.1s ease;
    position: relative;
    background: var(--primary);
    color: white;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.pixel-button:hover {
    transform: translate(1px, 1px);
    box-shadow: 
        calc(var(--pixel-size) * 0.5) calc(var(--pixel-size) * 0.5) 0 #000;
    color: white;
    text-decoration: none;
}

.pixel-button:active {
    transform: translate(2px, 2px);
    box-shadow: none;
}

.pixel-button.btn-outline {
    background: transparent;
    color: var(--text);
    border-color: var(--border);
}

.pixel-button.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pixel-button.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

.pixel-button.btn-block {
    width: 100%;
    justify-content: center;
}

/* Base Layout */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2.5rem;
    gap: 2rem;
    flex-wrap: wrap;
}

.header-content {
    flex: 1;
    min-width: 300px;
}

.page-title {
    margin: 0 0 0.75rem 0;
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--dark);
    text-shadow: 2px 2px 0 #000;
    letter-spacing: -0.5px;
}

.page-subtitle {
    margin: 0;
    color: var(--text-muted);
    font-size: 1.1rem;
    font-weight: 500;
}

.header-stats {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.stat-card {
    background: var(--card-bg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 160px;
    transition: all 0.2s ease;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.stat-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    border: var(--pixel-size) solid rgba(255, 255, 255, 0.5);
}

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 600;
}

/* Cards */
.card {
    background: var(--card-bg);
    margin-bottom: 2rem;
    transition: all 0.2s ease;
}

.card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.card-header {
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: var(--pixel-size) solid #000;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-secondary);
}

.card-title {
    margin: 0;
    font-size: 1.375rem;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-badge {
    background: var(--secondary);
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 700;
}

.card-body {
    padding: 1.5rem;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

/* Missions */
.missions-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.mission-item {
    background: white;
    padding: 1.5rem;
    transition: all 0.2s;
}

.mission-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.mission-main {
    display: flex;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
    align-items: flex-start;
}

.mission-icon {
    width: 60px;
    height: 60px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.5rem;
    flex-shrink: 0;
}

.mission-details {
    flex: 1;
}

.mission-title {
    margin: 0 0 0.75rem 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
}

.mission-location {
    margin: 0 0 0.75rem 0;
    color: var(--text-muted);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.mission-desc {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.5;
}

.mission-xp {
    font-size: 1.375rem;
    font-weight: 800;
    color: var(--primary);
    flex-shrink: 0;
}

.mission-actions {
    text-align: right;
}

/* Squad Section */
.squad-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
    padding-bottom: 1.25rem;
    border-bottom: var(--pixel-size) solid #000;
}

.squad-avatar {
    width: 70px;
    height: 70px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.squad-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.375rem;
    font-weight: 700;
}

.squad-info p {
    margin: 0;
    color: var(--text-muted);
    font-size: 1rem;
}

.members-list h5 {
    margin: 0 0 1.25rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 1rem;
}

.member-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem;
    background: var(--light);
    transition: all 0.2s;
    text-align: center;
}

.member-item.you {
    background: var(--primary);
    color: white;
}

.member-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
}

.member-avatar {
    width: 50px;
    height: 50px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: var(--primary);
    font-size: 1.25rem;
}

.member-item.you .member-avatar {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-color: white;
}

.member-name {
    font-size: 0.9rem;
    font-weight: 600;
}

/* Ranking */
.ranking-preview {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}

.ranking-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.ranking-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.ranking-info p {
    margin: 0 0 1.25rem 0;
    color: var(--text-muted);
    font-size: 0.95rem;
}

.current-ranking {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: var(--light);
}

.rank-badge {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: white;
    font-size: 1.375rem;
    flex-shrink: 0;
}

.rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); }
.rank-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); }
.rank-3 { background: linear-gradient(135deg, #CD7F32, #A66A28); }

.rank-details {
    flex: 1;
}

.rank-details strong {
    display: block;
    margin-bottom: 0.375rem;
    font-size: 1rem;
}

.rank-details span {
    font-size: 0.9rem;
    color: var(--text-muted);
}

/* Activity */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: var(--light);
    transition: all 0.2s;
}

.activity-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
}

.activity-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.status-pending { background: var(--warning); }
.status-approved { background: var(--success); }
.status-rejected { background: var(--danger); }

.activity-details {
    flex: 1;
}

.activity-text {
    margin: 0 0 0.75rem 0;
    font-weight: 600;
    font-size: 1rem;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.status-badge {
    padding: 0.375rem 0.875rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.status-pending { background: #fff3cd; color: #856404; }
.status-badge.status-approved { background: #d4edda; color: #155724; }
.status-badge.status-rejected { background: #f8d7da; color: #721c24; }

.activity-time {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    margin: 2rem 0;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1.75rem;
    opacity: 0.7;
}

.empty-state h2 {
    margin: 0 0 1.25rem 0;
    color: var(--text);
    font-size: 1.75rem;
}

.empty-state p {
    margin: 0 0 2.5rem 0;
    color: var(--text-muted);
    font-size: 1.1rem;
    line-height: 1.5;
}

.empty-card {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
    background: var(--light);
}

.empty-card i {
    font-size: 3rem;
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-card p {
    margin: 0 0 0.75rem 0;
    font-weight: 700;
    font-size: 1.1rem;
}

.empty-card small {
    font-size: 0.875rem;
}

/* Mobile menu toggle */
.mobile-menu-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1000;
    background: var(--primary);
    color: white;
    border: 2px solid #000;
    padding: 10px;
    cursor: pointer;
    box-shadow: 2px 2px 0 #000;
    font-size: 1.2rem;
    display: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }
    
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .container {
        padding: 0.75rem;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
        gap: 1.75rem;
    }
    
    .header-stats {
        justify-content: center;
        width: 100%;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .mission-main {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .mission-xp {
        text-align: center;
    }
    
    .ranking-preview {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .current-ranking {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .squad-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .members-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .activity-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .card-header {
        padding: 1.25rem 1.25rem 0.75rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }

    .mobile-menu-toggle {
        display: block;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0.5rem;
    }
    
    .header-stats {
        gap: 1rem;
    }
    
    .stat-card {
        min-width: 140px;
        padding: 1.25rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .members-grid {
        grid-template-columns: 1fr;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
    const mobileMenuToggle = document.createElement('button');
    mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    mobileMenuToggle.className = 'mobile-menu-toggle';
    mobileMenuToggle.style.display = 'none';
    document.body.appendChild(mobileMenuToggle);

    function checkScreenSize() {
        if (window.innerWidth <= 768) {
            mobileMenuToggle.style.display = 'block';
        } else {
            mobileMenuToggle.style.display = 'none';
            document.querySelector('.sidebar').classList.remove('active');
        }
    }

    mobileMenuToggle.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    window.addEventListener('resize', checkScreenSize);
    checkScreenSize();

    // Add pixelated hover effects
    const pixelItems = document.querySelectorAll('.pixel-border');
    pixelItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translate(-2px, -2px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translate(0, 0)';
        });
    });

    // Sidebar navigation interaction
    const navItems = document.querySelectorAll('.sidebar-nav .sidebar-link:not(.disabled)');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return false;
            }
            
            // Add click feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});
</script>