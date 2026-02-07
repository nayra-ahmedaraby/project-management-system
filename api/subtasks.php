<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $task_id = (int)$_POST['task_id'];
        $title = trim($_POST['title'] ?? '');
        
        if (empty($title)) {
            jsonResponse(['error' => 'Subtask title is required'], 400);
        }
        
        // Check task permission
        $stmt = $conn->prepare("SELECT assigned_to FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $task['assigned_to']) {
            jsonResponse(['error' => 'You can only add subtasks to your own tasks'], 403);
        }
        
        $stmt = $conn->prepare("INSERT INTO subtasks (task_id, title) VALUES (?, ?)");
        $stmt->bind_param("is", $task_id, $title);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            jsonResponse(['error' => 'Failed to add subtask'], 500);
        }
        break;
        
    case 'toggle':
        $subtask_id = (int)$_POST['subtask_id'];
        
        // Get subtask and task info
        $stmt = $conn->prepare("SELECT s.*, t.assigned_to FROM subtasks s JOIN tasks t ON s.task_id = t.id WHERE s.id = ?");
        $stmt->bind_param("i", $subtask_id);
        $stmt->execute();
        $subtask = $stmt->get_result()->fetch_assoc();
        
        if (!$subtask) {
            jsonResponse(['error' => 'Subtask not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $subtask['assigned_to']) {
            jsonResponse(['error' => 'You can only complete subtasks of your own tasks'], 403);
        }
        
        $completed = $subtask['completed'] ? 0 : 1;
        $completed_by = $completed ? getCurrentUserId() : null;
        
        $stmt = $conn->prepare("UPDATE subtasks SET completed = ?, completed_by = ? WHERE id = ?");
        $stmt->bind_param("iii", $completed, $completed_by, $subtask_id);
        
        if ($stmt->execute()) {
            // Calculate new progress
            $task_id = $subtask['task_id'];
            $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(completed) as done FROM subtasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $counts = $stmt->get_result()->fetch_assoc();
            $progress = $counts['total'] > 0 ? round(($counts['done'] / $counts['total']) * 100) : 0;
            
            jsonResponse(['success' => true, 'completed' => $completed, 'progress' => $progress, 'task_id' => $task_id]);
        } else {
            jsonResponse(['error' => 'Failed to update subtask'], 500);
        }
        break;
        
    case 'delete':
        $subtask_id = (int)$_POST['subtask_id'];
        
        // Get subtask and task info
        $stmt = $conn->prepare("SELECT s.*, t.assigned_to FROM subtasks s JOIN tasks t ON s.task_id = t.id WHERE s.id = ?");
        $stmt->bind_param("i", $subtask_id);
        $stmt->execute();
        $subtask = $stmt->get_result()->fetch_assoc();
        
        if (!$subtask) {
            jsonResponse(['error' => 'Subtask not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $subtask['assigned_to']) {
            jsonResponse(['error' => 'You can only delete subtasks of your own tasks'], 403);
        }
        
        $stmt = $conn->prepare("DELETE FROM subtasks WHERE id = ?");
        $stmt->bind_param("i", $subtask_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to delete subtask'], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
