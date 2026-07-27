function userFormHtml(user = null) {
    const pwdHint = user ? '(leave blank to keep current)' : '*';
    const pwdAttrs = user ? '' : 'required minlength="6"';
    const pwdVal = user ? '' : '';
    return `
        <form id="userForm" onsubmit="saveUser(event)">
            ${user ? `<input type="hidden" name="id" value="${user.id}">` : ''}
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" required value="${user?.full_name || ''}">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required value="${user?.username || ''}">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required value="${user?.email || ''}">
                </div>
            </div>
            <div class="form-group">
                <label>Password ${pwdHint}</label>
                <input type="password" name="password" class="form-control" ${pwdAttrs} value="${pwdVal}">
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" class="form-control">
                    <option value="user" ${user?.role === 'user' ? 'selected' : ''}>User</option>
                    <option value="super_admin" ${user?.role === 'super_admin' ? 'selected' : ''}>Super Admin</option>
                </select>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">${user ? 'Update' : 'Create'} User</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

function renderUsersTable(users) {
    const container = document.getElementById('usersContainer');
    if (!container) return;

    if (!users || !users.length) {
        container.innerHTML = '<div class="empty-state"><p>No users yet. Click "New User" to create one.</p></div>';
        return;
    }

    container.innerHTML = `<div class="table-wrapper"><table>
        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>${users.map(u => `<tr>
            <td><strong>${escapeHtml(u.full_name)}</strong></td>
            <td>${escapeHtml(u.username)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="badge ${u.role === 'super_admin' ? 'badge-urgent' : 'badge-medium'}">${u.role === 'super_admin' ? 'Super Admin' : 'User'}</span></td>
            <td>${formatDate(u.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editUser(${u.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function openCreateUser() {
    openModal('Create User', userFormHtml());
}

async function editUser(id) {
    const res = await api('/api/users.php?action=get&id=' + id);
    if (res.success) openModal('Edit User', userFormHtml(res.user));
    else showToast(res.message, 'error');
}

async function saveUser(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const action = data.id ? 'update' : 'create';
    if (!data.id && (!data.password || data.password.length < 6)) {
        showToast('Password must be at least 6 characters', 'error');
        return;
    }
    const res = await api('/api/users.php?action=' + action, { method: 'POST', body: data });
    if (res.success) {
        showToast(res.message);
        closeModal();
        loadUsers();
    } else {
        showToast(res.message, 'error');
    }
}

async function deleteUser(id) {
    if (!confirmAction('Delete this user?')) return;
    const res = await api('/api/users.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) {
        showToast('User deleted');
        loadUsers();
    } else {
        showToast(res.message, 'error');
    }
}

async function loadUsers() {
    const container = document.getElementById('usersContainer');
    try {
        const res = await api('/api/users.php?action=list');
        if (!res.success) {
            container.innerHTML = `<div class="empty-state"><p>${escapeHtml(res.message || 'Failed to load users')}</p></div>`;
            return;
        }
        renderUsersTable(res.users);
    } catch (err) {
        console.error(err);
        container.innerHTML = '<div class="empty-state"><p>Failed to load users. Refresh the page.</p></div>';
    }
}

async function loadDeleted(type) {
    const container = document.getElementById('deletedContainer');
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
    const res = await api('/api/' + type + '.php?action=list&show_deleted=1');
    const items = res[type] || [];
    if (!items.length) {
        container.innerHTML = '<div class="empty-state"><p>No deleted ' + type + '</p></div>';
        return;
    }
    const isTask = type === 'tasks';
    container.innerHTML = `<div class="table-wrapper"><table>
        <thead><tr><th>Title</th>${isTask ? '<th>Status</th>' : ''}<th>User</th><th>Deleted</th><th>Actions</th></tr></thead>
        <tbody>${items.map(item => `<tr class="deleted-item">
            <td>${escapeHtml(item.title)}</td>
            ${isTask ? `<td>${statusBadge(item.status)}</td>` : ''}
            <td>${escapeHtml(item.user_name)}</td>
            <td>${formatDateTime(item.updated_at)}</td>
            <td><button class="btn btn-sm btn-success" onclick="restoreItem('${type}', ${item.id})"><i class="fas fa-undo"></i> Restore</button></td>
        </tr>`).join('')}</tbody></table></div>`;
}

async function restoreItem(type, id) {
    const res = await api('/api/' + type + '.php?action=restore', { method: 'POST', body: { id } });
    if (res.success) {
        showToast('Restored successfully');
        loadDeleted(type);
    } else {
        showToast(res.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('usersContainer')) {
        loadUsers();
    }
});
