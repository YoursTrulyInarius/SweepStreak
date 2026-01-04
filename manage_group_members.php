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

// Get group ID from URL
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Prepare variables
$group = null;
$current_members = [];
$available_students = [];
$students_in_other_groups = [];
$error = null;

try {
    // Get group and class details, ensure teacher owns the class
    $stmt = $pdo->prepare("
        SELECT g.*, c.name as class_name, c.id as class_id, u.name as teacher_name
        FROM groups g
        JOIN classes c ON g.class_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE g.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$group_id, $teacher_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        header('Location: manage_groups.php');
        exit();
    }

    // Current group members
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.birthday, u.age, u.phone
        FROM group_members gm
        JOIN users u ON gm.student_id = u.id
        WHERE gm.group_id = ?
        ORDER BY u.name
    ");
    $stmt->execute([$group_id]);
    $current_members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Available students:
    // - Only students in the same class who are NOT in any group for this class.
    // This enforces: a student already in another group is NOT allowed to be added
    // unless the teacher explicitly uses the Transfer action.
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.birthday, u.age, u.phone
        FROM users u
        WHERE u.role = 'student'
          AND u.id NOT IN (
              SELECT gm.student_id
              FROM group_members gm
              JOIN groups g ON gm.group_id = g.id
              WHERE g.class_id = ?
          )
        ORDER BY u.name
    ");
    $stmt->execute([$group['class_id']]);
    $available_students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Students currently in other groups (same class) - useful for transfer UI
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.birthday, u.age, u.phone,
               g.name as current_group_name, g.id as current_group_id
        FROM users u
        JOIN group_members gm ON u.id = gm.student_id
        JOIN groups g ON gm.group_id = g.id
        JOIN classes c ON g.class_id = c.id
        WHERE c.id = ?
          AND g.id != ?
          AND c.teacher_id = ?
        GROUP BY u.id, g.id
        ORDER BY u.name
    ");
    $stmt->execute([$group['class_id'], $group_id, $teacher_id]);
    $students_in_other_groups = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    $error = "Error loading group data: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_students'])) {
            $student_ids = $_POST['student_ids'] ?? [];
            $added_count = 0;
            $skipped_count = 0;

            if (!empty($student_ids)) {
                foreach ($student_ids as $sid) {
                    $student_id = (int)$sid;
                    if ($student_id <= 0) continue;

                    // Defensive: skip if already in THIS group
                    $check_in_this = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND student_id = ?");
                    $check_in_this->execute([$group_id, $student_id]);
                    if ($check_in_this->fetch()) {
                        // already in this group -> skip
                        continue;
                    }

                    // IMPORTANT: Enforce rule: if the student is already in ANY group
                    // within this class, they cannot be added here via "Add Selected Students".
                    // The teacher must use Transfer to move them.
                    $check_in_class = $pdo->prepare("
                        SELECT 1 FROM group_members gm
                        JOIN groups g ON gm.group_id = g.id
                        WHERE gm.student_id = ? AND g.class_id = ?
                        LIMIT 1
                    ");
                    $check_in_class->execute([$student_id, $group['class_id']]);
                    if ($check_in_class->fetch()) {
                        // Student is already in another group in this class; skip and count as skipped.
                        $skipped_count++;
                        continue;
                    }

                    try {
                        // Insert membership for this group
                        $ins = $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
                        $ins->execute([$group_id, $student_id]);
                        $added_count++;
                    } catch (PDOException $e) {
                        // skip failures for individual inserts (duplicates, constraints)
                        continue;
                    }
                }
            }

            // Build success message with skipped info if any
            $messages = [];
            if ($added_count > 0) {
                $messages[] = "Successfully added {$added_count} student(s) to the group!";
            }
            if ($skipped_count > 0) {
                $messages[] = "{$skipped_count} student(s) were not added because they are already assigned to another group in this class. Use Transfer to move them.";
            }
            if (empty($messages)) {
                $messages[] = "No students were selected.";
            }
            $_SESSION['success'] = implode(' ', $messages);

            header('Location: manage_group_members.php?group_id=' . $group_id);
            exit();

        } elseif (isset($_POST['remove_student'])) {
            $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
            if ($student_id > 0) {
                $del = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND student_id = ?");
                $del->execute([$group_id, $student_id]);
                $_SESSION['success'] = "Student removed from group successfully!";
            }
            header('Location: manage_group_members.php?group_id=' . $group_id);
            exit();

        } elseif (isset($_POST['transfer_student'])) {
            $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
            if ($student_id > 0) {
                // Transfer: remove from other groups in the same class, then add to this group.
                $pdo->beginTransaction();
                try {
                    // Delete memberships that belong to groups in the same class
                    $del = $pdo->prepare("
                        DELETE gm FROM group_members gm
                        JOIN groups g ON gm.group_id = g.id
                        WHERE gm.student_id = ? AND g.class_id = ?
                    ");
                    $del->execute([$student_id, $group['class_id']]);

                    // Insert into this group if not already
                    $ins = $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
                    $ins->execute([$group_id, $student_id]);

                    $pdo->commit();
                    $_SESSION['success'] = "Student transferred to this group successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            header('Location: manage_group_members.php?group_id=' . $group_id);
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error updating group members: " . $e->getMessage();
    }
}

// Get pending submissions count for bottom nav
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
    $pending_submissions = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // not critical
}

require_once 'includes/header.php';
?>

<div class="page-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2 class="sidebar-title">Teacher Menu</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="teacher_dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="view_classes.php" class="sidebar-link">
        <i class="fas fa-users"></i> Classes
      </a>
            <a href="manage_groups.php" class="sidebar-link active">
                <i class="fas fa-users"></i> Groups
            </a>
            <a href="assign_tasks.php" class="sidebar-link">
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
            <div class="page-header">
                <div class="header-content">
                    <h1 class="page-title">Manage Group Members</h1>
                    <p class="page-subtitle">Add or remove students from <?php echo htmlspecialchars($group['name']); ?></p>
                </div>
                
                <div class="header-stats">
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo count($current_members); ?></div>
                            <div class="stat-label">Members</div>
                        </div>
                    </div>
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo count($available_students); ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                    </div>
                    <div class="stat-card pixel-border">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo count($students_in_other_groups); ?></div>
                            <div class="stat-label">In Other Groups</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Info -->
            <div class="card pixel-border">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Group Information
                    </h2>
                    <a href="manage_groups.php" class="pixel-button btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Groups
                    </a>
                </div>
                <div class="card-body">
                    <div class="group-info">
                        <div class="group-avatar pixel-border">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="group-details">
                            <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                            <div class="group-meta">
                                <span class="class-name">
                                    <i class="fas fa-graduation-cap"></i>
                                    Class: <?php echo htmlspecialchars($group['class_name']); ?>
                                </span>
                                <span class="teacher-name">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    Teacher: <?php echo htmlspecialchars($group['teacher_name']); ?>
                                </span>
                                <?php if(!empty($group['is_deployed']) && !empty($group['deployment_area'])): ?>
                                    <span class="deployment-area">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($group['deployment_area']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-error pixel-border">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success pixel-border">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Add New Students -->
            <div class="card pixel-border">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-user-plus"></i>
                        Add New Students
                    </h2>
                    <span class="card-badge pixel-border"><?php echo count($available_students); ?> available</span>
                </div>
                <div class="card-body">
                    <?php if(empty($available_students)): ?>
                        <div class="empty-state pixel-border">
                            <div class="empty-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h3>No Students Available to Add</h3>
                            <p>All students in this class are already assigned to groups. To move students, use the Transfer section below.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="students-grid">
                                <?php foreach($available_students as $student): ?>
                                    <div class="student-checkbox-card pixel-border">
                                        <label class="student-checkbox">
                                            <input type="checkbox" name="student_ids[]" value="<?php echo (int)$student['id']; ?>">
                                            <div class="student-info">
                                                <div class="student-avatar pixel-border">
                                                    <?php echo strtoupper(htmlspecialchars(substr($student['name'], 0, 1))); ?>
                                                </div>
                                                <div class="student-details">
                                                    <div class="student-name" title="<?php echo htmlspecialchars($student['name']); ?>">
                                                        <?php echo htmlspecialchars($student['name']); ?>
                                                    </div>
                                                    <div class="student-email" title="<?php echo htmlspecialchars($student['email']); ?>">
                                                        <?php echo htmlspecialchars($student['email']); ?>
                                                    </div>
                                                    <div class="student-meta">
                                                        <?php if(!empty($student['age'])): ?>
                                                            <span title="Age"><?php echo (int)$student['age']; ?></span>
                                                        <?php endif; ?>
                                                        <?php if(!empty($student['phone'])): ?>
                                                            <span title="Phone">📞 <?php echo htmlspecialchars($student['phone']); ?></span>
                                                        <?php endif; ?>
                                                        <span class="student-status available pixel-border">🟢 Available</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="add_students" class="pixel-button btn-block">
                                    <i class="fas fa-user-plus"></i>
                                    Add Selected Students
                                </button>
                                <p class="form-help">
                                    Note: students already assigned to another group in this class cannot be added here. Use Transfer to move them.
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transfer Students from Other Groups -->
            <?php if(!empty($students_in_other_groups)): ?>
                <div class="card pixel-border">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-exchange-alt"></i>
                            Transfer Students from Other Groups
                        </h2>
                        <span class="card-badge pixel-border"><?php echo count($students_in_other_groups); ?> available</span>
                    </div>
                    <div class="card-body">
                        <div class="transfer-notice pixel-border">
                            <i class="fas fa-info-circle"></i>
                            <p>These students are currently in other groups. Transferring will remove them from their current group(s) in this class and add them to this group.</p>
                        </div>

                        <div class="students-grid">
                            <?php foreach($students_in_other_groups as $student): ?>
                                <div class="student-transfer-card pixel-border">
                                    <div class="student-info">
                                        <div class="student-avatar pixel-border">
                                            <?php echo strtoupper(htmlspecialchars(substr($student['name'], 0, 1))); ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name" title="<?php echo htmlspecialchars($student['name']); ?>"><?php echo htmlspecialchars($student['name']); ?></div>
                                            <div class="student-email" title="<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></div>
                                            <div class="student-meta">
                                                <?php if(!empty($student['age'])): ?>
                                                    <span title="Age"><?php echo (int)$student['age']; ?></span>
                                                <?php endif; ?>
                                                <?php if(!empty($student['phone'])): ?>
                                                    <span title="Phone">📞 <?php echo htmlspecialchars($student['phone']); ?></span>
                                                <?php endif; ?>
                                                <span class="student-status in-group pixel-border">🟡 Currently in: <?php echo htmlspecialchars($student['current_group_name']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" class="transfer-form" onsubmit="return confirm('Transfer <?php echo htmlspecialchars(addslashes($student['name'])); ?> from <?php echo htmlspecialchars(addslashes($student['current_group_name'])); ?> to <?php echo htmlspecialchars(addslashes($group['name'])); ?>?');">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                                        <button type="submit" name="transfer_student" class="pixel-button btn-warning">
                                            <i class="fas fa-exchange-alt"></i>
                                            Transfer
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Members Section -->
            <div class="card pixel-border">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-users"></i>
                        Current Group Members
                    </h2>
                    <span class="card-badge pixel-border"><?php echo count($current_members); ?> members</span>
                </div>
                <div class="card-body">
                    <?php if(empty($current_members)): ?>
                        <div class="empty-state pixel-border">
                            <div class="empty-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>No Members Yet</h3>
                            <p>Add students to this group to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="members-list">
                            <?php foreach($current_members as $member): ?>
                                <div class="member-card pixel-border">
                                    <div class="member-info">
                                        <div class="member-avatar pixel-border">
                                            <?php echo strtoupper(htmlspecialchars(substr($member['name'], 0, 1))); ?>
                                        </div>
                                        <div class="member-details">
                                            <div class="member-name" title="<?php echo htmlspecialchars($member['name']); ?>"><?php echo htmlspecialchars($member['name']); ?></div>
                                            <div class="member-email" title="<?php echo htmlspecialchars($member['email']); ?>"><?php echo htmlspecialchars($member['email']); ?></div>
                                            <div class="member-meta">
                                                <?php if(!empty($member['age'])): ?>
                                                    <span title="Age"><?php echo (int)$member['age']; ?></span>
                                                <?php endif; ?>
                                                <?php if(!empty($member['phone'])): ?>
                                                    <span title="Phone">📞 <?php echo htmlspecialchars($member['phone']); ?></span>
                                                <?php endif; ?>
                                                <span class="member-status current pixel-border">✅ Current Member</span>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" class="remove-form" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars(addslashes($member['name'])); ?> from this group?');">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$member['id']; ?>">
                                        <button type="submit" name="remove_student" class="pixel-button btn-danger">
                                            <i class="fas fa-user-minus"></i>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

/* ================== SIDEBAR STYLES FROM DASHBOARD ================== */
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
    position: relative;
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
    color: white;
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

.pixel-button.btn-warning {
    background: var(--warning);
    color: #000;
}

.pixel-button.btn-warning:hover {
    background: #e0a800;
    color: #000;
}

.pixel-button.btn-danger {
    background: var(--danger);
    color: white;
}

.pixel-button.btn-danger:hover {
    background: #c82333;
    color: white;
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

/* Group Info */
.group-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.group-avatar {
    width: 80px;
    height: 80px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    flex-shrink: 0;
}

.group-details h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}

.group-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.group-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s;
}

.alert:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
}

.alert-success {
    background: #f0f9f4;
    color: #0f5132;
}

.alert-error {
    background: #fdf2f2;
    color: #842029;
}

.alert i {
    font-size: 1.25rem;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.student-checkbox-card {
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
}

.student-checkbox-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.student-checkbox {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    padding: 1rem;
    cursor: pointer;
    margin: 0;
}

.student-checkbox input[type="checkbox"] {
    display: none;
}

.student-info {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    width: 100%;
    flex: 1 1 auto;
    min-width: 0;
}

.student-avatar, .member-avatar {
    width: 50px;
    height: 50px;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.student-details, .member-details {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.student-name, .member-name,
.student-email, .member-email {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}

.student-name, .member-name {
    font-weight: bold;
    font-size: 1.05rem;
    color: var(--text);
}

.student-email, .member-email {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.student-meta, .member-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    flex-wrap: wrap;
    align-items: center;
}

.student-meta span, .member-meta span {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-status, .member-status {
    font-weight: bold;
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
}

.student-status.available, .member-status.current {
    background: #d4edda;
    color: #155724;
}

.student-status.in-group {
    background: #fff3cd;
    color: #856404;
}

/* Transfer Section */
.transfer-notice {
    background: #fff3cd;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.transfer-notice i {
    color: #856404;
    margin-top: 0.2rem;
}

.transfer-notice p {
    margin: 0;
    color: #856404;
    font-size: 0.9rem;
}

.student-transfer-card, .member-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    transition: all 0.2s ease;
}

.student-transfer-card:hover, .member-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

/* Forms */
.form-actions {
    text-align: center;
    padding-top: 1rem;
    border-top: var(--pixel-size) solid #000;
}

.form-help {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-muted);
    text-align: center;
}

.remove-form, .transfer-form {
    display: flex;
    align-items: center;
}

/* Members List */
.members-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
    background: var(--light);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: var(--text);
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
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
@media (max-width: 968px) {
    .students-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .group-info {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .group-meta {
        align-items: center;
    }
}

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
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .student-transfer-card, .member-card {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        text-align: center;
    }
    
    .member-info, .student-info {
        flex-direction: column;
        text-align: center;
    }
    
    .member-meta, .student-meta {
        justify-content: center;
    }
    
    .remove-form, .transfer-form {
        justify-content: center;
    }
    
    .mobile-menu-toggle {
        display: block;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .student-meta, .member-meta {
        flex-direction: column;
        gap: 0.25rem;
        align-items: center;
    }
    
    .group-meta {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
    
    .stat-card {
        min-width: 140px;
        padding: 1.25rem;
    }
    
    .stat-number {
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

    // Student checkbox visual feedback
    const checkboxes = document.querySelectorAll('.student-checkbox input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const card = this.closest('.student-checkbox-card');
            if (card) {
                if (this.checked) {
                    card.style.background = 'var(--bg-secondary)';
                } else {
                    card.style.background = 'white';
                }
            }
        });
    });
});
</script>