<?php
// config/session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the logged-in user has a specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Force login + role check - FIXED REDIRECT PATH
 */
function requireRole($role) {
    if (!isLoggedIn() || !hasRole($role)) {
        // Get current directory to build proper redirect path
        $base_path = dirname($_SERVER['SCRIPT_NAME']);
        $base_path = rtrim($base_path, '/');
        
        header("Location: " . $base_path . "/login.php");
        exit();
    }
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF token functions
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>