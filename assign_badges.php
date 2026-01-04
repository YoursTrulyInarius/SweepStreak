<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// Get teacher's classes
$classes = [];
$total_students = 0;
$pending_submissions = 0;

try {
    // Get teacher's classes with counts
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(DISTINCT gm.student_id) as student_count,
               COUNT(DISTINCT g.id) as group_count
        FROM classes c
        LEFT JOIN groups g ON g.class_id = c.id
        LEFT JOIN group_members gm ON gm.group_id = g.id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.name
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll() ?: [];

    // Get total students
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT gm.student_id) as count
        FROM group_members gm
        JOIN groups g ON g.id = gm.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $total_students = $stmt->fetch()['count'];

    // Get pending submissions count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM submissions s
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE c.teacher_id = ? AND s.status = 'pending'
    ");
    $stmt->execute([$teacher_id]);
    $pending_submissions = $stmt->fetch()['count'];

} catch (PDOException $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Check if teacher has classes for sidebar nav
$has_classes = !empty($classes);

// Handle form submission for badge assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_id = $_POST['group_id'];
    $badge_id = $_POST['badge_id'];
    
    try {
        // Check if badge already awarded to this group
        $stmt = $pdo->prepare("SELECT id FROM group_badges WHERE group_id = ? AND badge_id = ?");
        $stmt->execute([$group_id, $badge_id]);
        $existing_badge = $stmt->fetch();
        
        if ($existing_badge) {
            $error = "This group already has this badge!";
        } else {
            // Use the correct columns that exist in your database
            $stmt = $pdo->prepare("INSERT INTO group_badges (group_id, badge_id, awarded_at) VALUES (?, ?, NOW())");
            $stmt->execute([$group_id, $badge_id]);
            
            $_SESSION['success'] = "Badge awarded successfully!";
            header('Location: assign_badges.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error awarding badge: " . $e->getMessage();
    }
}

// Function to get badge icon - with better emoji support
function getBadgeIcon($badge_name, $db_icon = '') {
    // First, try to use the database icon if it exists and is not empty
    if (!empty($db_icon) && trim($db_icon) !== '') {
        return '<span class="badge-emoji" data-emoji="' . htmlspecialchars($db_icon) . '">' . $db_icon . '</span>';
    }
    
    // Fallback to Font Awesome icons based on badge name
    $icon_mapping = [
        'First Clean' => 'fas fa-star',
        'Streak Master' => 'fas fa-fire',
        'Perfect Week' => 'fas fa-trophy',
        'Team Player' => 'fas fa-users',
        'Early Bird' => 'fas fa-sun'
    ];
    
    $fa_icon = $icon_mapping[$badge_name] ?? 'fas fa-award';
    return '<i class="' . $fa_icon . '"></i>';
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
      <a href="teacher_dashboard.php" class="sidebar-link">
        <i class="fas fa-home"></i> Dashboard
      </a>
      <a href="view_classes.php" class="sidebar-link">
        <i class="fas fa-users"></i> Classes
      </a>
      <a href="manage_groups.php" class="sidebar-link <?php echo !$has_classes ? 'disabled' : ''; ?>">
        <i class="fas fa-users"></i> Groups
      </a>
      <a href="assign_tasks.php" class="sidebar-link <?php echo !$has_classes ? 'disabled' : ''; ?>">
        <i class="fas fa-tasks"></i> Tasks
      </a>
      <a href="leaderboard.php" class="sidebar-link">
        <i class="fas fa-trophy"></i> Leaderboard
      </a>
      <a href="review_submissions.php" class="sidebar-link">
        <i class="fas fa-check-double"></i> Review
        <?php if($pending_submissions > 0): ?>
          <span class="pending-badge"><?php echo $pending_submissions; ?></span>
        <?php endif; ?>
      </a>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="container">
        <!-- Welcome Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">Award Badges</h1>
                <p class="welcome-subtitle">Recognize outstanding group achievements</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-badge">
                    <div class="stat-number"><?php echo count($classes); ?></div>
                    <div class="stat-label">Classes</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-number"><?php echo $pending_submissions; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>

        <!-- Badge Assignment Form Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Award New Badge</h2>
                <span class="view-all">Recognize group achievements</span>
            </div>

            <?php if(empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>No Classes Available</h3>
                    <p>You need to create a class and groups before you can award badges.</p>
                    <div class="empty-actions">
                        <a href="create_class.php" class="game-btn game-btn-primary">
                            <i class="fas fa-plus-circle"></i>
                            Create Your First Class
                        </a>
                        <a href="teacher_dashboard.php" class="game-btn game-btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if(isset($error)): ?>
                    <div class="game-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="game-alert game-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <div class="badge-form-container">
                    <form method="POST" class="badge-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="game-label">Select Group *</label>
                                <select name="group_id" class="game-input" required>
                                    <option value="">Choose a group...</option>
                                    <?php 
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT g.*, c.name as class_name
                                            FROM groups g
                                            JOIN classes c ON c.id = g.class_id
                                            WHERE c.teacher_id = ?
                                            ORDER BY c.name, g.name
                                        ");
                                        $stmt->execute([$teacher_id]);
                                        $groups = $stmt->fetchAll() ?: [];
                                        
                                        foreach($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>">
                                                <?php echo htmlspecialchars($group['class_name']); ?> - 
                                                <?php echo htmlspecialchars($group['name']); ?>
                                            </option>
                                        <?php endforeach;
                                    } catch (PDOException $e) {
                                        echo '<option value="">Error loading groups</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="game-label">Select Badge *</label>
                                <select name="badge_id" class="game-input" required>
                                    <option value="">Choose a badge...</option>
                                    <?php 
                                    try {
                                        $stmt = $pdo->prepare("SELECT * FROM badges ORDER BY name");
                                        $stmt->execute();
                                        $badges = $stmt->fetchAll() ?: [];
                                        
                                        if(empty($badges)): ?>
                                            <option value="">No badges available in system</option>
                                        <?php else: ?>
                                            <?php foreach($badges as $badge): ?>
                                                <option value="<?php echo $badge['id']; ?>">
                                                    <?php echo htmlspecialchars($badge['name']); ?> - <?php echo htmlspecialchars($badge['description']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif;
                                    } catch (PDOException $e) {
                                        echo '<option value="">Error loading badges</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="game-btn game-btn-primary btn-large">
                                <i class="fas fa-award"></i>
                                Award Badge
                            </button>
                            <a href="teacher_dashboard.php" class="game-btn game-btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Badges Section -->
        <?php if(!empty($classes)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Available Badges</h2>
                <span class="view-all">Badges students can earn</span>
            </div>

            <div class="available-badges">
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM badges ORDER BY name");
                    $stmt->execute();
                    $available_badges = $stmt->fetchAll() ?: [];

                    if(empty($available_badges)): ?>
                        <div class="empty-state small">
                            <i class="fas fa-award"></i>
                            <p>No badges available in system</p>
                            <small>Contact administrator to add badges</small>
                        </div>
                    <?php else: ?>
                        <div class="badges-grid">
                            <?php foreach($available_badges as $badge): ?>
                            <div class="badge-card">
                                <div class="badge-icon-large">
                                    <?php echo getBadgeIcon($badge['name'], $badge['icon']); ?>
                                </div>
                                <div class="badge-info">
                                    <h4 class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></h4>
                                    <p class="badge-description"><?php echo htmlspecialchars($badge['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif;
                } catch (PDOException $e) {
                    echo '<div class="empty-state small"><p>Error loading available badges</p></div>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recently Awarded Badges Section -->
        <?php if(!empty($classes)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recently Awarded Badges</h2>
                <a href="badge_history.php" class="view-all">View All Awards</a>
            </div>

            <div class="recent-badges">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT gb.*, g.name as group_name, c.name as class_name, b.name as badge_name, b.icon as badge_icon, b.description as badge_description
                        FROM group_badges gb
                        JOIN groups g ON g.id = gb.group_id
                        JOIN classes c ON c.id = g.class_id
                        JOIN badges b ON b.id = gb.badge_id
                        WHERE c.teacher_id = ?
                        ORDER BY gb.awarded_at DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$teacher_id]);
                    $recent_badges = $stmt->fetchAll() ?: [];

                    if(empty($recent_badges)): ?>
                        <div class="empty-state small">
                            <i class="fas fa-award"></i>
                            <p>No badges awarded yet</p>
                            <small>Recently awarded badges will appear here</small>
                        </div>
                    <?php else: ?>
                        <div class="badges-list">
                            <?php foreach($recent_badges as $badge): ?>
                            <div class="badge-item">
                                <div class="badge-icon">
                                    <?php echo getBadgeIcon($badge['badge_name'], $badge['badge_icon']); ?>
                                </div>
                                <div class="badge-content">
                                    <div class="badge-header">
                                        <h4 class="badge-name"><?php echo htmlspecialchars($badge['badge_name']); ?></h4>
                                        <span class="badge-date"><?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?></span>
                                    </div>
                                    <div class="badge-meta">
                                        <span class="badge-group"><?php echo htmlspecialchars($badge['class_name']); ?> - <?php echo htmlspecialchars($badge['group_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif;
                } catch (PDOException $e) {
                    echo '<div class="empty-state small"><p>Error loading recent badges</p></div>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
  </div>
</div>

<style>
/* Reset and Base Styles */
* {
    box-sizing: border-box;
}
html, body {
    height: 100%;
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #f8f9fa;
}

/* Page wrapper: flex container for sidebar + main content */
.page-wrapper {
    display: flex;
    height: 100vh;
    width: 100%;
}

/* Sidebar styles */
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
.pending-badge {
    background: #ff6b6b;
    color: #fff;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    font-weight: bold;
}

/* Main content styles */
.main-content {
    margin-left: 220px; /* same as sidebar width */
    padding: 20px;
    width: calc(100% - 220px);
    overflow-y: auto;
    height: 100vh;
}

/* Layout and spacing */
.container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1rem;
}

/* Dashboard Header */
.dashboard-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.welcome-section {
    flex: 1 1 auto;
    min-width: 220px;
}
.welcome-title {
    font-family: 'Courier New', monospace;
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: #3a86ff;
    text-shadow: 2px 2px 0 #000;
}
.welcome-subtitle {
    font-size: 0.95rem;
    color: #6c757d;
}
.header-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.stat-badge {
    background: #fff;
    border: 2px solid #f1f3f5;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    text-align: center;
    min-width: 100px;
    box-shadow: 1px 1px 4px rgba(0,0,0,0.05);
}
.stat-number {
    font-weight: bold;
    font-size: 1.2rem;
}
.stat-label {
    font-size: 0.75rem;
    color: #6c757d;
}

/* Badge Form Styles */
.badge-form-container {
    background: white;
    border: 3px solid #000;
    box-shadow: 4px 4px 0 #000;
    padding: 2rem;
    margin-bottom: 2rem;
}

.badge-form {
    max-width: 100%;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

/* Available Badges Grid */
.available-badges {
    margin-bottom: 2rem;
}

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.badge-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border: 2px solid #000;
    box-shadow: 3px 3px 0 #000;
    border-radius: 0;
    transition: all 0.2s ease;
}

.badge-card:hover {
    transform: translate(2px, 2px);
    box-shadow: 1px 1px 0 #000;
}

.badge-icon-large {
    width: 60px;
    height: 60px;
    background: #ffd700;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #000;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

/* Emoji styling - FORCE COLOR EMOJI DISPLAY */
.badge-emoji {
    font-size: 2.5rem !important;
    line-height: 1 !important;
    display: block !important;
    /* Force color emoji support */
    font-family: "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji", "Android Emoji", "EmojiOne Color", "Twemoji Mozilla", sans-serif !important;
    font-weight: normal !important;
    text-rendering: optimizeLegibility !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    /* Remove any filters that might make it monochrome */
    filter: none !important;
    -webkit-filter: none !important;
    /* Ensure color */
    color: inherit !important;
    /* Make sure it's visible */
    opacity: 1 !important;
    visibility: visible !important;
}

/* Test with specific emoji colors */
.badge-emoji[data-emoji*="⭐"] { color: #ffd700 !important; }
.badge-emoji[data-emoji*="🔥"] { color: #ff6b35 !important; }
.badge-emoji[data-emoji*="🏆"] { color: #ffd700 !important; }
.badge-emoji[data-emoji*="👥"] { color: #4a90e2 !important; }
.badge-emoji[data-emoji*="🌅"] { color: #ff8c42 !important; }

/* Font Awesome icon styling */
.badge-icon-large i {
    font-size: 1.8rem;
    color: #000;
}

.badge-info {
    flex: 1;
}

.badge-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: #333;
}

.badge-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Recent Badges Styles */
.recent-badges {
    margin-bottom: 2rem;
}

.badges-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.badge-item {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border: 2px solid #000;
    box-shadow: 3px 3px 0 #000;
    border-radius: 0;
    transition: all 0.2s ease;
}

.badge-item:hover {
    transform: translate(2px, 2px);
    box-shadow: 1px 1px 0 #000;
}

.badge-icon {
    width: 50px;
    height: 50px;
    background: #ffd700;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #000;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.badge-icon i {
    font-size: 1.2rem;
    color: #000;
}

.badge-content {
    flex: 1;
}

.badge-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.badge-name {
    margin: 0;
    font-weight: bold;
    font-size: 1.1rem;
    color: #333;
}

.badge-date {
    background: #3a86ff;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
    border: 2px solid #000;
}

.badge-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.badge-group {
    font-size: 0.85rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Section Styles */
.section {
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.view-all {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.view-all:hover {
    text-decoration: underline;
}

/* Game Button Styles */
.game-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: 2px solid #000;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    cursor: pointer;
    text-align: center;
    box-shadow: 2px 2px 0 #000;
}

.game-btn-primary {
    background: #3a86ff;
    color: white;
}

.game-btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 3px 3px 0 #000;
}

.game-btn-secondary {
    background: #ffc300;
    color: #000;
}

.game-btn-secondary:hover {
    background: #ffaa00;
    transform: translateY(-1px);
    box-shadow: 3px 3px 0 #000;
}

/* Game Form Styles */
.game-label {
    display: block;
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: #333;
    font-size: 1rem;
}

.game-input {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #000;
    box-shadow: 2px 2px 0 #000;
    font-size: 1rem;
    transition: all 0.2s ease;
    border-radius: 0;
    font-family: inherit;
}

.game-input:focus {
    outline: none;
    border-color: #3a86ff;
    box-shadow: 3px 3px 0 #3a86ff;
}

.form-text {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.85rem;
}

/* Game Alert */
.game-alert {
    background: #fef2f2;
    border: 2px solid #dc2626;
    color: #dc2626;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 2px 2px 0 #000;
}

.game-alert-success {
    background: #f0fdf4;
    border-color: #10b981;
    color: #065f46;
}

.game-alert i {
    font-size: 1.2rem;
}

/* Empty State Styles */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border: 2px solid #000;
    border-radius: 12px;
    box-shadow: 4px 4px 0 #000;
    margin: 2rem 0;
}

.empty-state.small {
    padding: 1.5rem;
    margin: 1rem 0;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #6c757d;
}

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: #374151;
    font-size: 1.5rem;
}

.empty-state p {
    margin: 0 0 2rem 0;
    color: #6b7280;
    font-size: 1rem;
    line-height: 1.5;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .badge-form-container {
        padding: 1.5rem;
    }

    .badges-grid {
        grid-template-columns: 1fr;
    }

    .badge-card {
        flex-direction: column;
        text-align: center;
    }

    .badge-item {
        flex-direction: column;
        text-align: center;
    }

    .badge-header {
        flex-direction: column;
        align-items: center;
    }

    .badge-meta {
        justify-content: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn-large {
        width: 100%;
    }

    .section-header {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }

    .dashboard-header {
        flex-direction: column;
        text-align: center;
    }

    .header-stats {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .badge-form-container {
        padding: 1rem;
    }

    .badge-card {
        padding: 1rem;
    }

    .badge-item {
        padding: 1rem;
    }

    .badge-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    .empty-actions {
        flex-direction: column;
        align-items: center;
    }

    .empty-actions .game-btn {
        width: 100%;
        max-width: 250px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // Mobile menu toggle
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

    // Force emoji color display
    const emojiElements = document.querySelectorAll('.badge-emoji');
    emojiElements.forEach((emoji) => {
        // Remove any CSS that might be making emojis monochrome
        emoji.style.webkitTextFillColor = 'initial';
        emoji.style.textFillColor = 'initial';
        emoji.style.color = 'inherit';
    });
});
</script>