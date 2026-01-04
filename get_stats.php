<?php
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    // Get total points from all groups
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) as total_points FROM points");
    $stmt->execute();
    $points_data = $stmt->fetch();
    $total_points = $points_data['total_points'];
    
    // Get total streaks (count groups with streaks > 0)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_streaks FROM points WHERE streak > 0");
    $stmt->execute();
    $streaks_data = $stmt->fetch();
    $total_streaks = $streaks_data['total_streaks'];
    
    // Get total badges awarded
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_badges FROM group_badges");
    $stmt->execute();
    $badges_data = $stmt->fetch();
    $total_badges = $badges_data['total_badges'];
    
    // Get total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $users_data = $stmt->fetch();
    $total_users = $users_data['total_users'];
    
    echo json_encode([
        'success' => true,
        'total_points' => $total_points,
        'total_streaks' => $total_streaks,
        'total_badges' => $total_badges,
        'total_users' => $total_users
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'total_points' => 0,
        'total_streaks' => 0,
        'total_badges' => 0,
        'total_users' => 0,
        'error' => $e->getMessage()
    ]);
}
?>