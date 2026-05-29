<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'mark_read' && $id) {
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Message marked as read.';
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Message deleted successfully.';
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM contact_messages";

if ($filter !== 'all') {
    $sql .= " WHERE status = ?";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($filter !== 'all') {
    $stmt->execute([$filter]);
} else {
    $stmt->execute();
}
$messages = $stmt->fetchAll();
?>
<?php include '../includes/admin-header.php'; ?>

<div class="admin-content">
    <div class="admin-header">
        <h1><i class="fas fa-envelope"></i> Contact Messages</h1>
        <div class="header-actions">
            <a href="index.php" class="btn btn-sm btn-outline">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="admin-card mb-4">
        <div class="filter-buttons">
            <a href="messages.php" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="messages.php?filter=new" class="filter-btn <?php echo $filter === 'new' ? 'active' : ''; ?>">New</a>
            <a href="messages.php?filter=read" class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
            <a href="messages.php?filter=replied" class="filter-btn <?php echo $filter === 'replied' ? 'active' : ''; ?>">Replied</a>
        </div>
    </div>

    <div class="admin-card">
        <?php if (empty($messages)): ?>
            <p class="empty-state">No messages found matching the criteria.</p>
        <?php else: ?>
            <div class="messages-list">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-item <?php echo $msg['status'] === 'new' ? 'status-new' : ''; ?>">
                        <div class="message-header">
                            <div class="sender-info">
                                <h3><?php echo htmlspecialchars($msg['name']); ?></h3>
                                <div class="sender-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($msg['email']); ?></span>
                                    <?php if ($msg['phone']): ?>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($msg['phone']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="far fa-clock"></i> <?php echo formatDate($msg['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="message-status">
                                <span class="status-badge status-<?php echo $msg['status']; ?>">
                                    <?php echo ucfirst($msg['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="message-body">
                            <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        </div>
                        <div class="message-footer">
                            <div class="message-actions">
                                <?php if ($msg['status'] === 'new'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-reply"></i> Reply
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.messages-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.message-item {
    padding: 1.5rem;
    border: 1px solid #eee;
    border-radius: 10px;
    background: #fff;
    transition: all 0.3s ease;
}
.message-item.status-new {
    border-left: 4px solid var(--primary-color);
    background: #fdf8f9;
}
.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.sender-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
    color: var(--dark-color);
}
.sender-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #777;
}
.sender-meta span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.message-body {
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
    color: #444;
    line-height: 1.6;
    margin-bottom: 1rem;
}
.message-footer {
    display: flex;
    justify-content: flex-end;
}
.message-actions {
    display: flex;
    gap: 0.5rem;
}
.mb-4 { margin-bottom: 1.5rem; }

.status-badge.status-new { background: var(--primary-color); color: white; }
.status-badge.status-read { background: #6c757d; color: white; }
.status-badge.status-replied { background: #28a745; color: white; }

.btn-outline-primary {
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
    background: transparent;
}
.btn-outline-primary:hover {
    background: var(--primary-color);
    color: white;
}
</style>

<?php include '../includes/admin-footer.php'; ?>
