<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Allow list action for all logged-in users
if ($action === 'list') {
    $result = $conn->query("SELECT p.id, p.name, p.color FROM projects p 
                           WHERE p.archived = 0 
                              OR p.completed_at IS NULL 
                              OR p.completed_at > DATE_SUB(NOW(), INTERVAL 2 DAY)
                           ORDER BY p.name");
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(['projects' => $projects]);
    exit;
}

// All other actions require manager
if (!isManager()) {
    jsonResponse(['error' => 'Only managers can manage projects'], 403);
}

switch ($action) {
    case 'create':
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#3498db';
        $created_by = getCurrentUserId();
        
        if (empty($name)) {
            jsonResponse(['error' => 'Project name is required'], 400);
        }
        
        $stmt = $conn->prepare("INSERT INTO projects (name, description, color, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $description, $color, $created_by);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Project created successfully']);
        } else {
            jsonResponse(['error' => 'Failed to create project'], 500);
        }
        break;
        
    case 'update':
        $project_id = (int)$_POST['project_id'];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#3498db';
        
        if (empty($name)) {
            jsonResponse(['error' => 'Project name is required'], 400);
        }
        
        $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, color = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $description, $color, $project_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Project updated successfully']);
        } else {
            jsonResponse(['error' => 'Failed to update project'], 500);
        }
        break;
        
    case 'delete':
        $project_id = (int)$_POST['project_id'];
        
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            jsonResponse(['error' => 'Failed to delete project'], 500);
        }
        break;
        
    case 'get':
        $project_id = (int)$_GET['project_id'];
        
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        if ($project) {
            jsonResponse($project);
        } else {
            jsonResponse(['error' => 'Project not found'], 404);
        }
        break;
    
    case 'list_all':
        // Active projects (not archived or archived less than 2 days ago)
        $resultActive = $conn->query("SELECT p.*, COUNT(DISTINCT t.id) as task_count, u.full_name as creator_name,
                                SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as done_count,
                                (SELECT COUNT(*) FROM files f WHERE f.project_id = p.id) as file_count
                               FROM projects p 
                               LEFT JOIN tasks t ON p.id = t.project_id 
                               LEFT JOIN users u ON p.created_by = u.id 
                               WHERE p.archived = 0 OR p.completed_at IS NULL 
                                  OR p.completed_at > DATE_SUB(NOW(), INTERVAL 2 DAY)
                               GROUP BY p.id 
                               ORDER BY p.name");
        $active = $resultActive->fetch_all(MYSQLI_ASSOC);
        
        // Archived projects (completed more than 2 days ago)
        $resultArchived = $conn->query("SELECT p.*, COUNT(DISTINCT t.id) as task_count, u.full_name as creator_name,
                                       (SELECT COUNT(*) FROM files f WHERE f.project_id = p.id) as file_count
                                       FROM projects p 
                                       LEFT JOIN tasks t ON p.id = t.project_id 
                                       LEFT JOIN users u ON p.created_by = u.id 
                                       WHERE p.archived = 1 AND p.completed_at IS NOT NULL 
                                         AND p.completed_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)
                                       GROUP BY p.id 
                                       ORDER BY p.completed_at DESC");
        $archived = $resultArchived->fetch_all(MYSQLI_ASSOC);
        
        jsonResponse(['active' => $active, 'archived' => $archived]);
        break;
    
    case 'restore':
        $project_id = (int)$_POST['project_id'];
        
        $stmt = $conn->prepare("UPDATE projects SET archived = 0, completed_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Project restored successfully']);
        } else {
            jsonResponse(['error' => 'Failed to restore project'], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
