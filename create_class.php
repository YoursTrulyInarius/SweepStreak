<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_class'])) {
    $class_name = trim($_POST['class_name']);
    
    if (empty($class_name)) {
        $error = "Class name is required!";
    } else {
        try {
            // Generate unique class code
            $class_code = strtoupper(substr(uniqid(), -6));
            
            // Check if code already exists (very unlikely but just in case)
            $check_stmt = $pdo->prepare("SELECT id FROM classes WHERE code = ?");
            $check_stmt->execute([$class_code]);
            
            if ($check_stmt->fetch()) {
                // Generate new code if collision
                $class_code = strtoupper(substr(uniqid(), -6));
            }
            
            // Create the class
            $stmt = $pdo->prepare("INSERT INTO classes (name, code, teacher_id) VALUES (?, ?, ?)");
            $stmt->execute([$class_name, $class_code, $teacher_id]);
            
            $_SESSION['success'] = "Class '$class_name' created successfully! Code: $class_code";
            
            // Redirect to view_classes.php instead of manage_groups.php
            header('Location: view_classes.php');
            exit();
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get teacher's classes count for sidebar nav
$classes_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes WHERE teacher_id = ?");
$classes_stmt->execute([$teacher_id]);
$has_classes = $classes_stmt->fetch()['count'] > 0;

// Get pending submissions count for sidebar
$pending_submissions = 0;
try {
    $pending_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM submissions s 
        JOIN groups g ON g.id = s.group_id 
        JOIN classes c ON c.id = g.class_id 
        WHERE c.teacher_id = ? AND s.status = 'pending'
    ");
    $pending_stmt->execute([$teacher_id]);
    $pending_submissions = $pending_stmt->fetch()['count'];
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
      <div class="page-header">
        <h1 class="page-title">Create New Class</h1>
        <p class="page-subtitle">Set up a new classroom for your students</p>
      </div>

      <?php if(isset($error)): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo $error; ?>
        </div>
      <?php endif; ?>

      <div class="create-class-card">
        <form method="POST" class="create-class-form">
          <div class="form-group">
            <label class="form-label">Class Name</label>
            <input type="text" 
                   name="class_name" 
                   class="form-input" 
                   placeholder="e.g., Grade 10 - Section A" 
                   required 
                   maxlength="100"
                   value="<?php echo isset($_POST['class_name']) ? htmlspecialchars($_POST['class_name']) : ''; ?>">
            <small class="form-help">Choose a descriptive name for your class</small>
          </div>

          <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
              <strong>What happens after creating a class?</strong>
              <ul>
                <li>You'll receive a unique class code</li>
                <li>Share this code with your students</li>
                <li>Students use the code to join your class</li>
                <li>You can then create groups and assign tasks</li>
              </ul>
            </div>
          </div>

          <div class="form-actions">
            <a href="teacher_dashboard.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i>
              Cancel
            </a>
            <button type="submit" name="create_class" class="btn btn-primary">
              <i class="fas fa-plus-circle"></i>
              Create Class
            </button>
          </div>
        </form>
      </div>

      <!-- Quick Guide -->
      <div class="guide-section">
        <h3 class="guide-title">Next Steps</h3>
        <div class="guide-steps">
          <div class="guide-step">
            <div class="step-number">1</div>
            <div class="step-content">
              <h4>Create Class</h4>
              <p>Give your class a name and create it</p>
            </div>
          </div>
          <div class="guide-step">
            <div class="step-number">2</div>
            <div class="step-content">
              <h4>Share Code</h4>
              <p>Students join using the unique class code</p>
            </div>
          </div>
          <div class="guide-step">
            <div class="step-number">3</div>
            <div class="step-content">
              <h4>Create Groups</h4>
              <p>Organize students into cleaning groups</p>
            </div>
          </div>
          <div class="guide-step">
            <div class="step-number">4</div>
            <div class="step-content">
              <h4>Assign Tasks</h4>
              <p>Create and assign cleaning tasks</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================== CSS for full layout with sidebar ================== -->
<style>
/* Reset */
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
}

/* Create Class Card */
.create-class-card {
  background: white;
  border: 3px solid #000;
  box-shadow: 5px 5px 0 #000;
  padding: 2rem;
  margin-bottom: 2rem;
  border-radius: 0;
}

.create-class-form {
  max-width: 600px;
  margin: 0 auto;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  font-weight: bold;
  margin-bottom: 0.5rem;
  color: #333;
  font-size: 1rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #000;
  box-shadow: 2px 2px 0 #000;
  font-size: 1rem;
  transition: all 0.2s ease;
  border-radius: 0;
  font-family: inherit;
}

.form-input:focus {
  outline: none;
  border-color: #3a86ff;
  box-shadow: 3px 3px 0 #3a86ff;
}

.form-help {
  display: block;
  margin-top: 0.5rem;
  color: #666;
  font-size: 0.85rem;
}

/* Info Box */
.info-box {
  background: #f0f9ff;
  border: 2px solid #3a86ff;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  display: flex;
  gap: 1rem;
  border-radius: 0;
}

.info-box i {
  font-size: 1.5rem;
  color: #3a86ff;
  flex-shrink: 0;
}

.info-box strong {
  display: block;
  margin-bottom: 0.5rem;
  color: #333;
}

.info-box ul {
  margin: 0;
  padding-left: 1.25rem;
}

.info-box li {
  margin: 0.25rem 0;
  color: #555;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-top: 2rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: 2px solid #000;
  box-shadow: 3px 3px 0 #000;
  font-weight: bold;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  border-radius: 0;
  font-family: inherit;
}

.btn:hover {
  transform: translate(1px, 1px);
  box-shadow: 2px 2px 0 #000;
}

.btn-primary {
  background: #3a86ff;
  color: white;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

/* Guide Section */
.guide-section {
  background: white;
  border: 3px solid #000;
  box-shadow: 5px 5px 0 #000;
  padding: 2rem;
  border-radius: 0;
}

.guide-title {
  font-weight: bold;
  margin-bottom: 1.5rem;
  text-align: center;
  font-size: 1.5rem;
  color: #333;
}

.guide-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.guide-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 1rem;
}

.step-number {
  width: 50px;
  height: 50px;
  background: #3a86ff;
  color: white;
  border: 2px solid #000;
  box-shadow: 2px 2px 0 #000;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.5rem;
  margin-bottom: 1rem;
  border-radius: 0;
}

.step-content h4 {
  margin: 0 0 0.5rem 0;
  color: #333;
  font-size: 1.1rem;
}

.step-content p {
  margin: 0;
  color: #666;
  font-size: 0.9rem;
}

/* Alert */
.alert {
  padding: 1rem;
  margin-bottom: 1.5rem;
  border: 2px solid #000;
  box-shadow: 3px 3px 0 #000;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 0;
}

.alert-error {
  background: #fee;
  color: #c00;
}

.alert i {
  font-size: 1.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .page-title {
    font-size: 1.5rem;
  }

  .create-class-card {
    padding: 1.5rem;
  }

  .form-actions {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .guide-steps {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .create-class-card {
    padding: 1rem;
  }

  .info-box {
    flex-direction: column;
    padding: 1rem;
  }

  .guide-section {
    padding: 1rem;
  }
}

/* Small mobile devices */
@media (max-width: 360px) {
  .page-title {
    font-size: 1.1rem;
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