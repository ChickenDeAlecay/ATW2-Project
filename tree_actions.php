<?php
require_once 'auth.php';
require_once 'reviews.php';

header('Content-Type: application/json');

// Require login for all actions
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_review':
        if (!isset($_POST['tree_id']) || !isset($_POST['rating'])) {
            echo json_encode(['success' => false, 'message' => 'Tree ID and rating required']);
            exit;
        }
        
        $tree_id = $_POST['tree_id'];
        $rating = (int)$_POST['rating'];
        $comment = $_POST['comment'] ?? '';
        
        $result = $reviewSystem->addReview($user['id'], $tree_id, $rating, $comment);
        echo json_encode($result);
        break;
        
    case 'upload_image':
        if (!isset($_POST['tree_id']) || !isset($_FILES['image'])) {
            echo json_encode(['success' => false, 'message' => 'Tree ID and image file required']);
            exit;
        }
        
        $tree_id = $_POST['tree_id'];
        $file = $_FILES['image'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            exit;
        }
        
        $result = $reviewSystem->uploadTreeImage($user['id'], $tree_id, $file);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>