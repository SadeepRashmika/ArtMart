<?php
// artisan/delete_product.php - Handle product deletion
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure only artisans can access this
requireRole('artisan');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get product ID from POST data
$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get artisan ID for current user
    $stmt = $db->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$artisan) {
        echo json_encode(['success' => false, 'message' => 'Artisan profile not found']);
        exit();
    }
    
    // Check if the product belongs to this artisan
    $stmt = $db->prepare("SELECT product_id, product_name FROM products WHERE product_id = ? AND artisan_id = ?");
    $stmt->execute([$product_id, $artisan['artisan_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
        exit();
    }
    
    // Check if product is in any pending orders
    $stmt = $db->prepare("
        SELECT COUNT(*) as order_count 
        FROM order_details od 
        JOIN orders o ON od.order_id = o.order_id 
        WHERE od.product_id = ? AND o.status IN ('Pending', 'Processing')
    ");
    $stmt->execute([$product_id]);
    $order_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order_check['order_count'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete product. It is part of pending or processing orders.'
        ]);
        exit();
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // First, remove from any cart items
        $stmt = $db->prepare("DELETE FROM cart_items WHERE product_id = ?");
        $stmt->execute([$product_id]);
        
        // Remove from any completed order details (optional - you might want to keep this for records)
        // $stmt = $db->prepare("DELETE FROM order_details WHERE product_id = ?");
        // $stmt->execute([$product_id]);
        
        // Finally, delete the product
        $stmt = $db->prepare("DELETE FROM products WHERE product_id = ? AND artisan_id = ?");
        $stmt->execute([$product_id, $artisan['artisan_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Product '{$product['product_name']}' has been deleted successfully"
            ]);
        } else {
            // Rollback transaction
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>