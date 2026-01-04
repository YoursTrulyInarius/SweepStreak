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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'];
    $task_name = $_POST['task_name'];
    $cleaning_area = $_POST['cleaning_area'];
    $points = $_POST['points'];
    $description = $_POST['description'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (class_id, name, description, cleaning_area, points) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $task_name, $description, $cleaning_area, $points]);
        
        $_SESSION['success'] = "Task assigned successfully!";
        header('Location: assign_tasks.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error assigning task: " . $e->getMessage();
    }
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
      <a href="assign_tasks.php" class="sidebar-link active">
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
                <h1 class="welcome-title">Assign Cleaning Tasks</h1>
                <p class="welcome-subtitle">Create new cleaning assignments for your classes</p>
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

        <!-- Task Assignment Form Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Create New Task</h2>
                <span class="view-all">Fill in the task details</span>
            </div>

            <?php if(empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>No Classes Available</h3>
                    <p>You need to create a class before you can assign tasks.</p>
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

                <div class="task-form-container">
                    <form method="POST" class="task-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="game-label">Select Class *</label>
                                <select name="class_id" class="game-input" required>
                                    <option value="">Choose a class...</option>
                                    <?php foreach($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> 
                                            (<?php echo $class['student_count']; ?> students, <?php echo $class['group_count']; ?> groups)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="game-label">Cleaning Area *</label>
                                <select name="cleaning_area" class="game-input" required>
                                    <option value="">Select area...</option>
                                    <option value="Classroom">Classroom</option>
                                    <option value="Comfort Room">Comfort Room</option>
                                    <option value="Hallway">Hallway</option>
                                    <option value="Science Lab">Science Lab</option>
                                    <option value="Library">Library</option>
                                    <option value="Canteen">Canteen</option>
                                    <option value="Garden">Garden</option>
                                    <option value="Gymnasium">Gymnasium</option>
                                    <option value="Auditorium">Auditorium</option>
                                    <option value="Computer Lab">Computer Lab</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="game-label">Task Name *</label>
                            <input type="text" name="task_name" class="game-input" 
                                   placeholder="e.g., Sweep the floor, Clean whiteboard, Organize books" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="game-label">Task Description</label>
                            <textarea name="description" class="game-input" rows="3" 
                                      placeholder="Describe what needs to be cleaned and any specific instructions..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="game-label">Points *</label>
                            <div class="points-input-container">
                                <input type="number" name="points" class="game-input" 
                                       min="10" max="100" value="50" required>
                                <div class="points-preview">
                                    <span class="points-value">50</span>
                                    <span class="points-label">points</span>
                                </div>
                            </div>
                            <div class="form-text">Points awarded for completing this task (10-100)</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="game-btn game-btn-primary btn-large">
                                <i class="fas fa-plus-circle"></i>
                                Assign Task
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

        <!-- Recent Tasks Section -->
        <?php if(!empty($classes)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recent Tasks</h2>
                <a href="view_tasks.php" class="view-all">View All Tasks</a>
            </div>

            <div class="recent-tasks">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT t.*, c.name as class_name, 
                               COUNT(s.id) as submission_count,
                               COUNT(CASE WHEN s.status = 'approved' THEN 1 END) as approved_count
                        FROM tasks t
                        JOIN classes c ON c.id = t.class_id
                        LEFT JOIN submissions s ON s.task_id = t.id
                        WHERE c.teacher_id = ?
                        GROUP BY t.id
                        ORDER BY t.created_at DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$teacher_id]);
                    $recent_tasks = $stmt->fetchAll() ?: [];

                    if(empty($recent_tasks)): ?>
                        <div class="empty-state small">
                            <i class="fas fa-tasks"></i>
                            <p>No tasks assigned yet</p>
                            <small>Your recent tasks will appear here</small>
                        </div>
                    <?php else: ?>
                        <div class="tasks-list">
                            <?php foreach($recent_tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-icon">
                                    <i class="fas fa-broom"></i>
                                </div>
                                <div class="task-content">
                                    <div class="task-header">
                                        <h4 class="task-name"><?php echo htmlspecialchars($task['name']); ?></h4>
                                        <span class="task-points"><?php echo $task['points']; ?> pts</span>
                                    </div>
                                    <div class="task-meta">
                                        <span class="task-class"><?php echo htmlspecialchars($task['class_name']); ?></span>
                                        <span class="task-area">📍 <?php echo htmlspecialchars($task['cleaning_area']); ?></span>
                                        <span class="task-submissions">
                                            <?php echo $task['approved_count']; ?>/<?php echo $task['submission_count']; ?> approved
                                        </span>
                                    </div>
                                    <?php if($task['description']): ?>
                                        <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif;
                } catch (PDOException $e) {
                    echo '<div class="empty-state small"><p>Error loading recent tasks</p></div>';
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

/* Task Form Styles */
.task-form-container {
    background: white;
    border: 3px solid #000;
    box-shadow: 4px 4px 0 #000;
    padding: 2rem;
    margin-bottom: 2rem;
}

.task-form {
    max-width: 100%;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.points-input-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.points-input-container .game-input {
    flex: 0 0 100px;
}

.points-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    border: 2px solid #000;
    border-radius: 0;
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.points-value {
    color: #3a86ff;
    font-size: 1.1rem;
}

.points-label {
    color: #666;
    font-size: 0.8rem;
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

/* Recent Tasks Styles */
.recent-tasks {
    margin-bottom: 2rem;
}

.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.task-item {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border: 2px solid #000;
    box-shadow: 3px 3px 0 #000;
    border-radius: 0;
    transition: all 0.2s ease;
}

.task-item:hover {
    transform: translate(2px, 2px);
    box-shadow: 1px 1px 0 #000;
}

.task-icon {
    width: 50px;
    height: 50px;
    background: #3a86ff;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: 2px solid #000;
    flex-shrink: 0;
}

.task-content {
    flex: 1;
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.task-name {
    margin: 0;
    font-weight: bold;
    font-size: 1.1rem;
    color: #333;
}

.task-points {
    background: #3a86ff;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
    border: 2px solid #000;
}

.task-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
}

.task-meta span {
    font-size: 0.85rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.task-description {
    margin: 0;
    color: #555;
    font-size: 0.9rem;
    line-height: 1.4;
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

    .task-form-container {
        padding: 1.5rem;
    }

    .points-input-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .points-input-container .game-input {
        flex: 1;
    }

    .task-item {
        flex-direction: column;
        text-align: center;
    }

    .task-header {
        flex-direction: column;
        align-items: center;
    }

    .task-meta {
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
    .task-form-container {
        padding: 1rem;
    }

    .task-item {
        padding: 1rem;
    }

    .task-meta {
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
    // Points preview update
    document.addEventListener('DOMContentLoaded', function() {
        const pointsInput = document.querySelector('input[name="points"]');
        const pointsValue = document.querySelector('.points-value');
        
        if (pointsInput && pointsValue) {
            pointsInput.addEventListener('input', function() {
                pointsValue.textContent = this.value;
            });
        }

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