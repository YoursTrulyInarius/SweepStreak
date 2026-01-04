<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
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
    // Silent fail
}

// HANDLE ATTENDANCE MARKING FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $student_id = $_POST['student_id'];
    $status = $_POST['status'];
    $attendance_date = date('Y-m-d');
    
    if (!empty($student_id) && in_array($status, ['present', 'absent'])) {
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, status, attendance_date) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    created_at = CURRENT_TIMESTAMP
            ");
            $result = $stmt->execute([$student_id, $status, $attendance_date]);
            
            if ($result) {
                $_SESSION['success'] = "Attendance marked as " . ucfirst($status) . "!";
            } else {
                $_SESSION['error'] = "Failed to save attendance.";
            }
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid data received.";
    }
    
    // Redirect to prevent form resubmission
    header('Location: manage_class.php?id=' . $class_id);
    exit();
}

// Handle group deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_group'])) {
    $group_id = $_POST['group_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        $stmt = $pdo->prepare("DELETE FROM points WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);
        
        $_SESSION['success'] = "Group deleted successfully!";
        header('Location: manage_class.php?id=' . $class_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting group: " . $e->getMessage();
        header('Location: manage_class.php?id=' . $class_id);
        exit();
    }
}

// Get class details
$class = null;
$groups = [];
$all_students = [];

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
               COUNT(gm.student_id) as member_count
        FROM groups g
        LEFT JOIN group_members gm ON gm.group_id = g.id
        WHERE g.class_id = ?
        GROUP BY g.id
        ORDER BY g.name
    ");
    $stmt->execute([$class_id]);
    $groups = $stmt->fetchAll() ?: [];

    // Get all students in this class (DISTINCT to prevent duplicates)
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, g.name as group_name, g.id as group_id,
               p.points, p.streak
        FROM group_members gm
        JOIN groups g ON g.id = gm.group_id
        JOIN users u ON u.id = gm.student_id
        LEFT JOIN points p ON p.group_id = g.id
        WHERE g.class_id = ?
        GROUP BY u.id, g.id
        ORDER BY g.name, u.name
    ");
    $stmt->execute([$class_id]);
    $all_students = $stmt->fetchAll() ?: [];

} catch (PDOException $e) {
    $error = "Error loading class data: " . $e->getMessage();
}

