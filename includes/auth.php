<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Optional: Add role-specific verification functions
function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'teacher';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}

// Redirect to appropriate dashboard based on role
function redirectToDashboard() {
    if (isTeacher()) {
        header('Location: teacher_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}
?>