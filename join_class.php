<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'student') {
    header('Location: dashboard.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_code = strtoupper(trim($_POST['class_code']));
    
    try {
        // Find class by code
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE code = ?");
        $stmt->execute([$class_code]);
        $class = $stmt->fetch();
        
        if ($class) {
            // Check if student is already in any group in this class
            $stmt = $pdo->prepare("
                SELECT gm.id 
                FROM group_members gm
                JOIN groups g ON g.id = gm.group_id
                WHERE gm.student_id = ? AND g.class_id = ?
            ");
            $stmt->execute([$student_id, $class['id']]);
            $existing_membership = $stmt->fetch();
            
            if ($existing_membership) {
                $error = "You are already a member of this class!";
            } else {
                // Find existing groups in this class
                $stmt = $pdo->prepare("SELECT id, name FROM groups WHERE class_id = ? ORDER BY id LIMIT 1");
                $stmt->execute([$class['id']]);
                $existing_group = $stmt->fetch();
                
                if ($existing_group) {
                    // Add student to existing group
                    $group_id = $existing_group['id'];
                } else {
                    // Create a new group for this class
                    $group_name = "Group " . $class['name'];
                    $stmt = $pdo->prepare("INSERT INTO groups (name, class_id) VALUES (?, ?)");
                    $stmt->execute([$group_name, $class['id']]);
                    $group_id = $pdo->lastInsertId();
                }
                
                // Add student to group
                $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
                $stmt->execute([$group_id, $student_id]);
                
                // Initialize points for group if not exists
                $stmt = $pdo->prepare("INSERT IGNORE INTO points (group_id, points, streak) VALUES (?, 0, 0)");
                $stmt->execute([$group_id]);
                
                $_SESSION['success'] = "Successfully joined class: " . htmlspecialchars($class['name']);
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = "Invalid class code! Please check with your teacher.";
        }
    } catch (PDOException $e) {
        $error = "Error joining class: " . $e->getMessage();
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
            <a href="join_class.php" class="sidebar-link active">
                <i class="fas fa-users"></i> Classes
            </a>
            <a href="leaderboard.php" class="sidebar-link">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <a href="submit_task.php" class="sidebar-link">
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
                    <h1 class="page-title">Join a Class</h1>
                    <p class="page-subtitle">Enter your teacher's class code to start your cleaning adventure</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if(isset($error)): ?>
                <div class="alert alert-error pixel-border">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="card pixel-border">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-key"></i>
                        Enter Class Code
                    </h2>
                </div>
                
                <div class="card-body">
                    <!-- Instructions -->
                    <div class="instructions-grid">
                        <div class="instruction-item pixel-border">
                            <div class="instruction-icon pixel-border">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="instruction-content">
                                <h4>Get Code from Teacher</h4>
                                <p>Your teacher can find the class code in their dashboard</p>
                            </div>
                        </div>
                        
                        <div class="instruction-item pixel-border">
                            <div class="instruction-icon pixel-border">
                                <i class="fas fa-keyboard"></i>
                            </div>
                            <div class="instruction-content">
                                <h4>Enter Code</h4>
                                <p>Type the code exactly as given by your teacher</p>
                            </div>
                        </div>
                        
                        <div class="instruction-item pixel-border">
                            <div class="instruction-icon pixel-border">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="instruction-content">
                                <h4>Join Your Squad</h4>
                                <p>Start earning XP with your classmates</p>
                            </div>
                        </div>
                    </div>

                    <!-- Join Form -->
                    <form method="POST" class="join-form">
                        <div class="form-group">
                            <label for="class_code" class="form-label">Class Code</label>
                            <input type="text" 
                                   name="class_code" 
                                   id="class_code"
                                   class="form-input pixel-border" 
                                   placeholder="Enter class code (e.g., SCI10A)" 
                                   required 
                                   maxlength="10"
                                   pattern="[A-Za-z0-9]{1,10}"
                                   title="Please enter the class code provided by your teacher">
                            <div class="form-help">Enter the code exactly as given by your teacher</div>
                        </div>
                        
                        <button type="submit" class="pixel-button btn-block">
                            <i class="fas fa-sign-in-alt"></i>
                            Join Class Adventure
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="card pixel-border">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-lightbulb"></i>
                        Quick Tips
                    </h2>
                </div>
                
                <div class="card-body">
                    <div class="tips-list">
                        <div class="tip-item pixel-border">
                            <i class="fas fa-check-circle"></i>
                            <span>Class codes are usually 4-8 characters long</span>
                        </div>
                        <div class="tip-item pixel-border">
                            <i class="fas fa-check-circle"></i>
                            <span>Codes are case-sensitive - enter exactly as shown</span>
                        </div>
                        <div class="tip-item pixel-border">
                            <i class="fas fa-check-circle"></i>
                            <span>Ask your teacher if you can't find the code</span>
                        </div>
                        <div class="tip-item pixel-border">
                            <i class="fas fa-check-circle"></i>
                            <span>You can only join one class at a time</span>
                        </div>
                    </div>
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

.pixel-button.btn-block {
    width: 100%;
    justify-content: center;
}

/* Base Layout */
.container {
    max-width: 800px;
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

/* Instructions Grid */
.instructions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.instruction-item {
    background: white;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.2s;
}

.instruction-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.instruction-icon {
    width: 60px;
    height: 60px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    margin: 0 auto 1rem auto;
}

.instruction-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
}

.instruction-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Forms */
.join-form {
    max-width: 400px;
    margin: 0 auto;
}

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
    font-size: 1.1rem;
    font-weight: 700;
    background: white;
    transition: all 0.2s;
    text-align: center;
    text-transform: uppercase;
}

.form-input:focus {
    outline: none;
    transform: translate(-1px, -1px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.form-help {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-muted);
    text-align: center;
}

/* Tips List */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--light);
    transition: all 0.2s;
}

.tip-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 2) calc(var(--pixel-size) * 2) 0 #000;
}

.tip-item i {
    color: var(--success);
    font-size: 1.1rem;
    min-width: 20px;
}

.tip-item span {
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

.alert-error {
    background: #fdf2f2;
    color: #842029;
}

.alert i {
    font-size: 1.25rem;
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
    
    .instructions-grid {
        grid-template-columns: 1fr;
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
    
    .instruction-item {
        padding: 1.25rem;
    }
    
    .instruction-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
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

    // Auto-uppercase class code input
    const classCodeInput = document.getElementById('class_code');
    if (classCodeInput) {
        classCodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Form submission enhancement
    const joinForm = document.querySelector('.join-form');
    if (joinForm) {
        joinForm.addEventListener('submit', function(e) {
            const classCode = classCodeInput.value.trim();
            if (classCode.length < 2) {
                e.preventDefault();
                alert('Please enter a valid class code (at least 2 characters)');
                classCodeInput.focus();
            }
        });
    }
});
</script>