<?php
// Task Modal Template with PHP for dynamic options
$projects = $projects ?? getAllProjects();
$users = $users ?? getAllUsers();
?>
<!-- Task Modal -->
<div id="taskModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Task</h2>
            <button class="modal-close" onclick="closeTaskModal()">&times;</button>
        </div>
        <form id="taskForm" onsubmit="saveTask(event)">
            <input type="hidden" id="taskId" name="task_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="taskTitle">Title *</label>
                        <input type="text" id="taskTitle" name="title" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="taskDescription">Description</label>
                        <textarea id="taskDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label for="taskProject">Project</label>
                        <select id="taskProject" name="project_id">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo sanitize($project['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="taskAssigned">Assign To</label>
                        <select id="taskAssigned" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo sanitize($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label for="taskDueDate">Due Date</label>
                        <input type="date" id="taskDueDate" name="due_date">
                    </div>
                    <div class="form-group">
                        <label for="taskPriority">Priority</label>
                        <select id="taskPriority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="taskStatus">Status</label>
                        <select id="taskStatus" name="status">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>
                
                <!-- Subtasks Section (for new tasks) -->
                <div class="form-row" id="subtasksSection">
                    <div class="form-group">
                        <label>Subtasks</label>
                        <div class="subtask-input-row">
                            <input type="text" id="newSubtaskInput" placeholder="Add a subtask...">
                            <button type="button" class="btn btn-sm btn-primary" onclick="addTempSubtask()">Add</button>
                        </div>
                        <div class="temp-subtasks-list" id="tempSubtasksList">
                            <!-- Temporary subtasks will be added here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Task Detail Modal -->
<div id="taskDetailModal" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h2 id="detailModalTitle">Task Details</h2>
            <button class="modal-close" onclick="closeTaskDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="taskDetailContent">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</div>
