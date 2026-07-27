<?php
$pageTitle = 'Kanban Board';
$currentPage = 'board';
$headerActions = '
    <button class="btn btn-secondary" onclick="openCreateSubPlan()"><i class="fas fa-folder-plus"></i> Sub-Plan</button>
    <button class="btn btn-primary" onclick="openCreateTask()"><i class="fas fa-plus"></i> Task</button>';
require_once __DIR__ . '/includes/header.php';
?>

<div class="filter-bar">
    <select id="planFilter" onchange="loadBoard()">
        <option value="">All Plans</option>
    </select>
    <select id="priorityFilter" onchange="loadBoard()">
        <option value="">All Priorities</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
    </select>
</div>

<div class="plans-board-strip" id="plansStrip">
    <div class="plans-strip-label"><i class="fas fa-clipboard-list"></i> All Plans:</div>
    <div class="plans-strip-items" id="plansStripItems"></div>
    <div class="plans-strip-hint"><i class="fas fa-hand-pointer"></i> Jiid plan → ku rid All, Weekly, ama column kale</div>
</div>

<div class="kanban-board" id="kanbanBoard">
    <?php
    $columns = [
        'all' => ['label' => 'All', 'icon' => 'fa-inbox', 'color' => '#64748b'],
        'weekly' => ['label' => 'Weekly', 'icon' => 'fa-calendar-week', 'color' => '#6366f1'],
        'daily' => ['label' => 'Daily', 'icon' => 'fa-sun', 'color' => '#8b5cf6'],
        'processing' => ['label' => 'Processing', 'icon' => 'fa-spinner', 'color' => '#f59e0b'],
        'done' => ['label' => 'Done', 'icon' => 'fa-check-circle', 'color' => '#10b981'],
        'failed' => ['label' => 'Failed', 'icon' => 'fa-times-circle', 'color' => '#ef4444'],
    ];
    foreach ($columns as $status => $col): ?>
    <div class="kanban-column" data-status="<?= $status ?>">
        <div class="column-header">
            <h3>
                <i class="fas <?= $col['icon'] ?>" style="color:<?= $col['color'] ?>"></i>
                <?= $col['label'] ?>
                <span class="column-count" id="count-<?= $status ?>">0</span>
            </h3>
            <button class="btn btn-sm btn-icon btn-secondary" onclick="openCreateTask('<?= $status ?>')" title="Add task">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="column-body" id="column-<?= $status ?>"
             ondragover="handleDragOver(event)"
             ondrop="handleDrop(event, '<?= $status ?>')"
             ondragleave="handleDragLeave(event)">
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$isAdmin = isSuperAdmin() ? 'true' : 'false';
$pageScript = "
let allTasks = [];
let allSubPlans = [];
let allMainPlans = [];
const isAdmin = {$isAdmin};
let draggedItem = null;

async function loadBoard() {
    const [tasksRes, subRes, mainRes] = await Promise.all([
        api('/api/tasks.php?action=list'),
        api('/api/plans.php?action=board'),
        api('/api/plans.php?action=main_board')
    ]);
    allTasks = tasksRes.tasks || [];
    allSubPlans = subRes.sub_plans || [];
    allMainPlans = mainRes.plans || [];

    const planFilter = document.getElementById('planFilter');
    const currentPlan = planFilter.value;
    planFilter.innerHTML = '<option value=\"\">All Plans</option>' +
        allMainPlans.map(p => `<option value=\"\${p.id}\" \${p.id == currentPlan ? 'selected' : ''}>\${p.title}</option>`).join('');

    renderPlansStrip();
    renderColumns();
}

