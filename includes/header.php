<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database configuration (with error handling)
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    // Log error but don't stop execution
    error_log("Database config error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SweepStreak - Classroom Cleaning Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome with multiple fallbacks -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        
        /* Main Navigation Styles */
        .game-nav {
            background: #ffffff;
            padding: 1rem 0;
            border-bottom: 3px solid #000;
            box-shadow: 0 3px 0 rgba(0,0,0,0.1);
            font-family: 'Press Start 2P', cursive;
        }
        
        .game-nav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .game-logo {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.1rem;
            text-shadow: 2px 2px 0 #000;
            letter-spacing: 1px;
            transition: transform 0.1s ease;
            color: #3a86ff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        
        .game-logo:hover {
            transform: translateY(-2px);
            color: #3a86ff;
            text-decoration: none;
        }
        
        .nav-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Guest Menu - Login/Register Buttons */
        .guest-menu {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .guest-menu .game-btn {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            border: 2px solid #000;
            box-shadow: 3px 3px 0 #000;
            padding: 0.6rem 1.2rem;
            transition: all 0.1s ease;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        
        .guest-menu .game-btn:hover {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0 #000;
            text-decoration: none;
        }
        
        .guest-menu .game-btn:active {
            transform: translate(3px, 3px);
            box-shadow: 0 0 0 #000;
        }
        
        .guest-menu .game-btn-primary {
            background: #3a86ff;
            color: white;
        }
        
        .guest-menu .game-btn-secondary {
            background: white;
            color: #333;
        }
        
        .guest-menu .btn-text {
            display: inline;
        }
        
        .guest-menu .btn-icon {
            display: none;
        }
        
        /* User Menu - Logged In State */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Press Start 2P', cursive;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #3a86ff;
            color: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: 3px solid #000;
            box-shadow: 3px 3px 0 #000;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            line-height: 1.4;
            font-weight: bold;
            color: #333;
        }
        
        .user-role {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.55rem;
            line-height: 1.4;
            color: #666;
            text-transform: uppercase;
        }
        
        .logout-btn {
            width: 40px;
            height: 40px;
            background: #ff6b6b;
            color: white;
            border: 3px solid #000;
            box-shadow: 3px 3px 0 #000;
            transition: all 0.1s ease;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .logout-btn:hover {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0 #000;
            color: white;
            text-decoration: none;
        }
        
        .logout-btn:active {
            transform: translate(3px, 3px);
            box-shadow: 0 0 0 #000;
        }
        
        /* Responsive Design - Icon Only Solution */
        @media (max-width: 768px) {
            .game-nav .container {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 0.5rem;
            }
            
            .game-logo {
                font-size: 0.9rem;
                flex-shrink: 0;
            }
            
            .nav-controls {
                flex-shrink: 0;
            }
            
            .guest-menu {
                gap: 0.5rem;
            }
            
            .guest-menu .game-btn {
                font-size: 0.6rem;
                padding: 0.5rem 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .game-logo {
                font-size: 0.8rem;
            }
            
            .guest-menu .game-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.55rem;
            }
        }
        
        /* Icon-only buttons on small screens */
        @media (max-width: 480px) {
            .game-logo {
                font-size: 0.75rem;
            }
            
            .guest-menu .game-btn {
                width: 40px;
                height: 40px;
                padding: 0;
                border-radius: 4px;
                position: relative;
            }
            
            .guest-menu .btn-text {
                display: none;
            }
            
            .guest-menu .btn-icon {
                display: inline;
                font-size: 0.9rem;
            }
            
            /* Tooltip for icon buttons */
            .guest-menu .game-btn::after {
                content: attr(title);
                position: absolute;
                bottom: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: #333;
                color: white;
                padding: 0.3rem 0.6rem;
                border-radius: 4px;
                font-size: 0.5rem;
                white-space: nowrap;
                opacity: 0;
                visibility: hidden;
                transition: all 0.2s ease;
                pointer-events: none;
                z-index: 1000;
            }
            
            .guest-menu .game-btn:hover::after {
                opacity: 1;
                visibility: visible;
            }
        }
        
        @media (max-width: 400px) {
            .game-logo {
                font-size: 0.7rem;
            }
            
            .guest-menu .game-btn {
                width: 35px;
                height: 35px;
            }
            
            .guest-menu .btn-icon {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 360px) {
            .game-logo {
                font-size: 0.65rem;
            }
            
            .guest-menu {
                gap: 0.4rem;
            }
            
            .guest-menu .game-btn {
                width: 32px;
                height: 32px;
            }
            
            .guest-menu .btn-icon {
                font-size: 0.75rem;
            }
        }
        
        /* Ultra small devices */
        @media (max-width: 320px) {
            .game-logo {
                font-size: 0.6rem;
            }
            
            .guest-menu .game-btn {
                width: 30px;
                height: 30px;
            }
            
            .guest-menu .btn-icon {
                font-size: 0.7rem;
            }
        }
        
        /* Large desktop */
        @media (min-width: 1200px) {
            .game-nav .container {
                max-width: 1140px;
                margin: 0 auto;
            }
        }
        
        /* Ensure main content has proper spacing */
        .game-main {
            min-height: calc(100vh - 80px);
        }
    </style>
</head>
<body>
    <div class="bg-elements" id="bgElements"></div>
    
    <nav class="game-nav">
        <div class="container">
            <a class="game-logo" href="<?php echo isset($_SESSION['user_id']) ? ($_SESSION['role'] == 'teacher' ? 'teacher_dashboard.php' : 'dashboard.php') : 'index.php'; ?>">
                <i class="fas fa-broom"></i>
                SweepStreak
            </a>
            
            <div class="nav-controls">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- User Info & Logout -->
                    <div class="user-menu">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                                <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                            </div>
                        </div>
                        <a href="logout.php" class="logout-btn" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Login/Register for guests -->
                    <div class="guest-menu">
                        <a href="login.php" class="game-btn game-btn-secondary" title="Login">
                            <span class="btn-text">Login</span>
                            <i class="fas fa-sign-in-alt btn-icon"></i>
                        </a>
                        <a href="register.php" class="game-btn game-btn-primary" title="Register">
                            <span class="btn-text">Register</span>
                            <i class="fas fa-user-plus btn-icon"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="game-main">