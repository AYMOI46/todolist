async function api(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
    };
    const config = { ...defaults, ...options };
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
        config.body = JSON.stringify(config.body);
    }
    if (config.body instanceof FormData) {
        delete config.headers['Content-Type'];
    }
    try {
        const res = await fetch(APP_URL + url, config);
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (parseErr) {
            console.error('Server response:', text);
            showToast('Server error - check console', 'error');
            return { success: false, message: 'Server error' };
        }
    } catch (err) {
        console.error('Fetch error:', err);
        showToast('Network error - is Apache/MySQL running?', 'error');
        return { success: false, message: 'Network error' };
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function openModal(title, bodyHtml) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML = bodyHtml;
    document.getElementById('modal').classList.add('active');
    setTimeout(() => initDatePickers(document.getElementById('modalBody')), 0);
}

function initDatePickers(container) {
    if (typeof flatpickr === 'undefined') return;
    const root = container || document;
    root.querySelectorAll('.date-picker').forEach(input => {
        if (input._flatpickr) input._flatpickr.destroy();

        flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            allowInput: false,
            clickOpens: true,
            defaultDate: input.dataset.defaultDate || input.value || null,
            animate: true,
            disableMobile: true,
            monthSelectorType: 'dropdown',
            prevArrow: '<i class="fas fa-chevron-left"></i>',
            nextArrow: '<i class="fas fa-chevron-right"></i>',
        });
    });
}

function dateInputField(name, label, value = '') {
    return `
        <div class="form-group">
            <label>${label}</label>
            <div class="date-input-wrapper">
                <input type="text" name="${name}" class="form-control date-picker" placeholder="Dooro taariikhda..." data-default-date="${value}" value="${value}" autocomplete="off" readonly>
                <i class="fas fa-calendar-alt date-input-icon"></i>
            </div>
        </div>
    `;
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
}

document.getElementById('modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'modal') closeModal();
});

