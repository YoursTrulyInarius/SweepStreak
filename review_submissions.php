<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

if ($_SESSION['role'] != 'teacher') {
    header('Location: dashboard.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
    $action = $_POST['action'] ?? '';
    $feedback = $_POST['feedback'] ?? '';

    try {
        if ($action === 'approve' && $submission_id > 0) {
            // Approve submission (only if pending)
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET status = 'approved', approved_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$submission_id]);

            // Update group points and streak
            $stmt = $pdo->prepare("
                UPDATE points p
                JOIN submissions s ON s.group_id = p.group_id
                JOIN tasks t ON t.id = s.task_id
                SET p.points = p.points + t.points,
                    p.last_submission_date = CURDATE(),
                    p.streak = CASE 
                        WHEN p.last_submission_date = CURDATE() - INTERVAL 1 DAY THEN p.streak + 1
                        ELSE 1
                    END
                WHERE s.id = ?
            ");
            $stmt->execute([$submission_id]);

            $_SESSION['success'] = "Submission approved successfully! Points awarded.";

        } elseif ($action === 'reject' && $submission_id > 0) {
            // Reject submission (only if pending)
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET status = 'rejected', notes = CONCAT(IFNULL(notes, ''), ?) 
                WHERE id = ? AND status = 'pending'
            ");
            $feedback_text = $feedback ? "\n\nTeacher Feedback: " . $feedback : "";
            $stmt->execute([$feedback_text, $submission_id]);

            // Break the group's streak immediately when a submission is rejected
            $stmt = $pdo->prepare("SELECT group_id FROM submissions WHERE id = ?");
            $stmt->execute([$submission_id]);
            $submission_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($submission_data && !empty($submission_data['group_id'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE points SET streak = 0 WHERE group_id = ?");
                    $stmt->execute([(int)$submission_data['group_id']]);
                } catch (PDOException $e) {
                    // Log but do not surface this internal error to the teacher
                    error_log("Error resetting streak after rejection: " . $e->getMessage());
                }
            }

            $_SESSION['success'] = "Submission rejected.";
        }

        header('Location: review_submissions.php');
        exit();

    } catch (PDOException $e) {
        $error = "Error processing submission: " . $e->getMessage();
    }
}

// Get pending submissions count for sidebar
$pending_count = 0;
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM submissions s
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE c.teacher_id = ? AND s.status = 'pending'
    ");
    $count_stmt->execute([$teacher_id]);
    $pending_count = (int)$count_stmt->fetchColumn();
} catch (PDOException $e) {
    // silent
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

// Fetch submissions
$pending_submissions = [];
$approved_submissions = [];
$rejected_submissions = [];
try {
    // Pending
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            t.name as task_name,
            t.points as task_points,
            t.cleaning_area,
            g.name as group_name,
            c.name as class_name,
            u.name as student_name,
            s.submitted_at
        FROM submissions s
        JOIN tasks t ON t.id = s.task_id
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        JOIN users u ON u.id = s.submitted_by
        WHERE c.teacher_id = ? AND s.status = 'pending'
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $pending_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Recently approved
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            t.name as task_name,
            t.points as task_points,
            t.cleaning_area,
            g.name as group_name,
            c.name as class_name,
            u.name as student_name,
            s.submitted_at,
            s.approved_at
        FROM submissions s
        JOIN tasks t ON t.id = s.task_id
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        JOIN users u ON u.id = s.submitted_by
        WHERE c.teacher_id = ? AND s.status = 'approved'
        ORDER BY s.approved_at DESC
        LIMIT 10
    ");
    $stmt->execute([$teacher_id]);
    $approved_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Recently rejected
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            t.name as task_name,
            t.points as task_points,
            t.cleaning_area,
            g.name as group_name,
            c.name as class_name,
            u.name as student_name,
            s.submitted_at
        FROM submissions s
        JOIN tasks t ON t.id = s.task_id
        JOIN groups g ON g.id = s.group_id
        JOIN classes c ON c.id = g.class_id
        JOIN users u ON u.id = s.submitted_by
        WHERE c.teacher_id = ? AND s.status = 'rejected'
        ORDER BY s.submitted_at DESC
        LIMIT 10
    ");
    $stmt->execute([$teacher_id]);
    $rejected_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    $error = "Error loading submissions: " . $e->getMessage();
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
      <a href="review_submissions.php" class="sidebar-link active">
        <i class="fas fa-check-double"></i> Review
        <?php if($pending_count > 0): ?>
          <span class="pending-badge"><?php echo $pending_count; ?></span>
        <?php endif; ?>
      </a>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="container">
      <div class="page-header">
        <h1 class="page-title">Review Submissions</h1>
        <p class="page-subtitle">Approve or reject student cleaning task submissions</p>
      </div>

      <?php if (isset($error)): ?>
        <div class="game-alert">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="game-alert game-alert-success">
          <i class="fas fa-check-circle"></i>
          <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
      <?php endif; ?>

      <!-- Pending Submissions -->
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">
            Pending Review
            <?php if (!empty($pending_submissions)): ?>
              <span class="badge badge-pending"><?php echo count($pending_submissions); ?> pending</span>
            <?php endif; ?>
          </h2>
        </div>

        <?php if (empty($pending_submissions)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
            <h3>All Caught Up!</h3>
            <p>No pending submissions to review.</p>
            <small style="color:#666; margin-top:1rem; display:block;">
              Submissions will appear here once students complete and submit their cleaning tasks.
            </small>
          </div>
        <?php else: ?>
          <div class="submissions-grid">
            <?php foreach ($pending_submissions as $submission): ?>
              <div class="submission-card pending">
                <div class="submission-header">
                  <div class="submission-info">
                    <h3 class="task-name"><?php echo htmlspecialchars($submission['task_name']); ?></h3>
                    <div class="submission-meta">
                      <span class="group-name"><i class="fas fa-users"></i> <?php echo htmlspecialchars($submission['group_name']); ?></span>
                      <span class="class-name"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($submission['class_name']); ?></span>
                      <span class="cleaning-area"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($submission['cleaning_area']); ?></span>
                    </div>
                    <div class="student-info"><i class="fas fa-user"></i> Submitted by: <?php echo htmlspecialchars($submission['student_name']); ?></div>
                    <div class="submission-time"><i class="fas fa-clock"></i> <?php echo date('M j, g:i A', strtotime($submission['submitted_at'])); ?></div>
                  </div>
                  <div class="points-badge">+<?php echo (int)$submission['task_points']; ?> pts</div>
                </div>

                <div class="submission-content">
                  <div class="photo-preview">
                    <div class="img-wrap">
                      <img src="<?php echo htmlspecialchars($submission['image_path']); ?>"
                           alt="Submission by <?php echo htmlspecialchars($submission['student_name']); ?>"
                           loading="lazy"
                           class="preview-image"
                           onerror="this.src='assets/img/image-missing.png'">
                      <div class="img-overlay">
                        <button type="button" class="btn btn-sm btn-outline" onclick="openImageModal('<?php echo htmlspecialchars($submission['image_path']); ?>', '<?php echo htmlspecialchars($submission['student_name']); ?>')">
                          <i class="fas fa-expand"></i> View
                        </button>
                      </div>
                    </div>
                  </div>

                  <?php if (!empty($submission['notes'])): ?>
                    <div class="student-notes">
                      <strong>Student Notes:</strong>
                      <p><?php echo nl2br(htmlspecialchars($submission['notes'])); ?></p>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="submission-actions">
                  <!-- Approve form -->
                  <form method="POST" class="approve-form">
                    <input type="hidden" name="submission_id" value="<?php echo (int)$submission['id']; ?>">
                    <input type="hidden" name="action" value="approve">

                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this submission?')">
                      <i class="fas fa-check"></i> Approve
                    </button>
                  </form>

                  <!-- Reject form -->
                  <form method="POST" class="reject-form">
                    <input type="hidden" name="submission_id" value="<?php echo (int)$submission['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="feedback" value="">

                    <button type="button" class="btn btn-danger" onclick="openRejectModal(<?php echo (int)$submission['id']; ?>)">
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recently Approved -->
      <?php if (!empty($approved_submissions)): ?>
        <div class="section">
          <div class="section-header"><h2 class="section-title">Recently Approved</h2></div>
          <div class="submissions-list">
            <?php foreach ($approved_submissions as $submission): ?>
              <div class="submission-item approved">
                <div class="submission-icon"><i class="fas fa-check-circle"></i></div>
                <div class="submission-details">
                  <div class="submission-text"><strong><?php echo htmlspecialchars($submission['group_name']); ?></strong> - <?php echo htmlspecialchars($submission['task_name']); ?></div>
                  <div class="submission-meta">
                    <span class="class-name"><?php echo htmlspecialchars($submission['class_name']); ?></span>
                    <span class="points">+<?php echo (int)$submission['task_points']; ?> points</span>
                    <span class="time">Approved: <?php echo date('M j, g:i A', strtotime($submission['approved_at'])); ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Recently Rejected -->
      <?php if (!empty($rejected_submissions)): ?>
        <div class="section">
          <div class="section-header"><h2 class="section-title">Recently Rejected</h2></div>
          <div class="submissions-list">
            <?php foreach ($rejected_submissions as $submission): ?>
              <div class="submission-item rejected">
                <div class="submission-icon"><i class="fas fa-times-circle"></i></div>
                <div class="submission-details">
                  <div class="submission-text"><strong><?php echo htmlspecialchars($submission['group_name']); ?></strong> - <?php echo htmlspecialchars($submission['task_name']); ?></div>
                  <div class="submission-meta">
                    <span class="class-name"><?php echo htmlspecialchars($submission['class_name']); ?></span>
                    <span class="time">Rejected: <?php echo date('M j, g:i A', strtotime($submission['submitted_at'])); ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Reject Submission</h3>
      <button type="button" class="modal-close" onclick="closeRejectModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <p>Please provide feedback for the student:</p>
      <form id="rejectForm" method="POST">
        <input type="hidden" name="submission_id" id="rejectSubmissionId">
        <input type="hidden" name="action" value="reject">
        <div class="form-group">
          <textarea name="feedback" class="form-input" rows="4" placeholder="Explain why this submission is being rejected..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
      <button type="submit" form="rejectForm" class="btn btn-danger"><i class="fas fa-times"></i> Reject Submission</button>
    </div>
  </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" aria-hidden="true">
  <div class="modal-content image-modal">
    <div class="modal-header">
      <h3 id="imageModalTitle">Submission Photo</h3>
      <div class="image-controls">
        <button type="button" class="btn btn-outline" id="downloadImageBtn" title="Download image"><i class="fas fa-download"></i></button>
        <button type="button" class="btn btn-outline" id="fullscreenBtn" title="Enter fullscreen"><i class="fas fa-expand-arrows-alt"></i></button>
        <button type="button" class="btn btn-outline" id="zoomBtn" title="Toggle zoom (double-tap)"><i class="fas fa-search-plus"></i></button>
        <button type="button" class="modal-close" onclick="closeImageModal()" title="Close"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="modal-body image-modal-body">
      <div class="image-loader" id="imageLoader" aria-hidden="true">Loading…</div>
      <img id="modalImage" src="" alt="Submission photo" />
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
.container { max-width: 1100px; margin: 0 auto; padding: 1rem; }
.page-header { text-align:center; margin-bottom: 1rem; padding-top: 1rem; }
.page-title { font-family: 'Courier New', monospace; font-weight:700; color:#3a86ff; font-size:1.8rem; margin:0; text-shadow: 2px 2px 0 #000; }
.page-subtitle { color:#666; margin-top:0.4rem; }

/* Section */
.section { margin-bottom:1.5rem; }
.section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.section-title { font-size:1.25rem; font-weight:700; color:#111827; }

/* Submissions grid */
.submissions-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap:1.25rem; }
.submission-card { background:#fff; border:3px solid #000; box-shadow:4px 4px 0 #000; padding:1rem; display:flex; flex-direction:column; gap:0.75rem; border-radius:4px; }
.submission-card.pending { border-color:#000; }

.submission-header { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; border-bottom:1px dashed #eee; padding-bottom:0.5rem; }
.submission-info { flex:1; }
.task-name { margin:0 0 0.5rem 0; font-weight:700; color:#111827; }
.submission-meta { display:flex; flex-wrap:wrap; gap:0.5rem; color:#6b7280; font-size:0.9rem; margin-bottom:0.5rem; }
.student-info, .submission-time { color:#6b7280; font-size:0.85rem; }

.points-badge { background:#3a86ff; color:#fff; padding:0.4rem 0.75rem; border-radius:20px; font-weight:700; }

/* Photo preview */
.photo-preview { margin-bottom:0.75rem; }
.img-wrap { position:relative; width:100%; aspect-ratio:16/9; background:linear-gradient(180deg,#f6f7f9,#fff); border:2px solid #000; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.img-wrap img.preview-image { width:100%; height:100%; object-fit:cover; display:block; transition:transform .15s ease; }
.img-wrap .img-overlay { position:absolute; inset:0; display:flex; align-items:flex-end; justify-content:flex-end; padding:8px; pointer-events:none; }
.img-overlay .btn { pointer-events:auto; }

.student-notes { background:#f8f9fa; padding:0.75rem; border-left:4px solid #3a86ff; }

/* Actions - FIXED FOR BALANCED BUTTONS */
.submission-actions { display:flex; gap:0.75rem; align-items:center; margin-top:0.5rem; }
.approve-form, .reject-form { flex:1; display:flex; }
.approve-form .btn, .reject-form .btn { width:100%; justify-content:center; }

/* Submissions list (approved/rejected) */
.submissions-list { display:flex; flex-direction:column; gap:0.75rem; }
.submission-item { display:flex; gap:0.75rem; align-items:center; padding:0.75rem; background:#fff; border:2px solid #000; box-shadow:3px 3px 0 #000; }
.submission-icon { width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:1.25rem; flex-shrink:0; }
.submission-item.approved .submission-icon { background:#d1fae5; color:#10b981; }
.submission-item.rejected .submission-icon { background:#fee2e2; color:#ef4444; }

/* Modals */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:2100; align-items:center; justify-content:center; padding:1rem; }
.modal-content { background:#fff; border:3px solid #000; box-shadow:5px 5px 0 #000; width:100%; max-width:560px; border-radius:4px; overflow:hidden; }
.modal .modal-header { display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; border-bottom:2px solid #000; }
.modal .modal-body { padding:1rem; }
.modal .modal-actions { padding:0.75rem 1rem; border-top:2px solid #000; display:flex; gap:0.5rem; justify-content:flex-end; }

/* Image modal specifics */
.image-modal { max-width:96%; max-height:96vh; display:flex; flex-direction:column; }
.image-modal .image-controls { display:flex; gap:0.5rem; align-items:center; }
.image-modal-body { padding:0.75rem; display:flex; align-items:center; justify-content:center; position:relative; background:#fff; }
#modalImage { max-width:100%; max-height:calc(100vh - 180px); object-fit:contain; user-select:none; -webkit-user-drag:none; transition:transform .12s ease; }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1rem; border:2px solid #000; background:#fff; cursor:pointer; font-weight:700; border-radius:4px; font-size:0.9rem; min-height:44px; }
.btn-sm { padding:0.35rem 0.5rem; font-size:0.85rem; min-height:auto; }
.btn-success { background:#10b981; color:#fff; border-color:rgba(0,0,0,0.05); }
.btn-danger { background:#ef4444; color:#fff; border-color:rgba(0,0,0,0.05); }
.btn-secondary { background:#6c757d; color:#fff; }
.btn-outline { background:rgba(255,255,255,0.95); border:1px solid #000; color:#333; }

.game-alert { padding:0.9rem 1rem; margin-bottom:1rem; border:2px solid #000; box-shadow:3px 3px 0 #000; display:flex; gap:0.6rem; align-items:center; background:#fff5f5; color:#900; }
.game-alert-success { background:#ecfdf5; color:#065f46; }

/* Responsive */
@media (max-width: 900px) {
  .submissions-grid { grid-template-columns: 1fr; }
  .img-wrap { aspect-ratio: 4/3; }
}
@media (max-width: 768px) {
  .page-title { font-size:1.5rem; }
}
@media (max-width: 480px) {
  .page-title { font-size:1.3rem; }
  .submission-header { flex-direction:column; align-items:flex-start; gap:0.5rem; }
  .submission-actions { flex-direction:column; align-items:stretch; }
  .btn { width:100%; justify-content:center; }
}

/* Small mobile devices */
@media (max-width: 360px) {
  .page-title { font-size:1.1rem; }
}
</style>

<script>
// Reject modal functions
function openRejectModal(submissionId) {
  document.getElementById('rejectSubmissionId').value = submissionId;
  document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
  var f = document.getElementById('rejectForm');
  if (f) f.reset();
}

// Image modal functions (responsive + zoom/pan basic)
(function(){
  const modal = document.getElementById('imageModal');
  const modalImage = document.getElementById('modalImage');
  const imageLoader = document.getElementById('imageLoader');
  const downloadBtn = document.getElementById('downloadImageBtn');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  const zoomBtn = document.getElementById('zoomBtn');
  let isZoomed = false;
  let scale = 1, lastX = 0, lastY = 0;
  let startX = 0, startY = 0, panning = false;

  window.openImageModal = function(src, title) {
    if (!src) return;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.getElementById('imageModalTitle').textContent = title || 'Submission Photo';
    imageLoader.style.display = 'block';
    modalImage.src = '';
    modalImage.style.transform = 'translate3d(0,0,0) scale(1)';
    scale = 1; lastX = 0; lastY = 0; isZoomed = false;
    modalImage.onload = function() { imageLoader.style.display = 'none'; };
    modalImage.onerror = function() { imageLoader.style.display = 'none'; modalImage.alt = 'Unable to load image'; };
    modalImage.src = src;
    downloadBtn.onclick = function() {
      const a = document.createElement('a'); a.href = src; a.download = 'submission-image'; document.body.appendChild(a); a.click(); a.remove();
    };
    fullscreenBtn.onclick = function() {
      if (!document.fullscreenElement) modal.requestFullscreen?.();
      else document.exitFullscreen?.();
    };
    zoomBtn.onclick = function() { toggleZoomAt(window.innerWidth/2, window.innerHeight/2); };
  };

  window.closeImageModal = function() {
    document.body.style.overflow = '';
    modal.style.display = 'none';
    modalImage.src = '';
    if (document.fullscreenElement) document.exitFullscreen?.();
  };

  function applyTransform() {
    modalImage.style.transform = `translate3d(${lastX}px, ${lastY}px, 0) scale(${scale})`;
  }

  function toggleZoomAt(cx, cy) {
    if (!isZoomed) {
      scale = 2;
      isZoomed = true;
      modalImage.classList.add('zoomed');
    } else {
      scale = 1;
      isZoomed = false;
      lastX = lastY = 0;
      modalImage.classList.remove('zoomed');
    }
    applyTransform();
  }

  modalImage.addEventListener('pointerdown', function(e){
    if (scale <= 1) return;
    panning = true;
    startX = e.clientX - lastX;
    startY = e.clientY - lastY;
    modalImage.setPointerCapture(e.pointerId);
  });
  modalImage.addEventListener('pointermove', function(e){
    if (!panning) return;
    lastX = e.clientX - startX;
    lastY = e.clientY - startY;
    applyTransform();
  });
  modalImage.addEventListener('pointerup', function(e){
    panning = false;
    try { modalImage.releasePointerCapture(e.pointerId); } catch(e){}
  });
  modal.addEventListener('click', function(e){
    if (e.target === modal && !modalImage.classList.contains('zoomed')) closeImageModal();
  });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { closeImageModal(); closeRejectModal(); } });
})();

// Sidebar navigation interaction
document.addEventListener('DOMContentLoaded', function(){
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