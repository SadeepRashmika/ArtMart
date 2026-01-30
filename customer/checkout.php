<?php
// customer/checkout.php
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

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

$cart_items = [];
$total = 0;
$error = "";
$success = "";

// Get cart items details
$product_ids = implode(',', array_keys($_SESSION['cart']));
$query = "SELECT p.*, a.name as artisan_name 
          FROM products p 
          JOIN artisans a ON p.artisan_id = a.artisan_id 
          WHERE p.product_id IN ($product_ids) AND p.status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Validate stock and calculate total
$stock_errors = [];
foreach ($products as $product) {
    $quantity = $_SESSION['cart'][$product['product_id']];
    
    if ($quantity > $product['stock']) {
        $stock_errors[] = $product['product_name'] . " (only " . $product['stock'] . " available)";
    }
    
    $subtotal = $product['price'] * $quantity;
    $total += $subtotal;
    
    $cart_items[] = [
        'product' => $product,
        'quantity' => $quantity,
        'subtotal' => $subtotal
    ];
}

if (!empty($stock_errors)) {
    $error = "Stock shortage for: " . implode(', ', $stock_errors);
}

// Process order
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($stock_errors)) {
    $shipping_address = sanitize($_POST['shipping_address']);
    
    if (empty($shipping_address)) {
        $error = "Please provide a shipping address";
    } else {
        try {
            $db->beginTransaction();
            
            // Create order
            $order_query = "INSERT INTO orders (customer_id, total_amount, shipping_address, status) 
                           VALUES (?, ?, ?, 'Pending')";
            $order_stmt = $db->prepare($order_query);
            $order_stmt->execute([$customer['customer_id'], $total, $shipping_address]);
            $order_id = $db->lastInsertId();
            
            // Add order details and update stock
            foreach ($cart_items as $item) {
                // Insert order detail
                $detail_query = "INSERT INTO order_details (order_id, product_id, quantity, unit_price, subtotal) 
                                VALUES (?, ?, ?, ?, ?)";
                $detail_stmt = $db->prepare($detail_query);
                $detail_stmt->execute([
                    $order_id, 
                    $item['product']['product_id'], 
                    $item['quantity'], 
                    $item['product']['price'], 
                    $item['subtotal']
                ]);
                
                // Update product stock
                $stock_query = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
                $stock_stmt = $db->prepare($stock_query);
                $stock_stmt->execute([$item['quantity'], $item['product']['product_id']]);
            }
            
            $db->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Redirect to success page
            header("Location: order_success.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error processing order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ArtMart</title>
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
            <h1 style="color: #333; margin-bottom: 2rem;">Checkout</h1>
            
            <?php if ($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Order Details -->
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; color: #333;">Order Summary</h2>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #e9ecef;">
                            <div>
                                <h4 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($item['product']['product_name']); ?></h4>
                                <p style="color: #666; font-size: 0.9rem;">
                                    by <?php echo htmlspecialchars($item['product']['artisan_name']); ?> × <?php echo $item['quantity']; ?>
                                </p>
                                <p style="color: #667eea; font-size: 0.9rem;">$<?php echo number_format($item['product']['price'], 2); ?> each</p>
                            </div>
                            <div style="text-align: right;">
                                <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #667eea;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem;">
                            <span>Total:</span>
                            <span>$<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout Form -->
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem; color: #333;">Shipping Information</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="customer_name">Customer Name</label>
                            <input type="text" id="customer_name" value="<?php echo htmlspecialchars($customer['name']); ?>" readonly style="background: #f8f9fa;">
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_email">Email</label>
                            <input type="email" id="customer_email" value="<?php echo htmlspecialchars($customer['email']); ?>" readonly style="background: #f8f9fa;">
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_phone">Phone</label>
                            <input type="tel" id="customer_phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" readonly style="background: #f8f9fa;">
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address *</label>
                            <textarea id="shipping_address" name="shipping_address" rows="4" required 
                                      placeholder="Enter your complete shipping address..."><?php echo htmlspecialchars($customer['address']); ?></textarea>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <h4 style="color: #333; margin-bottom: 0.5rem;">Payment Method</h4>
                            <p style="color: #666; font-size: 0.9rem;">💳 Cash on Delivery (COD)</p>
                            <p style="color: #666; font-size: 0.8rem;">Pay when you receive your order. No advance payment required.</p>
                        </div>
                        
                        <?php if (empty($stock_errors)): ?>
                            <button type="submit" class="btn btn-success" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                                Place Order
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn" style="width: 100%; background: #6c757d; cursor: not-allowed;" disabled>
                                Cannot Place Order (Stock Issues)
                            </button>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="cart.php" style="color: #667eea; text-decoration: none;">← Back to Cart</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Secure Checkout</p>
        </div>
    </footer>
</body>
</html>

<?php
// customer/order_success.php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');

$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT o.*, c.name as customer_name 
          FROM orders o 
          JOIN customers c ON o.customer_id = c.customer_id 
          WHERE o.order_id = ? AND c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Get order items
$items_query = "SELECT od.*, p.product_name, a.name as artisan_name
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
    <title>Order Success - ArtMart</title>
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
                        <li><a href="dashboard.php">My Account</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div style="text-align: center; margin: 3rem 0;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                <h1 style="color: #28a745; margin-bottom: 1rem;">Order Placed Successfully!</h1>
                <p style="color: #666; font-size: 1.1rem;">Thank you for your order. We'll process it shortly.</p>
            </div>
            
            <div style="max-width: 600px; margin: 0 auto;">
                <div class="card">
                    <h2 style="color: #333; margin-bottom: 1.5rem;">Order Details</h2>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <strong>Order ID:</strong> #<?php echo $order['order_id']; ?>
                            </div>
                            <div>
                                <strong>Date:</strong> <?php echo date('M j, Y H:i', strtotime($order['order_date'])); ?>
                            </div>
                            <div>
                                <strong>Status:</strong> <span class="status pending"><?php echo $order['status']; ?></span>
                            </div>
                            <div>
                                <strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <h3 style="margin-bottom: 1rem;">Items Ordered</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef;">
                            <div>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                <small style="color: #666;">by <?php echo htmlspecialchars($item['artisan_name']); ?> × <?php echo $item['quantity']; ?></small>
                            </div>
                            <div style="text-align: right;">
                                <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 2rem; text-align: center;">
                        <a href="orders.php" class="btn">View Order History</a>
                        <a href="../index.php" class="btn btn-secondary" style="margin-left: 1rem;">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Order Confirmation</p>
        </div>
    </footer>
</body>
</html>