<?php
include("connect.php");
session_start();

if (!isset($_SESSION['username'])) {
    die("Please login to continue");
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if (!$group_id) {
    die("Invalid group ID");
}

// Get group details
$group_query = "SELECT eg.*, u.username as creator_name 
                FROM expense_groups eg
                JOIN users u ON eg.created_by = u.user_id
                WHERE eg.group_id = ?";
$stmt = mysqli_prepare($conn, $group_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group) {
    die("Group not found");
}

$is_creator = ($group['created_by'] == $user_id);
?>

<div class="group-header" style="background: var(--brown-primary); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
    <h2 class="group-name" style="margin: 0; font-size: 1.5rem;">
        <i class="fas fa-users-between-lines"></i>
        <?php echo htmlspecialchars($group['group_name']); ?>
    </h2>
    <div class="group-meta" style="margin-top: 10px; font-size: 0.9rem; opacity: 0.9;">
        <p style="margin: 0;">
            <i class="fas fa-user-shield"></i> Created by: <?php echo htmlspecialchars($group['creator_name']); ?>
        </p>
        <p style="margin: 5px 0 0 0;">
            <i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($group['created_at'])); ?>
        </p>
    </div>
</div>

<div class="group-actions" style="display: flex; gap: 10px; margin-bottom: 20px;">
    <button class="button add-expense" onclick="openModal('addExpenseModal')">
        <i class="fas fa-plus"></i> Add Expense
    </button>
    <button class="button invite-members" onclick="handleInviteMembers(<?php echo $group_id; ?>)">
        <i class="fas fa-user-plus"></i> Invite Members
    </button>
    <?php if (!$is_creator): ?>
    <button class="button leave-group" onclick="confirmLeaveGroup(<?php echo $group_id; ?>)" style="margin-left: auto;">
        <i class="fas fa-sign-out-alt"></i> Leave Group
    </button>
    <?php endif; ?>
</div>

<div class="section-tabs" style="margin-bottom: 20px;">
    <button class="tab-button" onclick="showSection('members')">
        <i class="fas fa-users"></i> Members
    </button>
    <button class="tab-button" onclick="showSection('balances')">
        <i class="fas fa-balance-scale"></i> Balances
    </button>
    <button class="tab-button active" onclick="showSection('expenses')">
        <i class="fas fa-receipt"></i> Expenses
    </button>
</div>

<div id="members-section" class="section-content">
    <h3>Group Members</h3>
    <?php
// Get group members
    $members_query = "SELECT u.username, u.email, gm.invitation_status, u.profile_pic
                 FROM group_members gm 
                 JOIN users u ON gm.user_id = u.user_id 
                 WHERE gm.group_id = ?";
$stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$members_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($members_result) > 0): ?>
            <div class="members-grid">
                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                    <div class="member-card <?php echo $member['invitation_status'] === 'pending' ? 'pending' : ''; ?>">
                        <div class="member-avatar">
                        <?php if ($member['profile_pic']): ?>
                            <img src="<?php echo htmlspecialchars($member['profile_pic']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($member['username'], 0, 1)); ?>
                        <?php endif; ?>
                        </div>
                        <div class="member-info">
                            <h4><?php echo htmlspecialchars($member['username']); ?></h4>
                        <div class="member-email">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($member['email']); ?>
                        </div>
                        <?php if ($member['invitation_status'] === 'pending'): ?>
                            <span class="member-status pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        <?php else: ?>
                            <span class="member-status accepted">
                                <i class="fas fa-check-circle"></i> Member
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
    <?php else: ?>
        <p>No members found</p>
    <?php endif; ?>
        </div>

        <div id="balances-section" class="section-content">
    <h3>Group Balances</h3>
                <?php 
    // Get balances for this group
    $balances_query = "SELECT 
                        u.username,
                        SUM(CASE 
                            WHEN e.paid_by = u.user_id THEN e.amount
                            ELSE 0
                        END) as paid,
                        SUM(CASE 
                            WHEN es.user_id = u.user_id THEN es.share_amount
                            ELSE 0
                        END) as owed
                      FROM group_members gm
                      JOIN users u ON gm.user_id = u.user_id
                      LEFT JOIN expenses e ON e.group_id = gm.group_id AND e.paid_by = u.user_id
                      LEFT JOIN expense_shares es ON es.expense_id = e.expense_id AND es.user_id = u.user_id
                      WHERE gm.group_id = ? AND gm.invitation_status = 'accepted'
                      GROUP BY u.user_id";
    $stmt = mysqli_prepare($conn, $balances_query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $balances_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($balances_result) > 0): ?>
        <div class="balance-container">
            <?php while ($balance = mysqli_fetch_assoc($balances_result)):
                $net_balance = $balance['paid'] - $balance['owed'];
            ?>
                <div class="balance-card <?php echo $net_balance > 0 ? 'balance-positive' : 'balance-negative'; ?>">
                        <div class="balance-amount">
                            $<?php echo number_format(abs($net_balance), 2); ?>
                    </div>
                    <div class="balance-details">
                        <div class="balance-name">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($balance['username']); ?>
                        </div>
                        <div class="balance-status">
                            <?php if ($net_balance > 0): ?>
                                <i class="fas fa-arrow-up text-success"></i> Is owed
                            <?php else: ?>
                                <i class="fas fa-arrow-down text-danger"></i> Owes
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No balances found</p>
    <?php endif; ?>
        </div>

        <div id="expenses-section" class="section-content">
    <h3>Expenses</h3>
            <?php 
    // Get expenses for this group with additional details
    $expenses_query = "SELECT e.*, u.username as paid_by_user, 
                      (SELECT COUNT(*) FROM expense_shares es WHERE es.expense_id = e.expense_id AND es.status = 'settled') as settled_count,
                      (SELECT COUNT(*) FROM expense_shares es WHERE es.expense_id = e.expense_id) as total_shares
                      FROM expenses e
                      JOIN users u ON e.paid_by = u.user_id
                      WHERE e.group_id = ?
                      ORDER BY e.date_added DESC";
    $stmt = mysqli_prepare($conn, $expenses_query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $expenses_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($expenses_result) > 0): ?>
        <div class="expenses-list">
            <?php while ($expense = mysqli_fetch_assoc($expenses_result)): 
                $is_fully_settled = $expense['settled_count'] == $expense['total_shares'];
            ?>
                <div class="expense-card" onclick="showExpenseDetails(<?php echo $expense['expense_id']; ?>)">
                    <div class="expense-header">
                        <h4><?php echo htmlspecialchars($expense['description']); ?></h4>
                        <div class="expense-amount">
                            $<?php echo number_format($expense['amount'], 2); ?>
                        </div>
                    </div>
                    <div class="expense-meta">
                        <span>
                            <i class="fas fa-user"></i>
                            Paid by: <?php echo htmlspecialchars($expense['paid_by_user']); ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar"></i>
                            <?php echo date('M d, Y', strtotime($expense['date_added'])); ?>
                        </span>
                        <span class="settlement-status <?php echo $is_fully_settled ? 'settled' : 'pending'; ?>">
                            <i class="fas <?php echo $is_fully_settled ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                            <?php echo $is_fully_settled ? 'Settled' : 'Settlement Pending'; ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="no-expenses">No expenses yet</p>
    <?php endif; ?>
        </div>

<?php if ($is_creator): ?>
<div class="delete-group-container" style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid var(--nude-200);">
    <button class="button button-danger delete-group-btn" style="background-color: #dc3545;">
        <i class="fas fa-trash"></i> Delete Group
    </button>
                    </div>
                <?php endif; ?>

