<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Email already exists!";
        } else {
            // Handle student-specific fields
            if ($role == 'student') {
                $birthday = $_POST['birthday'] ?? null;
                $phone = $_POST['phone'] ?? null;
                
                // Validate required student fields
                if (empty($birthday) || empty($phone)) {
                    $error = "Birthday and phone number are required for students!";
                } else {
                    // Calculate age from birthday
                    $birthDate = new DateTime($birthday);
                    $today = new DateTime();
                    $age = $today->diff($birthDate)->y;
                    
                    // Validate phone number format (Philippines: 11 digits)
                    if (!preg_match('/^[0-9]{11}$/', $phone)) {
                        $error = "Please enter a valid 11-digit Philippine phone number!";
                    } else {
                        // Insert student with additional fields
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, birthday, age, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $hashed_password, $role, $birthday, $age, $phone]);
                        
                        // Get the new user ID
                        $user_id = $pdo->lastInsertId();
                    }
                }
            } else {
                // Insert teacher (no student-specific fields)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role]);
                $user_id = $pdo->lastInsertId();
            }
            
            if (!isset($error)) {
                // Set session and redirect based on role
                $_SESSION['user_id'] = $user_id;
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                
                if ($role == 'teacher') {
                    header('Location: teacher_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            }
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
    <title>Register - SweepStreak</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background-color: #f0f0f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .pixel-bg {
            background-image: 
                linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
                linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
        
        .game-container {
            width: 100%;
            max-width: 500px;
        }
        
        .game-screen {
            border: 4px solid #000;
            box-shadow: 8px 8px 0 #000;
            background: white;
            padding: 2rem;
            border-radius: 0;
            width: 100%;
        }
        
        .screen-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .screen-title {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.5rem;
            text-shadow: 3px 3px 0 #000;
            color: #3a86ff;
            margin-bottom: 0.5rem;
        }
        
        .screen-subtitle {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.7rem;
            color: #666;
            line-height: 1.6;
        }
        
        .game-form {
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .game-label {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            display: block;
            color: #333;
        }
        
        .game-input {
            font-family: 'Courier New', monospace;
            border: 3px solid #000;
            box-shadow: inset 2px 2px 0 rgba(0,0,0,0.1);
            padding: 0.8rem;
            font-size: 1rem;
            width: 100%;
            transition: all 0.1s ease;
        }
        
        .game-input:focus {
            border-color: #3a86ff;
            box-shadow: inset 2px 2px 0 rgba(0,0,0,0.1), 0 0 0 3px rgba(58, 134, 255, 0.3);
            outline: none;
        }
        
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input {
            padding-right: 3.5rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: 3px solid #000;
            box-shadow: 2px 2px 0 #000;
            cursor: pointer;
            color: #333;
            padding: 0.5rem;
            border-radius: 3px;
            transition: all 0.1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            width: 40px;
            height: 40px;
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
            cursor: pointer;
            display: block;
            width: 100%;
            background: #3a86ff;
            color: white;
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
            margin-bottom: 1.5rem;
            background: #f8d7da;
            color: #721c24;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .text-center {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .text-center p {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.6rem;
            line-height: 1.8;
            color: #666;
        }
        
        .role-selection {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 1rem;
            border: 3px solid #000;
            box-shadow: 3px 3px 0 #000;
            cursor: pointer;
            transition: all 0.1s ease;
            background: white;
        }
        
        .role-option:hover {
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 #000;
        }
        
        .role-option.active {
            background: #3a86ff;
            color: white;
            border-color: #2667cc;
        }
        
        .role-option input {
            display: none;
        }
        
        .role-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .role-label {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.6rem;
            text-transform: uppercase;
        }
        
        .student-fields {
            display: none;
            margin-top: 1rem;
            padding: 1.5rem;
            border: 3px solid #000;
            background: #f8f9fa;
            box-shadow: inset 2px 2px 0 rgba(0,0,0,0.1);
        }
        
        .student-fields.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .age-box {
            margin-top: 0.5rem;
            padding: 0.8rem;
            background: #d1ecf1;
            border: 3px solid #000;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.65rem;
            color: #0c5460;
            text-align: center;
            box-shadow: 2px 2px 0 #000;
        }
        
        .phone-container {
            display: flex;
            gap: 0.5rem;
        }
        
        .phone-prefix {
            flex: 0 0 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #3a86ff;
            color: white;
            border: 3px solid #000;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.6rem;
            padding: 0.8rem;
        }
        
        .error-message {
            font-family: 'Press Start 2P', cursive;
            font-size: 0.55rem;
            color: #dc3545;
            margin-top: 0.25rem;
            display: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .game-screen {
                padding: 1.5rem;
            }
            
            .screen-title {
                font-size: 1.2rem;
            }
            
            .screen-subtitle {
                font-size: 0.6rem;
            }
            
            .game-btn {
                font-size: 0.65rem;
                padding: 0.8rem 1.5rem;
            }
            
            .role-selection {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .game-screen {
                padding: 1rem;
            }
            
            .screen-title {
                font-size: 1rem;
            }
            
            .screen-subtitle {
                font-size: 0.5rem;
            }
            
            .game-label {
                font-size: 0.55rem;
            }
            
            .game-input {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .game-btn {
                font-size: 0.6rem;
                padding: 0.7rem 1rem;
            }
            
            .role-label {
                font-size: 0.5rem;
            }
            
            .role-icon {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body class="pixel-bg">
    <div class="game-container">
        <div class="game-screen">
            <div class="screen-header">
                <h2 class="screen-title">Join SweepStreak!</h2>
                <p class="screen-subtitle">Create your account to get started</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="game-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="game-form" id="registerForm">
                <div class="form-group">
                    <label class="game-label">Full Name</label>
                    <input type="text" name="name" class="game-input" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label class="game-label">Email Address</label>
                    <input type="email" name="email" class="game-input" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="game-label">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" class="game-input password-input" placeholder="Create a password" required minlength="6">
                        <button type="button" class="password-toggle" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="game-label">I am a:</label>
                    <div class="role-selection">
                        <label class="role-option active">
                            <input type="radio" name="role" value="student" checked>
                            <div class="role-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="role-label">Student</div>
                        </label>
                        
                        <label class="role-option">
                            <input type="radio" name="role" value="teacher">
                            <div class="role-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="role-label">Teacher</div>
                        </label>
                    </div>
                </div>
                
                <!-- Student Specific Fields -->
                <div class="student-fields active" id="studentFields">
                    <div class="form-group">
                        <label class="game-label">Birthday</label>
                        <input type="date" name="birthday" id="birthday" class="game-input" required>
                        <div class="age-box" id="ageDisplay">AGE: --</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="game-label">Phone Number</label>
                        <div class="phone-container">
                            <div class="phone-prefix">+63</div>
                            <input type="tel" name="phone" id="phone" class="game-input" placeholder="91234567890" maxlength="11" required>
                        </div>
                        <div class="error-message" id="phoneError">Please enter a valid 11-digit Philippine phone number</div>
                    </div>
                </div>
                
                <button type="submit" class="game-btn">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="text-center">
                <p>Already have an account? <a href="login.php" class="game-link">Sign in here</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Role selection handler
            const roleOptions = document.querySelectorAll('.role-option');
            const studentFields = document.getElementById('studentFields');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input');
                    const role = radio.value;
                    
                    // Update active state
                    roleOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    radio.checked = true;
                    
                    // Toggle student fields
                    if (role === 'student') {
                        studentFields.classList.add('active');
                        // Make student fields required
                        document.getElementById('birthday').required = true;
                        document.getElementById('phone').required = true;
                    } else {
                        studentFields.classList.remove('active');
                        // Remove required from student fields
                        document.getElementById('birthday').required = false;
                        document.getElementById('phone').required = false;
                    }
                });
            });

            // Password toggle functionality
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
            
            // Age calculation
            const birthdayInput = document.getElementById('birthday');
            const ageDisplay = document.getElementById('ageDisplay');
            
            birthdayInput.addEventListener('change', function() {
                if (this.value) {
                    const birthday = new Date(this.value);
                    const today = new Date();
                    let age = today.getFullYear() - birthday.getFullYear();
                    const monthDiff = today.getMonth() - birthday.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                        age--;
                    }
                    
                    ageDisplay.textContent = `AGE: ${age} years old`;
                } else {
                    ageDisplay.textContent = 'AGE: --';
                }
            });

            // Phone number validation
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phoneError');
            
            phoneInput.addEventListener('input', function() {
                // Remove any non-digit characters
                this.value = this.value.replace(/\D/g, '');
                
                // Validate length (11 digits)
                if (this.value.length === 11) {
                    phoneError.style.display = 'none';
                    this.style.borderColor = '#000';
                } else {
                    phoneError.style.display = 'block';
                    this.style.borderColor = '#dc3545';
                }
            });
            
            // Form validation
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                const role = document.querySelector('input[name="role"]:checked').value;
                
                if (role === 'student') {
                    const birthday = document.getElementById('birthday').value;
                    const phone = document.getElementById('phone').value;
                    
                    if (!birthday || !phone) {
                        e.preventDefault();
                        alert('Please fill in all required student fields: Birthday and Phone Number.');
                        return;
                    }
                    
                    // Validate phone number format (11 digits)
                    if (!/^\d{11}$/.test(phone)) {
                        e.preventDefault();
                        phoneError.style.display = 'block';
                        phoneInput.style.borderColor = '#dc3545';
                        phoneInput.focus();
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>