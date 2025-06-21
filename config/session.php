<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'nama' => $_SESSION['user_nama'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function logout() { 
    session_destroy();
    header('Location: login.php');
    exit();
}

function isAdmin() {
    $current_user = getCurrentUser();
    return $current_user && isset($current_user['role']) && $current_user['role'] === 'admin';
}

function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    if (!isAdmin()) {
        header('Location: admin/admin_dashboard.php');
        exit();
    }
}
?>
