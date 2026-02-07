// Kanban Board JavaScript

let currentProjectFilter = null;
let projects = [];
let users = [];

document.addEventListener('DOMContentLoaded', async function() {
    // Load projects and users from API
    await loadProjectsAndUsers();
    
    // Set project filter from URL FIRST (before loading tasks)
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project');
    if (projectId) {
        currentProjectFilter = projectId;
        const filterSelect = document.getElementById('projectFilter');
        if (filterSelect) {
            filterSelect.value = projectId;
        }
        // Show files button and load files count
        const filesBtn = document.getElementById('viewProjectFilesBtn');
        if (filesBtn) filesBtn.style.display = 'inline-block';
        loadProjectFilesCount(projectId);
    }
    
    // Show add task button if manager
    if (typeof isManager !== 'undefined' && isManager) {
        const addBtn = document.getElementById('addTaskBtn');
        if (addBtn) addBtn.style.display = 'block';
    }
    
    // Load tasks AFTER setting filter
    loadTasks();
    initDragAndDrop();
});

// Load projects and users from API
async function loadProjectsAndUsers() {
    try {
        const [projectsRes, usersRes] = await Promise.all([
            fetch('api/projects.php?action=list'),
            fetch('api/users.php?action=list')
        ]);
        
        const projectsData = await projectsRes.json();
        const usersData = await usersRes.json();
        
        projects = projectsData.projects || [];
        users = usersData.users || [];
        
        // Populate project filter dropdown
        const projectFilter = document.getElementById('projectFilter');
        if (projectFilter) {
            projects.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = p.name;
                projectFilter.appendChild(option);
            });
        }
        
        // Populate task form dropdowns
        populateFormDropdowns();
    } catch (error) {
        console.error('Failed to load projects/users:', error);
    }
}

// Populate form dropdowns with projects and users
function populateFormDropdowns() {
    const projectSelect = document.getElementById('taskProject');
    const assigneeSelect = document.getElementById('taskAssignee');
    
    if (projectSelect) {
        projectSelect.innerHTML = '<option value="">No Project</option>';
        projects.forEach(p => {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.name;
            projectSelect.appendChild(option);
        });
    }
    
    if (assigneeSelect) {
        assigneeSelect.innerHTML = '<option value="">Unassigned</option>';
        users.forEach(u => {
            const option = document.createElement('option');
            option.value = u.id;
            option.textContent = u.full_name;
            assigneeSelect.appendChild(option);
        });
    }
}

