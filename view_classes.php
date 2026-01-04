<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher's classes with detailed info
$classes = [];
$pending_submissions = 0;

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(DISTINCT gm.student_id) as student_count,
               COUNT(DISTINCT g.id) as group_count,
               COUNT(DISTINCT t.id) as task_count
        FROM classes c
        LEFT JOIN groups g ON g.class_id = c.id
        LEFT JOIN group_members gm ON gm.group_id = g.id
        LEFT JOIN tasks t ON t.class_id = c.id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll() ?: [];

    // Get pending submissions count for sidebar
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
    $error = "Error loading classes: " . $e->getMessage();
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
      <a href="teacher_dashboard.php" class="sidebar-link">
        <i class="fas fa-home"></i> Dashboard
      </a>
      <a href="view_classes.php" class="sidebar-link active">
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
        <h1 class="page-title">My Classes</h1>
        <p class="page-subtitle">Manage your classrooms and view student progress</p>
      </div>

      <?php if(isset($error)): ?>
        <div class="game-alert">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo $error; ?>
        </div>
      <?php endif; ?>

      <?php if(empty($classes)): ?>
        <div class="empty-state-main">
          <div class="empty-icon">
            <i class="fas fa-users"></i>
          </div>
          <h2>No Classes Yet</h2>
          <p>Create your first class to start managing classroom cleaning activities.</p>
          <div class="empty-actions">
            <a href="create_class.php" class="game-btn game-btn-primary">
              <i class="fas fa-plus-circle"></i>
              Create First Class
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="classes-grid">
          <?php foreach($classes as $class): ?>
          <div class="class-card">
            <div class="class-header">
              <div class="class-avatar">
                <i class="fas fa-users"></i>
              </div>
              <div class="class-info">
                <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                <div class="class-code">Code: <strong><?php echo htmlspecialchars($class['code']); ?></strong></div>
                <p class="class-date">Created: <?php echo date('M j, Y', strtotime($class['created_at'])); ?></p>
              </div>
            </div>
            
            <div class="class-stats">
              <div class="stat-item">
                <div class="stat-number"><?php echo $class['student_count']; ?></div>
                <div class="stat-label">Students</div>
              </div>
              <div class="stat-item">
                <div class="stat-number"><?php echo $class['group_count']; ?></div>
                <div class="stat-label">Groups</div>
              </div>
              <div class="stat-item">
                <div class="stat-number"><?php echo $class['task_count']; ?></div>
                <div class="stat-label">Tasks</div>
              </div>
            </div>
            
            <div class="class-actions">
              <a href="class_details.php?id=<?php echo $class['id']; ?>" class="game-btn game-btn-primary">
                <i class="fas fa-eye"></i>
                View Details
              </a>
              <a href="manage_class.php?id=<?php echo $class['id']; ?>" class="game-btn game-btn-secondary">
                <i class="fas fa-cog"></i>
                Manage
              </a>
              <button class="game-btn game-btn-outline copy-code" data-code="<?php echo htmlspecialchars($class['code']); ?>">
                <i class="fas fa-copy"></i>
                Copy Code
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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

/* MODIFIED: Pixelated Blue & White Title Style */
.page-title {
  /* Pixelated/Impact Font Style (Blue & White Theme) */
  font-family: 'Courier New', monospace; /* Pixelated font */
  -webkit-font-smoothing: none;
  -moz-osx-font-smoothing: grayscale;
  letter-spacing: 2px;
  font-size: 2.5rem; 
  font-weight: 900;
  line-height: 1.2;
  margin-bottom: 0.5rem;
  
  /* Blue & White Color Theme with Pixelated/Blocky Effect */
  color: white; /* White text */
  background: #3a86ff; /* Blue background block */
  text-shadow: 
      3px 3px 0 #000, /* Black outline */
      -3px -3px 0 #000; 
  
  padding: 5px 10px;
  border: 3px solid #000;
  display: inline-block;
  box-shadow: 5px 5px 0 #000;
}

