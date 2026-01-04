<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php'; // This will check login status

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// Get teacher's classes and data
$classes = [];
$total_students = 0;
$pending_submissions = 0;

try {
    // Get classes
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(DISTINCT gm.student_id) as student_count,
               COUNT(DISTINCT g.id) as group_count
        FROM classes c
        LEFT JOIN groups g ON g.class_id = c.id
        LEFT JOIN group_members gm ON gm.group_id = g.id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll() ?: [];

    // Total students
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT gm.student_id) as count
        FROM group_members gm
        JOIN groups g ON g.id = gm.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $total_students = $stmt->fetch()['count'] ?? 0;

    // Pending submissions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM submissions s
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE c.teacher_id = ? AND s.status='pending'
    ");
    $stmt->execute([$teacher_id]);
    $pending_submissions = $stmt->fetch()['count'] ?? 0;

} catch (PDOException $e) {
    $error = "System is initializing. Some features may not be available yet.";
}

// Check if teacher has classes for sidebar nav
$has_classes = !empty($classes);

require_once 'includes/header.php';
?>

<div class="page-wrapper">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <h2 class="sidebar-title">Menu</h2>
    </div>
    <nav class="sidebar-nav">
      <a href="teacher_dashboard.php" class="sidebar-link active">
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
      <!-- Dashboard Header -->
      <div class="dashboard-header">
        <div class="welcome-section">
          <h1 class="welcome-title pixelated">Hello, Professor <?php echo htmlspecialchars($teacher_name); ?>!</h1>
          <p class="welcome-subtitle">Manage your classroom cleaning activities</p>
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

      <!-- Quick Actions -->
      <div class="quick-actions-grid">
        <a href="create_class.php" class="action-card">
          <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
          <div class="action-content">
            <h3>Create Class</h3>
            <p>Set up a new classroom</p>
          </div>
        </a>
        <a href="assign_tasks.php" class="action-card <?php echo empty($classes) ? 'disabled' : ''; ?>">
          <div class="action-icon"><i class="fas fa-tasks"></i></div>
          <div class="action-content">
            <h3>Assign Tasks</h3>
            <p>Create cleaning assignments</p>
          </div>
        </a>
        <a href="review_submissions.php" class="action-card <?php echo $pending_submissions == 0 ? 'disabled' : ''; ?>">
          <div class="action-icon"><i class="fas fa-check-double"></i></div>
          <div class="action-content">
            <h3>Review Work</h3>
            <p><?php echo $pending_submissions; ?> pending</p>
          </div>
        </a>
        <a href="view_classes.php" class="action-card">
          <div class="action-icon"><i class="fas fa-eye"></i></div>
          <div class="action-content">
            <h3>View Classes</h3>
            <p>See all your classes</p>
          </div>
        </a>
        <a href="manage_groups.php" class="action-card <?php echo empty($classes) ? 'disabled' : ''; ?>">
          <div class="action-icon"><i class="fas fa-users"></i></div>
          <div class="action-content">
            <h3>Manage Groups</h3>
            <p>Organize student groups</p>
          </div>
        </a>
        <a href="assign_badges.php" class="action-card <?php echo empty($classes) ? 'disabled' : ''; ?>">
          <div class="action-icon"><i class="fas fa-award"></i></div>
          <div class="action-content">
            <h3>Award Badges</h3>
            <p>Recognize group achievements</p>
          </div>
        </a>
      </div>

      <!-- Additional sections like badges, leaderboards, etc., go here -->
      <!-- For brevity, only structure is shown, keep your existing code below -->

      <!-- Badge Assignment Section -->
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">🎖️ Quick Badge Assignment</h2>
          <span class="view-all">Reward outstanding groups</span>
        </div>
        <div class="badge-assignment-container">
          <?php
          // Your badge assignment code here
          // ...
          ?>
        </div>
      </div>

      <!-- Class Groups Competition -->
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">🏆 Class Groups Leaderboard</h2>
          <a href="manage_groups.php" class="view-all">Manage Groups</a>
        </div>
        <div class="groups-competition">
          <?php
          // Leaderboard code here
          // ...
          ?>
        </div>
      </div>

      <!-- Section Competition -->
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">🏆 Section Competition</h2>
        </div>
        <div class="leaderboard-preview">
          <?php
          // Section competition code
          // ...
          ?>
        </div>
      </div>

      <!-- Available Badges -->
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">Available Badges</h2>
          <span class="view-all">Students can earn these</span>
        </div>
        <div class="all-badges-grid">
          <?php
          require_once 'includes/badge_display.php';
          echo displayAvailableBadges('grid');
          ?>
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

/* Quick actions grid styles */
.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}
.action-card {
  background: #fff;
  border: 1px solid #e9ecef;
  padding: 1rem;
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.2s;
}
.action-card:hover {
  box-shadow: 0 8px 16px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}
.action-card.disabled {
  opacity: 0.6;
  pointer-events: none;
}
.action-icon i {
  font-size: 1.5rem;
  color: #3a86ff;
}
.action-content h3 {
  margin: 0;
  font-size: 1rem;
  color: #333;
}
.action-content p {
  margin: 0;
  font-size: 0.85rem;
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
}
.view-all {
  font-size: 0.9rem;
  color: #64748b;
  text-decoration: none;
}
.view-all:hover {
  color: #3a86ff;
}

/* Badge Grid */
.all-badges-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
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

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  color: #6c757d;
}

.empty-state-main h2 {
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
  
  .dashboard-header {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  
  .header-stats {
    justify-content: center;
  }
  
  .quick-actions-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
  
  .section-header {
    flex-direction: column;
    gap: 0.5rem;
    text-align: center;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 0.5rem;
  }
  
  .quick-actions-grid {
    grid-template-columns: 1fr;
  }
  
  .action-card {
    padding: 0.75rem;
  }
  
  .section {
    padding: 1rem;
  }
  
  .all-badges-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  }
}

/* Print Styles */
@media print {
  .sidebar,
  .quick-actions-grid,
  .action-card {
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

  // Mobile menu toggle (if needed in future)
  const mobileMenuToggle = document.createElement('button');
  mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
  mobileMenuToggle.className = 'mobile-menu-toggle';
  mobileMenuToggle.style.display = 'none';
  document.body.appendChild(mobileMenuToggle);

  // Show mobile menu toggle on small screens
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
});
</script>