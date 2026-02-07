// My Tasks Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Set the status filter dropdown to match URL parameter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter && typeof initialStatusFilter !== 'undefined' && initialStatusFilter) {
        statusFilter.value = initialStatusFilter;
    }
    
    loadMyTasks();
});

async function loadMyTasks() {
    try {
        let url = `api/tasks.php?action=list&user_id=${currentUserId}`;
        if (typeof initialStatusFilter !== 'undefined' && initialStatusFilter) {
            url += `&status=${initialStatusFilter}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.tasks) {
            renderMyTasks(data.tasks);
        } else {
            document.getElementById('tasksList').innerHTML = '<div class="empty-state"><p>No tasks assigned to you.</p></div>';
        }
    } catch (error) {
        console.error('Failed to load tasks:', error);
        document.getElementById('tasksList').innerHTML = '<div class="empty-state"><p>Failed to load tasks</p></div>';
    }
}

function renderMyTasks(tasks) {
    const container = document.getElementById('tasksList');
    
    if (!tasks || tasks.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No tasks assigned to you.</p></div>';
        return;
    }
    
    let html = '';
    
    tasks.forEach(task => {
        const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'done';
        
        html += `
        <div class="task-list-item" onclick="openTaskDetail(${task.id})">
            <div class="task-status-indicator status-${task.status}"></div>
            
            <div class="task-list-content">
                <div class="task-list-header">
                    ${task.project_name ? `<span class="task-project-badge" style="background: ${task.project_color}20; color: ${task.project_color}">${escapeHtml(task.project_name)}</span>` : ''}
                    <h4>${escapeHtml(task.title)}</h4>
                </div>
                
                ${task.description ? `<p class="task-list-desc">${escapeHtml(task.description.substring(0, 150))}</p>` : ''}
                
                <div class="task-list-meta">
                    ${task.due_date ? `<span class="task-due ${isOverdue ? 'overdue' : ''}">📅 ${formatDate(task.due_date)}</span>` : ''}
                    <span class="task-priority priority-${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span>
                    <span class="task-status-badge status-${task.status}">${task.status.replace('_', ' ')}</span>
                    ${task.subtasks && task.subtasks.length > 0 ? `<span class="task-subtask-count">✓ ${task.progress}%</span>` : ''}
                </div>
            </div>
            
            ${task.subtasks && task.subtasks.length > 0 ? `
            <div class="task-list-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${task.progress}%"></div>
                </div>
            </div>
            ` : ''}
        </div>`;
    });
    
    container.innerHTML = html;
}

function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location = url;
}
