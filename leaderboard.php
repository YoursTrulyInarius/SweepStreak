<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'];

// Get leaderboard data
$leaderboard_data = [];
$user_rank = 0;
$total_groups = 0;
$user_group = null;

try {
    if ($user_role == 'teacher') {
        // Teacher sees all groups from their classes
        $stmt = $pdo->prepare("
            SELECT 
                g.id AS group_id,
                g.name AS group_name,
                c.name AS class_name,
                u.name AS teacher_name,
                COALESCE(p.points, 0) AS total_points,
                COALESCE(p.streak, 0) AS current_streak,
                COUNT(DISTINCT gm.student_id) AS group_size,
                COUNT(DISTINCT s.id) AS tasks_completed,
                COUNT(DISTINCT gb.badge_id) AS badges_earned
            FROM groups g
            JOIN classes c ON g.class_id = c.id
            JOIN users u ON c.teacher_id = u.id
            LEFT JOIN points p ON g.id = p.group_id
            LEFT JOIN group_members gm ON g.id = gm.group_id
            LEFT JOIN submissions s ON g.id = s.group_id AND s.status = 'approved'
            LEFT JOIN group_badges gb ON g.id = gb.group_id
            WHERE c.teacher_id = ?
            GROUP BY g.id, g.name, c.name, u.name, p.points, p.streak
            ORDER BY COALESCE(p.points, 0) DESC, COALESCE(p.streak, 0) DESC, tasks_completed DESC
        ");
        $stmt->execute([$user_id]);
        $leaderboard_data = $stmt->fetchAll() ?: [];
        
    } else {
        // Student sees all groups
        $stmt = $pdo->prepare("
            SELECT 
                g.id AS group_id,
                g.name AS group_name,
                c.name AS class_name,
                u.name AS teacher_name,
                COALESCE(p.points, 0) AS total_points,
                COALESCE(p.streak, 0) AS current_streak,
                COUNT(DISTINCT gm.student_id) AS group_size,
                COUNT(DISTINCT s.id) AS tasks_completed,
                COUNT(DISTINCT gb.badge_id) AS badges_earned
            FROM groups g
            JOIN classes c ON g.class_id = c.id
            JOIN users u ON c.teacher_id = u.id
            LEFT JOIN points p ON g.id = p.group_id
            LEFT JOIN group_members gm ON g.id = gm.group_id
            LEFT JOIN submissions s ON g.id = s.group_id AND s.status = 'approved'
            LEFT JOIN group_badges gb ON g.id = gb.group_id
            GROUP BY g.id, g.name, c.name, u.name, p.points, p.streak
            ORDER BY COALESCE(p.points, 0) DESC, COALESCE(p.streak, 0) DESC, tasks_completed DESC
        ");
        $stmt->execute();
        $leaderboard_data = $stmt->fetchAll() ?: [];
    }
    
    $total_groups = count($leaderboard_data);
    
    // Get user's group rank and group info if student
    if ($user_role == 'student') {
        $stmt = $pdo->prepare("
            SELECT g.id, g.name, COALESCE(p.points,0) AS points,
                   (SELECT COUNT(*) FROM groups g2 
                    LEFT JOIN points p2 ON p2.group_id = g2.id 
                    WHERE COALESCE(p2.points, 0) > COALESCE(p.points, 0)) + 1 as rank
            FROM groups g
            JOIN group_members gm ON g.id = gm.group_id
            LEFT JOIN points p ON g.id = p.group_id
            WHERE gm.student_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $user_group = $stmt->fetch();
        $user_rank = $user_group ? $user_group['rank'] : 0;
    }
    
} catch (PDOException $e) {
    $error = "Error loading leaderboard: " . $e->getMessage();
}

// Get pending submissions count for teachers
$pending_submissions = 0;
if ($user_role == 'teacher') {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM submissions s
            JOIN groups g ON g.id = s.group_id
            JOIN classes c ON c.id = g.class_id
            WHERE c.teacher_id = ? AND s.status = 'pending'
        ");
        $stmt->execute([$user_id]);
        $pending_submissions = $stmt->fetch()['count'];
    } catch (PDOException $e) {
        // Silently fail, it's not critical
    }
}

// Check if teacher has classes for sidebar nav
$has_classes = false;
if ($user_role == 'teacher') {
    try {
        $classes_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes WHERE teacher_id = ?");
        $classes_stmt->execute([$user_id]);
        $has_classes = ((int)$classes_stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        // silent
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
      <?php if($user_role == 'teacher'): ?>
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
        <a href="leaderboard.php" class="sidebar-link active">
          <i class="fas fa-trophy"></i> Leaderboard
        </a>
        <a href="review_submissions.php" class="sidebar-link">
          <i class="fas fa-check-double"></i> Review
          <?php if($pending_submissions > 0): ?>
            <span class="pending-badge"><?php echo $pending_submissions; ?></span>
          <?php endif; ?>
        </a>
      <?php else: ?>
        <a href="dashboard.php" class="sidebar-link">
          <i class="fas fa-home"></i> Home
        </a>
        <a href="join_class.php" class="sidebar-link">
          <i class="fas fa-users"></i> Classes
        </a>
        <a href="leaderboard.php" class="sidebar-link active">
          <i class="fas fa-trophy"></i> Leaderboard
        </a>
        <a href="submit_task.php" class="sidebar-link">
          <i class="fas fa-camera"></i> Submit
        </a>
        <a href="student_profile.php" class="sidebar-link">
          <i class="fas fa-user"></i> Profile
        </a>
      <?php endif; ?>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="container">
      <!-- Header -->
      <div class="page-header">
        <div class="header-content">
          <h1 class="page-title">🏆 Leaderboard</h1>
          <p class="page-subtitle">See how squads are performing across all classes</p>
        </div>
        
        <div class="header-stats">
          <div class="stat-card pixel-border">
            <div class="stat-number"><?php echo $total_groups; ?></div>
            <div class="stat-label">Squads</div>
          </div>
          <div class="stat-card pixel-border">
            <div class="stat-number"><?php echo $user_role == 'student' && $user_rank > 0 ? '#' . $user_rank : '-'; ?></div>
            <div class="stat-label">Your Rank</div>
          </div>
          <?php if($user_role == 'teacher'): ?>
          <div class="stat-card pixel-border">
            <div class="stat-number"><?php echo $pending_submissions; ?></div>
            <div class="stat-label">Pending</div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- User Rank Card (for students) -->
      <?php if($user_role == 'student' && $user_rank > 0 && $user_group): ?>
      <div class="card pixel-border">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-user"></i>
            Your Squad Rank
          </h2>
        </div>
        <div class="card-body">
          <div class="user-rank-card">
            <div class="rank-main">
              <div class="rank-badge-large rank-<?php echo min($user_rank, 5); ?> pixel-border">
                <?php if($user_rank == 1): ?>
                  <i class="fas fa-crown"></i>
                <?php endif; ?>
                <div class="rank-number">#<?php echo $user_rank; ?></div>
                <div class="rank-label">Your Rank</div>
              </div>
              <div class="rank-details">
                <h3><?php echo htmlspecialchars($user_group['name']); ?></h3>
                <div class="rank-stats">
                  <div class="stat">
                    <div class="stat-value"><?php echo number_format($user_group['points'] ?? 0); ?></div>
                    <div class="stat-label">Total XP</div>
                  </div>
                  <div class="stat">
                    <div class="stat-value"><?php echo $total_groups; ?></div>
                    <div class="stat-label">Total Squads</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="rank-motivation pixel-border">
              <p>
                <?php if($user_rank == 1): ?>
                  🎉 <strong>You're #1!</strong> Keep up the amazing work!
                <?php elseif($user_rank <= 3): ?>
                  🔥 <strong>Top 3!</strong> You're crushing it!
                <?php elseif($user_rank <= 10): ?>
                  ⚡ <strong>Top 10!</strong> Great job, keep going!
                <?php else: ?>
                  💪 <strong>Keep cleaning!</strong> Every mission counts!
                <?php endif; ?>
              </p>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Leaderboard Section -->
      <div class="card pixel-border">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-trophy"></i>
            Squad Rankings
          </h2>
          <div class="leaderboard-filters">
            <span class="filter-label">Sorted by: XP</span>
          </div>
        </div>

        <div class="card-body">
          <?php if(empty($leaderboard_data)): ?>
            <div class="empty-state pixel-border">
              <div class="empty-icon">
                <i class="fas fa-trophy"></i>
              </div>
              <h3>No Leaderboard Data Yet</h3>
              <p>Squads need to complete missions to appear on the leaderboard.</p>
              <?php if($user_role == 'teacher'): ?>
              <div class="empty-actions">
                <a href="assign_tasks.php" class="btn btn-primary pixel-button">
                  <i class="fas fa-tasks"></i>
                  Assign Missions
                </a>
              </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="leaderboard-container pixel-border">
              <!-- Full Leaderboard List -->
              <div class="leaderboard-list">
                <div class="leaderboard-header">
                  <div class="header-rank">Rank</div>
                  <div class="header-group">Squad</div>
                  <div class="header-class desktop-only">Class</div>
                  <div class="header-points">XP</div>
                  <div class="header-streak">Streak</div>
                  <div class="header-badges">Badges</div>
                </div>
                
                <div class="leaderboard-rows">
                  <?php foreach($leaderboard_data as $index => $group): 
                    $rank = $index + 1;
                    $points = $group['total_points'] ?? 0;
                    $streak = $group['current_streak'] ?? 0;
                    $badges = $group['badges_earned'] ?? 0;
                    $is_user_group = ($user_role == 'student' && $user_rank == $rank);
                  ?>
                  <div class="leaderboard-row <?php echo $is_user_group ? 'user-group' : ''; ?>">
                    <div class="row-rank">
                      <div class="rank-number rank-<?php echo min($rank, 5); ?> pixel-border">
                        #<?php echo $rank; ?>
                      </div>
                    </div>
                    
                    <div class="row-group">
                      <div class="group-info">
                        <div class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></div>
                        <div class="group-meta">
                          <span class="group-size">👥 <?php echo $group['group_size']; ?> members</span>
                          <span class="tasks-completed">✅ <?php echo $group['tasks_completed']; ?> missions</span>
                          <span class="class-name mobile-only"><?php echo htmlspecialchars($group['class_name']); ?></span>
                        </div>
                      </div>
                    </div>
                    
                    <div class="row-class desktop-only">
                      <?php echo htmlspecialchars($group['class_name']); ?>
                    </div>
                    
                    <div class="row-points">
                      <div class="points-value"><?php echo number_format($points); ?></div>
                    </div>
                    
                    <div class="row-streak">
                      <?php if($streak > 0): ?>
                        <div class="streak-badge pixel-border">
                          <i class="fas fa-fire"></i>
                          <?php echo $streak; ?>
                        </div>
                      <?php else: ?>
                        <span class="no-streak">-</span>
                      <?php endif; ?>
                    </div>
                    
                    <div class="row-badges">
                      <?php if($badges > 0): ?>
                        <div class="badges-count pixel-border">
                          <i class="fas fa-medal"></i>
                          <?php echo $badges; ?>
                        </div>
                      <?php else: ?>
                        <span class="no-badges">-</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Section Competition -->
      <?php if($user_role == 'teacher'): ?>
      <div class="card pixel-border">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-flag"></i>
            Section Competition
          </h2>
        </div>
        <div class="card-body">
          <?php
          try {
            // For teachers: pick a class belonging to this teacher (highest points if multiple)
            $section_rank = false;
            if ($user_role === 'teacher') {
              $stmt = $pdo->prepare("
                SELECT c.id, c.name, COALESCE(SUM(p.points), 0) AS total_points
                FROM classes c
                LEFT JOIN groups g ON g.class_id = c.id
                LEFT JOIN points p ON p.group_id = g.id
                WHERE c.teacher_id = ?
                GROUP BY c.id, c.name
                ORDER BY total_points DESC
                LIMIT 1
              ");
              $stmt->execute([$user_id]);
              $section_rank = $stmt->fetch();
            }

            if ($section_rank) {
              // compute rank among all classes (how many classes have strictly more points)
              $points = (float)$section_rank['total_points'];

              $stmt = $pdo->prepare("
                SELECT COUNT(*) + 1 AS rank FROM (
                  SELECT c2.id, COALESCE(SUM(p2.points),0) AS pts
                  FROM classes c2
                  LEFT JOIN groups g2 ON g2.class_id = c2.id
                  LEFT JOIN points p2 ON p2.group_id = g2.id
                  GROUP BY c2.id
                ) t
                WHERE t.pts > ?
              ");
              $stmt->execute([$points]);
              $rankRow = $stmt->fetch();
              $rank = $rankRow ? (int)$rankRow['rank'] : 1;

              // total sections
              $stmt = $pdo->query("SELECT COUNT(DISTINCT id) AS total_sections FROM classes");
              $total_sections = $stmt->fetch()['total_sections'] ?? 0;

              $total_points = (int)$section_rank['total_points'];
              ?>
              <div class="rank-display-card">
                <div class="rank-main">
                  <div class="rank-badge-large rank-<?php echo min($rank, 5); ?> pixel-border">
                    <?php if($rank == 1): ?>
                      <i class="fas fa-crown"></i>
                    <?php endif; ?>
                    <div class="rank-number">#<?php echo $rank; ?></div>
                    <div class="rank-label">Rank</div>
                  </div>
                  <div class="rank-details">
                    <h3><?php echo htmlspecialchars($section_rank['name']); ?></h3>
                    <div class="rank-stats">
                      <div class="stat">
                        <div class="stat-value"><?php echo number_format($total_points); ?></div>
                        <div class="stat-label">Total XP</div>
                      </div>
                      <div class="stat">
                        <div class="stat-value"><?php echo $total_sections; ?></div>
                        <div class="stat-label">Total Sections</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            } else {
              // No section found for this teacher
              ?>
              <div class="empty-state pixel-border">
                <div class="empty-icon">
                  <i class="fas fa-flag"></i>
                </div>
                <h3>No Section Data</h3>
                <p>Sections will appear here once classes/squads have XP.</p>
              </div>
              <?php
            }
          } catch (PDOException $e) {
            echo '<div class="empty-state pixel-border"><p>Error loading section competition</p></div>';
          }
          ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Available Badges Section -->
      <div class="card pixel-border">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-award"></i>
            Available Badges
          </h2>
          <span class="card-badge">Students can earn these</span>
        </div>
        
        <div class="card-body">
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

/* Pixel Styling Variables */
:root {
  --pixel-size: 2px;
  --primary: #3a86ff;
  --primary-dark: #1e6feb;
  --primary-light: #6ba4ff;
  --secondary: #ff6b4a;
  --success: #28a745;
  --warning: #ffc107;
  --danger: #dc3545;
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
}

.pixel-button:hover {
  transform: translate(1px, 1px);
  box-shadow: 
      calc(var(--pixel-size) * 0.5) calc(var(--pixel-size) * 0.5) 0 #000;
}

.pixel-button:active {
  transform: translate(2px, 2px);
  box-shadow: none;
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
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--dark);
  text-shadow: 2px 2px 0 #000;
  letter-spacing: -0.5px;
  font-family: 'Courier New', monospace;
}

.page-subtitle {
  margin: 0;
  color: var(--text-muted);
  font-size: 1.2rem;
  font-weight: 500;
  font-family: 'Courier New', monospace;
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

/* Leaderboard Styles */
.leaderboard-container {
  background: white;
  overflow: hidden;
}

.leaderboard-list {
  background: white;
}

.leaderboard-header {
  display: grid;
  grid-template-columns: 80px 1fr 1fr 100px 80px 80px;
  gap: 1rem;
  padding: 1.25rem 1rem;
  background: var(--bg-secondary);
  border-bottom: var(--pixel-size) solid #000;
  font-weight: 700;
  font-family: 'Courier New', monospace;
  font-size: 0.95rem;
  color: var(--text);
}

.leaderboard-rows {
  display: flex;
  flex-direction: column;
}

.leaderboard-row {
  display: grid;
  grid-template-columns: 80px 1fr 1fr 100px 80px 80px;
  gap: 1rem;
  padding: 1.25rem 1rem;
  border-bottom: var(--pixel-size) solid #e9ecef;
  align-items: center;
  transition: all 0.2s ease;
  background: white;
}

.leaderboard-row:hover {
  background: var(--bg-primary);
  transform: translate(-2px, -2px);
  box-shadow: 
      calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
}

.leaderboard-row.user-group {
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  border-left: 4px solid var(--primary);
}

.leaderboard-row:last-child {
  border-bottom: none;
}

.row-rank {
  display: flex;
  justify-content: center;
}

.rank-number {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #6c757d;
  color: white;
  font-weight: 800;
  font-family: 'Courier New', monospace;
  font-size: 0.95rem;
  transition: all 0.2s ease;
}

.rank-1 { 
  background: linear-gradient(135deg, #FFD700, #FFA500);
  color: #000;
  transform: scale(1.1);
}
.rank-2 { 
  background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
}
.rank-3 { 
  background: linear-gradient(135deg, #CD7F32, #A66A28);
}
.rank-4 { 
  background: linear-gradient(135deg, #6c757d, #495057);
}
.rank-5 { 
  background: linear-gradient(135deg, #495057, #343a40);
}

.group-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.group-name {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--dark);
  font-family: 'Courier New', monospace;
}

.group-meta {
  display: flex;
  gap: 1.25rem;
  font-size: 0.85rem;
  color: var(--text-muted);
  font-family: 'Courier New', monospace;
}

.class-name.mobile-only {
  display: none;
}

.row-class {
  font-weight: 600;
  color: var(--text);
  font-family: 'Courier New', monospace;
}

.points-value {
  font-weight: 800;
  font-size: 1.2rem;
  color: var(--primary);
  text-align: center;
  font-family: 'Courier New', monospace;
}

.streak-badge {
  background: linear-gradient(135deg, #ff6b6b, #ff5252);
  color: white;
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  justify-content: center;
  font-family: 'Courier New', monospace;
  min-width: 60px;
  box-sizing: border-box;
}

.no-streak, .no-badges {
  color: var(--text-muted);
  font-style: italic;
  text-align: center;
  font-family: 'Courier New', monospace;
  min-width: 60px;
  display: inline-block;
}

.badges-count {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  justify-content: center;
  font-family: 'Courier New', monospace;
  min-width: 60px;
  box-sizing: border-box;
}

/* User Rank Card */
.user-rank-card {
  background: white;
}

.rank-main {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-bottom: 1.5rem;
}

.rank-badge-large {
  width: 120px;
  height: 120px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #6c757d;
  color: white;
  font-weight: 800;
  font-family: 'Courier New', monospace;
  transition: all 0.3s ease;
}

.rank-badge-large:hover {
  transform: scale(1.05) rotate(5deg);
}

.rank-badge-large i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.rank-number {
  font-size: 2.2rem;
  line-height: 1;
  font-weight: 800;
}

.rank-label {
  font-size: 0.9rem;
  margin-top: 0.375rem;
  font-weight: 600;
}

.rank-details {
  flex: 1;
}

.rank-details h3 {
  margin: 0 0 1.25rem 0;
  font-size: 1.75rem;
  color: var(--dark);
  font-weight: 700;
  font-family: 'Courier New', monospace;
}

.rank-stats {
  display: flex;
  gap: 2.5rem;
}

.rank-stats .stat {
  text-align: center;
}

.rank-stats .stat-value {
  font-weight: 800;
  font-size: 1.75rem;
  color: var(--primary);
  font-family: 'Courier New', monospace;
}

.rank-stats .stat-label {
  font-size: 0.95rem;
  color: var(--text-muted);
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

.rank-motivation {
  text-align: center;
  padding: 1.5rem;
  background: var(--bg-primary);
  font-family: 'Courier New', monospace;
  border: var(--pixel-size) solid #000;
}

.rank-motivation p {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text);
}

/* Empty States */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: var(--light);
  margin: 1rem 0;
}

.empty-icon {
  font-size: 4rem;
  color: var(--text-muted);
  margin-bottom: 1.5rem;
  opacity: 0.5;
}

.empty-state h3 {
  margin: 0 0 1.25rem 0;
  color: var(--text);
  font-size: 1.5rem;
  font-weight: 700;
  font-family: 'Courier New', monospace;
}

.empty-state p {
  margin: 0 0 2rem 0;
  color: var(--text-muted);
  font-size: 1.1rem;
  font-family: 'Courier New', monospace;
}

.empty-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 2rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 1rem;
  font-family: 'Courier New', monospace;
  text-transform: uppercase;
  border: none;
}

.btn-primary {
  background: var(--primary);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
  color: white;
}

.btn-block {
  width: 100%;
  justify-content: center;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .leaderboard-header {
    grid-template-columns: 70px 1fr 100px 70px 70px;
    gap: 0.8rem;
    padding: 1rem;
  }

  .leaderboard-row {
    grid-template-columns: 70px 1fr 100px 70px 70px;
    gap: 0.8rem;
    padding: 1rem;
  }

  .header-class.desktop-only,
  .row-class.desktop-only {
    display: none;
  }

  .class-name.mobile-only {
    display: inline;
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    text-align: center;
    gap: 1.75rem;
  }
  
  .header-stats {
    justify-content: center;
    width: 100%;
  }
  
  .leaderboard-header {
    grid-template-columns: 60px 1fr 80px 60px 60px;
    gap: 0.6rem;
    padding: 0.8rem;
    font-size: 0.8rem;
  }

  .leaderboard-row {
    grid-template-columns: 60px 1fr 80px 60px 60px;
    gap: 0.6rem;
    padding: 0.8rem;
  }

  .rank-number {
    width: 45px;
    height: 45px;
    font-size: 0.85rem;
  }

  .points-value {
    font-size: 1rem;
  }

  .streak-badge,
  .badges-count {
    min-width: 50px;
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
  }

  .no-streak,
  .no-badges {
    min-width: 50px;
    font-size: 0.75rem;
  }

  .group-meta {
    flex-direction: column;
    gap: 0.25rem;
  }

  .rank-main {
    flex-direction: column;
    text-align: center;
    gap: 1.5rem;
  }

  .rank-stats {
    justify-content: center;
  }
}

@media (max-width: 640px) {
  .leaderboard-header {
    grid-template-columns: 50px 1fr 70px 50px 50px;
    gap: 0.5rem;
    padding: 0.7rem;
    font-size: 0.75rem;
  }

  .leaderboard-row {
    grid-template-columns: 50px 1fr 70px 50px 50px;
    gap: 0.5rem;
    padding: 0.7rem;
  }

  .rank-number {
    width: 40px;
    height: 40px;
    font-size: 0.8rem;
  }

  .points-value {
    font-size: 0.9rem;
  }

  .streak-badge,
  .badges-count {
    min-width: 45px;
    font-size: 0.7rem;
    padding: 0.25rem 0.4rem;
  }

  .no-streak,
  .no-badges {
    min-width: 45px;
    font-size: 0.7rem;
  }

  .group-name {
    font-size: 1rem;
  }

  .group-meta {
    font-size: 0.75rem;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 1rem;
  }
  
  .leaderboard-header {
    grid-template-columns: 45px 1fr 60px 45px 45px;
    gap: 0.4rem;
    padding: 0.6rem;
    font-size: 0.7rem;
  }

  .leaderboard-row {
    grid-template-columns: 45px 1fr 60px 45px 45px;
    gap: 0.4rem;
    padding: 0.6rem;
  }

  .rank-number {
    width: 35px;
    height: 35px;
    font-size: 0.75rem;
  }

  .points-value {
    font-size: 0.85rem;
  }

  .streak-badge,
  .badges-count {
    min-width: 40px;
    font-size: 0.65rem;
    padding: 0.2rem 0.3rem;
  }

  .no-streak,
  .no-badges {
    min-width: 40px;
    font-size: 0.65rem;
  }

  .page-title {
    font-size: 2rem;
  }

  .stat-card {
    min-width: 140px;
    padding: 1.25rem;
  }
}

@media (max-width: 380px) {
  .leaderboard-header {
    grid-template-columns: 40px 1fr 55px 40px 40px;
    gap: 0.3rem;
    padding: 0.5rem;
    font-size: 0.65rem;
  }

  .leaderboard-row {
    grid-template-columns: 40px 1fr 55px 40px 40px;
    gap: 0.3rem;
    padding: 0.5rem;
  }

  .rank-number {
    width: 32px;
    height: 32px;
    font-size: 0.7rem;
  }

  .points-value {
    font-size: 0.8rem;
  }

  .streak-badge,
  .badges-count {
    min-width: 35px;
    font-size: 0.6rem;
    padding: 0.15rem 0.25rem;
  }

  .no-streak,
  .no-badges {
    min-width: 35px;
    font-size: 0.6rem;
  }

  .group-name {
    font-size: 0.9rem;
  }

  .group-meta {
    font-size: 0.7rem;
  }
}

/* Utility classes for responsive design */
.desktop-only {
  display: block;
}

.mobile-only {
  display: none;
}

@media (max-width: 1024px) {
  .desktop-only {
    display: none;
  }
  
  .mobile-only {
    display: inline;
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

  // Ensure badges are properly displayed on resize
  window.addEventListener('resize', function() {
    const badges = document.querySelectorAll('.badges-count, .streak-badge');
    badges.forEach(badge => {
      badge.style.minWidth = '60px';
    });
  });
});
</script>