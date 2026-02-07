<?php
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is manager
function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Can user modify task
function canModifyTask($task_assigned_to) {
    return isManager() || getCurrentUserId() == $task_assigned_to;
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Check if task is overdue
function isOverdue($due_date, $status) {
    if ($status === 'done') return false;
    return strtotime($due_date) < strtotime('today');
}

// Get all users
function getAllUsers() {
    $conn = getConnection();
    $result = $conn->query("SELECT id, username, full_name, role, avatar FROM users ORDER BY full_name");
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $users;
}

// Get all projects
function getAllProjects($includeArchived = false) {
    $conn = getConnection();
    if ($includeArchived) {
        $result = $conn->query("SELECT * FROM projects ORDER BY archived ASC, name");
    } else {
        // Exclude projects archived more than 2 days ago
        $result = $conn->query("SELECT * FROM projects 
                                WHERE archived = 0 
                                   OR completed_at IS NULL 
                                   OR completed_at > DATE_SUB(NOW(), INTERVAL 2 DAY) 
                                ORDER BY name");
    }
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $projects;
}

// Get archived projects only
function getArchivedProjects() {
    $conn = getConnection();
    $result = $conn->query("SELECT p.*, COUNT(t.id) as task_count, u.full_name as creator_name 
                           FROM projects p 
                           LEFT JOIN tasks t ON p.id = t.project_id 
                           LEFT JOIN users u ON p.created_by = u.id 
                           WHERE p.archived = 1 
                           GROUP BY p.id 
                           ORDER BY p.completed_at DESC");
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $projects;
}

// Check if all tasks in a project are done and update project status
function checkProjectCompletion($project_id) {
    if (!$project_id) return;
    
    $conn = getConnection();
    
    // Count total tasks and done tasks
    $stmt = $conn->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                            FROM tasks WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['total'] > 0 && $result['total'] == $result['done']) {
        // All tasks are done - mark project as completed
        $stmt = $conn->prepare("UPDATE projects SET completed_at = NOW(), archived = 1 WHERE id = ? AND completed_at IS NULL");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Not all tasks are done - unarchive if it was auto-archived
        $stmt = $conn->prepare("UPDATE projects SET completed_at = NULL, archived = 0 WHERE id = ? AND archived = 1");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
}

// JSON response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