// Get today's attendance for display - USE CURRENT DATE
$today_attendance = [];
$present_count = 0;
$absent_count = 0;
$today_date = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT student_id, status 
        FROM attendance 
        WHERE attendance_date = ?
        AND student_id IN (
            SELECT gm.student_id 
            FROM group_members gm 
            JOIN groups g ON g.id = gm.group_id 
            WHERE g.class_id = ?
        )
    ");
    $stmt->execute([$today_date, $class_id]);
    $attendance_records = $stmt->fetchAll();
    
    foreach($attendance_records as $record) {
        $today_attendance[$record['student_id']] = $record['status'];
        if ($record['status'] === 'present') {
            $present_count++;
        } else if ($record['status'] === 'absent') {
            $absent_count++;
        }
    }
} catch (PDOException $e) {
    // Silent fail - attendance might not exist yet
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
            <h1 class="page-title">Manage Class: <?php echo htmlspecialchars($class['name']); ?></h1>
            <p class="page-subtitle">
                Class Code: <strong><?php echo htmlspecialchars($class['code']); ?></strong> • 
                <span class="current-date"><?php echo date('F j, Y'); ?></span>
            </p>
        </div>

        <!-- Class Statistics -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Class Overview</h2>
            </div>
            
            <div class="class-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($groups); ?></div>
                        <div class="stat-label">Groups</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($all_students); ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $present_count; ?></div>
                        <div class="stat-label">Present Today</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $absent_count; ?></div>
                        <div class="stat-label">Absent Today</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUCCESS/ERROR MESSAGES AFTER CLASS OVERVIEW -->
        <?php if(isset($_SESSION['error'])): ?>
            <div class="game-alert game-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="game-alert game-alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Students by Group Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Student Attendance - <?php echo date('F j, Y'); ?></h2>
                <span class="student-count">
                    <?php echo count($all_students); ?> students • 
                    <?php echo $present_count; ?> present • 
                    <?php echo $absent_count; ?> absent
                </span>
            </div>

            <?php if(empty($groups)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h3>No Groups Created</h3>
                    <p>Groups will be automatically created when students join the class.</p>
                    <div class="class-code-reminder">
                        <strong>Class Code: <?php echo htmlspecialchars($class['code']); ?></strong>
                        <p>Share this code with your students so they can join your class.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="groups-students-container">
                    <?php 
                    $students_by_group = [];
                    foreach($all_students as $student) {
                        $group_id = $student['group_id'];
                        if (!isset($students_by_group[$group_id])) {
                            $students_by_group[$group_id] = [];
                        }
                        $students_by_group[$group_id][] = $student;
                    }
                    ?>

                    <?php foreach($groups as $group): ?>
                    <div class="group-students-card">
                        <div class="group-header">
                            <div class="group-info">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <p class="group-stats">
                                    <?php 
                                    $group_students = $students_by_group[$group['id']] ?? [];
                                    $group_present = 0;
                                    $group_absent = 0;
                                    foreach($group_students as $student) {
                                        $status = $today_attendance[$student['id']] ?? 'unmarked';
                                        if ($status === 'present') $group_present++;
                                        if ($status === 'absent') $group_absent++;
                                    }
                                    echo count($group_students) . ' students • ' . $group_present . ' present • ' . $group_absent . ' absent';
                                    ?>
                                </p>
                            </div>
                            <div class="group-actions">
                                <button type="button" class="game-btn game-btn-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                    Delete Group
                                </button>
                            </div>
                        </div>

                        <?php if(isset($students_by_group[$group['id']]) && !empty($students_by_group[$group['id']])): ?>
                        <div class="students-list">
                            <div class="students-grid">
                                <?php foreach($students_by_group[$group['id']] as $student): ?>
                                <div class="student-card">
                                    <div class="student-main">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-info">
                                            <h4 class="student-name"><?php echo htmlspecialchars($student['name']); ?></h4>
                                            <div class="student-stats">
                                                <?php if(isset($student['points'])): ?>
                                                    <span class="student-points">
                                                        <i class="fas fa-star"></i>
                                                        <?php echo $student['points']; ?> points
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="student-attendance">
                                        <?php
                                        $student_status = $today_attendance[$student['id']] ?? 'unmarked';
                                        $status_class = $student_status;
                                        // Display logic: UNMARKED by default, Present when present, Absent when absent
                                        if ($student_status === 'unmarked') {
                                            $status_display = 'UNMARKED';
                                        } else {
                                            $status_display = ucfirst($student_status);
                                        }
                                        ?>
                                        <div class="attendance-status">
                                            <span class="attendance-badge status-<?php echo $status_class; ?>">
                                                <?php echo $status_display; ?>
                                            </span>
                                        </div>
                                        <div class="attendance-buttons">
                                            <form method="POST" class="attendance-form">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="mark_attendance" value="1">
                                                <button type="submit" name="status" value="present" class="game-btn game-btn-success btn-sm <?php echo $student_status === 'present' ? 'active' : ''; ?>">
                                                    <i class="fas fa-check"></i> Present
                                                </button>
                                            </form>
                                            <form method="POST" class="attendance-form">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="mark_attendance" value="1">
                                                <button type="submit" name="status" value="absent" class="game-btn game-btn-danger btn-sm <?php echo $student_status === 'absent' ? 'active' : ''; ?>">
                                                    <i class="fas fa-times"></i> Absent
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-users"></i>
                            <p>No students in this group</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<!-- Delete Group Confirmation Form -->
<form id="deleteGroupForm" method="POST" style="display: none;">
    <input type="hidden" name="group_id" id="deleteGroupId">
    <input type="hidden" name="delete_group" value="1">
</form>

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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.current-date {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 700;
}

/* Class Statistics */
.class-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border: 3px solid #000;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 6px 6px 0 #000;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
}

/* Alerts - AFTER CLASS OVERVIEW */
.game-alert {
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: 3px solid #000;
    border-radius: 12px;
    box-shadow: 4px 4px 0 #000;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.game-alert-success {
    background: #d1fae5;
    color: #065f46;
    border-color: #10b981;
}

.game-alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}