<!-- Invite Members Modal -->
<div id="inviteMembersModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Invite Members</h3>
            <span class="close" onclick="closeModal('inviteMembersModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="inviteMembersForm">
                <input type="hidden" id="invite_group_id" name="group_id" value="<?php echo $group_id; ?>">
                <input type="hidden" id="invite_members" name="invite_members" value="">
                
                <div class="form-group">
                    <label>Enter email addresses:</label>
                    <div id="inviteTagContainer" class="tag-container">
                        <input type="text" id="inviteTagInput" placeholder="Type email and press Enter" class="tag-input">
                    </div>
                    <small class="form-text text-muted">Press Enter after each email address</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <i class="fas fa-paper-plane"></i> Send Invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Expense Details Modal -->
<div id="expenseDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-receipt"></i> Expense Details</h3>
            <span class="close" onclick="closeModal('expenseDetailsModal')">&times;</span>
        </div>
        <div class="modal-body" id="expenseDetailsContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<style>
.group-details {
        padding: 0;
        position: relative;
}

.group-header {
        background: var(--primary-color);
    color: white;
        padding: 20px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        position: relative;
    }

    .group-title {
        margin-bottom: 20px;
}

.group-title h2 {
        margin: 0;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
}

.group-description {
        margin: 10px 0;
    opacity: 0.9;
}

.group-meta {
    font-size: 0.9rem;
    opacity: 0.8;
}

    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .action-button {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .add-expense-btn {
        background: #2ECC71;
        color: white;
    }

    .add-expense-btn:hover {
        background: #27AE60;
    }

    .invite-members-btn {
        background: #3498DB;
        color: white;
    }

    .invite-members-btn:hover {
        background: #2980B9;
}

.section-tabs {
    display: flex;
        gap: 5px;
        margin-bottom: 20px;
        background: var(--brown-primary);
        padding: 10px;
        border-radius: 8px;
}

.tab-button {
        padding: 12px 24px;
        border: none;
    background: none;
        color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
        font-weight: 500;
        position: relative;
        transition: all 0.3s ease;
    display: flex;
    align-items: center;
        gap: 10px;
        border-radius: 6px;
        font-size: 1rem;
}

.tab-button:hover {
        color: white;
        background: rgba(255, 255, 255, 0.1);
}

.tab-button.active {
        color: white;
        background: rgba(255, 255, 255, 0.2);
    }

    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--nude-200);
        border-radius: 3px;
    }

    .tab-button i {
        font-size: 1.2rem;
    }

    .tab-content {
        padding: 20px;
        background: white;
}

.section-content {
    display: none;
        animation: slideIn 0.3s ease-out;
}

.section-content.active {
    display: block;
}

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
}

.member-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid var(--nude-200);
}

.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: var(--nude-300);
}

.member-card.pending {
    background: rgba(245, 158, 11, 0.05);
    border-color: rgba(245, 158, 11, 0.2);
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    font-weight: 500;
    border: 2px solid var(--nude-200);
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-info {
    flex-grow: 1;
}

.member-info h4 {
    margin: 0 0 5px 0;
    color: var(--brown-primary);
    font-size: 1rem;
    font-weight: 600;
}

.member-email {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.member-email i {
    font-size: 0.9rem;
    color: var(--nude-400);
}

.member-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1;
}

.member-status.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
}

.member-status.accepted {
    background: rgba(74, 222, 128, 0.1);
    color: #047857;
}

.member-status i {
    font-size: 0.8rem;
}

