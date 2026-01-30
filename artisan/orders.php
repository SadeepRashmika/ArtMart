<?php
// artisan/orders.php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in as artisan
requireRole('artisan');

$database = new Database();
$db = $database->getConnection();

// Get artisan information
$stmt = $db->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    $_SESSION['error'] = "Artisan profile not found.";
    header("Location: ../index.php");
    exit();
}

$artisan_id = $artisan['artisan_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build orders query for this artisan's products
$query = "
    SELECT o.order_id, o.order_date, o.status, o.total_amount, o.shipping_address,
           c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
           p.product_name, p.price as product_price,
           od.quantity, od.unit_price, od.subtotal
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE p.artisan_id = ?
";

$params = [$artisan_id];

if (!empty($status_filter)) {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_orders = count($orders);
$total_revenue = array_sum(array_column($orders, 'subtotal'));
$pending_orders = count(array_filter($orders, function($o) { return $o['status'] === 'Pending'; }));
$completed_orders = count(array_filter($orders, function($o) { return $o['status'] === 'Delivered'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Artisan Dashboard</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .filters {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .orders-table th, .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .orders-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-processing { color: #17a2b8; font-weight: bold; }
        .status-shipped { color: #fd7e14; font-weight: bold; }
        .status-delivered { color: #28a745; font-weight: bold; }
        .status-cancelled { color: #dc3545; font-weight: bold; }
        .order-group {
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">🎨 ArtMart</a>
                <nav>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="orders.php">Orders</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>📦 My Product Orders</h1>
            <p>Orders containing your products</p>

            <!-- Order Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div>Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">Rs <?php echo number_format($total_revenue, 2); ?></div>
                    <div>Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_orders; ?></div>
                    <div>Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completed_orders; ?></div>
                    <div>Completed Orders</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <h3>Filter Orders</h3>
                <form method="GET" class="filter-row">
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date:</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>To Date:</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Filter</button>
                        <a href="orders.php" class="btn" style="background: #6c757d; margin-left: 0.5rem;">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <?php if (empty($orders)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3>No orders found</h3>
                    <p>You haven't received any orders for your products yet, or no orders match your filters.</p>
                    <a href="dashboard.php" class="btn">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Orders (<?php echo count($orders); ?> found)</h2>
                    
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                                <th>Order Status</th>
                                <th>Customer Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td>Rs <?php echo number_format($order['unit_price'], 2); ?></td>
                                    <td>Rs <?php echo number_format($order['subtotal'], 2); ?></td>
                                    <td>
                                        <span class="status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            📧 <?php echo htmlspecialchars($order['customer_email']); ?><br>
                                            📱 <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Export Options -->
                <div style="margin-top: 1rem; text-align: center;">
                    <p><strong>Tip:</strong> You can contact customers directly using their email or phone to coordinate delivery and payment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Handmade Marketplace | Connecting Artists with Art Lovers</p>
        </div>
    </footer>
</body>
</html>