// Load tasks from API
async function loadTasks() {
    try {
        let url = 'api/tasks.php?action=list';
        if (currentProjectFilter) {
            url += `&project_id=${currentProjectFilter}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.tasks) {
            renderTasks(data.tasks);
        }
    } catch (error) {
        console.error('Failed to load tasks:', error);
    }
}

// Render tasks to columns
function renderTasks(tasks) {
    const columns = {
        todo: document.getElementById('todo-column'),
        in_progress: document.getElementById('in_progress-column'),
        done: document.getElementById('done-column')
    };
    
    // Clear columns
    Object.values(columns).forEach(col => {
        if (col) col.innerHTML = '';
    });
    
    // Count tasks
    const counts = { todo: 0, in_progress: 0, done: 0 };
    
    tasks.forEach(task => {
        const column = columns[task.status];
        if (column) {
            column.appendChild(createTaskCard(task));
            counts[task.status]++;
        }
    });
    
    // Update counts
    Object.keys(counts).forEach(status => {
        const countEl = document.getElementById(`${status}-count`);
        if (countEl) countEl.textContent = counts[status];
    });
    
    // Reinitialize drag and drop
    initDragAndDrop();
}

// Create task card HTML
function createTaskCard(task) {
    const canDrag = isManager || currentUserId == task.assigned_to;
    const dueDateInfo = getDueDateInfo(task.due_date, task.status);
    const hasSubtasks = task.subtasks && task.subtasks.length > 0;
    
    const card = document.createElement('div');
    card.className = `task-card ${canDrag ? 'draggable' : ''} ${task.status === 'done' ? 'task-completed' : ''}`;
    card.dataset.taskId = task.id;
    card.dataset.assignedTo = task.assigned_to || '';
    card.dataset.hasSubtasks = hasSubtasks ? '1' : '0';
    card.dataset.progress = task.progress || 0;
    if (canDrag) card.draggable = true;
    card.onclick = () => openTaskDetail(task.id);
    
    let html = '';
    
    if (task.project_name) {
        html += `<div class="task-project" style="background: ${task.project_color}20; color: ${task.project_color}">${escapeHtml(task.project_name)}</div>`;
    }
    
    html += `<h4 class="task-title">${escapeHtml(task.title)}</h4>`;
    
    if (task.description) {
        html += `<p class="task-desc">${escapeHtml(task.description.substring(0, 100))}</p>`;
    }
    
    // Progress bar - always show 100% for completed tasks
    if (task.status === 'done') {
        html += `
            <div class="task-progress">
                <div class="progress-bar completed">
                    <div class="progress-fill" style="width: 100%"></div>
                </div>
                <span class="progress-text completed">100%</span>
            </div>`;
    } else if (task.subtasks && task.subtasks.length > 0) {
        html += `
            <div class="task-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${task.progress}%"></div>
                </div>
                <span class="progress-text">${task.progress}%</span>
            </div>`;
    }
    
    html += `<div class="task-meta">`;
    if (task.due_date) {
        html += `<span class="task-due ${dueDateInfo.class}">${dueDateInfo.icon} ${dueDateInfo.text}</span>`;
    }
    html += `<span class="task-priority priority-${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span>`;
    html += `</div>`;
    
    html += `<div class="task-footer">`;
    if (task.assigned_name) {
        html += `
            <div class="task-assignee">
                <div class="avatar-small">${task.assigned_name.charAt(0).toUpperCase()}</div>
                <span>${escapeHtml(task.assigned_name)}</span>
            </div>`;
    } else {
        html += `<div class="task-assignee unassigned"><span>Unassigned</span></div>`;
    }
    html += `</div>`;
    
    card.innerHTML = html;
    return card;
}

// Get due date info with status and styling
function getDueDateInfo(dueDate, status) {
    if (!dueDate) return { class: '', text: '', icon: '' };
    
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const due = new Date(dueDate);
    due.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((due - now) / (1000 * 60 * 60 * 24));
    
    // Task is completed
    if (status === 'done') {
        if (diffDays > 0) {
            return { 
                class: 'due-early', 
                text: `Done ${diffDays} day${diffDays > 1 ? 's' : ''} early ✓`, 
                icon: '🎉' 
            };
        } else if (diffDays === 0) {
            return { 
                class: 'due-ontime', 
                text: 'Done on time ✓', 
                icon: '✅' 
            };
        } else {
            return { 
                class: 'due-late', 
                text: `Done ${Math.abs(diffDays)} day${Math.abs(diffDays) > 1 ? 's' : ''} late`, 
                icon: '⚠️' 
            };
        }
    }
    
    // Task is not completed
    if (diffDays < 0) {
        // Overdue
        return { 
            class: 'overdue', 
            text: `${Math.abs(diffDays)} day${Math.abs(diffDays) > 1 ? 's' : ''} overdue`, 
            icon: '🔴' 
        };
    } else if (diffDays === 0) {
        // Due today
        return { 
            class: 'due-today', 
            text: 'Due today!', 
            icon: '⚡' 
        };
    } else if (diffDays <= 2) {
        // Due soon (1-2 days)
        return { 
            class: 'due-soon', 
            text: `Due in ${diffDays} day${diffDays > 1 ? 's' : ''}`, 
            icon: '🟠' 
        };
    } else {
        // Normal
        return { 
            class: '', 
            text: formatDate(dueDate), 
            icon: '📅' 
        };
    }
}

// Drag and Drop functionality
function initDragAndDrop() {
    const draggables = document.querySelectorAll('.task-card.draggable');
    const columns = document.querySelectorAll('.column-content');
    
    draggables.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
    });
}

