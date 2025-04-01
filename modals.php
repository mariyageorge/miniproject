<?php
// Create Group Modal
?>
<div id="createGroupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-users"></i> Create New Group</h3>
            <span class="close" onclick="closeModal('createGroupModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="post" action="">
                <div class="form-group">
                    <label for="group_name">Group Name</label>
                    <input type="text" id="group_name" name="group_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Invite Members</label>
                    <div class="tag-input-container">
                        <input type="text" id="tagInput" placeholder="Type email and press Enter" class="tag-input">
                    </div>
                    <input type="hidden" id="members" name="members" value="">
                    <small class="form-text">Press Enter after each email address</small>
                </div>
                <div class="form-actions">
                    <button type="submit" name="create_group" class="button">
                        <i class="fas fa-plus"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-receipt"></i> Add New Expense</h3>
            <span class="close" onclick="closeModal('addExpenseModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="post" action="">
                <input type="hidden" id="expense_group_id" name="group_id" value="">
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" class="form-control" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="split_method">Split Method</label>
                    <select id="split_method" name="split_method" class="form-control" onchange="toggleSplitMethod()">
                        <option value="equal">Equal Split</option>
                        <option value="custom">Custom Split</option>
                    </select>
                </div>
                <div id="customSplitContainer" style="display: none;">
                    <!-- Custom split inputs will be dynamically added here -->
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_expense" class="button">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invite Members Modal -->
<div id="inviteMembersModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Invite Members</h3>
            <span class="close" onclick="closeModal('inviteMembersModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="inviteMembersForm">
                <input type="hidden" id="invite_group_id" name="group_id" value="">
                <input type="hidden" id="invite_members" name="invite_members" value="">
                <div class="form-group">
                    <label>Enter email addresses:</label>
                    <div id="inviteTagContainer" class="tag-input-container">
                        <input type="text" id="inviteTagInput" placeholder="Type email and press Enter" class="tag-input">
                    </div>
                    <small class="form-text">Press Enter after each email address</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button">
                        <i class="fas fa-paper-plane"></i> Send Invitations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div> 