<?php
require_once '_connect.php';

// Simple analytics for tree views
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tree_id'])) {
    $tree_id = $_POST['tree_id'];
    
    // Create analytics table if it doesn't exist
    $create_table = "
        CREATE TABLE IF NOT EXISTS tree_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tree_id VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tree_action (tree_id, action),
            INDEX idx_created_at (created_at)
        )
    ";
    
    mysqli_query($connect, $create_table);
    
    // Log the view
    $stmt = $connect->prepare("INSERT INTO tree_analytics (tree_id, action) VALUES (?, 'view')");
    $stmt->bind_param("s", $tree_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>