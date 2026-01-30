<?php
// customer/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');

$database = new Database();
$db = $database->getConnection();

// Get customer info
$query = "SELECT * FROM customers WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: ../logout.php");
    exit();
}

// Get customer statistics
$stats = [];

// Total orders
$query = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$customer['customer_id']]);
$stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total spent
$query = "SELECT SUM(total_amount) as total FROM orders WHERE customer_id = ? AND status != 'Cancelled'";
$stmt = $db->prepare($query);
$stmt->execute([$customer['customer_id']]);
$stats['spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent orders
$query = "SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$customer['customer_id']]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - ArtMart</title>
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
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="orders.php">Order History</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div>
                    <h1 style="color: #333; margin-bottom: 0.5rem;">Welcome back, <?php echo htmlspecialchars($customer['name']); ?>!</h1>
                    <p style="color: #666; margin-bottom: 2rem;">Manage your orders and account settings</p>
                    
                    <!-- Statistics Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                        <div class="card" style="text-align: center; background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                            <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem;"><?php echo $stats['orders']; ?></h3>
                            <p>Total Orders</p>
                        </div>
                        <div class="card" style="text-align: center; background: linear-gradient(135deg, #667eea, #764ba2);">
                            <h3 style="font-size: 2rem; margin-bottom: 0.5rem; color: white;">Rs <?php echo number_format($stats['spent'], 2); ?></h3>
                            <p style="color: white;">Total Spent</p>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="card">
                        <h2 style="margin-bottom: 1.5rem; color: #333;">Recent Orders</h2>
                        
                        <?php if (empty($recent_orders)): ?>
                            <div style="text-align: center; padding: 3rem;">
                                <h3>No orders yet</h3>
                                <p style="color: #666; margin-bottom: 2rem;">Start shopping to see your orders here</p>
                                <a href="../index.php" class="btn">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                            <td>Rs <?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status <?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 1rem;">
                                <a href="orders.php" class="btn">View All Orders</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card" style="margin-top: 2rem;">
                        <h2 style="margin-bottom: 1.5rem; color: #333;">Quick Actions</h2>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="../index.php" class="btn btn-success">Browse Products</a>
                            <a href="cart.php" class="btn">View Cart</a>
                            <a href="orders.php" class="btn btn-warning">Order History</a>
                            <a href="profile.php" class="btn btn-secondary">Update Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Customer Panel</p>
        </div>
    </footer>
</body>
</html>