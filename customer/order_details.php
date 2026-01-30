<?php
// customer/orders.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['error'] = "Access denied. Customer login required.";
    header("Location: ../login.php");
    exit();
}

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Get customer ID
$stmt = $db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['error'] = "Customer profile not found.";
    header("Location: ../index.php");
    exit();
}

$customer_id = $customer['customer_id'];

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if ($order_id > 0) {
        try {
            $db->beginTransaction();
            
            // Check if order can be cancelled (only pending orders)
            $stmt = $db->prepare("SELECT status FROM orders WHERE order_id = ? AND customer_id = ?");
            $stmt->execute([$order_id, $customer_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order && $order['status'] === 'Pending') {
                // Update order status
                $stmt = $db->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND customer_id = ?");
                $stmt->execute([$order_id, $customer_id]);
                
                // Restore product stock
                $stmt = $db->prepare("
                    SELECT od.product_id, od.quantity 
                    FROM order_details od 
                    WHERE od.order_id = ?
                ");
                $stmt->execute([$order_id]);
                $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($order_items as $item) {
                    $stmt = $db->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }
                
                $db->commit();
                $_SESSION['success'] = "Order cancelled successfully. Stock has been restored.";
            } else {
                $_SESSION['error'] = "Order cannot be cancelled. Only pending orders can be cancelled.";
            }
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error'] = "Error cancelling order: " . $e->getMessage();
        }
    }
    
    header("Location: orders.php");
    exit();
}

// Get orders with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of orders
$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $per_page);

// Get orders with basic details
$stmt = $db->prepare("
    SELECT 
        o.*,
        COUNT(od.order_detail_id) as item_count,
        GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') as product_names
    FROM orders o
    LEFT JOIN order_details od ON o.order_id = od.order_id
    LEFT JOIN products p ON od.product_id = p.product_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle success/error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ArtMart</title>
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
            color: #333;
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
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .order-stats {
            display: flex;
            gap: 30px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        
        .orders-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .orders-header {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .order-card {
            border-bottom: 1px solid #eee;
            padding: 25px 30px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .order-card:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .order-card:last-child {
            border-bottom: none;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-id {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .order-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 20px;
            align-items: center;
        }
        
        .order-info h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .product-list {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
            max-width: 400px;
        }
        
        .item-count {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #495057;
            text-align: center;
            min-width: 80px;
        }
        
        .order-total {
            text-align: right;
        }
        
        .total-amount {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #764ba2;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #667eea;
            color: #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #6c757d;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .success-message, .error-message {
            padding: 15px 20px;
            border-radius: 8px;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            
            .order-stats {
                justify-content: center;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .order-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">ArtMart</div>
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">My Orders</a>
                <a href="cart.php">Cart</a>
                <a href="profile.php">Profile</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <div class="order-stats">
                <?php
                // Get order statistics
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
                        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered_orders,
                        COALESCE(SUM(total_amount), 0) as total_spent
                    FROM orders 
                    WHERE customer_id = ?
                ");
                $stmt->execute([$customer_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total_orders']; ?></span>
                    <span>Total Orders</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['pending_orders']; ?></span>
                    <span>Pending</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['delivered_orders']; ?></span>
                    <span>Delivered</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">Rs. <?php echo number_format($stats['total_spent'], 0); ?></span>
                    <span>Total Spent</span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Orders List -->
        <div class="orders-container">
            <div class="orders-header">
                <?php if ($total_orders > 0): ?>
                    Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders
                <?php else: ?>
                    Your Orders
                <?php endif; ?>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <h3>No orders yet</h3>
                    <p>Start shopping to see your orders here!</p>
                    <a href="../index.php" class="btn btn-primary" style="margin-top: 20px;">
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card" onclick="location.href='order_details.php?id=<?php echo $order['order_id']; ?>'">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                                <div class="order-date"><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>
                        
                        <div class="order-details">
                            <div class="order-info">
                                <h4>Items Ordered</h4>
                                <div class="product-list">
                                    <?php 
                                    $product_names = $order['product_names'];
                                    if (strlen($product_names) > 80) {
                                        echo htmlspecialchars(substr($product_names, 0, 80)) . '...';
                                    } else {
                                        echo htmlspecialchars($product_names);
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="item-count">
                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?>
                            </div>
                            
                            <div class="order-total">
                                <div class="total-amount">Rs. <?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div class="order-actions" onclick="event.stopPropagation();">
                            <?php if ($order['status'] === 'Pending'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'Delivered'): ?>
                                <a href="../index.php" class="btn btn-primary">Buy Again</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>