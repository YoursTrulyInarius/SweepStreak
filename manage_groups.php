<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['create_group'])) {
            $class_id = $_POST['class_id'];
            $group_name = trim($_POST['group_name']);
            
            if (empty($group_name)) {
                $error = "Group name is required!";
            } else {
                // Check if group name already exists in this class
                $check_stmt = $pdo->prepare("SELECT id FROM groups WHERE class_id = ? AND name = ?");
                $check_stmt->execute([$class_id, $group_name]);
                
                if ($check_stmt->fetch()) {
                    $error = "A group with this name already exists in this class!";
                } else {
                    // Create new group
                    $stmt = $pdo->prepare("INSERT INTO groups (name, class_id) VALUES (?, ?)");
                    $stmt->execute([$group_name, $class_id]);
                    
                    // Initialize points for the group
                    $group_id = $pdo->lastInsertId();
                    $points_stmt = $pdo->prepare("INSERT INTO points (group_id, points, streak) VALUES (?, 0, 0)");
                    $points_stmt->execute([$group_id]);
                    
                    $success = "Group '$group_name' created successfully!";
                }
            }
        } elseif (isset($_POST['delete_group'])) {
            $group_id = $_POST['group_id'];
            
            $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$group_id]);
            
            $success = "Group deleted successfully!";
        } elseif (isset($_POST['update_group'])) {
            $group_id = $_POST['group_id'];
            $new_name = trim($_POST['group_name']);
            
            if (empty($new_name)) {
                $error = "Group name is required!";
            } else {
                $stmt = $pdo->prepare("UPDATE groups SET name = ? WHERE id = ?");
                $stmt->execute([$new_name, $group_id]);
                
                $success = "Group name updated successfully!";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get teacher's classes with student and group counts
$classes_stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT g.id) as group_count,
           COUNT(DISTINCT cm.student_id) as total_students,
           COUNT(DISTINCT gm.student_id) as assigned_students
    FROM classes c 
    LEFT JOIN groups g ON g.class_id = c.id 
    LEFT JOIN class_members cm ON cm.class_id = c.id
    LEFT JOIN group_members gm ON gm.group_id = g.id
    WHERE c.teacher_id = ? 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$classes_stmt->execute([$teacher_id]);
$classes = $classes_stmt->fetchAll();

// Check if teacher has classes for sidebar nav
$has_classes = !empty($classes);

// Get pending submissions count for sidebar
$pending_submissions = 0;
try {
    $pending_stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM submissions s 
        JOIN groups g ON g.id = s.group_id 
        JOIN classes c ON c.id = g.class_id 
        WHERE c.teacher_id = ? AND s.status = 'pending'
    ");
    $pending_stmt->execute([$teacher_id]);
    $pending_submissions = $pending_stmt->fetch()['pending_count'];
} catch (PDOException $e) {
    // silent
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
      <a href="manage_groups.php" class="sidebar-link active">
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
                <h1 class="welcome-title pixelated">Manage Groups</h1>
                <p class="welcome-subtitle">Create groups and assign students from your classes</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-badge">
                    <div class="stat-number"><?php echo count($classes); ?></div>
                    <div class="stat-label">Classes</div>
                </div>
                <?php
                $total_students = 0;
                $total_assigned = 0;
                foreach($classes as $class) {
                    $total_students += $class['total_students'];
                    $total_assigned += $class['assigned_students'];
                }
                $unassigned_students = $total_students - $total_assigned;
                ?>
                <div class="stat-badge">
                    <div class="stat-number"><?php echo $unassigned_students; ?></div>
                    <div class="stat-label">Unassigned</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-number"><?php echo $total_assigned; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="game-alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($success)): ?>
            <div class="game-alert" style="background: #f0f9ff; border-color: #0ea5e9; color: #0369a1;">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Create New Group Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    Create New Group
                </h2>
            </div>
            
            <div class="section-body">
                <form method="POST" class="create-group-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Select Class</label>
                            <select name="class_id" class="form-input" required>
                                <option value="">Choose a class...</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name']); ?> 
                                        (<?php echo $class['total_students']; ?> students, <?php echo $class['group_count']; ?> groups)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Group Name</label>
                            <input type="text" name="group_name" class="form-input" 
                                   placeholder="Enter unique group name" required maxlength="50">
                            <div class="form-help">This name will appear on leaderboards</div>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_group" class="game-btn game-btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Create Group
                    </button>
                </form>
            </div>
        </div>

        <!-- Classes and Groups Overview -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Your Classes & Groups
                </h2>
            </div>

            <div class="section-body">
                <?php if(empty($classes)): ?>
                    <div class="empty-state-main">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>No Classes Found</h3>
                        <p>Create a class first to start managing groups.</p>
                        <div class="empty-actions">
                            <a href="create_class.php" class="game-btn game-btn-primary">
                                <i class="fas fa-plus-circle"></i>
                                Create First Class
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($classes as $class): ?>
                        <?php
                        // Get groups for this class
                        $groups_stmt = $pdo->prepare("
                            SELECT g.*, p.points, p.streak, 
                                   COUNT(gm.student_id) as member_count
                            FROM groups g
                            LEFT JOIN points p ON p.group_id = g.id
                            LEFT JOIN group_members gm ON gm.group_id = g.id
                            WHERE g.class_id = ?
                            GROUP BY g.id
                            ORDER BY p.points DESC, g.name ASC
                        ");
                        $groups_stmt->execute([$class['id']]);
                        $groups = $groups_stmt->fetchAll();

                        // Get class students with their group assignments
                        $students_stmt = $pdo->prepare("
                            SELECT u.id, u.name, u.email,
                                   cm.joined_at,
                                   g.name as group_name,
                                   CASE 
                                       WHEN gm.group_id IS NOT NULL THEN 'assigned'
                                       ELSE 'unassigned'
                                   END as status
                            FROM class_members cm
                            JOIN users u ON u.id = cm.student_id
                            LEFT JOIN group_members gm ON gm.student_id = u.id AND gm.group_id IN (
                                SELECT id FROM groups WHERE class_id = ?
                            )
                            LEFT JOIN groups g ON g.id = gm.group_id
                            WHERE cm.class_id = ?
                            ORDER BY u.name
                        ");
                        $students_stmt->execute([$class['id'], $class['id']]);
                        $class_students = $students_stmt->fetchAll();
                        ?>
                        
                        <div class="class-section">
                            <div class="class-header">
                                <div class="class-info">
                                    <h3 class="class-name"><?php echo htmlspecialchars($class['name']); ?></h3>
                                    <div class="class-meta">
                                        <span class="class-badge">Code: <?php echo htmlspecialchars($class['code']); ?></span>
                                        <span class="student-count">
                                            <i class="fas fa-users"></i>
                                            <?php echo $class['total_students']; ?> total students
                                        </span>
                                        <span class="assigned-count">
                                            <i class="fas fa-user-check"></i>
                                            <?php echo $class['assigned_students']; ?> assigned
                                        </span>
                                        <span class="unassigned-count">
                                            <i class="fas fa-user-clock"></i>
                                            <?php echo $class['total_students'] - $class['assigned_students']; ?> unassigned
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Class Students -->
                            <div class="students-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-user-friends"></i>
                                    Class Students (<?php echo count($class_students); ?>)
                                </h4>
                                
                                <?php if(empty($class_students)): ?>
                                    <div class="empty-state-small">
                                        <p>No students have joined this class yet.</p>
                                        <p class="form-help">Students can join using class code: <strong><?php echo htmlspecialchars($class['code']); ?></strong></p>
                                    </div>
                                <?php else: ?>
                                    <div class="students-list">
                                        <?php foreach($class_students as $student): ?>
                                            <div class="student-item">
                                                <div class="student-avatar">
                                                    <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                </div>
                                                <div class="student-details">
                                                    <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                    <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                                    <div class="student-meta">
                                                        <span class="joined-date">
                                                            Joined: <?php echo date('M j, Y', strtotime($student['joined_at'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="student-group">
                                                    <?php if($student['status'] == 'assigned'): ?>
                                                        <span class="group-badge">
                                                            <i class="fas fa-users"></i>
                                                            <?php echo htmlspecialchars($student['group_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge unassigned">
                                                            <i class="fas fa-user-clock"></i>
                                                            Unassigned
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Groups -->
                            <div class="groups-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-users"></i>
                                    Groups (<?php echo count($groups); ?>)
                                </h4>
                                
                                <?php if(empty($groups)): ?>
                                    <div class="empty-state-small">
                                        <p>No groups created for this class yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="groups-grid">
                                        <?php foreach($groups as $group): ?>
                                        <div class="group-card">
                                            <div class="group-header">
                                                <h4 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h4>
                                                <div class="group-points">
                                                    <span class="points"><?php echo $group['points'] ?? 0; ?> XP</span>
                                                    <span class="streak">🔥 <?php echo $group['streak'] ?? 0; ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="group-stats">
                                                <div class="stat">
                                                    <i class="fas fa-users"></i>
                                                    <span><?php echo $group['member_count']; ?> members</span>
                                                </div>
                                                <div class="stat">
                                                    <i class="fas fa-trophy"></i>
                                                    <span><?php echo $group['points'] ?? 0; ?> points</span>
                                                </div>
                                            </div>
                                            
                                            <div class="group-actions">
                                                <button type="button" class="game-btn game-btn-secondary" 
                                                        onclick="editGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="manage_group_members.php?group_id=<?php echo $group['id']; ?>" 
                                                   class="game-btn game-btn-primary">
                                                    <i class="fas fa-user-plus"></i> Assign Students
                                                </a>
                                                <form method="POST" class="inline-form" 
                                                      onsubmit="return confirm('Are you sure you want to delete this group? All members will become unassigned.');">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" name="delete_group" class="game-btn" style="background: #dc2626; color: white;">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Edit Group Modal -->
<div id="editGroupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Group Name</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" id="editGroupForm">
            <input type="hidden" name="group_id" id="editGroupId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Group Name</label>
                    <input type="text" name="group_name" id="editGroupName" class="form-input" required maxlength="50">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="game-btn game-btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="update_group" class="game-btn game-btn-primary">Update Group</button>
            </div>
        </form>
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

/* Section Styles */
.section {
  background: #fff;
  border: 2px solid #000;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 4px 4px 0 #3a86ff;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.section-title {
  font-size: 1.5rem;
  font-weight: bold;
  margin: 0;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.section-body {
  margin-top: 1rem;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 700;
  color: #333;
}

.form-input {
  width: 100%;
  padding: 1rem;
  font-size: 1rem;
  background: white;
  border: 2px solid #000;
  border-radius: 8px;
  transition: all 0.2s;
  font-family: 'Courier New', monospace;
}

.form-input:focus {
  outline: none;
  transform: translate(-1px, -1px);
  box-shadow: 3px 3px 0 #000;
}

.form-help {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: #6c757d;
}

/* Class Section */
.class-section {
  background: #f8f9fa;
  border: 2px solid #000;
  padding: 1.5rem;
  margin-bottom: 2rem;
  border-radius: 8px;
}

.class-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.class-info h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #333;
}

.class-meta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: center;
}

.class-badge, .student-count, .assigned-count, .unassigned-count {
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  background: #fff;
  border: 1px solid #000;
  border-radius: 4px;
}

.student-count, .assigned-count, .unassigned-count {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Students Section */
.students-section {
  margin-bottom: 2rem;
}

.section-subtitle {
  font-size: 1.1rem;
  font-weight: 700;
  color: #333;
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.students-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.student-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border: 2px solid #000;
  border-radius: 8px;
  transition: all 0.2s;
}

.student-item:hover {
  transform: translate(-2px, -2px);
  box-shadow: 3px 3px 0 #000;
}

.student-avatar {
  width: 50px;
  height: 50px;
  background: #3a86ff;
  color: white;
  border: 2px solid #000;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.2rem;
  flex-shrink: 0;
  border-radius: 4px;
}

.student-details {
  flex: 1;
  min-width: 0;
}

.student-name {
  font-weight: 600;
  color: #333;
  margin-bottom: 0.25rem;
}

.student-email {
  font-size: 0.9rem;
  color: #6c757d;
  margin-bottom: 0.5rem;
}

.student-meta {
  font-size: 0.8rem;
  color: #6c757d;
}

.joined-date {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.student-group {
  flex-shrink: 0;
}

.group-badge, .status-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.5rem 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  border: 1px solid #000;
  border-radius: 4px;
}

.group-badge {
  background: #d4edda;
  color: #155724;
}

.status-badge.unassigned {
  background: #fff3cd;
  color: #856404;
}

/* Groups Section */
.groups-section {
  margin-top: 1.5rem;
}

.groups-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.group-card {
  background: white;
  padding: 1.25rem;
  border: 2px solid #000;
  border-radius: 8px;
  transition: all 0.2s;
}

.group-card:hover {
  transform: translate(-2px, -2px);
  box-shadow: 3px 3px 0 #000;
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.group-name {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  color: #333;
}

.group-points {
  text-align: right;
  flex-shrink: 0;
}

.points {
  display: block;
  font-weight: 700;
  color: #3a86ff;
  font-size: 1.1rem;
}

.streak {
  font-size: 0.8rem;
  color: #6c757d;
}

.group-stats {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.group-stats .stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #6c757d;
}

.group-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
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
  border-color: #000;
}

.game-btn-primary:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 3px 3px 0 #000;
}

.game-btn-secondary {
  background: #ffc300;
  color: #000;
  border-color: #000;
}

.game-btn-secondary:hover {
  background: #ffaa00;
  transform: translateY(-1px);
  box-shadow: 3px 3px 0 #000;
}

/* Empty States */
.empty-state-main {
  text-align: center;
  padding: 3rem 2rem;
  background: white;
  border: 2px solid #000;
  border-radius: 12px;
  box-shadow: 4px 4px 0 #000;
  margin: 2rem 0;
}

.empty-state-small {
  text-align: center;
  padding: 1.5rem;
  background: white;
  border: 2px solid #000;
  border-radius: 8px;
  margin: 1rem 0;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  color: #6c757d;
}

.empty-state-main h3 {
  margin: 0 0 1rem 0;
  color: #374151;
  font-size: 1.5rem;
}

.empty-state-main p {
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

.game-alert i {
  font-size: 1.2rem;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.modal-content {
  background: white;
  width: 100%;
  max-width: 500px;
  border: 2px solid #000;
  border-radius: 12px;
  box-shadow: 4px 4px 0 #000;
  animation: modalAppear 0.3s ease;
}

@keyframes modalAppear {
  from { transform: scale(0.8); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 2px solid #000;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.375rem;
  font-weight: 700;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
}

.modal-close:hover {
  color: #333;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 2px solid #000;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.inline-form {
  display: inline;
}

/* Responsive */
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
  
  .dashboard-header {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  
  .header-stats {
    justify-content: center;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .class-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .class-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .students-list {
    grid-template-columns: 1fr;
  }
  
  .student-item {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }
  
  .student-group {
    width: 100%;
    text-align: center;
  }
  
  .groups-grid {
    grid-template-columns: 1fr;
  }
  
  .group-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .group-points {
    text-align: left;
  }
  
  .group-actions {
    flex-direction: column;
  }
  
  .group-actions .game-btn {
    width: 100%;
    text-align: center;
  }
  
  .modal-content {
    margin: 1rem;
    width: calc(100% - 2rem);
  }
  
  .modal-footer {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 0.5rem;
  }
  
  .section {
    padding: 1rem;
  }
}

/* Print Styles */
@media print {
  .sidebar,
  .game-btn {
    display: none;
  }
  
  .main-content {
    margin-left: 0;
    width: 100%;
  }
  
  .section {
    box-shadow: none;
    border: 1px solid #ccc;
    break-inside: avoid;
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

function editGroup(groupId, currentName) {
    document.getElementById('editGroupId').value = groupId;
    document.getElementById('editGroupName').value = currentName;
    document.getElementById('editGroupModal').style.display = 'flex';
    document.getElementById('editGroupName').focus();
}

function closeEditModal() {
    document.getElementById('editGroupModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editGroupModal');
    if (event.target === modal) {
        closeEditModal();
    }
}

// Handle escape key to close modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});
</script>