function statusBadge(status) {
    return `<span class="badge badge-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function priorityBadge(priority) {
    return `<span class="badge badge-${priority}">${priority.charAt(0).toUpperCase() + priority.slice(1)}</span>`;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function confirmAction(message) {
    return confirm(message);
}

function statusOptionsHtml(selected = 'all') {
    const statuses = [
        { value: 'all', label: 'All' },
        { value: 'weekly', label: 'Weekly' },
        { value: 'daily', label: 'Daily' },
        { value: 'processing', label: 'Processing' },
        { value: 'done', label: 'Done' },
        { value: 'failed', label: 'Failed' },
    ];
    const sel = selected || 'all';
    return statuses.map(s => `<option value="${s.value}" ${sel === s.value ? 'selected' : ''}>${s.label}</option>`).join('');
}

function taskFormHtml(task = null, plans = []) {
    const planOptions = plans.map(p =>
        `<option value="${p.id}" ${task?.plan_id == p.id ? 'selected' : ''}>${p.title}</option>`
    ).join('');

    return `
        <form id="taskForm" onsubmit="saveTask(event)">
            ${task ? `<input type="hidden" name="id" value="${task.id}">` : ''}
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required value="${task?.title || ''}">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control">${task?.description || ''}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        ${statusOptionsHtml(task?.status || 'all')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low" ${task?.priority === 'low' ? 'selected' : ''}>Low</option>
                        <option value="medium" ${task?.priority === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="high" ${task?.priority === 'high' ? 'selected' : ''}>High</option>
                        <option value="urgent" ${task?.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Plan</label>
                    <select name="plan_id" class="form-control">
                        <option value="">No Plan</option>
                        ${planOptions}
                    </select>
                </div>
                ${dateInputField('task_date', 'Date', task?.task_date || new Date().toISOString().split('T')[0])}
            </div>
            <div class="form-group" id="failureReasonGroup" style="display:${task?.status === 'failed' ? 'block' : 'none'}">
                <label>Failure Reason *</label>
                <textarea name="failure_reason" class="form-control">${task?.failure_reason || ''}</textarea>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">${task ? 'Update' : 'Create'} Task</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

function planFormHtml(plan = null) {
    return `
        <form id="planForm" onsubmit="savePlan(event)">
            ${plan ? `<input type="hidden" name="id" value="${plan.id}">` : ''}
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required value="${plan?.title || ''}">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control">${plan?.description || ''}</textarea>
            </div>
            <div class="form-row">
                ${dateInputField('start_date', 'Start Date', plan?.start_date || '')}
                ${dateInputField('end_date', 'End Date', plan?.end_date || '')}
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">${plan ? 'Update' : 'Create'} Plan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

function subPlanFormHtml(subPlan = null, parentId = null, parentTitle = '') {
    return `
        <form id="subPlanForm" onsubmit="saveSubPlan(event)">
            ${subPlan ? `<input type="hidden" name="id" value="${subPlan.id}">` : ''}
            <input type="hidden" name="parent_id" value="${subPlan?.parent_id || parentId || ''}">
            ${parentTitle ? `<div style="padding:10px;background:rgba(99,102,241,0.1);border-radius:8px;margin-bottom:16px;font-size:0.875rem;"><i class="fas fa-folder"></i> Plan: <strong>${parentTitle}</strong></div>` : ''}
            <div class="form-group">
                <label>Sub-Plan Title *</label>
                <input type="text" name="title" class="form-control" required value="${subPlan?.title || ''}">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control">${subPlan?.description || ''}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        ${statusOptionsHtml(subPlan?.status || 'all')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low" ${subPlan?.priority === 'low' ? 'selected' : ''}>Low</option>
                        <option value="medium" ${subPlan?.priority === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="high" ${subPlan?.priority === 'high' ? 'selected' : ''}>High</option>
                        <option value="urgent" ${subPlan?.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="subFailureReasonGroup" style="display:${subPlan?.status === 'failed' ? 'block' : 'none'}">
                <label>Failure Reason</label>
                <textarea name="failure_reason" class="form-control">${subPlan?.failure_reason || ''}</textarea>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">${subPlan ? 'Update' : 'Create'} Sub-Plan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

function failureReasonFormHtml(itemId, itemType = 'task') {
    const label = itemType === 'subplan' ? 'Why did this sub-plan fail?' : 'Why did this task fail?';
    return `
        <form id="failureForm" onsubmit="submitFailure(event, ${itemId}, '${itemType}')">
            <div class="form-group">
                <label>${label} *</label>
                <textarea name="failure_reason" class="form-control" required placeholder="Geli sababta..."></textarea>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-danger">Mark as Failed</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

function completeFormHtml(itemId, itemType = 'task') {
    const label = itemType === 'subplan' ? 'Sub-Plan' : 'Task';
    const endpoint = itemType === 'subplan' ? '/api/plans.php?action=complete' : '/api/tasks.php?action=complete';
    const idField = itemType === 'subplan' ? 'plan_id' : 'task_id';
    return `
        <form id="completeForm" onsubmit="completeWithFile(event, '${endpoint}', '${idField}', ${itemId})">
            <div style="padding:12px;background:rgba(16,185,129,0.1);border-radius:8px;margin-bottom:16px;font-size:0.875rem;color:#34d399;">
                <i class="fas fa-info-circle"></i> ${label} Done — <strong>file upload waa qasab</strong> (shaqada aad qabatay)
            </div>
            <div class="upload-area" onclick="document.getElementById('completeFileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Dooro file-ka shaqada</p>
                <p style="font-size:0.75rem;margin-top:4px;">PDF, Images, Documents — Max 10MB</p>
            </div>
            <input type="file" id="completeFileInput" name="file" required style="display:none"
                onchange="document.getElementById('completeSelectedFile').textContent = this.files[0]?.name || ''">
            <p id="completeSelectedFile" style="margin-top:8px;font-size:0.875rem;color:var(--text-muted);"></p>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Done + Upload</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

async function completeWithFile(e, endpoint, idField, itemId) {
    e.preventDefault();
    const fileInput = document.getElementById('completeFileInput');
    if (!fileInput.files.length) { showToast('File waa qasab', 'error'); return; }
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append(idField, itemId);
    const res = await api(endpoint, { method: 'POST', body: formData });
    if (res.success) { showToast('Completed!'); closeModal(); if (typeof loadBoard === 'function') loadBoard(); else location.reload(); }
    else showToast(res.message || 'Failed', 'error');
}

function uploadFormHtml(taskId) {
    return `
        <form id="uploadForm" onsubmit="uploadFile(event, ${taskId})">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click to select file or drag & drop</p>
                <p style="font-size:0.75rem;margin-top:4px;">Max 10MB - PDF, Images, Documents</p>
            </div>
            <input type="file" id="fileInput" name="file" style="display:none" onchange="document.getElementById('selectedFile').textContent = this.files[0]?.name || ''">
            <p id="selectedFile" style="margin-top:8px;font-size:0.875rem;color:var(--text-muted);"></p>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="submit" class="btn btn-primary">Upload</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    `;
}

document.addEventListener('change', (e) => {
    if (e.target.name === 'status' && e.target.closest('#taskForm')) {
        const group = document.getElementById('failureReasonGroup');
        if (group) group.style.display = e.target.value === 'failed' ? 'block' : 'none';
    }
    if (e.target.name === 'status' && e.target.closest('#subPlanForm')) {
        const group = document.getElementById('subFailureReasonGroup');
        if (group) group.style.display = e.target.value === 'failed' ? 'block' : 'none';
    }
});
