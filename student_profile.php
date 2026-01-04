<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if ($_SESSION['role'] != 'student') {
    header('Location: dashboard.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Initialize variables
$student_data = [];
$group_info = [];
$class_info = [];
$teacher_info = [];
$available_icons = [];
$available_accessories = [];
$unlocked_items = [];

try {
    // Get student's basic information
    $stmt = $pdo->prepare("
        SELECT u.*, 
               DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), u.birthday)), '%Y') + 0 AS age,
               g.name as group_name,
               g.id as group_id,
               c.name as class_name,
               c.id as class_id,
               t.name as teacher_name,
               t.id as teacher_id,
               p.points as total_points,
               p.streak as current_streak
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.student_id
        LEFT JOIN groups g ON g.id = gm.group_id
        LEFT JOIN classes c ON c.id = g.class_id
        LEFT JOIN users t ON t.id = c.teacher_id
        LEFT JOIN points p ON p.group_id = g.id
        WHERE u.id = ?
    ");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch();

    // Get student's achievements and stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as tasks_completed,
            COUNT(DISTINCT gb.badge_id) as badges_earned,
            COUNT(DISTINCT s2.id) as pending_submissions
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.student_id
        LEFT JOIN groups g ON g.id = gm.group_id
        LEFT JOIN submissions s ON s.group_id = g.id AND s.status = 'approved'
        LEFT JOIN submissions s2 ON s2.group_id = g.id AND s2.status = 'pending'
        LEFT JOIN group_badges gb ON gb.group_id = g.id
        WHERE u.id = ?
    ");
    $stmt->execute([$student_id]);
    $student_stats = $stmt->fetch();

    // Get available avatar icons with fallback
    try {
        $stmt = $pdo->prepare("
            SELECT ai.*, 
                   CASE 
                       WHEN ai.unlocked_by_default = TRUE THEN TRUE
                       WHEN su.item_id IS NOT NULL THEN TRUE
                       ELSE FALSE
                   END as is_unlocked
            FROM avatar_icons ai
            LEFT JOIN student_unlocks su ON su.student_id = ? AND su.item_type = 'icon' AND su.item_id = ai.id
            ORDER BY ai.category, ai.display_name
        ");
        $stmt->execute([$student_id]);
        $available_icons = $stmt->fetchAll();
        
        // If no icons found, provide default set
        if (empty($available_icons)) {
            $available_icons = [
                ['name' => 'student', 'display_name' => 'Student', 'icon' => '👤', 'is_unlocked' => true],
                ['name' => 'star', 'display_name' => 'Star', 'icon' => '⭐', 'is_unlocked' => true],
                ['name' => 'book', 'display_name' => 'Book', 'icon' => '📚', 'is_unlocked' => true],
                ['name' => 'lightbulb', 'display_name' => 'Light Bulb', 'icon' => '💡', 'is_unlocked' => true],
            ];
        }
    } catch (PDOException $e) {
        // Fallback if table doesn't exist
        $available_icons = [
            ['name' => 'student', 'display_name' => 'Student', 'icon' => '👤', 'is_unlocked' => true],
            ['name' => 'star', 'display_name' => 'Star', 'icon' => '⭐', 'is_unlocked' => true],
            ['name' => 'book', 'display_name' => 'Book', 'icon' => '📚', 'is_unlocked' => true],
            ['name' => 'lightbulb', 'display_name' => 'Light Bulb', 'icon' => '💡', 'is_unlocked' => true],
        ];
    }

    // Get available avatar accessories with fallback
    try {
        $stmt = $pdo->prepare("
            SELECT aa.*,
                   CASE 
                       WHEN aa.unlocked_by_default = TRUE THEN TRUE
                       WHEN su.item_id IS NOT NULL THEN TRUE
                       WHEN ? >= aa.required_level THEN TRUE
                       ELSE FALSE
                   END as is_unlocked
            FROM avatar_accessories aa
            LEFT JOIN student_unlocks su ON su.student_id = ? AND su.item_type = 'accessory' AND su.item_id = aa.id
            ORDER BY aa.category, aa.required_level, aa.display_name
        ");
        $stmt->execute([$student_data['level'] ?? 1, $student_id]);
        $available_accessories = $stmt->fetchAll();
        
        // If no accessories found, provide default set
        if (empty($available_accessories)) {
            $available_accessories = [
                ['name' => 'none', 'display_name' => 'None', 'icon' => '', 'is_unlocked' => true, 'required_level' => 1],
                ['name' => 'glasses', 'display_name' => 'Glasses', 'icon' => '😎', 'is_unlocked' => true, 'required_level' => 1],
                ['name' => 'cap', 'display_name' => 'Cap', 'icon' => '🧢', 'is_unlocked' => true, 'required_level' => 1],
            ];
        }
    } catch (PDOException $e) {
        // Fallback if table doesn't exist
        $available_accessories = [
            ['name' => 'none', 'display_name' => 'None', 'icon' => '', 'is_unlocked' => true, 'required_level' => 1],
            ['name' => 'glasses', 'display_name' => 'Glasses', 'icon' => '😎', 'is_unlocked' => true, 'required_level' => 1],
            ['name' => 'cap', 'display_name' => 'Cap', 'icon' => '🧢', 'is_unlocked' => true, 'required_level' => 1],
        ];
    }

} catch (PDOException $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $birthday = $_POST['birthday'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $bio = trim($_POST['bio']);

    // Validate inputs
    if (empty($name)) {
        $error = "Name is required.";
    } elseif (!empty($birthday) && strtotime($birthday) > strtotime('today')) {
        $error = "Birthday cannot be in the future.";
    } else {
        try {
            // Update student profile
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, birthday = ?, phone = ?, address = ?, bio = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $birthday, $phone, $address, $bio, $student_id]);

            // Update session name if changed
            if ($name != $_SESSION['name']) {
                $_SESSION['name'] = $name;
            }

            $success = "Profile updated successfully!";
            
            // Refresh student data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$student_id]);
            $student_data = array_merge($student_data, $stmt->fetch());

        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle avatar customization
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_avatar'])) {
    $avatar_style = $_POST['avatar_style'] ?? 'pixel';
    $avatar_color = $_POST['avatar_color'] ?? '#3a86ff';
    $avatar_icon = $_POST['avatar_icon'] ?? 'student';
    $avatar_accessories = $_POST['avatar_accessories'] ?? 'none';

    // Validate that the selected items exist and are unlocked
    $icon_valid = false;
    $accessory_valid = false;

    // Check if icon exists and is unlocked
    foreach ($available_icons as $icon) {
        if ($icon['name'] == $avatar_icon) {
            if ($icon['is_unlocked']) {
                $icon_valid = true;
            }
            break;
        }
    }

    // Check if accessory exists and is unlocked
    foreach ($available_accessories as $accessory) {
        if ($accessory['name'] == $avatar_accessories) {
            if ($accessory['is_unlocked']) {
                $accessory_valid = true;
            }
            break;
        }
    }

    if (!$icon_valid) {
        $avatar_error = "Selected avatar icon is not available or unlocked!";
    } elseif (!$accessory_valid) {
        $avatar_error = "Selected accessory is not available or unlocked!";
    } else {
        try {
            // Update avatar customization
            $stmt = $pdo->prepare("
                UPDATE users 
                SET avatar_style = ?, avatar_color = ?, avatar_icon = ?, avatar_accessories = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$avatar_style, $avatar_color, $avatar_icon, $avatar_accessories, $student_id]);

            if ($result) {
                $avatar_success = "Avatar updated successfully!";
                
                // Refresh student data
                $student_data['avatar_style'] = $avatar_style;
                $student_data['avatar_color'] = $avatar_color;
                $student_data['avatar_icon'] = $avatar_icon;
                $student_data['avatar_accessories'] = $avatar_accessories;
            } else {
                $avatar_error = "Failed to update avatar in database.";
            }

        } catch (PDOException $e) {
            $avatar_error = "Error updating avatar: " . $e->getMessage();
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
            <a href="submit_task.php" class="sidebar-link">
                <i class="fas fa-camera"></i> Submit
            </a>
            <a href="student_profile.php" class="sidebar-link active">
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
                    <h1 class="page-title">My Profile</h1>
                    <p class="page-subtitle">Customize your avatar and manage your personal information</p>
                </div>
            </div>

            <!-- Global Messages - Only for profile updates -->
            <?php if(isset($success) && isset($_POST['update_profile'])): ?>
                <div class="alert alert-success global-alert pixel-border">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error) && isset($_POST['update_profile'])): ?>
                <div class="alert alert-error global-alert pixel-border">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Left Column: Avatar Customization & Stats -->
                <div class="profile-left">
                    <!-- Avatar Customization Section -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-user-astronaut"></i>
                                Custom Avatar
                            </h2>
                        </div>
                        <div class="card-body">
                            <!-- Avatar-specific alerts - INSIDE THE CARD -->
                            <?php if(isset($avatar_success)): ?>
                                <div class="alert alert-success avatar-alert pixel-border">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $avatar_success; ?>
                                </div>
                            <?php endif; ?>

                            <?php if(isset($avatar_error)): ?>
                                <div class="alert alert-error avatar-alert pixel-border">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $avatar_error; ?>
                                </div>
                            <?php endif; ?>

                            <div class="avatar-customization">
                                <!-- Avatar Preview -->
                                <div class="avatar-preview-container">
                                    <div class="avatar-preview <?php echo htmlspecialchars($student_data['avatar_style'] ?? 'pixel'); ?>-style pixel-border" 
                                         id="avatarPreview" 
                                         style="background-color: <?php echo htmlspecialchars($student_data['avatar_color'] ?? '#3a86ff'); ?>;">
                                        <div class="avatar-icon" id="avatarIcon">
                                            <?php 
                                            // Get the current avatar icon name
                                            $current_icon = $student_data['avatar_icon'] ?? 'student';
                                            $icon_found = false;
                                            
                                            // Find the matching icon from available_icons
                                            foreach($available_icons as $icon) {
                                                if ($icon['name'] == $current_icon) {
                                                    echo $icon['icon'];
                                                    $icon_found = true;
                                                    break;
                                                }
                                            }
                                            
                                            // Fallback if icon not found
                                            if (!$icon_found) {
                                                echo '👤'; // Default icon
                                            }
                                            ?>
                                        </div>
                                        <div class="avatar-accessory" id="avatarAccessory">
                                            <?php 
                                            $current_accessory = $student_data['avatar_accessories'] ?? 'none';
                                            if ($current_accessory != 'none') {
                                                $accessory_found = false;
                                                
                                                // Find the matching accessory from available_accessories
                                                foreach($available_accessories as $accessory) {
                                                    if ($accessory['name'] == $current_accessory) {
                                                        echo $accessory['icon'];
                                                        $accessory_found = true;
                                                        break;
                                                    }
                                                }
                                                
                                                // Fallback if accessory not found but should be displayed
                                                if (!$accessory_found && $current_accessory != 'none') {
                                                    echo '✨'; // Default accessory
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customization Form -->
                                <form method="POST" class="avatar-form" id="avatarForm">
                                    <input type="hidden" name="update_avatar" value="1">
                                    
                                    <!-- Style Selection -->
                                    <div class="customization-group">
                                        <label class="form-label">Avatar Style</label>
                                        <div class="style-options">
                                            <?php 
                                            $styles = [
                                                'pixel' => ['icon' => '🔲', 'name' => 'Pixel'],
                                                'round' => ['icon' => '⭕', 'name' => 'Round'], 
                                                'square' => ['icon' => '⬛', 'name' => 'Square']
                                            ];
                                            $current_style = $student_data['avatar_style'] ?? 'pixel';
                                            foreach($styles as $style => $data): 
                                                $is_selected = $current_style === $style;
                                            ?>
                                            <label class="style-option <?php echo $is_selected ? 'selected' : ''; ?> pixel-border">
                                                <input type="radio" name="avatar_style" value="<?php echo $style; ?>" 
                                                       <?php echo $is_selected ? 'checked' : ''; ?>>
                                                <span class="style-icon"><?php echo $data['icon']; ?></span>
                                                <span class="style-name"><?php echo $data['name']; ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Color Selection -->
                                    <div class="customization-group">
                                        <label class="form-label">Avatar Color</label>
                                        <div class="color-options">
                                            <?php 
                                            $colors = [
                                                '#3a86ff' => 'Blue',
                                                '#ff006e' => 'Pink', 
                                                '#8338ec' => 'Purple',
                                                '#fb5607' => 'Orange',
                                                '#ffbe0b' => 'Yellow',
                                                '#06d6a0' => 'Green'
                                            ];
                                            $current_color = $student_data['avatar_color'] ?? '#3a86ff';
                                            foreach($colors as $color => $name): 
                                                $is_selected = $current_color === $color;
                                            ?>
                                            <label class="color-option <?php echo $is_selected ? 'selected' : ''; ?> pixel-border" 
                                                   style="background-color: <?php echo $color; ?>">
                                                <input type="radio" name="avatar_color" value="<?php echo $color; ?>" 
                                                       <?php echo $is_selected ? 'checked' : ''; ?>>
                                                <span class="color-checkmark">✓</span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Icon Selection -->
                                    <div class="customization-group">
                                        <label class="form-label">Avatar Icon</label>
                                        <div class="icon-options">
                                            <?php 
                                            $current_icon = $student_data['avatar_icon'] ?? 'student';
                                            foreach($available_icons as $icon): 
                                                $is_selected = $current_icon === $icon['name'];
                                                $is_unlocked = $icon['is_unlocked'];
                                            ?>
                                            <label class="icon-option <?php echo $is_selected ? 'selected' : ''; ?> <?php echo !$is_unlocked ? 'locked' : ''; ?> pixel-border"
                                                   title="<?php echo htmlspecialchars($icon['display_name'] ?? $icon['name']); ?><?php echo !$is_unlocked ? ' (Locked)' : ''; ?>">
                                                <input type="radio" name="avatar_icon" value="<?php echo htmlspecialchars($icon['name']); ?>" 
                                                       <?php echo $is_selected ? 'checked' : ''; ?> <?php echo !$is_unlocked ? 'disabled' : ''; ?>>
                                                <span class="icon-display"><?php echo $icon['icon']; ?></span>
                                                <?php if(!$is_unlocked): ?>
                                                    <div class="lock-overlay">🔒</div>
                                                <?php endif; ?>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Accessory Selection -->
                                    <div class="customization-group">
                                        <label class="form-label">Accessories</label>
                                        <div class="accessory-options">
                                            <?php 
                                            $current_accessory = $student_data['avatar_accessories'] ?? 'none';
                                            foreach($available_accessories as $accessory): 
                                                $is_selected = $current_accessory === $accessory['name'];
                                                $is_unlocked = $accessory['is_unlocked'];
                                            ?>
                                            <label class="accessory-option <?php echo $is_selected ? 'selected' : ''; ?> <?php echo !$is_unlocked ? 'locked' : ''; ?> pixel-border"
                                                   title="<?php echo htmlspecialchars($accessory['display_name'] ?? $accessory['name']); ?><?php echo !$is_unlocked ? ' (Requires Level ' . ($accessory['required_level'] ?? 1) . ')' : ''; ?>">
                                                <input type="radio" name="avatar_accessories" value="<?php echo htmlspecialchars($accessory['name']); ?>" 
                                                       <?php echo $is_selected ? 'checked' : ''; ?> <?php echo !$is_unlocked ? 'disabled' : ''; ?>>
                                                <span class="accessory-display"><?php echo $accessory['icon']; ?></span>
                                                <?php if(!$is_unlocked): ?>
                                                    <div class="lock-overlay">🔒</div>
                                                    <div class="requirement-badge pixel-border">Lv. <?php echo $accessory['required_level'] ?? 1; ?></div>
                                                <?php endif; ?>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <button type="submit" class="pixel-button btn-block">
                                        <i class="fas fa-save"></i>
                                        Save Avatar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-chart-bar"></i>
                                My Stats
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-item pixel-border">
                                    <div class="stat-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $student_data['total_points'] ?? 0; ?></div>
                                        <div class="stat-label">Total XP</div>
                                    </div>
                                </div>
                                <div class="stat-item pixel-border">
                                    <div class="stat-icon">
                                        <i class="fas fa-fire"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $student_data['current_streak'] ?? 0; ?></div>
                                        <div class="stat-label">Day Streak</div>
                                    </div>
                                </div>
                                <div class="stat-item pixel-border">
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $student_stats['tasks_completed'] ?? 0; ?></div>
                                        <div class="stat-label">Missions Done</div>
                                    </div>
                                </div>
                                <div class="stat-item pixel-border">
                                    <div class="stat-icon">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $student_stats['badges_earned'] ?? 0; ?></div>
                                        <div class="stat-label">Badges</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Profile Form & Class Info -->
                <div class="profile-right">
                    <!-- Personal Information -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-user-edit"></i>
                                Personal Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="profile-form">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" name="name" id="name" 
                                               class="form-input pixel-border" 
                                               value="<?php echo htmlspecialchars($student_data['name'] ?? ''); ?>" 
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="birthday" class="form-label">Birthday</label>
                                        <input type="date" name="birthday" id="birthday" 
                                               class="form-input pixel-border" 
                                               value="<?php echo htmlspecialchars($student_data['birthday'] ?? ''); ?>">
                                        <?php if(isset($student_data['age'])): ?>
                                            <div class="form-help">Age: <?php echo $student_data['age']; ?> years old</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" id="phone" 
                                               class="form-input pixel-border" 
                                               value="<?php echo htmlspecialchars($student_data['phone'] ?? ''); ?>"
                                               placeholder="+63 XXX XXX XXXX">
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea name="address" id="address" 
                                                  class="form-input pixel-border" 
                                                  rows="3"
                                                  placeholder="Enter your complete address"><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="bio" class="form-label">Bio</label>
                                        <textarea name="bio" id="bio" 
                                                  class="form-input pixel-border" 
                                                  rows="4"
                                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($student_data['bio'] ?? ''); ?></textarea>
                                        <div class="form-help">Max 500 characters</div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="pixel-button">
                                        <i class="fas fa-save"></i>
                                        Save Changes
                                    </button>
                                    <button type="reset" class="pixel-button btn-outline">
                                        <i class="fas fa-undo"></i>
                                        Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Class Information -->
                    <?php if($student_data['class_name']): ?>
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-users"></i>
                                Class Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="class-info">
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-users"></i>
                                        Squad:
                                    </div>
                                    <div class="info-value"><?php echo htmlspecialchars($student_data['group_name']); ?></div>
                                </div>
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-graduation-cap"></i>
                                        Class:
                                    </div>
                                    <div class="info-value"><?php echo htmlspecialchars($student_data['class_name']); ?></div>
                                </div>
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        Teacher:
                                    </div>
                                    <div class="info-value"><?php echo htmlspecialchars($student_data['teacher_name']); ?></div>
                                </div>
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-user-tag"></i>
                                        Role: 
                                    </div>
                                    <div class="info-value">
                                        <span class="role-badge pixel-border">Student</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-users"></i>
                                Class Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="empty-class-state">
                                <i class="fas fa-users-slash"></i>
                                <h3>Not in a Class</h3>
                                <p>Join a class to start participating in cleaning missions and earning XP!</p>
                                <a href="join_class.php" class="pixel-button">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Join a Class
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Account Information -->
                    <div class="card pixel-border">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-shield-alt"></i>
                                Account Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="account-info">
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-envelope"></i>
                                        Email
                                    </div>
                                    <div class="info-value"><?php echo htmlspecialchars($student_data['email'] ?? 'Not set'); ?></div>
                                </div>
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-calendar-plus"></i>
                                        Member Since
                                    </div>
                                    <div class="info-value">
                                        <?php echo $student_data['created_at'] ? date('F j, Y', strtotime($student_data['created_at'])) : 'Unknown'; ?>
                                    </div>
                                </div>
                                <div class="info-item pixel-border">
                                    <div class="info-label">
                                        <i class="fas fa-clock"></i>
                                        Last Updated
                                    </div>
                                    <div class="info-value">
                                        <?php echo $student_data['updated_at'] ? date('F j, Y g:i A', strtotime($student_data['updated_at'])) : 'Never'; ?>
                                    </div>
                                </div>
                            </div>
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

.pixel-button.btn-outline {
    background: transparent;
    color: var(--text);
    border-color: var(--border);
}

.pixel-button.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
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

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 2rem;
}

/* Avatar Alert Styles */
.avatar-alert {
    margin: -1.5rem -1.5rem 2rem -1.5rem;
    border-left: none;
    border-right: none;
    border-top: none;
    border-radius: 0;
    text-align: center;
    font-weight: bold;
    padding: 1rem 1.5rem;
}

.avatar-alert.alert-success {
    background: #f0f9f4;
    border-bottom: 3px solid var(--success);
    color: #0f5132;
}

.avatar-alert.alert-error {
    background: #fdf2f2;
    border-bottom: 3px solid var(--danger);
    color: #842029;
}

/* Global Alert Styles */
.global-alert {
    position: relative;
    margin: 1rem auto 2rem auto;
    max-width: 100%;
    z-index: 10;
}

/* Avatar Customization Styles */
.avatar-customization {
    text-align: center;
}

.avatar-preview-container {
    margin-bottom: 2.5rem;
}

.avatar-preview {
    width: 140px;
    height: 140px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    position: relative;
    transition: all 0.3s ease;
    background-color: <?php echo htmlspecialchars($student_data['avatar_color'] ?? '#3a86ff'); ?>;
}

/* Style classes for different avatar shapes */
.pixel-style {
    border-radius: 0 !important;
}

.round-style {
    border-radius: 50% !important;
}

.square-style {
    border-radius: 20px !important;
}

.avatar-icon {
    font-size: 3.5rem;
    transition: transform 0.2s ease;
}

.avatar-accessory {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 2rem;
    z-index: 2;
    transition: all 0.3s ease;
}

.customization-group {
    margin-bottom: 2rem;
    text-align: left;
}

.customization-group:last-child {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 1rem;
    font-weight: 700;
    color: var(--text);
    font-family: 'Courier New', monospace;
    font-size: 1.1rem;
}

.style-options, .color-options, .icon-options, .accessory-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.style-option, .color-option, .icon-option, .accessory-option {
    padding: 1rem 0.5rem;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
    background: white;
    position: relative;
    min-height: 70px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.style-option:hover, .color-option:hover, .icon-option:hover, .accessory-option:hover {
    transform: translate(-2px, -2px);
    box-shadow: 4px 4px 0 #000;
    background: #f8f9fa;
}

.style-option.selected, .color-option.selected, .icon-option.selected, .accessory-option.selected {
    background: var(--primary);
    color: white;
    transform: translate(-1px, -1px);
    box-shadow: 2px 2px 0 #000;
}

.color-option {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    padding: 0;
    position: relative;
    min-height: auto;
}

.color-checkmark {
    display: none;
    color: white;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
    text-shadow: 1px 1px 0 #000;
    font-size: 1.4rem;
}

.color-option.selected .color-checkmark {
    display: block;
}

.icon-option, .accessory-option {
    font-size: 1.8rem;
    padding: 1rem 0.5rem;
    min-height: 80px;
}

.icon-option.locked, .accessory-option.locked {
    opacity: 0.5;
    cursor: not-allowed;
    background: #e9ecef;
}

.icon-option.locked:hover, .accessory-option.locked:hover {
    transform: none;
    box-shadow: none;
    background: #e9ecef;
}

.lock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border-radius: 0;
}

.requirement-badge {
    position: absolute;
    bottom: -8px;
    right: -8px;
    background: var(--primary);
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border: 2px solid #000;
    font-weight: bold;
}

.style-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.style-name {
    font-size: 0.85rem;
    font-weight: 600;
}

.icon-display, .accessory-display {
    font-size: 2rem;
    display: block;
}

/* Hide radio inputs but keep them accessible */
.style-option input[type="radio"],
.color-option input[type="radio"], 
.icon-option input[type="radio"],
.accessory-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

/* Improved spacing for form elements */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-input {
    width: 100%;
    padding: 1rem;
    font-size: 1rem;
    background: white;
    transition: all 0.2s;
    font-family: 'Courier New', monospace;
    margin-bottom: 0.5rem;
}

.form-input:focus {
    outline: none;
    transform: translate(-1px, -1px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.form-help {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

/* Stats grid improvements */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.stat-item {
    background: var(--light);
    padding: 1.5rem 1rem;
    text-align: center;
    transition: all 0.2s;
}

.stat-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.stat-icon {
    font-size: 1.8rem;
    color: var(--primary);
    margin-bottom: 0.75rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text);
    font-family: 'Courier New', monospace;
}

.stat-label {
    font-size: 0.95rem;
    color: var(--text-muted);
    font-family: 'Courier New', monospace;
}

/* Info Items */
.class-info, .account-info {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem;
    background: var(--light);
    transition: all 0.2s;
}

.info-item:hover {
    transform: translate(-2px, -2px);
    box-shadow: 
        calc(var(--pixel-size) * 3) calc(var(--pixel-size) * 3) 0 #000;
}

.info-label {
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-value {
    color: var(--text-muted);
    font-weight: 500;
}

.role-badge {
    background: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 700;
}

/* Empty States */
.empty-class-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.empty-class-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-class-state h3 {
    margin: 0 0 1rem 0;
    color: var(--text);
}

.empty-class-state p {
    margin: 0 0 2rem 0;
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
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .style-options, .color-options, .icon-options, .accessory-options {
        grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
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
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    .avatar-preview {
        width: 120px;
        height: 120px;
        font-size: 3rem;
    }
    
    .avatar-icon {
        font-size: 3rem;
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
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .style-options, .color-options, .icon-options, .accessory-options {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .avatar-preview {
        width: 100px;
        height: 100px;
        font-size: 2.5rem;
    }
    
    .avatar-icon {
        font-size: 2.5rem;
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

    // Avatar customization real-time preview
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarIcon = document.getElementById('avatarIcon');
    const avatarAccessory = document.getElementById('avatarAccessory');
    
    // Function to update avatar style
    function updateAvatarStyle(style) {
        // Remove all style classes
        avatarPreview.classList.remove('pixel-style', 'round-style', 'square-style');
        // Add the selected style class
        avatarPreview.classList.add(style + '-style');
    }

    // Function to update avatar color
    function updateAvatarColor(color) {
        avatarPreview.style.backgroundColor = color;
    }

    // Function to update avatar icon
    function updateAvatarIcon(icon) {
        // Find the selected icon option and get its display content
        const selectedIconOption = document.querySelector(`.icon-option input[value="${icon}"]`);
        if (selectedIconOption) {
            const iconDisplay = selectedIconOption.closest('.icon-option').querySelector('.icon-display');
            if (iconDisplay) {
                avatarIcon.textContent = iconDisplay.textContent;
            }
        }
    }

    // Function to update avatar accessory
    function updateAvatarAccessory(accessory) {
        if (accessory === 'none') {
            avatarAccessory.textContent = '';
        } else {
            // Find the selected accessory option and get its display content
            const selectedAccessoryOption = document.querySelector(`.accessory-option input[value="${accessory}"]`);
            if (selectedAccessoryOption) {
                const accessoryDisplay = selectedAccessoryOption.closest('.accessory-option').querySelector('.accessory-display');
                if (accessoryDisplay) {
                    avatarAccessory.textContent = accessoryDisplay.textContent;
                }
            }
        }
    }

    // Add event listeners to all customization options
    document.querySelectorAll('.style-option').forEach(option => {
        option.addEventListener('click', function(e) {
            const radio = this.querySelector('input[type="radio"]');
            if (radio && !radio.disabled) {
                radio.checked = true;
                updateAvatarStyle(radio.value);
                
                // Update visual selection
                document.querySelectorAll('.style-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            }
        });
    });

    document.querySelectorAll('.color-option').forEach(option => {
        option.addEventListener('click', function(e) {
            const radio = this.querySelector('input[type="radio"]');
            if (radio && !radio.disabled) {
                radio.checked = true;
                updateAvatarColor(radio.value);
                
                // Update visual selection
                document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            }
        });
    });

    document.querySelectorAll('.icon-option:not(.locked)').forEach(option => {
        option.addEventListener('click', function(e) {
            const radio = this.querySelector('input[type="radio"]');
            if (radio && !radio.disabled) {
                radio.checked = true;
                updateAvatarIcon(radio.value);
                
                // Update visual selection
                document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            }
        });
    });

    document.querySelectorAll('.accessory-option:not(.locked)').forEach(option => {
        option.addEventListener('click', function(e) {
            const radio = this.querySelector('input[type="radio"]');
            if (radio && !radio.disabled) {
                radio.checked = true;
                updateAvatarAccessory(radio.value);
                
                // Update visual selection
                document.querySelectorAll('.accessory-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            }
        });
    });

    // Form validation for profile form
    const profileForm = document.querySelector('.profile-form');
    const nameInput = document.getElementById('name');
    const birthdayInput = document.getElementById('birthday');

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const name = nameInput.value.trim();
            
            if (name === '') {
                e.preventDefault();
                alert('Please enter your name.');
                nameInput.focus();
                return;
            }

            if (birthdayInput.value) {
                const birthday = new Date(birthdayInput.value);
                const today = new Date();
                if (birthday > today) {
                    e.preventDefault();
                    alert('Birthday cannot be in the future.');
                    birthdayInput.focus();
                    return;
                }
            }
        });
    }

    // Character counter for bio
    const bioTextarea = document.getElementById('bio');
    if (bioTextarea) {
        bioTextarea.addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            
            if (currentLength > maxLength) {
                this.value = this.value.substring(0, maxLength);
            }
        });
    }
});
</script>