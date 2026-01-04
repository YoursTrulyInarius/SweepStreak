<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] == 'teacher') {
                header('Location: teacher_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = "Invalid email or password!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SweepStreak</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        
        .pixel-bg {
            background-image: 
                linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
                linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
        
        .game-screen {
            border: 4px solid #000;
            box-shadow: 8px 8px 0 #000;
        }
        
        .screen-title {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.5rem;
            text-shadow: 3px 3px 0 #000;
            color: #3a86ff;
        }
        
        .screen-subtitle {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.7rem;
            color: #666;
            line-height: 1.6;
        }
        
        .game-label {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .game-input {
            font-family: 'Courier New', monospace;
            border: 3px solid #000;
            box-shadow: inset 2px 2px 0 rgba(0,0,0,0.1);
            padding: 0.8rem;
            font-size: 1rem;
        }
        
        .game-input:focus {
            border-color: #3a86ff;
            box-shadow: inset 2px 2px 0 rgba(0,0,0,0.1), 0 0 0 3px rgba(58, 134, 255, 0.3);
            outline: none;
        }
        
        .password-toggle {
            border: 3px solid #000;
            box-shadow: 2px 2px 0 #000;
            transition: all 0.1s ease;
        }
        
        .password-toggle:hover {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 #000;
        }
        
        .password-toggle:active {
            transform: translate(2px, 2px);
            box-shadow: 0 0 0 #000;
        }
        
        .game-btn {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.75rem;
            border: 3px solid #000;
            box-shadow: 4px 4px 0 #000;
            padding: 1rem 2rem;
            transition: all 0.1s ease;
            text-transform: uppercase;
        }
        
        .game-btn:hover {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 #000;
        }
        
        .game-btn:active {
            transform: translate(4px, 4px);
            box-shadow: 0 0 0 #000;
        }
        
        .game-alert {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            border: 3px solid #dc3545;
            box-shadow: 4px 4px 0 #dc3545;
            padding: 1rem;
            line-height: 1.6;
        }
        
        .game-link {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            text-decoration: none;
            color: #3a86ff;
            text-shadow: 1px 1px 0 #000;
            transition: all 0.1s ease;
        }
        
        .game-link:hover {
            color: #ff006e;
            transform: translateY(-1px);
        }
        
        .text-center p {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.6rem;
            line-height: 1.8;
        }
    </style>
</head>
<body class="pixel-bg">
    <div class="game-container">
        <div class="game-screen">
            <div class="screen-header">
                <h2 class="screen-title">Welcome Back!</h2>
                <p class="screen-subtitle">Sign in to your account</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="game-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="game-form">
                <div class="form-group">
                    <label class="game-label">Email Address</label>
                    <input type="email" name="email" class="game-input" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="game-label">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" class="game-input password-input" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="game-btn game-btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="text-center">
                <p>Don't have an account? <a href="register.php" class="game-link">Sign up here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Fixed Password toggle functionality for login
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.querySelector('.password-toggle');
            const passwordInput = document.querySelector('.password-input');
            const passwordIcon = passwordToggle.querySelector('i');
            
            passwordToggle.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.className = 'fas fa-eye-slash';
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.className = 'fas fa-eye';
                    this.setAttribute('aria-label', 'Show password');
                }
                
                // Focus back on input for better UX
                passwordInput.focus();
            });
            
            // Handle Enter key submission
            passwordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        });
    </script>
</body>
</html>