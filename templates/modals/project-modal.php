<?php
// Project Modal Template
?>
<div id="projectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="projectModalTitle">New Project</h2>
            <button class="modal-close" onclick="closeProjectModal()">&times;</button>
        </div>
        <form id="projectForm" onsubmit="saveProject(event)">
            <input type="hidden" id="projectId" name="project_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="projectName">Project Name *</label>
                    <input type="text" id="projectName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="projectDescription">Description</label>
                    <textarea id="projectDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="projectColor">Color</label>
                    <input type="color" id="projectColor" name="color" value="#3498db">
                </div>
                
                <!-- Files Section (only for new projects) -->
                <div class="form-group" id="projectFilesSection">
                    <label>Project Files</label>
                    <div class="file-upload-area project-files-upload" id="projectFileDropZone">
                        <div class="upload-icon">📁</div>
                        <p>Drag & drop files here or click to browse</p>
                        <input type="file" id="projectFileInput" multiple style="display: none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('projectFileInput').click()">Choose Files</button>
                    </div>
                    <div class="project-files-list" id="projectFilesList">
                        <!-- Selected files will appear here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProjectModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Project</button>
            </div>
        </form>
    </div>
</div>
