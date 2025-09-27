<?php
require_once 'auth.php';

class ReviewSystem {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    public function addReview($user_id, $tree_id, $rating, $comment = '') {
        // Validate rating
        if ($rating < 0 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 0 and 5'];
        }
        
        // Check if user already reviewed this tree
        $stmt = $this->db->prepare("SELECT id FROM tree_reviews WHERE user_id = ? AND tree_id = ?");
        $stmt->bind_param("is", $user_id, $tree_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing review
            $stmt = $this->db->prepare("UPDATE tree_reviews SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND tree_id = ?");
            $stmt->bind_param("isis", $rating, $comment, $user_id, $tree_id);
        } else {
            // Insert new review
            $stmt = $this->db->prepare("INSERT INTO tree_reviews (user_id, tree_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $user_id, $tree_id, $rating, $comment);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review saved successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to save review'];
        }
    }
    
    public function getTreeReviews($tree_id) {
        $stmt = $this->db->prepare("
            SELECT tr.*, u.display_name 
            FROM tree_reviews tr 
            JOIN users u ON tr.user_id = u.id 
            WHERE tr.tree_id = ? 
            ORDER BY tr.created_at DESC
        ");
        $stmt->bind_param("s", $tree_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getUserReview($user_id, $tree_id) {
        $stmt = $this->db->prepare("SELECT * FROM tree_reviews WHERE user_id = ? AND tree_id = ?");
        $stmt->bind_param("is", $user_id, $tree_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function getTreeAverageRating($tree_id) {
        $stmt = $this->db->prepare("
            SELECT 
                AVG(rating) as avg_rating, 
                COUNT(*) as review_count 
            FROM tree_reviews 
            WHERE tree_id = ?
        ");
        $stmt->bind_param("s", $tree_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function uploadTreeImage($user_id, $tree_id, $file) {
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/trees/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed'];
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['success' => false, 'message' => 'File size must be less than 5MB'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('tree_' . $tree_id . '_') . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save to database
            $stmt = $this->db->prepare("
                INSERT INTO tree_images (user_id, tree_id, filename, original_filename, file_size, mime_type) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssis", $user_id, $tree_id, $filename, $file['name'], $file['size'], $file['type']);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Image uploaded successfully. It will be reviewed by an admin before appearing on the site.'];
            } else {
                unlink($filepath); // Remove file if DB insert failed
                return ['success' => false, 'message' => 'Failed to save image information'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    }
    
    public function getTreeImages($tree_id, $approved_only = true) {
        $sql = "
            SELECT ti.*, u.display_name 
            FROM tree_images ti 
            JOIN users u ON ti.user_id = u.id 
            WHERE ti.tree_id = ?
        ";
        
        if ($approved_only) {
            $sql .= " AND ti.is_approved = 1";
        }
        
        $sql .= " ORDER BY ti.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $tree_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getPendingImages() {
        $stmt = $this->db->prepare("
            SELECT ti.*, u.display_name 
            FROM tree_images ti 
            JOIN users u ON ti.user_id = u.id 
            WHERE ti.is_approved = 0 
            ORDER BY ti.created_at ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function approveImage($image_id, $admin_id) {
        $stmt = $this->db->prepare("
            UPDATE tree_images 
            SET is_approved = 1, approved_by = ?, approved_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $admin_id, $image_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Image approved successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to approve image'];
        }
    }
    
    public function rejectImage($image_id) {
        // Get image info first
        $stmt = $this->db->prepare("SELECT filename FROM tree_images WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $image = $result->fetch_assoc();
        
        if ($image) {
            // Delete file
            $filepath = 'uploads/trees/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM tree_images WHERE id = ?");
            $stmt->bind_param("i", $image_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Image rejected and deleted'];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to reject image'];
    }
}

// Create global review system instance
$reviewSystem = new ReviewSystem($connect);
?>