.page-subtitle {
  font-family: 'Inter', sans-serif;
  color: #64748b;
  font-size: 1rem;
  margin-top: 0;
}

/* Classes Grid - Responsive */
.classes-grid {
  display: grid;
  /* Adjusted minmax for better screen utilization */
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

/* --- Class Card Redesign --- */
.class-card {
  background: #f7f9fc; /* Light background for contrast */
  border-radius: 12px;
  border: 3px solid #000; /* Thicker, punchier border */
  padding: 1.5rem;
  box-shadow: 6px 6px 0 #3a86ff; /* Primary color shadow */
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden; /* For pseudo-elements/decorations */
}

.class-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 10px;
  background: #3a86ff; /* Accent line at the top */
}

.class-card:hover {
  transform: translateY(-4px); /* More pronounced lift */
  box-shadow: 8px 8px 0 #ff6b6b; /* Accent color on hover */
}

.class-header {
  display: flex;
  align-items: center; /* Align items to center */
  gap: 1rem;
  margin-bottom: 1.5rem; /* Increased spacing */
}

.class-avatar {
  width: 70px; /* Slightly larger icon */
  height: 70px;
  border-radius: 50%; /* Circle shape */
  background: #3a86ff; /* Primary color */
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem; /* Larger icon */
  flex-shrink: 0;
  border: 3px solid #000; /* Border for pop */
  box-shadow: 2px 2px 0 #000;
}

.class-info {
  flex: 1;
}

.class-info h3 {
  margin: 0 0 0.25rem 0;
  color: #1e293b;
  font-size: 1.5rem; /* Larger title */
  font-weight: 800; /* Extra bold */
  line-height: 1.2;
}

.class-code {
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.class-code strong {
  color: #000;
  font-family: 'Courier New', monospace;
  background: #fee78b; /* Highlight color */
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  border: 2px solid #000; /* Strong border for the code badge */
  font-weight: bold;
  box-shadow: 1px 1px 0 #000;
}

.class-date {
  color: #94a3b8;
  font-size: 0.85rem;
  margin: 0;
  font-style: italic;
}

.class-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* Ensure 3 columns */
  gap: 1rem;
  margin: 1.5rem 0;
  padding: 1rem 0;
  border-top: 2px dashed #e2e8f0; /* Dashed separator */
  border-bottom: 2px dashed #e2e8f0;
}

.stat-item {
  text-align: center;
  padding: 0.5rem;
  background: white;
  border-radius: 8px;
  border: 2px solid #000;
  box-shadow: 2px 2px 0 #94a3b8;
}

.stat-number {
  font-size: 1.75rem; /* Larger number */
  font-weight: 900; /* Black weight */
  color: #ff6b6b; /* Accent color for metrics */
  display: block;
  line-height: 1;
}

.stat-label {
  font-size: 0.75rem; /* Smaller label */
  color: #374151;
  margin-top: 0.5rem;
  text-transform: uppercase;
  font-weight: bold;
}

/* Actions Layout - Desktop */
.class-actions {
  display: grid;
  grid-template-columns: 1fr 1fr; /* Two buttons per row for most actions */
  gap: 0.5rem;
  margin-top: 1.5rem;
  padding-top: 0.5rem;
}

.class-actions .game-btn-primary,
.class-actions .game-btn-secondary {
  grid-column: span 1;
}

.class-actions .copy-code {
  grid-column: 1 / -1; /* Make Copy Code button span full width on desktop */
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
  background: #ffc300; /* Changed secondary color for better contrast */
  color: #000;
  border-color: #000;
}

.game-btn-secondary:hover {
  background: #ffaa00;
  transform: translateY(-1px);
  box-shadow: 3px 3px 0 #000;
}

.game-btn-outline {
  background: #f0f4f8; /* Lighter outline background */
  color: #374151;
  border-color: #000;
}

