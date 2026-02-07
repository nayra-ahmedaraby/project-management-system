// Projects Page JavaScript

let showingArchive = false;
let projectFiles = []; // Files to upload with new project

// Load projects on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    initProjectModal();
});

// Load projects from API
async function loadProjects() {
    try {
        const response = await fetch('api/projects.php?action=list_all');
        const data = await response.json();
        
        if (data.active) {
            renderActiveProjects(data.active);
        }
        if (data.archived) {
            renderArchivedProjects(data.archived);
        }
    } catch (error) {
        console.error('Failed to load projects:', error);
    }
}

// Render active projects
function renderActiveProjects(projects) {
    const container = document.getElementById('projectsGrid');
    if (!container) return;
    
    if (projects.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No projects yet. Create your first project!</p></div>';
        return;
    }
    
    container.innerHTML = projects.map(project => {
        const isCompleted = project.archived == 1;
        const progress = project.task_count > 0 ? Math.round((project.done_count / project.task_count) * 100) : 0;
        
        return `
        <div class="project-card ${isCompleted ? 'completed' : ''}" style="border-left-color: ${project.color}">
            <div class="project-header">
                <div class="project-color" style="background: ${project.color}"></div>
                <h3>${escapeHtml(project.name)}</h3>
                ${isCompleted ? '<span class="badge badge-success">Completed</span>' : ''}
            </div>
            
            ${project.description ? `<p class="project-desc">${escapeHtml(project.description)}</p>` : ''}
            
            <div class="project-meta">
                <span class="task-count">${project.task_count} tasks</span>
                ${parseInt(project.file_count) > 0 ? `<span class="file-count">📎 ${project.file_count} file${project.file_count > 1 ? 's' : ''}</span>` : ''}
                <span class="creator">by ${escapeHtml(project.creator_name || 'Unknown')}</span>
            </div>
            
            ${project.task_count > 0 ? `
            <div class="project-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${progress}%"></div>
                </div>
                <span>${progress}% complete</span>
            </div>
            ` : ''}
            
            <div class="project-actions">
                <button class="btn btn-sm btn-secondary" onclick="editProject(${project.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteProject(${project.id}, '${escapeHtml(project.name)}')">Delete</button>
                <a href="index.php?project=${project.id}" class="btn btn-sm btn-primary">View Board</a>
            </div>
        </div>`;
    }).join('');
}

// Render archived projects
function renderArchivedProjects(projects) {
    const container = document.getElementById('archivedProjectsGrid');
    if (!container) return;
    
    if (projects.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No archived projects yet.</p></div>';
        return;
    }
    
    container.innerHTML = projects.map(project => `
        <div class="project-card archived" style="border-left-color: ${project.color}">
            <div class="project-header">
                <div class="project-color" style="background: ${project.color}"></div>
                <h3>${escapeHtml(project.name)}</h3>
                <span class="badge badge-muted">Archived</span>
            </div>
            
            ${project.description ? `<p class="project-desc">${escapeHtml(project.description)}</p>` : ''}
            
            <div class="project-meta">
                <span class="task-count">${project.task_count} tasks</span>
                <span class="completed-date">Completed ${formatDate(project.completed_at)}</span>
            </div>
            
            <div class="project-actions">
                <button class="btn btn-sm btn-secondary" onclick="restoreProject(${project.id})">Restore</button>
                <button class="btn btn-sm btn-danger" onclick="deleteProject(${project.id}, '${escapeHtml(project.name)}')">Delete</button>
            </div>
        </div>
    `).join('');
}

function toggleArchive() {
    showingArchive = !showingArchive;
    document.getElementById('activeProjects').style.display = showingArchive ? 'none' : 'block';
    document.getElementById('archivedProjects').style.display = showingArchive ? 'block' : 'none';
    document.getElementById('archiveToggle').textContent = showingArchive ? 'Show Active' : 'Show Archive';
}

function openProjectModal() {
    document.getElementById('projectModalTitle').textContent = 'New Project';
    document.getElementById('projectForm').reset();
    document.getElementById('projectId').value = '';
    projectFiles = [];
    renderProjectFiles();
    // Hide existing files section for new projects
    document.getElementById('existingFilesSection').style.display = 'none';
    document.getElementById('existingFilesList').innerHTML = '';
    // Show files section for new projects
    document.getElementById('projectFilesSection').style.display = 'block';
    document.getElementById('projectModal').classList.add('active');
}

function closeProjectModal() {
    document.getElementById('projectModal').classList.remove('active');
    projectFiles = [];
}

