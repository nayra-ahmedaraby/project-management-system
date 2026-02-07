<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $task_id = (int)$_POST['task_id'];
        $content = trim($_POST['content'] ?? '');
        $user_id = getCurrentUserId();
        
        if (empty($content)) {
            jsonResponse(['error' => 'Comment cannot be empty'], 400);
        }
        
        // Check if task exists
        $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        $stmt = $conn->prepare("INSERT INTO comments (task_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $task_id, $user_id, $content);
        
        if ($stmt->execute()) {
            $comment_id = $stmt->insert_id;
            
            // Get the full comment with user info
            $stmt = $conn->prepare("SELECT c.*, u.full_name as user_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $comment = $stmt->get_result()->fetch_assoc();
            
            jsonResponse(['success' => true, 'comment' => $comment]);
        } else {
            jsonResponse(['error' => 'Failed to add comment'], 500);
        }
        break;
        
    case 'get':
        $task_id = (int)$_GET['task_id'];
        
        $stmt = $conn->prepare("SELECT c.*, u.full_name as user_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.task_id = ? ORDER BY c.created_at ASC");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        jsonResponse(['comments' => $comments]);
        break;
        
    case 'delete':
        $comment_id = (int)$_POST['comment_id'];
        
        // Check permission - only the comment author or manager can delete
        $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $comment = $stmt->get_result()->fetch_assoc();
        
        if (!$comment) {
            jsonResponse(['error' => 'Comment not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $comment['user_id']) {
            jsonResponse(['error' => 'You can only delete your own comments'], 403);
        }
        
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to delete comment'], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
