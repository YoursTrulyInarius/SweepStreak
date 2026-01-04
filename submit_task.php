<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'student') {
    header('Location: dashboard.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Get student's current tasks
$tasks = [];
$group = null;

try {
    // Get student's group
    $stmt = $pdo->prepare("
        SELECT g.*, c.name as class_name, c.id as class_id
        FROM group_members gm
        JOIN groups g ON g.id = gm.group_id
        JOIN classes c ON c.id = g.class_id
        WHERE gm.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $group = $stmt->fetch();

    if ($group) {
        // Get today's tasks (only tasks that don't have pending submissions from this group)
        $stmt = $pdo->prepare("
            SELECT t.* 
            FROM tasks t
            WHERE t.class_id = ? 
            AND (t.due_date IS NULL OR t.due_date >= CURDATE())
            AND NOT EXISTS (
                SELECT 1 FROM submissions s 
                WHERE s.task_id = t.id 
                AND s.group_id = ? 
                AND s.status = 'pending'
            )
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$group['class_id'], $group['id']]);
        $tasks = $stmt->fetchAll() ?: [];
    }
} catch (PDOException $e) {
    $error = "Error loading tasks: " . $e->getMessage();
}

// Handle photo submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = $_POST['task_id'];
    $notes = $_POST['notes'] ?? '';
    
    // Validate task exists and belongs to student's class
    if ($group) {
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND class_id = ?");
        $stmt->execute([$task_id, $group['class_id']]);
        $valid_task = $stmt->fetch();
        
        if (!$valid_task) {
            $error = "Invalid task selected.";
        }
        
        // CRITICAL: Check if there's already a pending submission for this task from this group
        $check_pending_stmt = $pdo->prepare("
            SELECT id FROM submissions 
            WHERE task_id = ? AND group_id = ? AND status = 'pending'
        ");
        $check_pending_stmt->execute([$task_id, $group['id']]);
        
        if ($check_pending_stmt->fetch()) {
            $error = "Your squad already has a pending submission for this mission. Please wait for it to be reviewed before submitting another one.";
        }
    }
    
    if (!isset($error)) {
        // Handle file upload
        if (isset($_FILES['cleaning_photo']) && $_FILES['cleaning_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['cleaning_photo']['name'], PATHINFO_EXTENSION));
            $filename = 'submission_' . time() . '_' . $student_id . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            // Validate file type and size
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($file_extension, $allowed_types)) {
                if ($_FILES['cleaning_photo']['size'] <= $max_file_size) {
                    if (move_uploaded_file($_FILES['cleaning_photo']['tmp_name'], $file_path)) {
                        try {
                            // Double-check one more time before inserting (race condition protection)
                            $final_check_stmt = $pdo->prepare("
                                SELECT id FROM submissions 
                                WHERE task_id = ? AND group_id = ? AND status = 'pending'
                            ");
                            $final_check_stmt->execute([$task_id, $group['id']]);
                            
                            if ($final_check_stmt->fetch()) {
                                $error = "Another squad member just submitted this mission. Please refresh the page.";
                                // Clean up uploaded file
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            } else {
                                // Create submission
                                $stmt = $pdo->prepare("
                                    INSERT INTO submissions (task_id, group_id, image_path, submitted_by, notes) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$task_id, $group['id'], $file_path, $student_id, $notes]);
                                
                                $_SESSION['success'] = "Mission submitted successfully! Waiting for teacher approval.";
                                header('Location: dashboard.php');
                                exit();
                            }
                        } catch (PDOException $e) {
                            // Check if it's a duplicate submission error
                            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'unique_pending_task_group') !== false) {
                                $error = "Another squad member just submitted this mission. Please refresh the page.";
                            } else {
                                $error = "Error submitting mission: " . $e->getMessage();
                            }
                            // Clean up uploaded file
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    } else {
                        $error = "Error uploading file. Please try again.";
                    }
                } else {
                    $error = "File is too large. Maximum size is 5MB.";
                }
            } else {
                $error = "Please upload a valid image file (JPG, PNG, GIF).";
            }
        } else {
            $upload_error = $_FILES['cleaning_photo']['error'] ?? 'Unknown error';
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File is too large.',
                UPLOAD_ERR_FORM_SIZE => 'File is too large.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'Please select a photo of your completed cleaning mission.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
            ];
            $error = $error_messages[$upload_error] ?? 'Please select a photo of your completed cleaning mission.';
        }
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
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="join_class.php" class="sidebar-link">
                <i class="fas fa-users"></i> Classes
            </a>
            <a href="leaderboard.php" class="sidebar-link">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <a href="submit_task.php" class="sidebar-link active">
                <i class="fas fa-camera"></i> Submit
            </a>
            <a href="student_profile.php" class="sidebar-link">
                <i class="fas fa-user"></i> Profile
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Header -->
            <div class="page-header">
                <div class="header-content">
                    <h1 class="page-title">Submit Mission Proof</h1>
                    <p class="page-subtitle">Upload photo evidence of your completed cleaning mission</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if(isset($error)): ?>
                <div class="alert alert-error pixel-border">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(!$group): ?>
                <!-- No Class State -->
                <div class="empty-state pixel-border">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h2>No Squad Found</h2>
                    <p>You need to join a class before you can submit cleaning missions.</p>
                    <div class="empty-actions">
                        <a href="join_class.php" class="btn btn-primary pixel-button">
                            <i class="fas fa-sign-in-alt"></i>
                            Join a Class
                        </a>
                        <a href="dashboard.php" class="btn btn-outline pixel-button">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

            <?php elseif(empty($tasks)): ?>
                <!-- No Tasks State -->
                <div class="empty-state pixel-border">
                    <div class="empty-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h2>All Missions Completed!</h2>
                    <p>Great work! There are no missions available for submission right now.</p>
                    <div class="empty-actions">
                        <a href="dashboard.php" class="btn btn-primary pixel-button">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Mission Submission Form -->
                <div class="card pixel-border">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-camera"></i>
                            Submit Mission Proof
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="submission-form" id="submissionForm">
                            <!-- Mission Selection -->
                            <div class="form-group">
                                <label for="task_id" class="form-label">Select Mission</label>
                                <select name="task_id" id="task_id" class="form-input pixel-border" required>
                                    <option value="">Choose a mission to submit...</option>
                                    <?php foreach($tasks as $task): ?>
                                        <option value="<?php echo $task['id']; ?>" 
                                                data-area="<?php echo htmlspecialchars($task['cleaning_area']); ?>" 
                                                data-points="<?php echo $task['points']; ?>" 
                                                data-description="<?php echo htmlspecialchars($task['description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($task['name']); ?> - <?php echo htmlspecialchars($task['cleaning_area']); ?> (+<?php echo $task['points']; ?> XP)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Mission Preview -->
                            <div class="mission-preview pixel-border" id="missionPreview" style="display: none;">
                                <div class="preview-header">
                                    <h4>Mission Details</h4>
                                </div>
                                <div class="preview-content">
                                    <div class="preview-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span id="previewArea"></span>
                                    </div>
                                    <div class="preview-item">
                                        <i class="fas fa-star"></i>
                                        <span id="previewPoints"></span>
                                    </div>
                                    <div class="preview-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="previewDescription"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Photo Upload -->
                            <div class="form-group">
                                <label class="form-label">Upload Proof Photo</label>
                                
                                <!-- Mobile Camera Options -->
                                <div class="camera-options" id="cameraOptions">
                                    <button type="button" id="openCamera" class="pixel-button">
                                        <i class="fas fa-camera"></i>
                                        Take Photo
                                    </button>
                                    <button type="button" id="uploadFile" class="pixel-button btn-outline">
                                        <i class="fas fa-upload"></i>
                                        Choose File
                                    </button>
                                </div>

                                <!-- Desktop Upload Area -->
                                <div class="upload-area pixel-border" id="uploadArea">
                                    <div class="upload-icon">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                    <div class="upload-text">
                                        <h4>Take a Photo of Your Work</h4>
                                        <p>Click to select or drag & drop a photo</p>
                                        <p class="upload-hint">Supports JPG, PNG, GIF • Max 5MB</p>
                                    </div>
                                    <input type="file" name="cleaning_photo" id="cleaning_photo" accept="image/*" required hidden>
                                </div>

                                <!-- Image Preview -->
                                <div class="preview-container pixel-border" id="previewContainer" style="display: none;">
                                    <div class="preview-header">
                                        <h4>Photo Preview</h4>
                                    </div>
                                    <img id="imagePreview" src="" alt="Preview">
                                    <div class="preview-actions">
                                        <button type="button" id="retakePhoto" class="pixel-button btn-outline">
                                            <i class="fas fa-redo"></i>
                                            Retake
                                        </button>
                                        <button type="button" id="removeImage" class="pixel-button btn-danger">
                                            <i class="fas fa-times"></i>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="form-group">
                                <label for="notes" class="form-label">Mission Notes (Optional)</label>
                                <textarea name="notes" id="notes" class="form-input pixel-border" rows="3" 
                                          placeholder="Add any notes about your cleaning work..."></textarea>
                            </div>

                            <!-- Submission Actions -->
                            <div class="form-actions">
                                <button type="submit" class="pixel-button btn-block" id="submitButton" disabled>
                                    <i class="fas fa-paper-plane"></i>
                                    Submit for Approval
                                </button>
                                <a href="dashboard.php" class="pixel-button btn-outline btn-block">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
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
    max-width: 600px;
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

.card-body {
    padding: 1.5rem;
}

/* Forms */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 700;
    color: var(--text);
}

.form-input {
    width: 100%;
    padding: 1rem;
    font-size: 1rem;
    background: white;
    transition: all 0.2s;
    font-family: 'Courier New', monospace;
}

.form-input:focus {
    outline: none;
    transform: translate(-1px, -1px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

textarea.form-input {
    resize: vertical;
    min-height: 100px;
}

/* Mission Preview */
.mission-preview {
    background: var(--light);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.2s;
}

.mission-preview:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.preview-header {
    margin-bottom: 1rem;
    border-bottom: var(--pixel-size) solid #000;
    padding-bottom: 0.5rem;
}

.preview-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
}

.preview-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.preview-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.preview-item i {
    color: var(--primary);
    width: 20px;
    text-align: center;
}

/* Upload Area */
.camera-options {
    display: none;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.upload-area {
    background: var(--light);
    padding: 3rem 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 1rem;
}

.upload-area:hover {
    background: #e9ecef;
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.upload-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.upload-text h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text);
}

.upload-text p {
    margin: 0 0 0.5rem 0;
    color: var(--text-muted);
}

.upload-hint {
    font-size: 0.875rem;
    color: var(--text-muted);
}

/* Preview Container */
.preview-container {
    background: var(--light);
    padding: 1.5rem;
    text-align: center;
    transition: all 0.2s;
}

.preview-container:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.preview-container img {
    max-width: 100%;
    max-height: 300px;
    border: var(--pixel-size) solid #000;
    margin-bottom: 1rem;
}

.preview-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Form Actions */
.form-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 2rem;
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

.alert-error {
    background: #fdf2f2;
    color: #842029;
}

.alert i {
    font-size: 1.25rem;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    margin: 2rem 0;
    transition: all 0.2s;
}

.empty-state:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.empty-state h2 {
    margin: 0 0 1rem 0;
    color: var(--text);
}

.empty-state p {
    margin: 0 0 2rem 0;
    color: var(--text-muted);
    font-size: 1.1rem;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
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
    
    .camera-options {
        display: flex;
    }
    
    .upload-area {
        display: none;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }

    .page-header {
        flex-direction: column;
        text-align: center;
        gap: 1.75rem;
    }

    .empty-actions {
        flex-direction: column;
        align-items: center;
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
        font-size: 1.5rem;
    }
    
    .upload-area, .mission-preview, .preview-container {
        padding: 1rem;
    }
    
    .preview-actions {
        flex-direction: column;
    }
    
    .empty-actions {
        flex-direction: column;
    }
    
    .pixel-button {
        padding: 0.875rem 1.5rem;
        font-size: 0.9rem;
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

    // Form elements
    const taskSelect = document.getElementById('task_id');
    const missionPreview = document.getElementById('missionPreview');
    const previewArea = document.getElementById('previewArea');
    const previewPoints = document.getElementById('previewPoints');
    const previewDescription = document.getElementById('previewDescription');
    const fileInput = document.getElementById('cleaning_photo');
    const uploadArea = document.getElementById('uploadArea');
    const cameraOptions = document.getElementById('cameraOptions');
    const previewContainer = document.getElementById('previewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const openCameraBtn = document.getElementById('openCamera');
    const uploadFileBtn = document.getElementById('uploadFile');
    const retakeButton = document.getElementById('retakePhoto');
    const removeButton = document.getElementById('removeImage');
    const submitButton = document.getElementById('submitButton');
    const form = document.getElementById('submissionForm');

    // Task selection preview
    taskSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const area = selectedOption.getAttribute('data-area');
            const points = selectedOption.getAttribute('data-points');
            const description = selectedOption.getAttribute('data-description');
            
            previewArea.textContent = area;
            previewPoints.textContent = '+' + points + ' XP';
            previewDescription.textContent = description || 'No description provided';
            missionPreview.style.display = 'block';
        } else {
            missionPreview.style.display = 'none';
        }
        updateSubmitButton();
    });

    // Mobile camera options
    if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        cameraOptions.style.display = 'flex';
        uploadArea.style.display = 'none';
    }

    // Open camera
    openCameraBtn.addEventListener('click', function() {
        fileInput.setAttribute('capture', 'environment');
        fileInput.click();
    });

    // Upload file
    uploadFileBtn.addEventListener('click', function() {
        fileInput.removeAttribute('capture');
        fileInput.click();
    });

    // Desktop upload area
    uploadArea.addEventListener('click', () => fileInput.click());

    // File input change
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Remove image
    removeButton.addEventListener('click', function() {
        resetFileInput();
    });

    // Retake photo
    retakeButton.addEventListener('click', function() {
        resetFileInput();
        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            cameraOptions.style.display = 'flex';
        } else {
            uploadArea.style.display = 'block';
        }
    });

    function handleFileSelect(file) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                previewContainer.style.display = 'block';
                uploadArea.style.display = 'none';
                cameraOptions.style.display = 'none';
                updateSubmitButton();
            };
            reader.readAsDataURL(file);
        } else {
            alert('Please select an image file (JPG, PNG, GIF).');
            resetFileInput();
        }
    }

    function resetFileInput() {
        fileInput.value = '';
        previewContainer.style.display = 'none';
        
        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            cameraOptions.style.display = 'flex';
        } else {
            uploadArea.style.display = 'block';
        }
        updateSubmitButton();
    }

    function updateSubmitButton() {
        const taskSelected = taskSelect.value !== '';
        const fileSelected = fileInput.files.length > 0;
        submitButton.disabled = !(taskSelected && fileSelected);
    }

    // Form validation
    form.addEventListener('submit', function(e) {
        const taskSelected = taskSelect.value !== '';
        const fileSelected = fileInput.files.length > 0;
        
        if (!taskSelected || !fileSelected) {
            e.preventDefault();
            alert('Please select a mission and upload a photo before submitting.');
        }
    });
});
</script>