async function editProject(id) {
    try {
        const [projectRes, filesRes] = await Promise.all([
            fetch(`api/projects.php?action=get&project_id=${id}`),
            fetch(`api/files.php?action=list_project&project_id=${id}`)
        ]);
        
        const project = await projectRes.json();
        const filesData = await filesRes.json();
        
        if (project.error) {
            alert(project.error);
            return;
        }
        
        document.getElementById('projectModalTitle').textContent = 'Edit Project';
        document.getElementById('projectId').value = project.id;
        document.getElementById('projectName').value = project.name;
        document.getElementById('projectDescription').value = project.description || '';
        document.getElementById('projectColor').value = project.color;
        
        // Show existing files if any
        const existingFilesSection = document.getElementById('existingFilesSection');
        const existingFilesList = document.getElementById('existingFilesList');
        
        if (filesData.files && filesData.files.length > 0) {
            existingFilesSection.style.display = 'block';
            existingFilesList.innerHTML = filesData.files.map(file => `
                <div class="project-file-item">
                    <span class="file-icon">📄</span>
                    <a href="api/files.php?action=download&file_id=${file.id}" class="file-name">${escapeHtml(file.original_name)}</a>
                    <span class="file-size">${formatFileSize(file.file_size)}</span>
                    <button type="button" class="btn-icon" onclick="deleteProjectFile(${file.id}, ${project.id})">×</button>
                </div>
            `).join('');
        } else {
            existingFilesSection.style.display = 'none';
            existingFilesList.innerHTML = '';
        }
        
        // Clear new files list
        projectFiles = [];
        renderProjectFiles();
        
        // Show files section for adding more files
        document.getElementById('projectFilesSection').style.display = 'block';
        document.getElementById('projectModal').classList.add('active');
    } catch (error) {
        alert('Failed to load project');
    }
}

async function saveProject(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('projectForm'));
    const projectId = formData.get('project_id');
    formData.append('action', projectId ? 'update' : 'create');
    
    try {
        const response = await fetch('api/projects.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            // Upload any new files (for both new and existing projects)
            const targetId = projectId || result.id;
            if (projectFiles.length > 0 && targetId) {
                await uploadProjectFiles(targetId);
            }
            location.reload();
        } else {
            alert(result.error || 'Failed to save project');
        }
    } catch (error) {
        alert('Failed to save project');
    }
}

// Upload files for a project
async function uploadProjectFiles(projectId) {
    for (const file of projectFiles) {
        const formData = new FormData();
        formData.append('action', 'upload_project');
        formData.append('project_id', projectId);
        formData.append('file', file);
        
        try {
            await fetch('api/files.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Failed to upload file:', file.name);
        }
    }
}

// Handle file selection
function handleProjectFileSelect(e) {
    const files = e.target.files;
    for (const file of files) {
        projectFiles.push(file);
    }
    renderProjectFiles();
}

// Remove a file from the list
function removeProjectFile(index) {
    projectFiles.splice(index, 1);
    renderProjectFiles();
}

// Delete an existing project file from the server
async function deleteProjectFile(fileId, projectId) {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_project_file');
    formData.append('file_id', fileId);
    
    try {
        const response = await fetch('api/files.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            // Refresh the file list
            editProject(projectId);
        } else {
            alert(result.error || 'Failed to delete file');
        }
    } catch (error) {
        alert('Failed to delete file');
    }
}

// Render the file list
function renderProjectFiles() {
    const container = document.getElementById('projectFilesList');
    if (!container) return;
    
    if (projectFiles.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = projectFiles.map((file, index) => `
        <div class="project-file-item">
            <span class="file-icon">📄</span>
            <span class="file-name">${escapeHtml(file.name)}</span>
            <span class="file-size">${formatFileSize(file.size)}</span>
            <button type="button" class="btn-icon" onclick="removeProjectFile(${index})">×</button>
        </div>
    `).join('');
}

async function deleteProject(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"? All associated tasks will be deleted.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('project_id', id);
    
    try {
        const response = await fetch('api/projects.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to delete project');
        }
    } catch (error) {
        alert('Failed to delete project');
    }
}

async function restoreProject(id) {
    if (!confirm('Are you sure you want to restore this project? It will appear on the Kanban board again.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'restore');
    formData.append('project_id', id);
    
    try {
        const response = await fetch('api/projects.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to restore project');
        }
    } catch (error) {
        alert('Failed to restore project');
    }
}

// Initialize project modal
function initProjectModal() {
    const modal = document.getElementById('projectModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeProjectModal();
            }
        });
    }
    
    // File input change handler
    const fileInput = document.getElementById('projectFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', handleProjectFileSelect);
    }
    
    // Drag and drop handlers
    const dropZone = document.getElementById('projectFileDropZone');
    if (dropZone) {
        // Click to browse files
        dropZone.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                document.getElementById('projectFileInput').click();
            }
        });
        
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            for (const file of files) {
                projectFiles.push(file);
            }
            renderProjectFiles();
        });
    }
}
