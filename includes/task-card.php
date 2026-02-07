<?php
// Task card partial - used in Kanban board
$isOverdueTask = isOverdue($task['due_date'], $task['status']);
$canDrag = isManager() || getCurrentUserId() == $task['assigned_to'];
?>
<div class="task-card <?php echo $canDrag ? 'draggable' : ''; ?>" 
     data-task-id="<?php echo $task['id']; ?>"
     data-assigned-to="<?php echo $task['assigned_to']; ?>"
     <?php echo $canDrag ? 'draggable="true"' : ''; ?>
     onclick="openTaskDetail(<?php echo $task['id']; ?>)">
    
    <?php if ($task['project_name']): ?>
    <div class="task-project" style="background: <?php echo $task['project_color']; ?>20; color: <?php echo $task['project_color']; ?>">
        <?php echo sanitize($task['project_name']); ?>
    </div>
    <?php endif; ?>
    
    <h4 class="task-title"><?php echo sanitize($task['title']); ?></h4>
    
    <?php if ($task['description']): ?>
    <p class="task-desc"><?php echo sanitize(substr($task['description'], 0, 100)); ?></p>
    <?php endif; ?>
    
    <?php if (count($task['subtasks']) > 0): ?>
    <div class="task-progress">
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%"></div>
        </div>
        <span class="progress-text"><?php echo $task['progress']; ?>%</span>
    </div>
    <?php endif; ?>
    
    <div class="task-meta">
        <?php if ($task['due_date']): ?>
        <span class="task-due <?php echo $isOverdueTask ? 'overdue' : ''; ?>">
            📅 <?php echo formatDate($task['due_date']); ?>
        </span>
        <?php endif; ?>
        
        <span class="task-priority priority-<?php echo $task['priority']; ?>">
            <?php echo ucfirst($task['priority']); ?>
        </span>
    </div>
    
    <div class="task-footer">
        <?php if ($task['assigned_name']): ?>
        <div class="task-assignee">
            <div class="avatar-small">
                <?php echo strtoupper(substr($task['assigned_name'], 0, 1)); ?>
            </div>
            <span><?php echo sanitize($task['assigned_name']); ?></span>
        </div>
        <?php else: ?>
        <div class="task-assignee unassigned">
            <span>Unassigned</span>
        </div>
        <?php endif; ?>
    </div>
</div>