function handleDragStart(e) {
    e.target.classList.add('dragging');
    e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    document.querySelectorAll('.column-content').forEach(col => {
        col.classList.remove('drag-over');
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function handleDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) {
        e.currentTarget.classList.remove('drag-over');
    }
}

async function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    
    const taskId = e.dataTransfer.getData('text/plain');
    const card = document.querySelector(`.task-card[data-task-id="${taskId}"]`);
    const newStatus = e.currentTarget.id.replace('-column', '');
    
    // Check if trying to move to done with incomplete subtasks
    if (newStatus === 'done') {
        const hasSubtasks = card.dataset.hasSubtasks === '1';
        const progress = parseInt(card.dataset.progress) || 0;
        
        if (hasSubtasks && progress < 100) {
            showNotification(`Cannot mark as complete! Subtasks are only ${progress}% done. Complete all subtasks first.`, 'error');
            return;
        }
    }
    
    // Move card visually
    e.currentTarget.appendChild(card);
    
    // Update on server
    try {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('task_id', taskId);
        formData.append('status', newStatus);
        
        const response = await fetch('api/tasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            showNotification(result.error || 'Failed to update task', 'error');
            loadTasks(); // Reload to revert
        } else {
            updateColumnCounts();
            // Reload tasks to update card UI (due date status, etc.)
            loadTasks();
        }
    } catch (error) {
        showNotification('Failed to update task', 'error');
        loadTasks(); // Reload to revert
    }
}

function updateColumnCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelector('.column-content').children.length;
        col.querySelector('.task-count').textContent = count;
    });
}

// Filter by project
function filterByProject(projectId) {
    currentProjectFilter = projectId || null;
    
    // Show/hide project files button
    const filesBtn = document.getElementById('viewProjectFilesBtn');
    if (filesBtn) {
        filesBtn.style.display = projectId ? 'inline-block' : 'none';
    }
    
    // Load project files count if project selected
    if (projectId) {
        loadProjectFilesCount(projectId);
    } else {
        const filesBar = document.getElementById('projectFilesBar');
        if (filesBar) filesBar.style.display = 'none';
    }
    
    loadTasks();
}

// Load project files count
async function loadProjectFilesCount(projectId) {
    try {
        const response = await fetch(`api/files.php?action=list_project&project_id=${projectId}`);
        const data = await response.json();
        
        const filesBar = document.getElementById('projectFilesBar');
        const filesCount = document.getElementById('projectFilesCount');
        
        if (data.files && data.files.length > 0) {
            filesBar.style.display = 'block';
            filesCount.textContent = `📎 ${data.files.length} file${data.files.length > 1 ? 's' : ''} attached to this project`;
        } else {
            filesBar.style.display = 'none';
        }
    } catch (error) {
        console.error('Failed to load project files count:', error);
    }
}

// Open project files modal
async function openProjectFilesModal() {
    if (!currentProjectFilter) return;
    
    const modal = document.getElementById('projectFilesModal');
    const content = document.getElementById('projectFilesContent');
    const title = document.getElementById('projectFilesModalTitle');
    
    // Find project name
    const project = projects.find(p => p.id == currentProjectFilter);
    title.textContent = project ? `Files: ${project.name}` : 'Project Files';
    
    content.innerHTML = '<div class="loading-spinner">Loading files...</div>';
    modal.classList.add('active');
    
    try {
        const response = await fetch(`api/files.php?action=list_project&project_id=${currentProjectFilter}`);
        const data = await response.json();
        
        if (data.files && data.files.length > 0) {
            content.innerHTML = `
                <div class="project-files-list">
                    ${data.files.map(file => `
                        <div class="project-file-item">
                            <span class="file-icon">${getFileIcon(file.file_type)}</span>
                            <div class="file-info">
                                <a href="api/files.php?action=download&file_id=${file.id}" class="file-name">${escapeHtml(file.original_name)}</a>
                                <span class="file-meta">Uploaded by ${escapeHtml(file.uploader_name)} • ${formatFileSize(file.file_size)}</span>
                            </div>
                            <a href="api/files.php?action=download&file_id=${file.id}" class="btn btn-sm btn-secondary">Download</a>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            content.innerHTML = '<p class="no-items">No files attached to this project.</p>';
        }
    } catch (error) {
        content.innerHTML = '<div class="error">Failed to load files</div>';
    }
}

function closeProjectFilesModal() {
    document.getElementById('projectFilesModal').classList.remove('active');
}

function getFileIcon(fileType) {
    if (!fileType) return '📄';
    if (fileType.includes('image')) return '🖼️';
    if (fileType.includes('pdf')) return '📕';
    if (fileType.includes('word') || fileType.includes('document')) return '📘';
    if (fileType.includes('excel') || fileType.includes('spreadsheet')) return '📗';
    if (fileType.includes('zip') || fileType.includes('archive')) return '📦';
    return '📄';
}

