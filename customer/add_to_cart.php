<?php
// customer/add_to_cart.php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in as customer
if (!isLoggedIn() || !hasRole('customer')) {
    $_SESSION['error'] = "Please log in as a customer to add items to cart.";
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: ../index.php");
        exit();
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($product_id <= 0 || $quantity <= 0) {
        $_SESSION['error'] = "Invalid product or quantity.";
        header("Location: ../index.php");
        exit();
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get customer_id from customers table using user_id
        $stmt = $db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            $_SESSION['error'] = "Customer profile not found. Please contact support.";
            header("Location: ../index.php");
            exit();
        }
        
        $customer_id = $customer['customer_id'];
        
        // Check if product exists and has enough stock
        $stmt = $db->prepare("
            SELECT p.product_id, p.product_name, p.stock, p.status, a.status as artisan_status 
            FROM products p 
            JOIN artisans a ON p.artisan_id = a.artisan_id 
            WHERE p.product_id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $_SESSION['error'] = "Product not found.";
            header("Location: ../index.php");
            exit();
        }
        
        if ($product['status'] !== 'active' || $product['artisan_status'] !== 'active') {
            $_SESSION['error'] = "This product is no longer available.";
            header("Location: ../index.php");
            exit();
        }
        
        if ($product['stock'] < $quantity) {
            $_SESSION['error'] = "Not enough stock available. Only " . $product['stock'] . " items left.";
            header("Location: ../index.php");
            exit();
        }
        
        // Create cart_items table if it doesn't exist
        $create_cart_table = "
            CREATE TABLE IF NOT EXISTS cart_items (
                cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_cart_item (customer_id, product_id),
                FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
            )
        ";
        $db->exec($create_cart_table);
        
        // Check if item already exists in cart
        $stmt = $db->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$customer_id, $product_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_item) {
            // Update existing cart item
            $new_quantity = $existing_item['quantity'] + $quantity;
            
            if ($new_quantity > $product['stock']) {
                $_SESSION['error'] = "Cannot add more items. Total would exceed available stock.";
                header("Location: ../index.php");
                exit();
            }
            
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $stmt->execute([$new_quantity, $existing_item['cart_item_id']]);
            
            $_SESSION['success'] = "Updated cart: " . htmlspecialchars($product['product_name']) . " (Total: $new_quantity)";
        } else {
            // Add new cart item
            $stmt = $db->prepare("INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$customer_id, $product_id, $quantity]);
            
            $_SESSION['success'] = "Added to cart: " . htmlspecialchars($product['product_name']) . " x$quantity";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding to cart. Please try again.";
        error_log("Add to cart error: " . $e->getMessage());
    }
    
} else {
    $_SESSION['error'] = "Invalid request method.";
}

// Redirect back to main page
header("Location: ../index.php");
exit();
?>