function renderPlansStrip() {
    const container = document.getElementById('plansStripItems');
    if (!allMainPlans.length) {
        container.innerHTML = '<span class=\"text-muted\" style=\"font-size:0.875rem;color:var(--text-muted);\">No plans yet</span>';
        return;
    }
    container.innerHTML = allMainPlans.map(p => {
        const subs = allSubPlans.filter(s => s.parent_id == p.id);
        const done = subs.filter(s => s.status === 'done').length;
        const safeTitle = p.title.replace(/'/g, \"\\\\'\").replace(/\"/g, '&quot;');
        return `<div class=\"plan-strip-card\"
            draggable=\"true\"
            data-type=\"stripplan\"
            data-id=\"\${p.id}\"
            data-title=\"\${safeTitle}\"
            ondragstart=\"handlePlanStripDragStart(event)\"
            ondragend=\"handleDragEnd(event)\"
            title=\"Jiid plan-ka oo ku rid column-ka (Weekly, Daily, iwm.)\">
            <i class=\"fas fa-grip-vertical drag-handle\"></i>
            <i class=\"fas fa-folder\"></i>
            <span>\${p.title}</span>
            <span class=\"plan-strip-count\">\${done}/\${subs.length || p.subplan_count || 0}</span>
            <button class=\"btn btn-sm btn-icon\" onclick=\"event.stopPropagation();openCreateSubPlan(\${p.id}, '\${safeTitle}')\" title=\"Add sub-plan\"><i class=\"fas fa-plus\"></i></button>
        </div>`;
    }).join('');
}

function filterByPlan(id) {
    document.getElementById('planFilter').value = id;
    loadBoard();
}

function renderColumns() {
    const planFilter = document.getElementById('planFilter').value;
    const priorityFilter = document.getElementById('priorityFilter').value;

    let tasks = allTasks;
    let subPlans = allSubPlans;

    if (planFilter) {
        tasks = tasks.filter(t => t.plan_id == planFilter);
        subPlans = subPlans.filter(s => s.parent_id == planFilter);
    }
    if (priorityFilter) {
        tasks = tasks.filter(t => t.priority === priorityFilter);
        subPlans = subPlans.filter(s => s.priority === priorityFilter);
    }

    ['all','weekly','daily','processing','done','failed'].forEach(status => {
        const col = document.getElementById('column-' + status);
        const colTasks = tasks.filter(t => t.status === status);
        const colSubs = subPlans.filter(s => s.status === status);
        document.getElementById('count-' + status).textContent = colTasks.length + colSubs.length;

        let html = '';

        // Group sub-plans by parent plan
        const parentIds = [...new Set(colSubs.map(s => s.parent_id))];
        parentIds.forEach(pid => {
            const parent = allMainPlans.find(p => p.id == pid);
            const parentTitle = parent?.title || colSubs.find(s => s.parent_id == pid)?.parent_title || 'Plan';
            const subs = colSubs.filter(s => s.parent_id == pid);
            html += `<div class=\"plan-group\">
                <div class=\"plan-group-header\"><i class=\"fas fa-folder-open\"></i> \${parentTitle}</div>
                \${subs.map(s => renderSubPlanCard(s)).join('')}
            </div>`;
        });

        // Main plans with no sub-plans in this column but visible in weekly
        if (status === 'weekly' && !planFilter) {
            allMainPlans.forEach(p => {
                const hasSubsInCol = colSubs.some(s => s.parent_id == p.id);
                const hasAnySubs = allSubPlans.some(s => s.parent_id == p.id);
                if (!hasAnySubs) {
                    html += renderMainPlanCard(p);
                } else if (!hasSubsInCol && colSubs.filter(s => s.parent_id == p.id).length === 0) {
                    // show plan header if plan exists but no subs in this column - skip duplicate headers
                }
            });
        }

        // Standalone tasks
        if (colTasks.length) {
            if (html) html += '<div class=\"board-section-label\"><i class=\"fas fa-tasks\"></i> Tasks</div>';
            html += colTasks.map(t => renderTaskCard(t)).join('');
        }

        if (!html) html = '<div class=\"empty-state\" style=\"padding:20px;\"><p style=\"font-size:0.8rem;\">Empty</p></div>';
        col.innerHTML = html;
    });
}

function renderMainPlanCard(plan) {
    return `<div class=\"kanban-card plan-card\" data-type=\"mainplan\" data-id=\"\${plan.id}\">
        <div class=\"card-title\"><i class=\"fas fa-folder\" style=\"color:var(--primary-light);margin-right:6px;\"></i>\${plan.title}</div>
        <div style=\"font-size:0.75rem;color:var(--text-muted);margin:6px 0;\">\${(plan.description||'').substring(0,60)}</div>
        <div class=\"card-actions\">
            <button onclick=\"openCreateSubPlan(\${plan.id}, '\${plan.title.replace(/'/g, \"\\\\'\")}')\" title=\"Add Sub-Plan\"><i class=\"fas fa-plus\"></i> Sub-Plan</button>
            <button onclick=\"filterByPlan(\${plan.id})\" title=\"Filter\"><i class=\"fas fa-filter\"></i></button>
        </div>
    </div>`;
}

function renderSubPlanCard(sp) {
    const failReason = sp.status === 'failed' && sp.failure_reason ?
        `<div class=\"fail-reason\"><i class=\"fas fa-exclamation-triangle\"></i> \${sp.failure_reason}</div>` : '';
    const dateStr = sp.task_date ? `<span><i class=\"fas fa-calendar\"></i> \${formatDate(sp.task_date)}</span>` : '';
    const fileIcon = sp.file_count > 0 ? `<span><i class=\"fas fa-paperclip\"></i> \${sp.file_count}</span>` : '';
    const retryBtn = sp.status === 'failed' ?
        `<button onclick=\"retryFromFailed(\${sp.id}, 'subplan')\" title=\"Retry — copy cusub\" style=\"color:var(--warning);\"><i class=\"fas fa-copy\"></i> Retry</button>` : '';
    return `<div class=\"kanban-card subplan-card\" draggable=\"true\" data-type=\"subplan\" data-id=\"\${sp.id}\" ondragstart=\"handleDragStart(event)\" ondragend=\"handleDragEnd(event)\">
        <div class=\"card-type-badge\">Sub-Plan</div>
        <div class=\"card-title\"><span class=\"priority-dot \${sp.priority}\"></span>\${sp.title}</div>
        \${sp.description ? `<div style=\"font-size:0.8rem;color:var(--text-muted);margin-bottom:6px;\">\${sp.description.substring(0,60)}</div>` : ''}
        \${failReason}
        <div class=\"card-meta\">
            <span><i class=\"fas fa-folder\"></i> \${sp.parent_title || ''}</span>
            \${dateStr} \${fileIcon}
            \${isAdmin ? `<span><i class=\"fas fa-user\"></i> \${sp.user_name}</span>` : ''}
        </div>
        <div class=\"card-actions\">
            \${retryBtn}
            <button onclick=\"editSubPlan(\${sp.id})\" title=\"Edit\"><i class=\"fas fa-edit\"></i></button>
            \${sp.status === 'done' && sp.file_count > 0 ? `<button onclick=\"window.location='\${APP_URL}/files.php?type=plan&item_id=\${sp.id}'\" title=\"Download\"><i class=\"fas fa-download\"></i></button>` : ''}
            <button onclick=\"deleteSubPlan(\${sp.id})\" title=\"Delete\" style=\"color:var(--danger);\"><i class=\"fas fa-trash\"></i></button>
        </div>
    </div>`;
}

function renderTaskCard(task) {
    const planName = task.plan_title ? `<span><i class=\"fas fa-clipboard-list\"></i> \${task.plan_title}</span>` : '';
    const fileIcon = task.file_count > 0 ? `<span><i class=\"fas fa-paperclip\"></i> \${task.file_count}</span>` : '';
    const dateStr = task.task_date ? `<span><i class=\"fas fa-calendar\"></i> \${formatDate(task.task_date)}</span>` : '';
    const failReason = task.status === 'failed' && task.failure_reason ?
        `<div class=\"fail-reason\"><i class=\"fas fa-exclamation-triangle\"></i> \${task.failure_reason}</div>` : '';
    const retryBtn = task.status === 'failed' ?
        `<button onclick=\"retryFromFailed(\${task.id}, 'task')\" title=\"Retry — copy cusub\" style=\"color:var(--warning);\"><i class=\"fas fa-copy\"></i> Retry</button>` : '';
    return `<div class=\"kanban-card\" draggable=\"true\" data-type=\"task\" data-id=\"\${task.id}\" ondragstart=\"handleDragStart(event)\" ondragend=\"handleDragEnd(event)\">
        <div class=\"card-type-badge task-badge\">Task</div>
        <div class=\"card-title\"><span class=\"priority-dot \${task.priority}\"></span>\${task.title}</div>
        \${task.description ? `<div style=\"font-size:0.8rem;color:var(--text-muted);margin-bottom:6px;\">\${task.description.substring(0,60)}</div>` : ''}
        \${failReason}
        <div class=\"card-meta\">\${planName} \${dateStr} \${fileIcon}</div>
        <div class=\"card-actions\">
            \${retryBtn}
            <button onclick=\"viewTask(\${task.id})\" title=\"View\"><i class=\"fas fa-eye\"></i></button>
            <button onclick=\"editTask(\${task.id})\" title=\"Edit\"><i class=\"fas fa-edit\"></i></button>
            \${task.status === 'done' && task.file_count > 0 ? `<button onclick=\"window.location='\${APP_URL}/files.php?type=task&item_id=\${task.id}'\" title=\"Download\"><i class=\"fas fa-download\"></i></button>` : ''}
            <button onclick=\"deleteTask(\${task.id})\" title=\"Delete\" style=\"color:var(--danger);\"><i class=\"fas fa-trash\"></i></button>
        </div>
    </div>`;
}

function handlePlanStripDragStart(e) {
    const card = e.currentTarget;
    draggedItem = { type: 'stripplan', id: card.dataset.id, title: card.dataset.title };
    card.classList.add('dragging');
    document.getElementById('plansStrip').classList.add('drag-active');
    document.getElementById('kanbanBoard').classList.add('drop-target-active');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.id);
}

function handleDragStart(e) {
    const card = e.target.closest('.kanban-card');
    if (!card || !card.dataset.type || card.dataset.type === 'mainplan') { e.preventDefault(); return; }
    draggedItem = { type: card.dataset.type, id: card.dataset.id };
    card.classList.add('dragging');
    document.getElementById('kanbanBoard').classList.add('drop-target-active');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    e.currentTarget.classList.remove('dragging');
    document.getElementById('plansStrip')?.classList.remove('drag-active');
    document.getElementById('kanbanBoard')?.classList.remove('drop-target-active');
    document.querySelectorAll('.column-body.drag-over').forEach(el => el.classList.remove('drag-over'));
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-over');
}

function handleDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) {
        e.currentTarget.classList.remove('drag-over');
    }
}

async function handleDrop(e, newStatus) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    document.querySelector('.dragging')?.classList.remove('dragging');
    document.getElementById('plansStrip')?.classList.remove('drag-active');
    document.getElementById('kanbanBoard')?.classList.remove('drop-target-active');
    if (!draggedItem) return;

    const { type, id, title } = draggedItem;

    // Plan ka strip-ka la jiiday → fur sub-plan form status-ka column-ka
    if (type === 'stripplan') {
        const planTitle = title || allMainPlans.find(p => p.id == id)?.title || '';
        openCreateSubPlan(parseInt(id), planTitle, newStatus);
        showToast('Plan ku dar ' + newStatus + ' — buuxi sub-plan-ka');
        draggedItem = null;
        return;
    }

    let current;
    if (type === 'task') current = allTasks.find(t => t.id == id);
    else current = allSubPlans.find(s => s.id == id);

    if (!current || current.status === newStatus) { draggedItem = null; return; }

    if (newStatus === 'failed') {
        openModal('Mark as Failed', failureReasonFormHtml(id, type === 'subplan' ? 'subplan' : 'task'));
        draggedItem = null;
        return;
    }

    if (newStatus === 'done') {
        openCompleteModal(parseInt(id), type);
        draggedItem = null;
        return;
    }

    const endpoint = type === 'subplan' ? '/api/plans.php?action=move' : '/api/tasks.php?action=move';
    const res = await api(endpoint, { method: 'POST', body: { id: parseInt(id), status: newStatus } });
    if (res.success) {
        showToast(res.duplicated ? 'Copy created — Failed wuu joogaa' : 'Moved to ' + newStatus);
        loadBoard();
    } else showToast(res.message, 'error');
    draggedItem = null;
}

function openCompleteModal(itemId, itemType) {
    openModal('Complete — Upload File', completeFormHtml(itemId, itemType));
}

async function retryFromFailed(id, type, targetStatus = 'weekly') {
    const endpoint = type === 'subplan' ? '/api/plans.php?action=duplicate' : '/api/tasks.php?action=duplicate';
    const res = await api(endpoint, { method: 'POST', body: { id, status: targetStatus } });
    if (res.success) {
        showToast('Copy cusub ayaa la abuuray — Failed wuu weli joogaa');
        loadBoard();
    } else showToast(res.message, 'error');
}

async function submitFailure(e, itemId, itemType) {
    e.preventDefault();
    const reason = e.target.failure_reason.value;
    const endpoint = itemType === 'subplan' ? '/api/plans.php?action=move' : '/api/tasks.php?action=move';
    const res = await api(endpoint, { method: 'POST', body: { id: itemId, status: 'failed', failure_reason: reason } });
    if (res.success) { showToast('Marked as failed'); closeModal(); loadBoard(); }
    else showToast(res.message, 'error');
}

function openCreateTask(status = 'weekly') {
    openModal('Create Task', taskFormHtml({ status }, allMainPlans));
    document.querySelector('[name=status]').value = status;
}

function openCreateSubPlan(parentId = null, parentTitle = '', status = 'weekly') {
    if (!parentId && allMainPlans.length) {
        const opts = allMainPlans.map(p => `<option value=\"\${p.id}\">\${p.title}</option>`).join('');
        openModal('Create Sub-Plan', `
            <div class=\"form-group\"><label>Select Plan *</label>
            <select id=\"subPlanParentSelect\" class=\"form-control\" required>\${opts}</select></div>
            <div id=\"subPlanFormArea\"></div>
            <button class=\"btn btn-primary\" style=\"margin-top:12px;\" onclick=\"confirmSubPlanParent('\" + status + \"')\">Continue</button>
        `);
        return;
    }
    openModal('Create Sub-Plan', subPlanFormHtml({ status }, parentId, parentTitle));
    const statusEl = document.querySelector('#subPlanForm [name=status]');
    if (statusEl) statusEl.value = status;
}

