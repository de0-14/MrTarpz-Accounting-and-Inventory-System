<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'check_username') {
        $username = sanitize($_POST['username']);
        
        $sql = "SELECT user_id FROM users WHERE username = '$username'";
        $result = $conn->query($sql);
        
        echo json_encode(['available' => $result->num_rows == 0]);
        exit;
    }
    
    if ($_POST['action'] == 'register') {
        $full_name = sanitize($_POST['full_name']);
        $username = sanitize($_POST['username']);
        $email = !empty($_POST['email']) ? sanitize($_POST['email']) : 'NULL';
        $password = $_POST['password']; // In production, use password_hash()
        $role = sanitize($_POST['role']);
        
        // Validate input
        if (empty($full_name) || empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
            exit;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }
        
        // Check if username already exists
        $check_sql = "SELECT user_id FROM users WHERE username = '$username'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        // Check if email already exists (if provided)
        if ($email != 'NULL') {
            $check_email_sql = "SELECT user_id FROM users WHERE email = '$email'";
            $check_email_result = $conn->query($check_email_sql);
            
            if ($check_email_result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already registered']);
                exit;
            }
        }
        
        // Insert new user
        $sql = "INSERT INTO users (full_name, username, email, password, role) 
                VALUES ('$full_name', '$username', $email, '$password', '$role')";
        
        if ($conn->query($sql)) {
            // Log the registration
            $user_id = $conn->insert_id;
            error_log("New user registered: $username (ID: $user_id)");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Account created successfully',
                'user_id' => $user_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
}

// If no valid action, redirect to login
header('Location: index.php');
exit;
?>