// Task Modal Functions
let tempSubtasks = []; // Temporary subtasks for new task

function openTaskModal(taskId = null) {
    const modal = document.getElementById('taskModal');
    const form = document.getElementById('taskForm');
    
    // Clear temp subtasks
    tempSubtasks = [];
    renderTempSubtasks();
    
    if (taskId) {
        // Edit mode - load task data
        loadTaskForEdit(taskId);
        document.getElementById('taskModalTitle').textContent = 'Edit Task';
        // Hide subtasks section in edit mode (use detail modal for that)
        document.getElementById('newTaskSubtasks').style.display = 'none';
    } else {
        // Create mode
        form.reset();
        document.getElementById('taskId').value = '';
        document.getElementById('taskModalTitle').textContent = 'Add New Task';
        document.getElementById('newTaskSubtasks').style.display = 'block';
    }
    
    modal.classList.add('active');
}

function addTempSubtask() {
    const input = document.getElementById('tempSubtaskInput');
    const title = input.value.trim();
    
    if (!title) return;
    
    tempSubtasks.push(title);
    input.value = '';
    renderTempSubtasks();
}

function removeTempSubtask(index) {
    tempSubtasks.splice(index, 1);
    renderTempSubtasks();
}

function renderTempSubtasks() {
    const container = document.getElementById('tempSubtasksList');
    if (!container) return;
    
    if (tempSubtasks.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = tempSubtasks.map((title, index) => `
        <div class="temp-subtask-item">
            <span>☐ ${escapeHtml(title)}</span>
            <button type="button" class="btn-icon" onclick="removeTempSubtask(${index})">×</button>
        </div>
    `).join('');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
}

async function loadTaskForEdit(taskId) {
    try {
        const response = await fetch(`api/tasks.php?action=get&task_id=${taskId}`);
        const task = await response.json();
        
        if (task.error) {
            showNotification(task.error, 'error');
            return;
        }
        
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title;
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskProject').value = task.project_id || '';
        document.getElementById('taskAssignee').value = task.assigned_to || '';
        document.getElementById('taskDueDate').value = task.due_date || '';
        document.getElementById('taskPriority').value = task.priority;
    } catch (error) {
        showNotification('Failed to load task', 'error');
    }
}

async function saveTask(e) {
    e.preventDefault();
    
    const form = document.getElementById('taskForm');
    const formData = new FormData(form);
    const taskId = formData.get('task_id');
    
    formData.append('action', taskId ? 'update' : 'create');
    
    // Add subtasks for new tasks
    if (!taskId && tempSubtasks.length > 0) {
        formData.append('subtasks', JSON.stringify(tempSubtasks));
    }
    
    try {
        const response = await fetch('api/tasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message || 'Task saved successfully');
            closeTaskModal();
            tempSubtasks = []; // Clear temp subtasks
            location.reload();
        } else {
            showNotification(result.error || 'Failed to save task', 'error');
        }
    } catch (error) {
        showNotification('Failed to save task', 'error');
    }
}

async function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('task_id', taskId);
    
    try {
        const response = await fetch('api/tasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Task deleted successfully');
            closeTaskDetailModal();
            location.reload();
        } else {
            showNotification(result.error || 'Failed to delete task', 'error');
        }
    } catch (error) {
        showNotification('Failed to delete task', 'error');
    }
}

// Task Detail Modal
async function openTaskDetail(taskId) {
    const modal = document.getElementById('taskDetailModal');
    const content = document.getElementById('taskDetailContent');
    
    content.innerHTML = '<div class="loading-spinner">Loading...</div>';
    modal.classList.add('active');
    
    try {
        const response = await fetch(`api/tasks.php?action=get&task_id=${taskId}`);
        const task = await response.json();
        
        if (task.error) {
            content.innerHTML = `<div class="error">${task.error}</div>`;
            return;
        }
        
        renderTaskDetail(task);
    } catch (error) {
        content.innerHTML = '<div class="error">Failed to load task details</div>';
    }
}

function closeTaskDetailModal() {
    document.getElementById('taskDetailModal').classList.remove('active');
}

// Alias for HTML onclick handler
function closeTaskDetail() {
    closeTaskDetailModal();
}

