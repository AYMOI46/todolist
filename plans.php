<?php
$pageTitle = 'Plans';
$currentPage = 'plans';
$headerActions = '<button class="btn btn-primary" onclick="openCreatePlan()"><i class="fas fa-plus"></i> New Plan</button>';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clipboard-list"></i> All Plans & Sub-Plans</h2>
    </div>
    <div id="plansContainer">
        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>
    </div>
</div>

<?php
$isAdmin = isSuperAdmin() ? 'true' : 'false';
$pageScript = "
const isAdmin = {$isAdmin};

async function loadPlans() {
    const res = await api('/api/plans.php?action=list&type=hierarchy');
    const container = document.getElementById('plansContainer');
    if (!res.plans || !res.plans.length) {
        container.innerHTML = '<div class=\"empty-state\"><i class=\"fas fa-clipboard-list\"></i><p>No plans yet. Create your first plan!</p></div>';
        return;
    }
    container.innerHTML = res.plans.map(p => renderPlanBlock(p)).join('');
}

function renderPlanBlock(plan) {
    const subs = plan.sub_plans || [];
    const subsHtml = subs.length ? subs.map(sp => `
        <div class=\"sub-plan-row\">
            <div class=\"sub-plan-info\">
                <i class=\"fas fa-level-down-alt\"></i>
                <strong>\${sp.title}</strong>
                \${statusBadge(sp.status)}
                \${priorityBadge(sp.priority)}
                <span style=\"color:var(--text-muted);font-size:0.8rem;\">\${(sp.description||'').substring(0,50)}</span>
            </div>
            <div class=\"sub-plan-actions\">
                <button class=\"btn btn-sm btn-secondary\" onclick=\"editSubPlan(\${sp.id})\"><i class=\"fas fa-edit\"></i></button>
                <button class=\"btn btn-sm btn-danger\" onclick=\"deletePlan(\${sp.id})\"><i class=\"fas fa-trash\"></i></button>
            </div>
        </div>
    `).join('') : '<div class=\"no-sub-plans\">No sub-plans yet</div>';

    return `<div class=\"plan-block\">
        <div class=\"plan-block-header\">
            <div>
                <h3><i class=\"fas fa-folder\"></i> \${plan.title}</h3>
                <p>\${plan.description || ''}</p>
                <div class=\"plan-meta\">
                    <span><i class=\"fas fa-calendar\"></i> \${formatDate(plan.start_date)} - \${formatDate(plan.end_date)}</span>
                    <span class=\"badge badge-medium\">\${subs.length} sub-plans</span>
                    <span class=\"badge badge-medium\">\${plan.task_count} tasks</span>
                    \${isAdmin ? `<span><i class=\"fas fa-user\"></i> \${plan.user_name}</span>` : ''}
                </div>
            </div>
            <div class=\"plan-block-actions\">
                <button class=\"btn btn-sm btn-primary\" onclick=\"openCreateSubPlan(\${plan.id}, '\${plan.title.replace(/'/g, \"\\\\'\")}')\"><i class=\"fas fa-plus\"></i> Sub-Plan</button>
                <button class=\"btn btn-sm btn-secondary\" onclick=\"editPlan(\${plan.id})\"><i class=\"fas fa-edit\"></i></button>
                <button class=\"btn btn-sm btn-danger\" onclick=\"deletePlan(\${plan.id})\"><i class=\"fas fa-trash\"></i></button>
            </div>
        </div>
        <div class=\"sub-plans-list\">\${subsHtml}</div>
    </div>`;
}

function openCreatePlan() { openModal('Create Plan', planFormHtml()); }

async function editPlan(id) {
    const res = await api('/api/plans.php?action=get&id=' + id);
    if (res.success) openModal('Edit Plan', planFormHtml(res.plan));
}

async function editSubPlan(id) {
    const res = await api('/api/plans.php?action=get&id=' + id);
    if (res.success) openModal('Edit Sub-Plan', subPlanFormHtml(res.plan, res.plan.parent_id, res.plan.parent_title));
}

function openCreateSubPlan(parentId, parentTitle) {
    openModal('Create Sub-Plan', subPlanFormHtml(null, parentId, parentTitle));
}

async function savePlan(e) {
    e.preventDefault();
    const form = e.target;
    // Ensure flatpickr dates are synced before submit
    form.querySelectorAll('.date-picker').forEach(input => {
        if (input._flatpickr) input._flatpickr.close();
    });
    const data = Object.fromEntries(new FormData(form));
    if (!data.title || !data.title.trim()) { showToast('Title is required', 'error'); return; }
    const action = data.id ? 'update' : 'create';
    const res = await api('/api/plans.php?action=' + action, { method: 'POST', body: data });
    if (res.success) { showToast(res.message); closeModal(); loadPlans(); }
    else showToast(res.message || 'Failed to save plan', 'error');
}

async function saveSubPlan(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const action = data.id ? 'update' : 'create';
    const res = await api('/api/plans.php?action=' + action, { method: 'POST', body: data });
    if (res.success) { showToast(res.message); closeModal(); loadPlans(); }
    else showToast(res.message, 'error');
}

async function deletePlan(id) {
    if (!confirmAction('Delete this item?')) return;
    const res = await api('/api/plans.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast('Deleted'); loadPlans(); }
    else showToast(res.message, 'error');
}

loadPlans();
";
require_once __DIR__ . '/includes/footer.php';
?>
