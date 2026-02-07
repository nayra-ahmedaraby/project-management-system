<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

switch ($action) {
    case 'upload':
        $task_id = (int)$_POST['task_id'];
        $user_id = getCurrentUserId();
        
        // Check task permission
        $stmt = $conn->prepare("SELECT assigned_to FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        
        if (!isManager() && getCurrentUserId() != $task['assigned_to']) {
            jsonResponse(['error' => 'You can only upload files to your own tasks'], 403);
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'File upload failed'], 400);
        }
        
        $file = $_FILES['file'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        
        // Generate unique filename
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $stmt = $conn->prepare("INSERT INTO files (task_id, user_id, filename, original_name, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissis", $task_id, $user_id, $filename, $originalName, $fileSize, $fileType);
            
            if ($stmt->execute()) {
                jsonResponse([
                    'success' => true, 
                    'file' => [
                        'id' => $stmt->insert_id,
                        'filename' => $filename,
                        'original_name' => $originalName,
                        'file_size' => $fileSize
                    ]
                ]);
            } else {
                unlink($uploadDir . $filename);
                jsonResponse(['error' => 'Failed to save file info'], 500);
            }
        } else {
            jsonResponse(['error' => 'Failed to save file'], 500);
        }
        break;
        
    case 'delete':
        $file_id = (int)$_POST['file_id'];
        
        // Get file info
        $stmt = $conn->prepare("SELECT f.*, t.assigned_to FROM files f JOIN tasks t ON f.task_id = t.id WHERE f.id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['error' => 'File not found'], 404);
        }
        
        // Check permission
        if (!isManager() && getCurrentUserId() != $file['user_id']) {
            jsonResponse(['error' => 'You can only delete your own files'], 403);
        }
        
        // Delete physical file
        $filePath = $uploadDir . $file['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to delete file'], 500);
        }
        break;
    
    case 'delete_project_file':
        // Only managers can delete project files
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can delete project files'], 403);
        }
        
        $file_id = (int)$_POST['file_id'];
        
        // Get file info
        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND project_id IS NOT NULL");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            jsonResponse(['error' => 'File not found'], 404);
        }
        
        // Delete physical file
        $filePath = $uploadDir . $file['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to delete file'], 500);
        }
        break;
        
    case 'download':
        $file_id = (int)$_GET['file_id'];
        
        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        
        if (!$file) {
            http_response_code(404);
            exit('File not found');
        }
        
        $filePath = $uploadDir . $file['filename'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            exit('File not found');
        }
        
        header('Content-Type: ' . $file['file_type']);
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
        break;
    
    case 'upload_project':
        // Only managers can upload project files
        if (!isManager()) {
            jsonResponse(['error' => 'Only managers can upload project files'], 403);
        }
        
        $project_id = (int)$_POST['project_id'];
        $user_id = getCurrentUserId();
        
        // Check project exists
        $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            jsonResponse(['error' => 'Project not found'], 404);
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'File upload failed'], 400);
        }
        
        $file = $_FILES['file'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        
        // Generate unique filename
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = 'project_' . $project_id . '_' . uniqid() . '.' . $extension;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $stmt = $conn->prepare("INSERT INTO files (project_id, user_id, filename, original_name, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissis", $project_id, $user_id, $filename, $originalName, $fileSize, $fileType);
            
            if ($stmt->execute()) {
                jsonResponse([
                    'success' => true, 
                    'file' => [
                        'id' => $stmt->insert_id,
                        'filename' => $filename,
                        'original_name' => $originalName,
                        'file_size' => $fileSize
                    ]
                ]);
            } else {
                unlink($uploadDir . $filename);
                jsonResponse(['error' => 'Failed to save file info'], 500);
            }
        } else {
            jsonResponse(['error' => 'Failed to save file'], 500);
        }
        break;
    
    case 'list_project':
        $project_id = (int)$_GET['project_id'];
        
        $stmt = $conn->prepare("SELECT f.*, u.full_name as uploader_name 
                               FROM files f 
                               JOIN users u ON f.user_id = u.id 
                               WHERE f.project_id = ? 
                               ORDER BY f.created_at DESC");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        jsonResponse(['success' => true, 'files' => $files]);
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

$conn->close();