.balance-card {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.balance-card.positive {
        border-left: 4px solid #2ECC71;
}

.balance-card.negative {
        border-left: 4px solid #E74C3C;
    }

    .expense-item {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.expense-amount {
    font-weight: bold;
        color: #8B4513;
    }

    .delete-group-container {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #E8D3C7;
        text-align: center;
    }

    .delete-group-btn {
        background: #E74C3C;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .delete-group-btn:hover {
        background: #C0392B;
    }

.group-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.group-actions .button {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.button.add-expense {
    background: #2ECC71;
    color: white;
    box-shadow: 0 2px 4px rgba(46, 204, 113, 0.2);
}

.button.add-expense:hover {
    background: #27AE60;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(46, 204, 113, 0.3);
}

.button.invite-members {
    background: #E67E22;
    color: white;
    box-shadow: 0 2px 4px rgba(230, 126, 34, 0.2);
}

.button.invite-members:hover {
    background: #D35400;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(230, 126, 34, 0.3);
}

.tag-container {
    border: 1px solid var(--nude-200);
    border-radius: 8px;
    padding: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 42px;
    background: white;
}

.tag-input {
    border: none !important;
    outline: none !important;
    padding: 4px 8px !important;
    margin: 0 !important;
    flex: 1;
    min-width: 120px;
    background: transparent !important;
}

.tag {
    background: var(--nude-100);
    border: 1px solid var(--nude-200);
    padding: 4px 8px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.tag-close {
    cursor: pointer;
    font-weight: bold;
    color: var(--brown-primary);
    transition: color 0.2s;
}

.tag-close:hover {
    color: #dc3545;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.button.leave-group {
    background: #dc3545;
    color: white;
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
    margin-left: auto;
}

.button.leave-group:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.group-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.group-meta p {
    display: flex;
    align-items: center;
    gap: 8px;
}

.expense-card {
    cursor: pointer;
    transition: all 0.3s ease;
}

.expense-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.settlement-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.settlement-status.settled {
    background: rgba(74, 222, 128, 0.1);
    color: #047857;
}

.settlement-status.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
}

.expense-details {
    padding: 20px;
}

.expense-details-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--nude-200);
}

.expense-details-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.expense-details-meta-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.expense-details-meta-item label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.expense-details-meta-item span {
    font-weight: 500;
}

.shares-list {
    border: 1px solid var(--nude-200);
    border-radius: 8px;
    overflow: hidden;
}

.share-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid var(--nude-200);
}

.share-item:last-child {
    border-bottom: none;
}

.share-item-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.share-item-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.share-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.share-status.settled {
    background: rgba(74, 222, 128, 0.1);
    color: #047857;
}

.share-status.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
}

.settle-button {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    background: var(--brown-primary);
    color: white;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.settle-button:hover {
    background: var(--brown-hover);
}

.settle-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<script>
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.section-content').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected section and activate tab
    document.getElementById(`${sectionId}-section`).classList.add('active');
    document.querySelector(`.tab-button[onclick*="${sectionId}"]`).classList.add('active');
}

function openAddExpenseModal(groupId, groupName) {
    // Close the current modal
    document.getElementById('groupDetailsModal').style.display = 'none';
    
    // Set up and open the add expense modal
    document.getElementById('expense_group_id').value = groupId;
    document.getElementById('addExpenseModal').style.display = 'flex';
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function handleInviteMembers(groupId) {
    document.getElementById('invite_group_id').value = groupId;
    openModal('inviteMembersModal');
}

function confirmDeleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        fetch('delete_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `group_id=${groupId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                showToast(data.error || 'Failed to delete group', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while deleting the group', 'error');
        });
    }
}