function renderTaskDetail(task) {
    const content = document.getElementById('taskDetailContent');
    const canModify = isManager || currentUserId == task.assigned_to;
    const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done';
    
    document.getElementById('taskDetailTitle').textContent = task.title;
    
    let subtasksHtml = '';
    if (task.subtasks && task.subtasks.length > 0) {
        subtasksHtml = task.subtasks.map(sub => `
            <div class="subtask-item ${sub.completed ? 'completed' : ''}">
                <label class="checkbox-label">
                    <input type="checkbox" ${sub.completed ? 'checked' : ''} 
                           ${canModify ? `onchange="toggleSubtask(${sub.id})"` : 'disabled'}>
                    <span>${escapeHtml(sub.title)}</span>
                </label>
                ${canModify ? `<button class="btn-icon" onclick="deleteSubtask(${sub.id})">×</button>` : ''}
            </div>
        `).join('');
    }
    
    let commentsHtml = '';
    if (task.comments && task.comments.length > 0) {
        commentsHtml = task.comments.map(comment => `
            <div class="comment-item">
                <div class="comment-avatar">${comment.user_name.charAt(0).toUpperCase()}</div>
                <div class="comment-content">
                    <div class="comment-header">
                        <strong>${escapeHtml(comment.user_name)}</strong>
                        <span class="comment-date">${formatDateTime(comment.created_at)}</span>
                    </div>
                    <p>${escapeHtml(comment.content)}</p>
                </div>
                ${(isManager || currentUserId == comment.user_id) ? 
                    `<button class="btn-icon" onclick="deleteComment(${comment.id}, ${task.id})">×</button>` : ''}
            </div>
        `).join('');
    }
    
    content.innerHTML = `
        <div class="task-detail">
            <div class="task-detail-main">
                <div class="task-detail-section">
                    <h3>Description</h3>
                    <p class="task-description">${task.description ? escapeHtml(task.description) : '<em>No description</em>'}</p>
                </div>
                
                <div class="task-detail-section">
                    <div class="section-header">
                        <h3>Subtasks</h3>
                        ${canModify ? `
                        <div class="add-subtask-form">
                            <input type="text" id="newSubtask" placeholder="Add a subtask...">
                            <button class="btn btn-sm btn-primary" onclick="addSubtask(${task.id})">Add</button>
                        </div>
                        ` : ''}
                    </div>
                    <div class="subtasks-list" id="subtasksList">
                        ${subtasksHtml || '<p class="no-items">No subtasks</p>'}
                    </div>
                    ${task.subtasks && task.subtasks.length > 0 ? `
                    <div class="progress-section">
                        <div class="progress-bar large">
                            <div class="progress-fill" style="width: ${task.progress}%"></div>
                        </div>
                        <span>${task.progress}% complete</span>
                    </div>
                    ` : ''}
                </div>
                
                <div class="task-detail-section">
                    <h3>Comments</h3>
                    <div class="comments-list" id="commentsList">
                        ${commentsHtml || '<p class="no-items">No comments yet</p>'}
                    </div>
                    <div class="add-comment-form">
                        <textarea id="newComment" placeholder="Add a comment..." rows="2"></textarea>
                        <button class="btn btn-primary" onclick="addComment(${task.id})">Post Comment</button>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-sidebar">
                <div class="detail-field">
                    <label>Status</label>
                    <span class="status-badge status-${task.status}">${task.status.replace('_', ' ')}</span>
                </div>
                
                <div class="detail-field">
                    <label>Priority</label>
                    <span class="priority-badge priority-${task.priority}">${task.priority}</span>
                </div>
                
                <div class="detail-field">
                    <label>Project</label>
                    <span>${task.project_name || 'None'}</span>
                </div>
                
                <div class="detail-field">
                    <label>Assigned To</label>
                    <span>${task.assigned_name || 'Unassigned'}</span>
                </div>
                
                <div class="detail-field">
                    <label>Due Date</label>
                    <span class="${isOverdue ? 'overdue' : ''}">${task.due_date ? formatDate(task.due_date) : 'Not set'}</span>
                </div>
                
                <div class="detail-actions">
                    ${isManager ? `
                    <button class="btn btn-secondary btn-block" onclick="closeTaskDetailModal(); openTaskModal(${task.id})">Edit Task</button>
                    ` : ''}
                    ${isManager ? `
                    <button class="btn btn-danger btn-block" onclick="deleteTask(${task.id})">Delete Task</button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

// Subtask functions
async function addSubtask(taskId) {
    const input = document.getElementById('newSubtask');
    const title = input.value.trim();
    
    if (!title) return;
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('task_id', taskId);
    formData.append('title', title);
    
    try {
        const response = await fetch('api/subtasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            openTaskDetail(taskId); // Refresh
        } else {
            showNotification(result.error || 'Failed to add subtask', 'error');
        }
    } catch (error) {
        showNotification('Failed to add subtask', 'error');
    }
}

async function toggleSubtask(subtaskId) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('subtask_id', subtaskId);
    
    try {
        const response = await fetch('api/subtasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the checkbox visual
            const checkbox = document.querySelector(`.subtask-item input[onchange*="toggleSubtask(${subtaskId})"]`);
            if (checkbox) {
                checkbox.checked = result.completed;
                checkbox.closest('.subtask-item').classList.toggle('completed', result.completed);
            }
            
            // Update progress bar in detail modal
            const progressBar = document.querySelector('.progress-section .progress-fill');
            const progressText = document.querySelector('.progress-section span');
            if (progressBar) {
                progressBar.style.width = result.progress + '%';
            }
            if (progressText) {
                progressText.textContent = result.progress + '% complete';
            }
            
            // Update progress bar on the task card in kanban
            const taskCard = document.querySelector(`.task-card[data-task-id="${result.task_id}"]`);
            if (taskCard) {
                // Update the data-progress attribute for drag validation
                taskCard.dataset.progress = result.progress;
                
                const progressFill = taskCard.querySelector('.progress-fill');
                if (progressFill) {
                    progressFill.style.width = result.progress + '%';
                }
            }
            
            // Update task card progress text
            const taskProgress = document.querySelector(`.task-card[data-task-id="${result.task_id}"] .progress-text`);
            if (taskProgress) {
                taskProgress.textContent = result.progress + '%';
            }
        } else {
            showNotification(result.error || 'Failed to update subtask', 'error');
        }
    } catch (error) {
        showNotification('Failed to update subtask', 'error');
    }
}

