<?php
// customer/order_confirmation.php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in as customer
requireRole('customer');

// Check if order success data exists
if (!isset($_SESSION['order_success'])) {
    $_SESSION['error'] = "No order information found.";
    header("Location: cart.php");
    exit();
}

$order_data = $_SESSION['order_success'];
unset($_SESSION['order_success']); // Clear the session data

$database = new Database();
$db = $database->getConnection();

// Group items by artisan for better display
$artisans_orders = [];
foreach ($order_data['items'] as $item) {
    if (!isset($artisans_orders[$item['artisan_id']])) {
        $artisans_orders[$item['artisan_id']] = [
            'name' => $item['artisan_name'],
            'email' => $item['artisan_email'],
            'items' => [],
            'subtotal' => 0
        ];
    }
    $artisans_orders[$item['artisan_id']]['items'][] = $item;
    $artisans_orders[$item['artisan_id']]['subtotal'] += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .success-header {
            text-align: center;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .success-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.5rem;
        }
        .order-details {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .artisan-order-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .artisan-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        .artisan-items {
            padding: 1rem;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-info {
            flex: 1;
        }
        .item-price {
            font-weight: bold;
            color: #28a745;
        }
        .total-section {
            background: #f8f9fa;
            padding: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 1.2rem;
            border-top: 1px solid #ddd;
            padding-top: 0.5rem;
            margin-top: 1rem;
        }
        .next-steps {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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
                        <li><a href="dashboard.php">My Account</a></li>
                        <li><a href="cart.php">Cart (0)</a></li>
                        <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="confirmation-container">
                <!-- Success Header -->
                <div class="success-header">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h1>Order Placed Successfully!</h1>
                    <p style="font-size: 1.2rem; margin: 0;">Order #<?php echo $order_data['order_id']; ?></p>
                    <p style="margin: 0.5rem 0 0 0;">Thank you for supporting our artisan community!</p>
                </div>

                <!-- Order Details -->
                <div class="order-details">
                    <h2>📋 Order Details</h2>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <strong>Order ID:</strong> #<?php echo $order_data['order_id']; ?><br>
                        <strong>Order Date:</strong> <?php echo date('F j, Y \a\t g:i A'); ?><br>
                        <strong>Shipping Address:</strong><br>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-top: 0.5rem;">
                            <?php echo nl2br(htmlspecialchars($order_data['shipping_address'])); ?>
                        </div>
                    </div>

                    <!-- Artisan Breakdown -->
                    <h3>🎨 Your Order by Artisan</h3>
                    <?php foreach ($artisans_orders as $artisan_id => $artisan_order): ?>
                        <div class="artisan-order-section">
                            <div class="artisan-header">
                                <h4 style="margin: 0; color: #28a745;">
                                    👨‍🎨 <?php echo htmlspecialchars($artisan_order['name']); ?>
                                </h4>
                                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">
                                    📧 <?php echo htmlspecialchars($artisan_order['email']); ?>
                                </p>
                            </div>
                            
                            <div class="artisan-items">
                                <?php foreach ($artisan_order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-info">
                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                            <span style="color: #666; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($item['category_name']); ?> • 
                                                Quantity: <?php echo $item['quantity']; ?> • 
                                                $<?php echo number_format($item['price'], 2); ?> each
                                            </span>
                                        </div>
                                        <div class="item-price">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="total-section">
                                    <div class="total-row">
                                        <span>Subtotal from this artisan:</span>
                                        <span><strong>$<?php echo number_format($artisan_order['subtotal'], 2); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Final Total -->
                    <div class="total-section">
                        <div class="total-row">
                            <span>Order Subtotal:</span>
                            <span>$<?php echo number_format($order_data['total'] - ($order_data['total'] > 50 ? 0 : 5.99), 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span><?php echo ($order_data['total'] > 50) ? 'FREE' : '$5.99'; ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Final Total:</span>
                            <span>$<?php echo number_format($order_data['total'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="next-steps">
                    <h3>📞 What Happens Next?</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 1rem 0;">
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">1️⃣</div>
                            <h4>Artisan Notification</h4>
                            <p>Our artisans have been notified of your order and will begin preparing your items.</p>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">2️⃣</div>
                            <h4>Contact & Payment</h4>
                            <p>Artisans will contact you directly for payment details and delivery arrangements.</p>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">3️⃣</div>
                            <h4>Delivery</h4>
                            <p>Your handmade items will be carefully prepared and delivered to your address.</p>
                        </div>
                    </div>
                    
                    <div style="background: #fff; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                        <p style="margin: 0; font-weight: bold; color: #2196f3;">
                            💡 Need help? Contact us at: orders@artmart.com or +94 76 943 1050
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="../index.php" class="btn" style="background: #28a745; flex: 1; text-align: center;">
                        🛍️ Continue Shopping
                    </a>
                    <a href="orders.php" class="btn" style="background: #007bff; flex: 1; text-align: center;">
                        📋 View My Orders
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Handmade Marketplace | Connecting Artists with Art Lovers</p>
        </div>
    </footer>
</body>
</html>