function confirmSubPlanParent(status = 'weekly') {
    const sel = document.getElementById('subPlanParentSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('modalBody').innerHTML = subPlanFormHtml({ status }, sel.value, opt.text);
    initDatePickers(document.getElementById('modalBody'));
}

async function editSubPlan(id) {
    const res = await api('/api/plans.php?action=get&id=' + id);
    if (res.success) openModal('Edit Sub-Plan', subPlanFormHtml(res.plan, res.plan.parent_id, res.plan.parent_title));
}

async function saveSubPlan(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const action = data.id ? 'update' : 'create';
    const res = await api('/api/plans.php?action=' + action, { method: 'POST', body: data });
    if (res.success) { showToast(res.message); closeModal(); loadBoard(); }
    else showToast(res.message, 'error');
}

async function deleteSubPlan(id) {
    if (!confirmAction('Delete this sub-plan?')) return;
    const res = await api('/api/plans.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast('Deleted'); loadBoard(); }
}

async function editTask(id) {
    const res = await api('/api/tasks.php?action=get&id=' + id);
    if (res.success) openModal('Edit Task', taskFormHtml(res.task, allMainPlans));
}

async function viewTask(id) {
    const res = await api('/api/tasks.php?action=get&id=' + id);
    if (!res.success) return;
    const t = res.task;
    let filesHtml = '';
    if (t.files && t.files.length) {
        filesHtml = '<div class=\"file-list\"><h4>Attachments</h4>' +
            t.files.map(f => `<div class=\"file-item\"><span><i class=\"fas fa-file\"></i> \${f.original_name}</span><a href=\"\${APP_URL}/download.php?type=task&id=\${f.id}\" class=\"btn btn-sm btn-primary\"><i class=\"fas fa-download\"></i> Download</a></div>`).join('') + '</div>';
    }
    openModal(t.title, `
        <div style=\"margin-bottom:16px;\">\${statusBadge(t.status)} \${priorityBadge(t.priority)}</div>
        <p style=\"color:var(--text-muted);margin-bottom:16px;\">\${t.description || 'No description'}</p>
        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.875rem;margin-bottom:16px;\">
            <div><strong>Date:</strong> \${formatDate(t.task_date)}</div>
            \${t.plan_title ? `<div><strong>Plan:</strong> \${t.plan_title}</div>` : ''}
        </div>
        \${t.failure_reason ? `<div class=\"fail-reason\" style=\"padding:12px;margin-bottom:16px;\">\${t.failure_reason}</div>` : ''}
        \${filesHtml}
        <div style=\"display:flex;gap:12px;margin-top:20px;\">
            <button class=\"btn btn-primary\" onclick=\"editTask(\${t.id})\">Edit</button>
            \${t.status === 'done' ? `<button class=\"btn btn-success\" onclick=\"openUpload(\${t.id})\">Upload</button>` : ''}
            <button class=\"btn btn-secondary\" onclick=\"closeModal()\">Close</button>
        </div>
    `);
}

async function saveTask(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    if (data.status === 'failed' && !data.failure_reason) { showToast('Failure reason required', 'error'); return; }
    const action = data.id ? 'update' : 'create';
    const res = await api('/api/tasks.php?action=' + action, { method: 'POST', body: data });
    if (res.success) { showToast(res.message); closeModal(); loadBoard(); }
    else showToast(res.message, 'error');
}

async function deleteTask(id) {
    if (!confirmAction('Delete this task?')) return;
    const res = await api('/api/tasks.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast('Deleted'); loadBoard(); }
}

function openUpload(taskId) { openModal('Upload File', uploadFormHtml(taskId)); }

async function uploadFile(e, taskId) {
    e.preventDefault();
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files.length) { showToast('Select a file', 'error'); return; }
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('task_id', taskId);
    const res = await api('/api/upload.php?action=upload', { method: 'POST', body: formData });
    if (res.success) { showToast('Uploaded'); closeModal(); loadBoard(); }
    else showToast(res.message, 'error');
}

loadBoard();
";
require_once __DIR__ . '/includes/footer.php';
?>
