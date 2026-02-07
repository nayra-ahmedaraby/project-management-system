<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can create tasks'], 403);
        }
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $project_id = $_POST['project_id'] ?: null;
        $assigned_to = $_POST['assigned_to'] ?: null;
        $due_date = $_POST['due_date'] ?: null;
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'todo';
        $created_by = getCurrentUserId();
        $subtasks = isset($_POST['subtasks']) ? json_decode($_POST['subtasks'], true) : [];
        
        if (empty($title)) {
            jsonResponse(['error' => 'Title is required'], 400);
        }
        
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, project_id, assigned_to, created_by, due_date, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiisss", $title, $description, $project_id, $assigned_to, $created_by, $due_date, $priority, $status);
        
        if ($stmt->execute()) {
            $task_id = $stmt->insert_id;
            
            // Add subtasks if provided
            if (!empty($subtasks) && is_array($subtasks)) {
                $stmtSub = $conn->prepare("INSERT INTO subtasks (task_id, title) VALUES (?, ?)");
                foreach ($subtasks as $subtaskTitle) {
                    $subtaskTitle = trim($subtaskTitle);
                    if (!empty($subtaskTitle)) {
                        $stmtSub->bind_param("is", $task_id, $subtaskTitle);
                        $stmtSub->execute();
                    }
                }
                $stmtSub->close();
            }
            
            jsonResponse(['success' => true, 'id' => $task_id, 'message' => 'Task created successfully']);
        } else {
            jsonResponse(['error' => 'Failed to create task'], 500);
        }
        break;
        
    case 'update':
        $task_id = (int)$_POST['task_id'];
        
        // Check permission and get old project_id
        $stmt = $conn->prepare("SELECT assigned_to, created_by, project_id as old_project_id FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $task['assigned_to']) {
            jsonResponse(['error' => 'You can only edit your own tasks'], 403);
        }
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $project_id = $_POST['project_id'] ?: null;
        $assigned_to = $_POST['assigned_to'] ?: null;
        $due_date = $_POST['due_date'] ?: null;
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'todo';
        
        if (empty($title)) {
            jsonResponse(['error' => 'Title is required'], 400);
        }
        
        $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, project_id=?, assigned_to=?, due_date=?, priority=?, status=? WHERE id=?");
        $stmt->bind_param("ssiisssi", $title, $description, $project_id, $assigned_to, $due_date, $priority, $status, $task_id);
        
        if ($stmt->execute()) {
            // Check completion for both old and new project
            if ($task['old_project_id']) {
                checkProjectCompletion($task['old_project_id']);
            }
            if ($project_id && $project_id != $task['old_project_id']) {
                checkProjectCompletion($project_id);
            }
            jsonResponse(['success' => true, 'message' => 'Task updated successfully']);
        } else {
            jsonResponse(['error' => 'Failed to update task'], 500);
        }
        break;
        
    case 'delete':
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can delete tasks'], 403);
        }
        
        $task_id = (int)$_POST['task_id'];
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Task deleted successfully']);
        } else {
            jsonResponse(['error' => 'Failed to delete task'], 500);
        }
        break;
        
    case 'get':
        $task_id = (int)$_GET['task_id'];
        
        $stmt = $conn->prepare("SELECT t.*, p.name as project_name, p.color as project_color, 
                               u.full_name as assigned_name 
                               FROM tasks t 
                               LEFT JOIN projects p ON t.project_id = p.id 
                               LEFT JOIN users u ON t.assigned_to = u.id 
                               WHERE t.id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        // Get subtasks
        $stmtSub = $conn->prepare("SELECT s.*, u.full_name as completed_by_name FROM subtasks s LEFT JOIN users u ON s.completed_by = u.id WHERE s.task_id = ?");
        $stmtSub->bind_param("i", $task_id);
        $stmtSub->execute();
        $task['subtasks'] = $stmtSub->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get comments
        $stmtCom = $conn->prepare("SELECT c.*, u.full_name as user_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.task_id = ? ORDER BY c.created_at ASC");
        $stmtCom->bind_param("i", $task_id);
        $stmtCom->execute();
        $task['comments'] = $stmtCom->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get files
        $stmtFiles = $conn->prepare("SELECT f.*, u.full_name as uploader_name FROM files f JOIN users u ON f.user_id = u.id WHERE f.task_id = ? ORDER BY f.created_at DESC");
        $stmtFiles->bind_param("i", $task_id);
        $stmtFiles->execute();
        $task['files'] = $stmtFiles->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate progress
        $totalSubtasks = count($task['subtasks']);
        $completedSubtasks = array_reduce($task['subtasks'], function($carry, $item) {
            return $carry + ($item['completed'] ? 1 : 0);
        }, 0);
        $task['progress'] = $totalSubtasks > 0 ? round(($completedSubtasks / $totalSubtasks) * 100) : 0;
        
        jsonResponse($task);
        break;
        
    case 'update_status':
        $task_id = (int)$_POST['task_id'];
        $status = $_POST['status'];
        
        // Check permission and get project_id
        $stmt = $conn->prepare("SELECT assigned_to, project_id FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $task['assigned_to']) {
            jsonResponse(['error' => 'You can only move your own tasks'], 403);
        }
        
        $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        
        if ($status === 'done') {
            $completedAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $completedAt, $task_id);
        } else {
            $stmt = $conn->prepare("UPDATE tasks SET status = ?, completed_at = NULL WHERE id = ?");
            $stmt->bind_param("si", $status, $task_id);
        }
        if ($stmt->execute()) {
            // Check if all tasks in project are done
            if ($task['project_id']) {
                checkProjectCompletion($task['project_id']);
            }
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to update status'], 500);
        }
        break;
    
    case 'list':
        // Get all tasks or filter by project
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $includeArchived = isset($_GET['include_archived']) && $_GET['include_archived'] === 'true';
        
        $sql = "SELECT t.*, p.name as project_name, p.color as project_color, 
                u.full_name as assigned_name, u.avatar as assigned_avatar
                FROM tasks t 
                LEFT JOIN projects p ON t.project_id = p.id 
                LEFT JOIN users u ON t.assigned_to = u.id 
                WHERE 1=1";
        
        // Exclude tasks from archived projects (completed > 2 days ago)
        if (!$includeArchived) {
            $sql .= " AND (p.archived = 0 OR p.archived IS NULL OR p.completed_at IS NULL 
                      OR p.completed_at > DATE_SUB(NOW(), INTERVAL 2 DAY))";
        }
        
        $params = [];
        $types = "";
        
        if ($projectId) {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
            $types .= "i";
        }
        
        if ($userId) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $userId;
            $types .= "i";
        }
        
        if ($status && in_array($status, ['todo', 'in_progress', 'done'])) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY t.position, t.created_at DESC";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $tasks = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get subtasks for each task and calculate progress
        foreach ($tasks as &$task) {
            $stmtSub = $conn->prepare("SELECT * FROM subtasks WHERE task_id = ?");
            $stmtSub->bind_param("i", $task['id']);
            $stmtSub->execute();
            $task['subtasks'] = $stmtSub->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSub->close();
            
            $totalSubtasks = count($task['subtasks']);
            $completedSubtasks = array_reduce($task['subtasks'], function($carry, $item) {
                return $carry + ($item['completed'] ? 1 : 0);
            }, 0);
            $task['progress'] = $totalSubtasks > 0 ? round(($completedSubtasks / $totalSubtasks) * 100) : 0;
        }
        
        jsonResponse(['success' => true, 'tasks' => $tasks]);
        break;
    
    case 'calendar':
        // Get tasks for calendar view
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        
        $stmt = $conn->prepare("SELECT t.*, p.name as project_name, p.color as project_color, u.full_name as assigned_name
                                FROM tasks t 
                                LEFT JOIN projects p ON t.project_id = p.id 
                                LEFT JOIN users u ON t.assigned_to = u.id
                                WHERE t.due_date BETWEEN ? AND ?
                                ORDER BY t.due_date ASC");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        jsonResponse([
            'success' => true,
            'tasks' => $tasks,
            'month' => $month,
            'year' => $year,
            'daysInMonth' => $daysInMonth
        ]);
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
