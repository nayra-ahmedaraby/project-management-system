<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'current':
        // Get current user info
        jsonResponse([
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'isManager' => isManager()
        ]);
        break;
        
    case 'list':
        // Get all users
        $users = getAllUsers();
        jsonResponse(['users' => $users]);
        break;
    
    case 'list_stats':
        // Get all users with task statistics
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can view member stats'], 403);
        }
        
        $result = $conn->query("SELECT u.*, 
                        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as task_count,
                        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'done') as completed_count,
                        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'done' 
                            AND (due_date IS NULL OR DATE(updated_at) <= due_date)) as ontime_count,
                        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'done' 
                            AND due_date IS NOT NULL AND DATE(updated_at) > due_date) as late_count
                        FROM users u ORDER BY u.role DESC, u.full_name");
        $members = $result->fetch_all(MYSQLI_ASSOC);
        jsonResponse(['members' => $members]);
        break;
    
    case 'get':
        // Get single user
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can view user details'], 403);
        }
        
        $user_id = (int)$_GET['user_id'];
        $stmt = $conn->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            jsonResponse($user);
        } else {
            jsonResponse(['error' => 'User not found'], 404);
        }
        break;
    
    case 'create':
        // Create new user
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can create users'], 403);
        }
        
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            jsonResponse(['error' => 'All fields are required'], 400);
        }
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            jsonResponse(['error' => 'Username or email already exists'], 400);
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $username, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Member created successfully']);
        } else {
            jsonResponse(['error' => 'Failed to create member'], 500);
        }
        break;
    
    case 'update':
        // Update user
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can update users'], 403);
        }
        
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        
        if (empty($full_name) || empty($username) || empty($email)) {
            jsonResponse(['error' => 'Name, username and email are required'], 400);
        }
        
        // Check if username or email already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            jsonResponse(['error' => 'Username or email already exists'], 400);
        }
        
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $full_name, $username, $email, $hashed_password, $role, $user_id);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $username, $email, $role, $user_id);
        }
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Member updated successfully']);
        } else {
            jsonResponse(['error' => 'Failed to update member'], 500);
        }
        break;
    
    case 'delete':
        // Delete user
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can delete users'], 403);
        }
        
        $user_id = (int)$_POST['user_id'];
        
        // Cannot delete yourself
        if ($user_id == getCurrentUserId()) {
            jsonResponse(['error' => 'You cannot delete yourself'], 400);
        }
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Member deleted successfully']);
        } else {
            jsonResponse(['error' => 'Failed to delete member'], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
