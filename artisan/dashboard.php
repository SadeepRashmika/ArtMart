<?php
// artisan/dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an artisan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'artisan') {
    $_SESSION['error'] = "Access denied. Artisan login required.";
    header("Location: ../login.php");
    exit();
}

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Handle AJAX requests for order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';
    
    // Validate status
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    try {
        // Get artisan ID
        $stmt = $db->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify this order contains products from this artisan
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM order_details od
            JOIN products p ON od.product_id = p.product_id
            WHERE od.order_id = ? AND p.artisan_id = ?
        ");
        $stmt->execute([$order_id, $artisan['artisan_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
            exit();
        }
        
        // Update order status
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $success = $stmt->execute([$new_status, $order_id]);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Get artisan information
$stmt = $db->prepare("SELECT * FROM artisans WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    $_SESSION['error'] = "Artisan profile not found.";
    header("Location: ../index.php");
    exit();
}

// Get artisan's products with category information
$stmt = $db->prepare("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.artisan_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$artisan['artisan_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        AVG(price) as avg_price,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products
    FROM products 
    WHERE artisan_id = ?
");
$stmt->execute([$artisan['artisan_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get orders containing artisan's products
$stmt = $db->prepare("
    SELECT DISTINCT
        o.order_id,
        o.order_date,
        o.status,
        o.total_amount,
        o.shipping_address,
        c.name as customer_name,
        c.email as customer_email,
        c.phone as customer_phone
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE p.artisan_id = ?
    ORDER BY o.order_date DESC
    LIMIT 10
");
$stmt->execute([$artisan['artisan_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order details for each order
$order_details = [];
foreach ($orders as $order) {
    $stmt = $db->prepare("
        SELECT 
            od.*,
            p.product_name,
            p.price as current_price
        FROM order_details od
        JOIN products p ON od.product_id = p.product_id
        WHERE od.order_id = ? AND p.artisan_id = ?
    ");
    $stmt->execute([$order['order_id'], $artisan['artisan_id']]);
    $order_details[$order['order_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate total revenue
$stmt = $db->prepare("
    SELECT SUM(od.subtotal) as total_revenue
    FROM order_details od
    JOIN products p ON od.product_id = p.product_id
    WHERE p.artisan_id = ?
");
$stmt->execute([$artisan['artisan_id']]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for forms
$stmt = $db->prepare("SELECT * FROM categories ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle success/error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Helper function to get the correct image path
function getImagePath($image_url) {
    if (empty($image_url)) {
        return null;
    }
    
    // If it's already a complete URL, return as is
    if (filter_var($image_url, FILTER_VALIDATE_URL)) {
        return $image_url;
    }
    
    // If it starts with uploads/, it's already relative from root
    if (strpos($image_url, 'uploads/') === 0) {
        return '../' . $image_url;
    }
    
    // If it's just a filename, assume it's in uploads/products/
    if (!strpos($image_url, '/')) {
        return '../uploads/products/' . $image_url;
    }
    
    // Otherwise, prepend ../ for relative path from artisan folder
    return '../' . ltrim($image_url, '/');
}

// Helper function to check if image file exists
function imageExists($image_path) {
    if (empty($image_path)) {
        return false;
    }
    
    // For URLs, we'll assume they exist (can't easily check remote files)
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        return true;
    }
    
    // For local files, check if they exist
    return file_exists($image_path);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Dashboard - ArtMart</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .dashboard-tabs {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab-button {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            background: #667eea;
            color: white;
        }
        
        .tab-content {
            padding: 30px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .order-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            background: white;
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-content {
            padding: 20px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover:not(:disabled) { opacity: 0.8; transform: translateY(-1px); }
        
        .product-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #667eea;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-image:hover img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .success-message, .error-message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .image-debug {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tab-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">ArtMart - Artisan Dashboard</div>
            <div class="nav-links">
                <a href="../index.php">Home</a>
                
                <a href="orders.php"> Orders</a>
                <a href="profile.php">Profile</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($artisan['name']); ?>!</h1>
            <p>Manage your products and orders from your artisan dashboard.</p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($artisan['email']); ?> | 
               <strong>Phone:</strong> <?php echo htmlspecialchars($artisan['phone']); ?> | 
               <strong>Member since:</strong> <?php echo date('M d, Y', strtotime($artisan['join_date'])); ?></p>
        </div>

        <!-- Messages -->
        <div id="message-container">
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_products'] ?: 0; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_products'] ?: 0; ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_stock'] ?: 0; ?></div>
                <div class="stat-label">Total Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rs. <?php echo number_format($revenue['total_revenue'] ?: 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Dashboard Tabs -->
        <div class="dashboard-tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('orders')">Orders Management</button>
                <button class="tab-button" onclick="showTab('products')">My Products</button>
                <button class="tab-button" onclick="showTab('analytics')">Analytics</button>
            </div>

            <!-- Orders Tab -->
            <div id="orders" class="tab-content active">
                <h2>Order Management</h2>
                <p>Manage and update the status of orders containing your products.</p>
                
                <?php if (empty($orders)): ?>
                    <div class="order-card">
                        <div class="order-content">
                            <p>No orders found for your products yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <strong>Order #<?php echo $order['order_id']; ?></strong>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </div>
                                <div>
                                    <small><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="order-content">
                                <div class="info-grid">
                                    <div>
                                        <h4>Customer Information</h4>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                    </div>
                                    <div>
                                        <h4>Shipping Information</h4>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address'] ?: 'Not provided'); ?></p>
                                        <p><strong>Total Amount:</strong> Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                                    </div>
                                </div>

                                <h4>Your Products in this Order</h4>
                                <?php if (isset($order_details[$order['order_id']])): ?>
                                    <?php foreach ($order_details[$order['order_id']] as $detail): ?>
                                        <div class="product-item">
                                            <strong><?php echo htmlspecialchars($detail['product_name']); ?></strong>
                                            <br>
                                            Quantity: <?php echo $detail['quantity']; ?> | 
                                            Unit Price: Rs. <?php echo number_format($detail['unit_price'], 2); ?> | 
                                            Subtotal: Rs. <?php echo number_format($detail['subtotal'], 2); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div style="margin-top: 20px;">
                                    <h4>Update Order Status</h4>
                                    <div class="status-buttons" data-order-id="<?php echo $order['order_id']; ?>">
                                        <button class="btn btn-primary" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Processing')" 
                                                <?php echo $order['status'] !== 'Pending' ? 'disabled' : ''; ?>>
                                            Confirm Order
                                        </button>
                                        <button class="btn btn-success" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Shipped')"
                                                <?php echo !in_array($order['status'], ['Pending', 'Processing']) ? 'disabled' : ''; ?>>
                                            Mark as Shipped
                                        </button>
                                        <button class="btn btn-warning" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Delivered')"
                                                <?php echo $order['status'] !== 'Shipped' ? 'disabled' : ''; ?>>
                                            Mark as Delivered
                                        </button>
                                        <button class="btn btn-danger" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Cancelled')"
                                                <?php echo in_array($order['status'], ['Delivered', 'Cancelled']) ? 'disabled' : ''; ?>>
                                            Cancel Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Products Tab -->
            <div id="products" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>My Products</h2>
                    <a href="add_product.php" class="btn btn-primary">Add New Product</a>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="product-card">
                        <div class="product-info">
                            <p>You haven't added any products yet. <a href="add_product.php">Add your first product!</a></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php 
                                    $image_path = getImagePath($product['image_url']);
                                    if ($image_path && imageExists($image_path)): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             onerror="this.parentElement.innerHTML='<span>Image not found</span><div class=\'image-debug\'>Path: <?php echo htmlspecialchars($image_path); ?></div>';">
                                    <?php else: ?>
                                        <span>No Image Available</span>
                                        <?php if ($product['image_url']): ?>
                                            <div class="image-debug">
                                                Stored: <?php echo htmlspecialchars($product['image_url']); ?><br>
                                                Resolved: <?php echo htmlspecialchars($image_path ?: 'null'); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
                                    <p><strong>Price:</strong> Rs. <?php echo number_format($product['price'], 2); ?></p>
                                    <p><strong>Stock:</strong> <?php echo $product['stock']; ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-badge status-<?php echo strtolower($product['status']); ?>">
                                            <?php echo $product['status']; ?>
                                        </span>
                                    </p>
                                    <p><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                                    <div style="margin-top: 15px;">
                                        <a href="update_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-warning">Edit</a>
                                        <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>)">Delete</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics" class="tab-content">
                <h2>Analytics & Insights</h2>
                <div class="info-grid">
                    <div>
                        <h4>Product Performance</h4>
                        <p><strong>Average Product Price:</strong> Rs. <?php echo number_format($stats['avg_price'] ?: 0, 2); ?></p>
                        <p><strong>Total Products:</strong> <?php echo $stats['total_products'] ?: 0; ?></p>
                        <p><strong>Active Products:</strong> <?php echo $stats['active_products'] ?: 0; ?></p>
                        <p><strong>Total Stock Value:</strong> Rs. <?php echo number_format(($stats['total_stock'] ?: 0) * ($stats['avg_price'] ?: 0), 2); ?></p>
                    </div>
                    <div>
                        <h4>Sales Summary</h4>
                        <p><strong>Total Revenue:</strong> Rs. <?php echo number_format($revenue['total_revenue'] ?: 0, 2); ?></p>
                        <p><strong>Total Orders:</strong> <?php echo count($orders); ?></p>
                        <p><strong>Pending Orders:</strong> <?php echo count(array_filter($orders, function($o) { return $o['status'] === 'Pending'; })); ?></p>
                        <p><strong>Completed Orders:</strong> <?php echo count(array_filter($orders, function($o) { return $o['status'] === 'Delivered'; })); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function updateOrderStatus(orderId, status) {
            if (!confirm(`Are you sure you want to change the order status to "${status}"?`)) {
                return;
            }

            // Show loading state
            const buttons = document.querySelector(`[data-order-id="${orderId}"]`);
            const originalContent = buttons.innerHTML;
            buttons.innerHTML = '<p style="color: #6c757d;">Updating...</p>';

            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_order_status&order_id=${orderId}&status=${encodeURIComponent(status)}`
            })
            .then(response => response.json())
            .then(data => {
                const messageContainer = document.getElementById('message-container');
                
                if (data.success) {
                    messageContainer.innerHTML = `<div class="success-message">${data.message}</div>`;
                    // Reload page to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    messageContainer.innerHTML = `<div class="error-message">${data.message}</div>`;
                    buttons.innerHTML = originalContent;
                }
                
                // Scroll to message
                messageContainer.scrollIntoView({ behavior: 'smooth' });
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message-container').innerHTML = 
                    '<div class="error-message">An error occurred while updating the order status.</div>';
                buttons.innerHTML = originalContent;
            });
        }

        function deleteProduct(productId) {
            if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                return;
            }
            
            fetch('delete_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                const messageContainer = document.getElementById('message-container');
                
                if (data.success) {
                    messageContainer.innerHTML = `<div class="success-message">${data.message}</div>`;
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    messageContainer.innerHTML = `<div class="error-message">${data.message}</div>`;
                }
                
                messageContainer.scrollIntoView({ behavior: 'smooth' });
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message-container').innerHTML = 
                    '<div class="error-message">An error occurred while deleting the product.</div>';
            });
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                if (!msg.closest('#message-container')) return;
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>