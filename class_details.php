<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get class ID from URL
$class_id = $_GET['id'] ?? null;

if (!$class_id) {
    header('Location: view_classes.php');
    exit();
}

// Check if teacher has classes for sidebar nav
$has_classes = false;
try {
    $classes_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes WHERE teacher_id = ?");
    $classes_stmt->execute([$teacher_id]);
    $has_classes = ((int)$classes_stmt->fetchColumn()) > 0;
} catch (PDOException $e) {
    // silent
}

// Get pending submissions count for sidebar
$pending_submissions = 0;
try {
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
    // Silent fail - we'll use 0 as default
}

// Handle badge awarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_badge'])) {
    $group_id = $_POST['group_id'];
    $badge_id = $_POST['badge_id'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO group_badges (group_id, badge_id, awarded_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$group_id, $badge_id]);
        $_SESSION['success'] = "Badge awarded successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error awarding badge: " . $e->getMessage();
    }
    
    header("Location: class_details.php?id=$class_id");
    exit();
}

// Get class details
$class = null;
$tasks = [];
$recent_submissions = [];
$groups = [];

try {
    // Verify teacher owns this class
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM classes c 
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$class_id, $teacher_id]);
    $class = $stmt->fetch();

    if (!$class) {
        $_SESSION['error'] = "Class not found or access denied.";
        header('Location: view_classes.php');
        exit();
    }

    // Get all groups in this class
    $stmt = $pdo->prepare("
        SELECT g.*, 
               COUNT(gm.student_id) as member_count,
               p.points,
               p.streak
        FROM groups g
        LEFT JOIN group_members gm ON gm.group_id = g.id
        LEFT JOIN points p ON p.group_id = g.id
        WHERE g.class_id = ?
        GROUP BY g.id
        ORDER BY g.name
    ");
    $stmt->execute([$class_id]);
    $groups = $stmt->fetchAll() ?: [];

    // Get all tasks for this class
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(s.id) as submission_count,
               SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
               SUM(CASE WHEN s.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
               SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM tasks t
        LEFT JOIN submissions s ON s.task_id = t.id
        WHERE t.class_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$class_id]);
    $tasks = $stmt->fetchAll() ?: [];

    // Get recent submissions with student info
    $stmt = $pdo->prepare("
        SELECT s.*, 
               t.name as task_name,
               t.points as task_points,
               g.name as group_name,
               u.name as student_name,
               s.status,
               s.submitted_at,
               s.image_path
        FROM submissions s
        JOIN tasks t ON t.id = s.task_id
        JOIN groups g ON g.id = s.group_id
        JOIN users u ON u.id = s.submitted_by
        WHERE t.class_id = ?
        ORDER BY s.submitted_at DESC
        LIMIT 10
    ");
    $stmt->execute([$class_id]);
    $recent_submissions = $stmt->fetchAll() ?: [];

} catch (PDOException $e) {
    $error = "Error loading class details: " . $e->getMessage();
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
        <!-- Class Header -->
        <div class="page-header">
            <h1 class="page-title"><?php echo htmlspecialchars($class['name']); ?></h1>
            <p class="page-subtitle">
                Class Code: <strong><?php echo htmlspecialchars($class['code']); ?></strong> • 
                Created: <?php echo date('F j, Y', strtotime($class['created_at'])); ?>
            </p>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="game-alert game-alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="game-alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="game-alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Class Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo count($tasks); ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo count($groups); ?></div>
                    <div class="stat-label">Groups</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?php
                        $total_submissions = 0;
                        foreach($tasks as $task) {
                            $total_submissions += $task['submission_count'];
                        }
                        echo $total_submissions;
                        ?>
                    </div>
                    <div class="stat-label">Submissions</div>
                </div>
            </div>
        </div>

        <!-- Groups Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Groups</h2>
                <a href="manage_class.php?id=<?php echo $class_id; ?>" class="view-all-link">Manage Groups</a>
            </div>

            <?php if(empty($groups)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>No Groups Yet</h3>
                    <p>Students will be automatically assigned to groups when they join the class.</p>
                </div>
            <?php else: ?>
                <div class="groups-grid">
                    <?php foreach($groups as $group): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-avatar">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="group-info">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <div class="group-stats">
                                    <span><?php echo $group['member_count']; ?> members</span>
                                    <span><?php echo $group['points'] ?? 0; ?> points</span>
                                    <span>Streak: <?php echo $group['streak'] ?? 0; ?> days</span>
                                </div>
                            </div>
                        </div>
                        <div class="group-actions">
                            <a href="manage_class.php?id=<?php echo $class_id; ?>" class="game-btn game-btn-primary">
                                View Students
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tasks Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Cleaning Tasks</h2>
                <a href="assign_tasks.php" class="view-all-link">Assign New Task</a>
            </div>

            <?php if(empty($tasks)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>No Tasks Assigned</h3>
                    <p>Start by creating cleaning tasks for your students.</p>
                    <a href="assign_tasks.php" class="game-btn game-btn-primary">
                        Create First Task
                    </a>
                </div>
            <?php else: ?>
                <div class="tasks-list">
                    <?php foreach($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div class="task-icon">
                                <i class="fas fa-broom"></i>
                            </div>
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($task['name']); ?></h4>
                                <p class="task-area">📍 <?php echo htmlspecialchars($task['cleaning_area']); ?></p>
                                <p class="task-points">+<?php echo $task['points']; ?> points</p>
                            </div>
                        </div>
                        
                        <div class="task-stats">
                            <div class="task-stat">
                                <span class="stat-number"><?php echo $task['submission_count']; ?></span>
                                <span class="stat-label">Total</span>
                            </div>
                            <div class="task-stat approved">
                                <span class="stat-number"><?php echo $task['approved_count']; ?></span>
                                <span class="stat-label">Approved</span>
                            </div>
                            <div class="task-stat pending">
                                <span class="stat-number"><?php echo $task['pending_count']; ?></span>
                                <span class="stat-label">Pending</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recent Submissions</h2>
                <a href="review_submissions.php" class="view-all-link">Review All</a>
            </div>

            <?php if(empty($recent_submissions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>No Submissions Yet</h3>
                    <p>Student submissions will appear here once they start completing tasks.</p>
                </div>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach($recent_submissions as $submission): ?>
                    <div class="activity-item">
                        <div class="activity-icon status-<?php echo $submission['status']; ?>">
                            <i class="fas fa-<?php echo $submission['status'] == 'approved' ? 'check-circle' : ($submission['status'] == 'rejected' ? 'times-circle' : 'clock'); ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong><?php echo htmlspecialchars($submission['student_name']); ?></strong> from 
                                <strong><?php echo htmlspecialchars($submission['group_name']); ?></strong>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-task"><?php echo htmlspecialchars($submission['task_name']); ?></span>
                                <span class="activity-time"><?php echo date('M j, g:i A', strtotime($submission['submitted_at'])); ?></span>
                                <span class="activity-status status-<?php echo $submission['status']; ?>">
                                    <?php echo ucfirst($submission['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-top: 1rem;
}

.page-title {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    text-shadow: 2px 2px 0 #000;
    color: #3a86ff;
    margin-bottom: 0.5rem;
    font-size: 2rem;
}

.page-subtitle {
    color: #666;
    font-size: 1.1rem;
}

/* Class Details Specific Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.stat-card {
    background: white;
    border-radius: 12px;
    border: 2px solid #000;
    padding: 1.5rem;
    box-shadow: 4px 4px 0 #000;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 6px 6px 0 #000;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #1e293b;
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
}

/* Groups Grid */
.groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.group-card {
    background: white;
    border-radius: 12px;
    border: 2px solid #000;
    padding: 1.5rem;
    box-shadow: 4px 4px 0 #000;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
}

.group-card:hover {
    transform: translateY(-2px);
    box-shadow: 6px 6px 0 #000;
}

.group-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.group-avatar {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.group-info h3 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.1rem;
}

.group-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #64748b;
}

.group-actions {
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

/* Tasks List */
.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.task-card {
    background: white;
    border-radius: 12px;
    border: 2px solid #000;
    padding: 1.5rem;
    box-shadow: 4px 4px 0 #000;
    transition: all 0.2s ease;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 6px 6px 0 #000;
}

.task-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.task-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.task-info h4 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.1rem;
}

.task-area {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0 0 0.25rem 0;
}

.task-points {
    color: #3b82f6;
    font-weight: 600;
    font-size: 0.9rem;
    margin: 0;
}

.task-stats {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.task-stat {
    text-align: center;
    flex: 1;
}

.task-stat.approved {
    color: #10b981;
}

.task-stat.pending {
    color: #f59e0b;
}

.task-stat .stat-number {
    font-size: 1.2rem;
    font-weight: bold;
    display: block;
}

.task-stat .stat-label {
    font-size: 0.7rem;
    margin-top: 0.25rem;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    background: white;
    border-radius: 12px;
    border: 2px solid #000;
    padding: 1rem;
    box-shadow: 4px 4px 0 #000;
    transition: all 0.2s ease;
}

.activity-item:hover {
    transform: translateY(-2px);
    box-shadow: 6px 6px 0 #000;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.activity-icon.status-approved {
    background: #f0fdf4;
    color: #10b981;
}

.activity-icon.status-pending {
    background: #fffbeb;
    color: #f59e0b;
}

.activity-icon.status-rejected {
    background: #fef2f2;
    color: #ef4444;
}

.activity-content {
    flex: 1;
}

.activity-text {
    margin-bottom: 0.5rem;
    color: #1e293b;
}

.activity-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #64748b;
}

.activity-status {
    font-weight: 600;
}

.activity-status.status-approved {
    color: #10b981;
}

.activity-status.status-pending {
    color: #f59e0b;
}

.activity-status.status-rejected {
    color: #ef4444;
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

.view-all-link {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.view-all-link:hover {
    text-decoration: underline;
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

/* Responsive Design */
@media (max-width: 1024px) {
    .groups-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .stat-number {
        font-size: 1.25rem;
    }
    
    .groups-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .task-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .task-icon {
        align-self: center;
    }
    
    .task-stats {
        gap: 0.5rem;
    }
    
    .activity-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .activity-icon {
        align-self: center;
    }
    
    .activity-meta {
        justify-content: center;
    }
    
    .section-header {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 1rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .stat-number {
        font-size: 1.1rem;
    }
    
    .group-card,
    .task-card,
    .activity-item {
        padding: 1rem;
    }
    
    .group-stats {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .task-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .activity-meta {
        flex-direction: column;
        gap: 0.25rem;
        align-items: center;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
}

/* Small mobile devices */
@media (max-width: 360px) {
    .page-title {
        font-size: 1.3rem;
    }
    
    .class-name {
        font-size: 1.1rem;
    }
    
    .group-name {
        font-size: 1rem;
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
});
</script>