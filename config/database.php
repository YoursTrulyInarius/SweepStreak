<?php
$host = 'localhost';
$dbname = 'sweepstreak';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    function createTables($pdo) {
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                role ENUM('teacher', 'student') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(10) UNIQUE NOT NULL,
                teacher_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                class_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS group_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                student_id INT NOT NULL,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_group_member (group_id, student_id)
            )",
            
            "CREATE TABLE IF NOT EXISTS tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                class_id INT NOT NULL,
                cleaning_area VARCHAR(255) NOT NULL,
                points INT DEFAULT 50,
                due_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                group_id INT NOT NULL,
                image_path VARCHAR(500) NOT NULL,
                submitted_by INT NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                notes TEXT,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                submission_id INT NOT NULL,
                student_id INT NOT NULL,
                status ENUM('present', 'absent') NOT NULL,
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                points INT DEFAULT 0,
                streak INT DEFAULT 0,
                last_submission_date DATE,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                UNIQUE KEY unique_group_points (group_id)
            )",
            
            "CREATE TABLE IF NOT EXISTS badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                icon VARCHAR(255)
            )",
            
            "CREATE TABLE IF NOT EXISTS group_badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                badge_id INT NOT NULL,
                awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
            )"
        ];
        
        foreach ($tables as $table) {
            $pdo->exec($table);
        }
        
        // Insert default badges only if they don't exist
        $badges = [
            ['First Clean', 'Complete your first cleaning task', 'fas fa-star'],
            ['Streak Master', 'Maintain a 7-day cleaning streak', 'fas fa-fire'],
            ['Perfect Week', 'Complete all tasks for a week', 'fas fa-trophy'],
            ['Team Player', 'Complete tasks with full group attendance', 'fas fa-users'],
            ['Early Bird', 'Submit cleaning task before 8 AM', 'fas fa-sun']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO badges (name, description, icon) VALUES (?, ?, ?)");
        foreach ($badges as $badge) {
            $stmt->execute($badge);
        }
    }
    
    // Check if we need to create tables (only on fresh install)
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        createTables($pdo);
    }
    
} catch(PDOException $e) {
    // Don't die() in header includes - just log the error
    error_log("Database connection failed: " . $e->getMessage());
    // Set $pdo to null to indicate connection failed
    $pdo = null;
}
?>