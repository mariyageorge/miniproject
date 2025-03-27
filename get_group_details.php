<?php
include("connect.php");
session_start();

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo "Please login to continue";
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if (!$group_id) {
    http_response_code(400);
    echo "Invalid group ID";
    exit();
}

// Check if user is member of this group
$member_check = "SELECT gm.invitation_status, eg.* 
                FROM group_members gm 
                JOIN expense_groups eg ON gm.group_id = eg.group_id 
                WHERE gm.group_id = ? AND gm.user_id = ? AND gm.invitation_status = 'accepted'";
$stmt = mysqli_prepare($conn, $member_check);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    http_response_code(403);
    echo "You don't have access to this group";
    exit();
}

$group_info = mysqli_fetch_assoc($result);

// Get group members
$members_query = "SELECT u.user_id, u.username, u.email, gm.invitation_status 
                 FROM group_members gm 
                 JOIN users u ON gm.user_id = u.user_id 
                 WHERE gm.group_id = ?";
$stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$members_result = mysqli_stmt_get_result($stmt);

// Get group expenses
$expenses_query = "SELECT e.*, u.username as paid_by_user,
                  (SELECT SUM(share_amount) FROM expense_shares WHERE expense_id = e.expense_id) as total_shares
                  FROM expenses e 
                  JOIN users u ON e.paid_by = u.user_id 
                  WHERE e.group_id = ? 
                  ORDER BY e.date_added DESC";
