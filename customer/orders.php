<?php
// customer/orders.php - Customer Order History
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');

$database = new Database();
$db = $database->getConnection();

// Get customer info
$query = "SELECT customer_id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get customer orders with order details
$query = "SELECT o.*, 
          COUNT(od.order_detail_id) as item_count,
          GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') as product_names
          FROM orders o 
          LEFT JOIN order_details od ON o.order_id = od.order_id
          LEFT JOIN products p ON od.product_id = p.product_id
          WHERE o.customer_id = ? 
          GROUP BY o.order_id
          ORDER BY o.order_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$customer['customer_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">🎨 ArtMart</a>
                <nav>
                    <ul>
                        <li><a href="../index.php">Shop</a></li>
                        <li><a href="cart.php">Cart</a></li>
                        <li><a href="dashboard.php">My Account</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="dashboard">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3 style="margin-bottom: 1rem; color: #333;">My Account</h3>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="orders.php" class="active">Order History</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div>
                    <h1 style="color: #333; margin-bottom: 2rem;">Order History</h1>
                    
                    <?php if (empty($orders)): ?>
                        <div class="card" style="text-align: center; padding: 3rem;">
                            <h3>No orders found</h3>
                            <p style="color: #666; margin-bottom: 2rem;">You haven't placed any orders yet</p>
                            <a href="../index.php" class="btn">Start Shopping</a>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <h2 style="margin-bottom: 1.5rem;">All Orders (<?php echo count($orders); ?>)</h2>
                            
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Products</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                            <td><?php echo date('M j, Y H:i', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo $order['item_count']; ?> items</td>
                                            <td>
                                                <small style="color: #666;">
                                                    <?php 
                                                    $products = $order['product_names'];
                                                    if (strlen($products) > 50) {
                                                        echo substr($products, 0, 50) . '...';
                                                    } else {
                                                        echo $products;
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td><strong>Rs <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status <?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" 
                                                   class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Order History</p>
        </div>
    </footer>
</body>
</html>

<?php
// customer/order_details.php - Detailed Order View
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');

$order_id = intval($_GET['id'] ?? 0);

$database = new Database();
$db = $database->getConnection();

// Get customer info
$customer_query = "SELECT customer_id FROM customers WHERE user_id = ?";
$customer_stmt = $db->prepare($customer_query);
$customer_stmt->execute([$_SESSION['user_id']]);
$customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);

// Get order details
$order_query = "SELECT o.* FROM orders o WHERE o.order_id = ? AND o.customer_id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id, $customer['customer_id']]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT od.*, p.product_name, p.image_url, a.name as artisan_name
                FROM order_details od
                JOIN products p ON od.product_id = p.product_id
                JOIN artisans a ON p.artisan_id = a.artisan_id
                WHERE od.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">🎨 ArtMart</a>
                <nav>
                    <ul>
                        <li><a href="../index.php">Shop</a></li>
                        <li><a href="orders.php">Orders</a></li>
                        <li><a href="dashboard.php">My Account</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div style="margin-bottom: 2rem;">
                <a href="orders.php" style="color: #667eea; text-decoration: none;">← Back to Orders</a>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Order Items -->
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; color: #333;">Order Items</h2>
                    
                    <?php foreach ($order_items as $item): ?>
                        <div style="display: flex; align-items: center; padding: 1rem 0; border-bottom: 1px solid #e9ecef;">
                            <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    🎨
                                <?php endif; ?>
                            </div>
                            
                            <div style="flex: 1;">
                                <h4 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    by <?php echo htmlspecialchars($item['artisan_name']); ?>
                                </p>
                                <p style="color: #667eea; font-size: 0.9rem;">
                                    $<?php echo number_format($item['unit_price'], 2); ?> × <?php echo $item['quantity']; ?>
                                </p>
                            </div>
                            
                            <div style="text-align: right;">
                                <strong style="font-size: 1.1rem;">$<?php echo number_format($item['subtotal'], 2); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Order Summary -->
                <div>
                    <div class="card">
                        <h3 style="margin-bottom: 1rem; color: #333;">Order Summary</h3>
                        
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem;">
                                <div><strong>Order ID:</strong></div>
                                <div>#<?php echo $order['order_id']; ?></div>
                                
                                <div><strong>Date:</strong></div>
                                <div><?php echo date('M j, Y H:i', strtotime($order['order_date'])); ?></div>
                                
                                <div><strong>Status:</strong></div>
                                <div>
                                    <span class="status <?php echo strtolower($order['status']); ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </div>
                                
                                <div><strong>Total:</strong></div>
                                <div><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: 0.5rem; color: #333;">Shipping Address</h4>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; font-size: 0.9rem; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($order['status'] === 'Pending'): ?>
                            <div style="margin-top: 1.5rem; padding: 1rem; background: #fff3cd; border-radius: 8px; color: #856404;">
                                <strong>⏳ Order Processing</strong><br>
                                <small>Your order is being prepared. We'll notify you once it ships.</small>
                            </div>
                        <?php elseif ($order['status'] === 'Shipped'): ?>
                            <div style="margin-top: 1.5rem; padding: 1rem; background: #d4edda; border-radius: 8px; color: #155724;">
                                <strong>🚚 Order Shipped</strong><br>
                                <small>Your order is on its way! Expected delivery in 3-5 business days.</small>
                            </div>
                        <?php elseif ($order['status'] === 'Delivered'): ?>
                            <div style="margin-top: 1.5rem; padding: 1rem; background: #d1ecf1; border-radius: 8px; color: #0c5460;">
                                <strong>✅ Order Delivered</strong><br>
                                <small>Thank you for shopping with ArtMart!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Order Details</p>
        </div>
    </footer>
</body>
</html>