function showExpenseDetails(expenseId) {
    fetch(`get_expense_details.php?expense_id=${expenseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const expense = data.expense;
                const content = `
                    <div class="expense-details">
                        <div class="expense-details-header">
                            <div>
                                <h3>${expense.description}</h3>
                                <p class="text-secondary">Added on ${expense.date_added}</p>
                            </div>
                            <div class="expense-amount">$${parseFloat(expense.amount).toFixed(2)}</div>
                        </div>
                        
                        <div class="expense-details-meta">
                            <div class="expense-details-meta-item">
                                <label>Paid By</label>
                                <span>${expense.paid_by_user}</span>
                            </div>
                            <div class="expense-details-meta-item">
                                <label>Split Method</label>
                                <span>${expense.split_method}</span>
                            </div>
                            ${expense.notes ? `
                            <div class="expense-details-meta-item">
                                <label>Notes</label>
                                <span>${expense.notes}</span>
                            </div>
                            ` : ''}
                        </div>

                        <h4>Shares</h4>
                        <div class="shares-list">
                            ${expense.shares.map(share => `
                                <div class="share-item">
                                    <div class="share-item-left">
                                        <i class="fas fa-user"></i>
                                        ${share.username}
                                    </div>
                                    <div class="share-item-right">
                                        <div>$${parseFloat(share.share_amount).toFixed(2)}</div>
                                        <div class="share-status ${share.is_settled ? 'settled' : 'pending'}">
                                            <i class="fas ${share.is_settled ? 'fa-check-circle' : 'fa-clock'}"></i>
                                            ${share.is_settled ? 'Settled' : 'Pending'}
                                        </div>
                                        ${!share.is_settled && share.user_id == ${user_id} ? `
                                            <button class="settle-button" onclick="settleExpense(${expense.expense_id}, ${share.user_id})">
                                                Settle
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                document.getElementById('expenseDetailsContent').innerHTML = content;
                openModal('expenseDetailsModal');
            } else {
                showToast(data.error || 'Failed to load expense details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load expense details', 'error');
        });
}

function settleExpense(expenseId, userId) {
    if (!confirm('Are you sure you want to mark this expense as settled?')) {
        return;
    }

    fetch('settle_expense.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `expense_id=${expenseId}&user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Expense settled successfully', 'success');
            showExpenseDetails(expenseId); // Refresh the modal
            // Refresh the expenses list
            location.reload();
        } else {
            showToast(data.error || 'Failed to settle expense', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to settle expense', 'error');
    });
}

// Fix leave group functionality
function confirmLeaveGroup(groupId) {
    if (!confirm('Are you sure you want to leave this group?')) {
        return;
    }

    const button = document.querySelector('.button.leave-group');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Leaving...';

    fetch('leave_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `group_id=${groupId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Successfully left the group', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1500);
        } else {
            showToast(data.error || 'Failed to leave group', 'error');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sign-out-alt"></i> Leave Group';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while leaving the group', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-sign-out-alt"></i> Leave Group';
    });
}

// Initialize the invite members functionality
document.addEventListener('DOMContentLoaded', function() {
    const inviteMembersForm = document.getElementById('inviteMembersForm');
    const inviteTagContainer = document.getElementById('inviteTagContainer');
    const inviteTagInput = document.getElementById('inviteTagInput');
    const inviteMembersHidden = document.getElementById('invite_members');
    const inviteTags = new Set();

    function addInviteTag(email) {
        if (email && isValidEmail(email) && !inviteTags.has(email)) {
            const tag = document.createElement('span');
            tag.className = 'tag';
            tag.innerHTML = `
                ${email}
                <span class='tag-close' data-email='${email}'>&times;</span>
            `;
            inviteTagContainer.insertBefore(tag, inviteTagInput);
            inviteTags.add(email);
            updateInviteHiddenInput();
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function updateInviteHiddenInput() {
        inviteMembersHidden.value = Array.from(inviteTags).join(',');
    }

    inviteTagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const email = this.value.trim();
            if (email) {
                if (isValidEmail(email)) {
                    addInviteTag(email);
                    this.value = '';
                } else {
                    showToast('Please enter a valid email address', 'error');
                }
            }
        }
    });

    inviteTagContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('tag-close')) {
            const email = e.target.getAttribute('data-email');
            inviteTags.delete(email);
            e.target.parentElement.remove();
            updateInviteHiddenInput();
        }
    });

    inviteMembersForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (inviteTags.size === 0) {
            showToast('Please enter at least one email address', 'error');
            return;
        }

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;

        fetch('invite_members.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
        if (data.success) {
                showToast(data.message, 'success');
                closeModal('inviteMembersModal');
                // Clear the form
                inviteTags.clear();
                const existingTags = inviteTagContainer.querySelectorAll('.tag');
                existingTags.forEach(tag => tag.remove());
                updateInviteHiddenInput();
                inviteTagInput.value = '';
        } else {
                showToast(data.error || 'Failed to send invitations', 'error');
        }
        })
        .catch(error => {
        console.error('Error:', error);
            showToast('Failed to send invitations', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
});
</script> 