.game-btn-outline:hover {
  background: #e2e8f0;
  transform: translateY(-1px);
  box-shadow: 3px 3px 0 #000;
}

.game-btn.copied {
  background: #10b981;
  color: white;
  border-color: #059669;
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

/* Responsive Design Overrides */
@media (max-width: 1024px) {
  .classes-grid {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
  }
}

@media (max-width: 768px) {
  .classes-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .class-card {
    padding: 1.25rem;
  }

  .class-header {
    flex-direction: row;
    text-align: left;
    align-items: center;
  }
  
  .class-avatar {
    align-self: flex-start;
  }
  
  .class-stats {
    gap: 0.75rem;
    grid-template-columns: repeat(3, 1fr);
  }
  
  .stat-number {
    font-size: 1.5rem;
  }
  
  /* --- Action Button Centering Fix --- */
  .class-actions {
    /* Override grid to use flex for centered stacking */
    display: flex; 
    flex-direction: column;
    align-items: center; /* Centers buttons horizontally */
    gap: 0.5rem;
  }
  
  .class-actions .game-btn { 
    width: 80%; /* Constrain width */
    max-width: 300px;
  }
  
  /* Ensure grid span settings don't interfere with new flex centering */
  .class-actions .game-btn-primary,
  .class-actions .game-btn-secondary,
  .class-actions .copy-code {
    grid-column: 1 / -1; 
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

@media (max-width: 480px) {
  .dashboard-container .container {
    padding: 0 1rem;
  }
  
  .class-card {
    padding: 1rem;
    margin: 0 -0.5rem;
  }
  
  .class-header {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
  }
  
  .class-avatar {
    align-self: center;
    width: 50px;
    height: 50px;
    font-size: 1.5rem;
  }
  
  .class-info h3 {
    font-size: 1.25rem;
  }
  
  .class-stats {
    /* Switch to column view for stats on tiny screens */
    grid-template-columns: 1fr;
    gap: 0.75rem;
    padding: 0.75rem 0;
  }
  
  .stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-align: left;
    box-shadow: 1px 1px 0 #94a3b8;
    padding: 0.5rem 1rem;
  }
  
  .stat-number {
    font-size: 1.2rem;
  }
  
  .stat-label {
    margin-top: 0;
  }
  
  .game-btn {
    padding: 0.6rem 0.8rem;
    font-size: 0.85rem;
  }
  
  .empty-state-main {
    padding: 2rem 1rem;
    margin: 1rem -0.5rem;
  }
  
  .empty-icon {
    font-size: 3rem;
  }
  
  .empty-state-main h2 {
    font-size: 1.25rem;
  }
}

/* Print Styles */
@media print {
  .class-actions,
  .bottom-nav {
    display: none;
  }
  
  .class-card {
    box-shadow: none;
    border: 1px solid #ccc;
    break-inside: avoid;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Copy class code functionality
  document.querySelectorAll('.copy-code').forEach(button => {
    button.addEventListener('click', function() {
      // NOTE: Using document.execCommand('copy') for better compatibility in iframe environments
      const code = this.getAttribute('data-code');
      
      // Create a temporary input element
      const tempInput = document.createElement('input');
      tempInput.value = code;
      document.body.appendChild(tempInput);
      tempInput.select();
      
      try {
        // Execute the copy command
        const successful = document.execCommand('copy');
        
        if (successful) {
          const originalText = this.innerHTML;
          this.innerHTML = '<i class="fas fa-check"></i> Copied!';
          this.classList.add('copied');
          
          setTimeout(() => {
            this.innerHTML = originalText;
            this.classList.remove('copied');
          }, 2000);
        }
      } catch (err) {
        // Fallback for failed copy (e.g., console log an error)
        console.error('Failed to copy text: ', err);
      } finally {
        // Remove the temporary input element
        document.body.removeChild(tempInput);
      }
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