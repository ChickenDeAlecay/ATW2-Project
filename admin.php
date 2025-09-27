<?php
require_once 'auth.php';
require_once 'reviews.php';

// Require admin access
$auth->requireAdmin();

$message = '';
$message_type = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $current_user = $auth->getCurrentUser();
        
        if ($_POST['action'] === 'approve' && isset($_POST['image_id'])) {
            $result = $reviewSystem->approveImage($_POST['image_id'], $current_user['id']);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        } elseif ($_POST['action'] === 'reject' && isset($_POST['image_id'])) {
            $result = $reviewSystem->rejectImage($_POST['image_id']);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
    }
}

// Get pending images
$pending_images = $reviewSystem->getPendingImages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bristol Trees - Admin Portal</title>
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Portal</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['display_name']); ?>!</p>
        </div>
        
        <div class="admin-nav">
            <a href="index.php">← Back to Map</a>
            <a href="?view=images">Pending Images</a>
            <a href="logout.php">Logout</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($pending_images); ?></div>
                <div>Pending Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $stmt = $connect->prepare("SELECT COUNT(*) as count FROM tree_images WHERE is_approved = 1");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    echo $result->fetch_assoc()['count'];
                    ?>
                </div>
                <div>Approved Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $stmt = $connect->prepare("SELECT COUNT(*) as count FROM tree_reviews");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    echo $result->fetch_assoc()['count'];
                    ?>
                </div>
                <div>Total Reviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $stmt = $connect->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 0");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    echo $result->fetch_assoc()['count'];
                    ?>
                </div>
                <div>Registered Users</div>
            </div>
        </div>
        
        <h2>Pending Image Approvals</h2>
        
        <?php if (empty($pending_images)): ?>
            <div class="empty-state">
                <h3>No pending images</h3>
                <p>All uploaded images have been reviewed.</p>
            </div>
        <?php else: ?>
            <div class="pending-images">
                <?php foreach ($pending_images as $image): ?>
                    <div class="image-card">
                        <img src="uploads/trees/<?php echo htmlspecialchars($image['filename']); ?>" 
                             alt="Tree image" class="image-preview">
                        
                        <div class="image-info">
                            <h4>Tree ID: <?php echo htmlspecialchars($image['tree_id']); ?></h4>
                            <div class="image-meta">
                                <strong>Uploaded by:</strong> <?php echo htmlspecialchars($image['display_name']); ?>
                            </div>
                            <div class="image-meta">
                                <strong>Upload date:</strong> <?php echo date('M j, Y g:i A', strtotime($image['created_at'])); ?>
                            </div>
                            <div class="image-meta">
                                <strong>File size:</strong> <?php echo round($image['file_size'] / 1024, 1); ?> KB
                            </div>
                            <div class="image-meta">
                                <strong>Original name:</strong> <?php echo htmlspecialchars($image['original_filename']); ?>
                            </div>
                        </div>
                        
                        <div class="image-actions">
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <button type="submit" class="btn admin-btn btn-approve" 
                                        onclick="return confirm('Approve this image?')">
                                    Approve
                                </button>
                            </form>
                            
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <button type="submit" class="btn admin-btn btn-reject" 
                                        onclick="return confirm('Reject and delete this image? This cannot be undone.')">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>