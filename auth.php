<?php
// includes/auth.php

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function loginAdmin($email, $password) {
        $query = "SELECT * FROM admins WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['user_type'] = 'admin';
                return true;
            }
        }
        return false;
    }
    
    public function loginRestaurant($email, $password) {
        $query = "SELECT * FROM restaurants WHERE email = :email AND status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $restaurant = $stmt->fetch();
            if (password_verify($password, $restaurant['password'])) {
                $_SESSION['restaurant_id'] = $restaurant['id'];
                $_SESSION['restaurant_name'] = $restaurant['name'];
                $_SESSION['restaurant_email'] = $restaurant['email'];
                $_SESSION['user_type'] = 'restaurant';
                return true;
            }
        }
        return false;
    }
    
    public function isAdminLoggedIn() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    public function isRestaurantLoggedIn() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'restaurant';
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function getRestaurantId() {
        return $_SESSION['restaurant_id'] ?? null;
    }
    
    public function getAdminId() {
        return $_SESSION['admin_id'] ?? null;
    }
}