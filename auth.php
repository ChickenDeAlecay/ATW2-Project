<?php
require_once '_connect.php';

session_start();

class Auth {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    public function register($email, $display_name, $password) {
        // Validate input
        if (empty($email) || empty($display_name) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password and insert user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $display_name, $password_hash);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful'];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, email, display_name, password_hash, is_admin FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Create session record in database
            $session_id = session_id();
            $expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
            
            $stmt = $this->db->prepare("INSERT INTO user_sessions (id, user_id, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)");
            $stmt->bind_param("sis", $session_id, $user['id'], $expires_at);
            $stmt->execute();
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
        } else {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }
    
    public function logout() {
        // Remove session from database
        if (isset($_SESSION['user_id'])) {
            $session_id = session_id();
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
            $stmt->bind_param("s", $session_id);
            $stmt->execute();
        }
        
        // Clear PHP session
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['email'],
                'display_name' => $_SESSION['display_name'],
                'is_admin' => $_SESSION['is_admin']
            ];
        }
        return null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: index.php?error=access_denied');
            exit;
        }
    }
}

// Create global auth instance
$auth = new Auth($connect);
?>