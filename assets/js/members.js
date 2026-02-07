// Members Page JavaScript

// Load on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    initMemberModal();
});

// Load members from API
async function loadMembers() {
    try {
        const response = await fetch('api/users.php?action=list_stats');
        const data = await response.json();
        
        if (data.members) {
            renderMembers(data.members);
        }
    } catch (error) {
        console.error('Failed to load members:', error);
    }
}

// Render members
function renderMembers(members) {
    const container = document.getElementById('membersGrid');
    if (!container) return;
    
    if (members.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No members yet.</p></div>';
        return;
    }
    
    container.innerHTML = members.map(member => {
        const progress = member.task_count > 0 ? Math.round((member.ontime_count / member.task_count) * 100) : 0;
        const latePercent = member.task_count > 0 ? Math.round((member.late_count / member.task_count) * 100) : 0;
        
        return `
        <div class="member-card">
            <div class="member-avatar">
                ${member.full_name.charAt(0).toUpperCase()}
            </div>
            <div class="member-info">
                <h3>${escapeHtml(member.full_name)}</h3>
                <span class="member-username">@${escapeHtml(member.username)}</span>
                <span class="member-email">${escapeHtml(member.email)}</span>
            </div>
            <div class="member-role">
                <span class="role-badge role-${member.role}">
                    ${member.role.charAt(0).toUpperCase() + member.role.slice(1)}
                </span>
            </div>
            <div class="member-stats">
                <div class="stat">
                    <span class="stat-value">${member.task_count}</span>
                    <span class="stat-label">Tasks</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-success">${member.ontime_count}</span>
                    <span class="stat-label">On Time</span>
                </div>
                <div class="stat">
                    <span class="stat-value ${latePercent > 0 ? 'stat-danger' : ''}">${member.late_count}</span>
                    <span class="stat-label">Late</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-success">${progress}%</span>
                    <span class="stat-label">Progress</span>
                </div>
                ${latePercent > 0 ? `
                <div class="stat">
                    <span class="stat-value stat-danger">-${latePercent}%</span>
                    <span class="stat-label">Late Rate</span>
                </div>
                ` : ''}
            </div>
            <div class="member-actions">
                <button class="btn btn-sm btn-secondary" onclick="editMember(${member.id})">Edit</button>
                ${member.id != currentUserId ? `
                <button class="btn btn-sm btn-danger" onclick="deleteMember(${member.id}, '${escapeHtml(member.full_name)}')">Delete</button>
                ` : ''}
            </div>
        </div>`;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initMemberModal() {
    const modal = document.getElementById('memberModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMemberModal();
            }
        });
    }
}

function openMemberModal() {
    document.getElementById('memberModalTitle').textContent = 'Add Member';
    document.getElementById('memberForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('memberPassword').required = true;
    document.getElementById('passwordHint').textContent = '(required for new member)';
    document.getElementById('memberModal').classList.add('active');
}

function closeMemberModal() {
    document.getElementById('memberModal').classList.remove('active');
}

async function editMember(id) {
    try {
        const response = await fetch(`api/users.php?action=get&user_id=${id}`);
        const user = await response.json();
        
        if (user.error) {
            alert(user.error);
            return;
        }
        
        document.getElementById('memberModalTitle').textContent = 'Edit Member';
        document.getElementById('memberId').value = user.id;
        document.getElementById('memberFullName').value = user.full_name;
        document.getElementById('memberUsername').value = user.username;
        document.getElementById('memberEmail').value = user.email;
        document.getElementById('memberRole').value = user.role;
        document.getElementById('memberPassword').value = '';
        document.getElementById('memberPassword').required = false;
        document.getElementById('passwordHint').textContent = '(leave empty to keep current)';
        document.getElementById('memberModal').classList.add('active');
    } catch (error) {
        alert('Failed to load member');
    }
}

async function saveMember(e) {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('memberForm'));
    const userId = formData.get('user_id');
    formData.append('action', userId ? 'update' : 'create');
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to save member');
        }
    } catch (error) {
        alert('Failed to save member');
    }
}

async function deleteMember(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"? Their tasks will be unassigned.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('user_id', id);
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to delete member');
        }
    } catch (error) {
        alert('Failed to delete member');
    }
}
