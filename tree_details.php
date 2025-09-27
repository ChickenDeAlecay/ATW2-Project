<?php
require_once 'auth.php';
require_once 'reviews.php';

header('Content-Type: application/json');

if (!isset($_GET['tree_id'])) {
    echo json_encode(['error' => 'Tree ID required']);
    exit;
}

$tree_id = $_GET['tree_id'];
$user = $auth->getCurrentUser();

// Get tree reviews
$reviews = $reviewSystem->getTreeReviews($tree_id);

// Get average rating
$average_rating = $reviewSystem->getTreeAverageRating($tree_id);

// Get approved images
$images = $reviewSystem->getTreeImages($tree_id, true);

// Get user's review if logged in
$user_review = null;
if ($user) {
    $user_review = $reviewSystem->getUserReview($user['id'], $tree_id);
}

$response = [
    'tree_id' => $tree_id,
    'reviews' => $reviews,
    'average_rating' => $average_rating,
    'images' => $images,
    'user_review' => $user_review
];

echo json_encode($response);
?>