$stmt = mysqli_prepare($conn, $expenses_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$expenses_result = mysqli_stmt_get_result($stmt);

// Calculate balances
$balances_query = "SELECT 
                    u.user_id,
                    u.username,
                    COALESCE(SUM(CASE 
                        WHEN e.paid_by = u.user_id THEN e.amount
                        ELSE 0
                    END), 0) as paid_amount,
                    COALESCE(SUM(CASE 
                        WHEN es.user_id = u.user_id THEN es.share_amount
                        ELSE 0
                    END), 0) as owed_amount
                  FROM users u
                  JOIN group_members gm ON u.user_id = gm.user_id
                  LEFT JOIN expenses e ON e.group_id = gm.group_id AND e.paid_by = u.user_id
                  LEFT JOIN expense_shares es ON es.user_id = u.user_id
                  WHERE gm.group_id = ? AND gm.invitation_status = 'accepted'
                  GROUP BY u.user_id";
$stmt = mysqli_prepare($conn, $balances_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$balances_result = mysqli_stmt_get_result($stmt);
?>

<div class="group-details">
    <div class="group-header">
        <div class="group-title">
            <h2><i class="fas fa-users-gear"></i> <?php echo htmlspecialchars($group_info['group_name']); ?></h2>
            <p class="group-description">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($group_info['description'] ?? 'No description'); ?>
            </p>
            <p class="group-meta">
                <span><i class="fas fa-calendar-alt"></i> Created: <?php echo date('M d, Y', strtotime($group_info['created_at'])); ?></span>
            </p>
        </div>
        <div class="group-actions">
            <?php if ($group_info['created_by'] == $user_id): ?>
                <button class="button button-danger" onclick="confirmDeleteGroup(<?php echo $group_id; ?>)">
                    <i class="fas fa-trash-alt"></i> Delete Group
                </button>
            <?php endif; ?>
            <button class="button" onclick="openAddExpenseModal(<?php echo $group_id; ?>, '<?php echo htmlspecialchars($group_info['group_name']); ?>')">
                <i class="fas fa-plus-circle"></i> Add Expense
            </button>
            <button class="button" onclick="openInviteMembersModal(<?php echo $group_id; ?>)">
                <i class="fas fa-user-plus"></i> Invite Members
            </button>
        </div>
    </div>

    <div class="group-content">
        <!-- Navigation Tabs -->
        <div class="section-tabs">
            <button class="tab-button active" onclick="showSection('members')">
                <i class="fas fa-users"></i> Members
            </button>
            <button class="tab-button" onclick="showSection('balances')">
                <i class="fas fa-scale-balanced"></i> Balances
            </button>
            <button class="tab-button" onclick="showSection('expenses')">
                <i class="fas fa-receipt"></i> Expenses
            </button>
            <button class="tab-button" onclick="showSection('messages')">
                <i class="fas fa-comments"></i> Messages
            </button>
        </div>

        <!-- Members Section -->
        <div id="members-section" class="section-content active">
            <div class="members-grid">
                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                    <div class="member-card <?php echo $member['invitation_status'] === 'pending' ? 'pending' : ''; ?>">
                        <div class="member-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="member-info">
                            <h4><?php echo htmlspecialchars($member['username']); ?></h4>
                            <p class="member-email">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                            </p>
                            <span class="member-status <?php echo $member['invitation_status']; ?>">
                                <?php echo ucfirst($member['invitation_status']); ?>
                            </span>
                            <?php if ($member['user_id'] != $user_id): ?>
                                <button class="button button-outline" onclick="openMessageModal(<?php echo $member['user_id']; ?>, '<?php echo htmlspecialchars($member['username']); ?>')">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Balances Section -->
        <div id="balances-section" class="section-content">
            <div class="balances-grid">
                <?php 
                mysqli_data_seek($balances_result, 0);
                while ($balance = mysqli_fetch_assoc($balances_result)): 
                    $net_balance = $balance['paid_amount'] - $balance['owed_amount'];
                    $balance_class = $net_balance > 0 ? 'positive' : ($net_balance < 0 ? 'negative' : 'neutral');
                ?>
                    <div class="balance-card <?php echo $balance_class; ?>">
                        <h4><?php echo htmlspecialchars($balance['username']); ?></h4>
                        <div class="balance-amount">
                            <?php if ($net_balance > 0): ?>
                                <i class="fas fa-arrow-trend-up"></i>
                            <?php elseif ($net_balance < 0): ?>
                                <i class="fas fa-arrow-trend-down"></i>
                            <?php else: ?>
                                <i class="fas fa-equals"></i>
                            <?php endif; ?>
                            $<?php echo number_format(abs($net_balance), 2); ?>
                        </div>
                        <p class="balance-status">
                            <?php if ($net_balance > 0): ?>
                                To receive
                            <?php elseif ($net_balance < 0): ?>
                                To pay
                            <?php else: ?>
                                Settled
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Expenses Section -->
        <div id="expenses-section" class="section-content">
            <?php 
            mysqli_data_seek($expenses_result, 0);
            if (mysqli_num_rows($expenses_result) > 0): 
            ?>
                <div class="expenses-list">
                    <?php while ($expense = mysqli_fetch_assoc($expenses_result)): ?>
                        <div class="expense-card">
                            <div class="expense-header">
                                <h4><?php echo htmlspecialchars($expense['description']); ?></h4>
                                <span class="expense-amount">$<?php echo number_format($expense['amount'], 2); ?></span>
                            </div>
                            <div class="expense-meta">
                                <span><i class="fas fa-user"></i> Paid by: <?php echo htmlspecialchars($expense['paid_by_user']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($expense['date_added'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="no-data"><i class="fas fa-info-circle"></i> No expenses recorded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Messages Section -->
        <div id="messages-section" class="section-content">
            <div class="messages-container">
                <?php
                // Get messages for this group
                $messages_query = "SELECT m.*, u.username as sender_name 
                                 FROM group_messages m 
                                 JOIN users u ON m.sender_id = u.user_id 
                                 WHERE m.group_id = ? 
                                 ORDER BY m.created_at DESC";
                $stmt = mysqli_prepare($conn, $messages_query);
                mysqli_stmt_bind_param($stmt, "i", $group_id);
                mysqli_stmt_execute($stmt);
                $messages_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($messages_result) > 0):
                    while ($message = mysqli_fetch_assoc($messages_result)):
                ?>
                    <div class="message-card <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                        <div class="message-header">
                            <span class="sender-name"><?php echo htmlspecialchars($message['sender_name']); ?></span>
                            <span class="message-time"><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <p class="no-data"><i class="fas fa-comments"></i> No messages yet. Start a conversation!</p>
                <?php endif; ?>
            </div>
            <form id="messageForm" class="message-form" onsubmit="sendMessage(event)">
                <textarea name="message" placeholder="Type your message..." required></textarea>
                <button type="submit" class="button">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>
        </div>
    </div>

    <!-- Invite Members Modal -->
    <div id="inviteMembersModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal" onclick="closeInviteMembersModal()">&times;</span>
            <h2><i class="fas fa-user-plus"></i> Invite Members</h2>
            <form id="inviteForm" onsubmit="inviteMembers(event)">
                <div class="form-group">
                    <label for="inviteEmails">Email Addresses (one per line)</label>
                    <textarea id="inviteEmails" name="emails" rows="5" required 
                              placeholder="Enter email addresses, one per line"></textarea>
                </div>
                <div class="form-group">
                    <label for="inviteMessage">Message (optional)</label>
                    <textarea id="inviteMessage" name="message" rows="3" 
                              placeholder="Add a personal message to your invitation"></textarea>
                </div>
                <button type="submit" class="button">
                    <i class="fas fa-paper-plane"></i> Send Invitations
                </button>
            </form>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Send Message</h3>
                <button class="modal-close" onclick="closeMessageModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="directMessageForm" onsubmit="sendDirectMessage(event)">
                    <input type="hidden" id="recipient_id" name="recipient_id">
                    <div class="form-group">
                        <label for="messageText">Message</label>
                        <textarea id="messageText" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="button">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.group-details {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.group-header {
    background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
    color: white;
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.group-title h2 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.group-description {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 1rem;
}

.group-meta {
    font-size: 0.9rem;
    opacity: 0.8;
}

.group-actions {
    display: flex;
    gap: 1rem;
}

.button-danger {
    background: #dc3545;
    border-color: #dc3545;
}

.button-danger:hover {
    background: #c82333;
}

.group-content {
    padding: 2rem;
}

.section-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--nude-300);
    padding-bottom: 1rem;
}

.tab-button {
    background: none;
    border: none;
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
    color: var(--brown-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.tab-button:hover {
    color: var(--brown-hover);
}

.tab-button.active {
    color: var(--brown-primary);
    border-bottom: 2px solid var(--brown-primary);
    margin-bottom: -2px;
}

.section-content {
    display: none;
}

.section-content.active {
    display: block;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.member-card {
    background: var(--nude-100);
    border-radius: 10px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.member-card.pending {
    opacity: 0.7;
}

.member-avatar {
    font-size: 2.5rem;
    color: var(--brown-primary);
}

.member-info h4 {
    margin-bottom: 0.3rem;
    color: var(--brown-primary);
}

.member-email {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.member-status {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    text-transform: capitalize;
}

.member-status.accepted {
    background: var(--accent-green);
    color: white;
}

.member-status.pending {
    background: var(--accent-orange);
    color: white;
}

.member-status.declined {
    background: #dc3545;
    color: white;
}

.balances-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.balance-card {
    padding: 1.5rem;
    border-radius: 10px;
    color: white;
    text-align: center;
}

.balance-card.positive {
    background: linear-gradient(135deg, var(--accent-green), #458B74);
}

.balance-card.negative {
    background: linear-gradient(135deg, #E57373, #C62828);
}

.balance-card.neutral {
    background: linear-gradient(135deg, #90A4AE, #546E7A);
}

.balance-amount {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 1rem 0;
}

.expenses-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.expense-card {
    background: var(--nude-100);
    border-radius: 10px;
    padding: 1.5rem;
}

.expense-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.expense-amount {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--brown-primary);
}

.expense-meta {
    display: flex;
    gap: 1.5rem;
    font-size: 0.9rem;
    color: #666;
}

.no-data {
    text-align: center;
    padding: 2rem;
    color: #666;
    font-style: italic;
}

@media (max-width: 768px) {
    .group-header {
        flex-direction: column;
        gap: 1.5rem;
    }

    .group-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .button {
        width: 100%;
    }

    .section-tabs {
        flex-wrap: wrap;
    }

    .tab-button {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .message-card {
        max-width: 90%;
    }
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.close-modal {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--brown-primary);
    font-weight: 500;
}

.form-group textarea {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid var(--nude-300);
    border-radius: 8px;
    font-size: 1rem;
    resize: vertical;
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--brown-primary);
    box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.1);
}

.button.loading {
    position: relative;
    color: transparent;
}

.button.loading::after {
    content: '';
    position: absolute;
    width: 1rem;
    height: 1rem;
    top: 50%;
    left: 50%;
    margin: -0.5rem 0 0 -0.5rem;
    border: 2px solid white;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Message Styles */
.messages-container {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--nude-100);
    border-radius: 8px;
}

.message-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    max-width: 80%;
}

.message-card.sent {
    margin-left: auto;
    background: var(--brown-primary);
    color: white;
}

.message-card.received {
    margin-right: auto;
}

.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.sender-name {
    font-weight: bold;
}

.message-time {
    opacity: 0.8;
}

.message-content {
    line-height: 1.5;
}

.message-form {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.message-form textarea {
    flex: 1;
    padding: 0.8rem;
    border: 1px solid var(--nude-300);
    border-radius: 8px;
    resize: none;
    height: 60px;
}

.message-form button {
    align-self: flex-end;
}
</style>

<script>
function confirmDeleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        fetch('delete_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'group_id=' + groupId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'expense_splitter.php';
            } else {
                alert(data.error || 'Failed to delete group');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the group');
        });
    }
}

function openInviteMembersModal() {
    document.getElementById('inviteMembersModal').style.display = 'flex';
}

function closeInviteMembersModal() {
    document.getElementById('inviteMembersModal').style.display = 'none';
    document.getElementById('inviteForm').reset();
}

async function inviteMembers(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.classList.add('loading');

    const emails = form.emails.value.split('\n').map(email => email.trim()).filter(email => email);
    const message = form.message.value.trim();

    try {
        const response = await fetch('invite_members.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `group_id=<?php echo $group_id; ?>&emails=${encodeURIComponent(JSON.stringify(emails))}&message=${encodeURIComponent(message)}`
        });

        const data = await response.json();
        
        if (data.success) {
            closeInviteMembersModal();
            alert('Invitations sent successfully!');
            // Refresh the members list
            location.reload();
        } else {
            alert(data.error || 'Failed to send invitations');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to send invitations');
    } finally {
        submitBtn.classList.remove('loading');
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.section-content').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(sectionId + '-section').classList.add('active');
    
    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
}

function openMessageModal(userId, username) {
    document.getElementById('recipient_id').value = userId;
    document.getElementById('messageModal').style.display = 'flex';
}

function closeMessageModal() {
    document.getElementById('messageModal').style.display = 'none';
    document.getElementById('directMessageForm').reset();
}

async function sendDirectMessage(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.classList.add('loading');

    try {
        const response = await fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                recipient_id: form.recipient_id.value,
                message: form.message.value,
                group_id: <?php echo $group_id; ?>
            })
        });

        const data = await response.json();
        
        if (data.success) {
            closeMessageModal();
            alert('Message sent successfully!');
        } else {
            alert(data.error || 'Failed to send message');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to send message');
    } finally {
        submitBtn.classList.remove('loading');
    }
}

async function sendMessage(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.classList.add('loading');

    try {
        const response = await fetch('send_group_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                group_id: <?php echo $group_id; ?>,
                message: form.message.value
            })
        });

        const data = await response.json();
        
        if (data.success) {
            form.reset();
            location.reload();
        } else {
            alert(data.error || 'Failed to send message');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to send message');
    } finally {
        submitBtn.classList.remove('loading');
    }
}
</script> 