async function deleteSubtask(subtaskId) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('subtask_id', subtaskId);
    
    try {
        const response = await fetch('api/subtasks.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.querySelector(`.subtask-item input[onchange*="${subtaskId}"]`).closest('.subtask-item').remove();
        } else {
            showNotification(result.error || 'Failed to delete subtask', 'error');
        }
    } catch (error) {
        showNotification('Failed to delete subtask', 'error');
    }
}

// Comment functions
async function addComment(taskId) {
    const textarea = document.getElementById('newComment');
    const content = textarea.value.trim();
    
    if (!content) return;
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('task_id', taskId);
    formData.append('content', content);
    
    try {
        const response = await fetch('api/comments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            textarea.value = '';
            openTaskDetail(taskId); // Refresh
        } else {
            showNotification(result.error || 'Failed to add comment', 'error');
        }
    } catch (error) {
        showNotification('Failed to add comment', 'error');
    }
}

async function deleteComment(commentId, taskId) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('comment_id', commentId);
    
    try {
        const response = await fetch('api/comments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            openTaskDetail(taskId); // Refresh
        } else {
            showNotification(result.error || 'Failed to delete comment', 'error');
        }
    } catch (error) {
        showNotification('Failed to delete comment', 'error');
    }
}

// File functions
function handleFileDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function handleFileDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

async function handleFileDrop(e, taskId) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        await uploadFileToTask(files[0], taskId);
    }
}

async function uploadFile(taskId) {
    const input = document.getElementById('fileInput');
    if (input.files.length > 0) {
        await uploadFileToTask(input.files[0], taskId);
    }
}

async function uploadFileToTask(file, taskId) {
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('task_id', taskId);
    formData.append('file', file);
    
    try {
        const response = await fetch('api/files.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('File uploaded successfully');
            openTaskDetail(taskId); // Refresh
        } else {
            showNotification(result.error || 'Failed to upload file', 'error');
        }
    } catch (error) {
        showNotification('Failed to upload file', 'error');
    }
}

async function deleteFile(fileId, taskId) {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('file_id', fileId);
    
    try {
        const response = await fetch('api/files.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            openTaskDetail(taskId); // Refresh
        } else {
            showNotification(result.error || 'Failed to delete file', 'error');
        }
    } catch (error) {
        showNotification('Failed to delete file', 'error');
    }
}