/* Student Cards */
.students-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.student-card {
    background: white;
    border: 3px solid #000;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 6px 6px 0 #000;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.student-main {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex: 1;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.student-info {
    flex: 1;
}

.student-name {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.3rem;
    font-weight: 700;
}

.student-stats {
    display: flex;
    gap: 1rem;
}

.student-points {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Attendance Section */
.student-attendance {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    min-width: 160px;
}

.attendance-status {
    text-align: center;
}

.attendance-badge {
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    border: 2px solid;
    display: inline-block;
    min-width: 100px;
}

.attendance-badge.status-present {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.attendance-badge.status-absent {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.attendance-badge.status-unmarked {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.attendance-buttons {
    display: flex;
    gap: 0.5rem;
}

.attendance-form {
    display: flex;
}

/* Game Buttons */
.game-btn {
    padding: 0.6rem 1rem;
    border: 2px solid #000;
    border-radius: 8px;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 3px 3px 0 #000;
}

.game-btn:hover {
    transform: translateY(-2px);
    box-shadow: 5px 5px 0 #000;
}

.game-btn-success {
    background: #10b981;
    color: white;
}

.game-btn-danger {
    background: #ef4444;
    color: white;
}

.game-btn-success.active {
    background: #065f46;
    box-shadow: inset 3px 3px 6px rgba(0,0,0,0.3);
}

.game-btn-danger.active {
    background: #991b1b;
    box-shadow: inset 3px 3px 6px rgba(0,0,0,0.3);
}

.btn-sm {
    padding: 0.5rem 0.8rem;
    font-size: 0.8rem;
}

/* Groups Container */
.groups-students-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.group-students-card {
    background: #f8fafc;
    border: 3px solid #000;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 6px 6px 0 #000;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 3px solid #e2e8f0;
}

.group-info h3 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.5rem;
    font-weight: 800;
}

.group-stats {
    margin: 0;
    color: #64748b;
    font-size: 1rem;
    font-weight: 600;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0;
}

.student-count {
    color: #64748b;
    font-weight: 600;
}

/* Section Styles */
.section {
    margin-bottom: 2rem;
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

.class-code-reminder {
    background: #f0f9ff;
    border: 2px solid #3a86ff;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 1.5rem;
}

.class-code-reminder strong {
    color: #1e40af;
    font-size: 1.2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .student-card {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .student-main {
        flex-direction: column;
    }
    
    .student-attendance {
        flex-direction: row;
        width: 100%;
    }
    
    .class-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .group-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .section-header {
        flex-direction: column;
        text-align: center;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 1rem;
    }
    
    .attendance-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .attendance-form {
        width: 100%;
    }
    
    .game-btn {
        width: 100%;
        justify-content: center;
    }
    
    .class-stats-grid {
        grid-template-columns: 1fr;
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
        font-size: 1.5rem;
    }
    
    .student-card,
    .group-students-card {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.3rem;
    }
}

/* Small mobile devices */
@media (max-width: 360px) {
    .page-title {
        font-size: 1.1rem;
    }
    
    .student-name {
        font-size: 1.1rem;
    }
    
    .group-info h3 {
        font-size: 1.2rem;
    }
}
</style>

<script>
function confirmDelete(groupId, groupName) {
    if (confirm(`Are you sure you want to delete "${groupName}"? This cannot be undone!`)) {
        document.getElementById('deleteGroupId').value = groupId;
        document.getElementById('deleteGroupForm').submit();
    }
}

// Auto-hide success messages after 4 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successAlerts = document.querySelectorAll('.game-alert-success');
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
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