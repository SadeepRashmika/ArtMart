<?php
// artisan/confirm_orders.php
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

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $order_id = $_POST['order_id'] ?? 0;
    
    try {
        // Get artisan ID
        $stmt = $db->prepare("SELECT artisan_id FROM artisans WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artisan) {
            $_SESSION['error'] = "Artisan profile not found.";
        } else {
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
                $_SESSION['error'] = "Order not found or access denied.";
            } else {
                // Update order status from Pending to Processing
                $stmt = $db->prepare("UPDATE orders SET status = 'Processing' WHERE order_id = ? AND status = 'Pending'");
                $success = $stmt->execute([$order_id]);
                
                if ($success && $stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Order #$order_id confirmed successfully!";
                } else {
                    $_SESSION['error'] = "Failed to confirm order. Order may already be processed.";
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("Location: confirm_orders.php");
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

// Get pending orders containing artisan's products
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
    WHERE p.artisan_id = ? AND o.status = 'Pending'
    ORDER BY o.order_date ASC
");
$stmt->execute([$artisan['artisan_id']]);
$pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order details for each pending order
$order_details = [];
foreach ($pending_orders as $order) {
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
    <title>Confirm Orders - ArtMart</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .order-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            background: #fff;
        }
        
        .order-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-content {
            padding: 25px;
        }
        
        .customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .product-item {
            background: #e3f2fd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
        }
        
        .confirm-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
            margin-top: 20px;
        }
        
        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .no-orders {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            text-decoration: none;
        }
        
        .order-total {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="header">
            <h1>🛍️ Confirm Pending Orders</h1>
            <p>Hello <?php echo htmlspecialchars($artisan['name']); ?>! Please review and confirm your pending orders below.</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="success-message">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Pending Orders -->
        <?php if (empty($pending_orders)): ?>
            <div class="no-orders">
                <h3>🎉 No Pending Orders</h3>
                <p>All orders have been processed or you have no orders yet.</p>
                <a href="dashboard.php" class="back-btn">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <?php foreach ($pending_orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h3>Order #<?php echo $order['order_id']; ?></h3>
                            <p>📅 <?php echo date('M d, Y at H:i', strtotime($order['order_date'])); ?></p>
                        </div>
                        <div>
                            <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-weight: bold;">
                                ⏳ PENDING CONFIRMATION
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-content">
                        <!-- Customer Information -->
                        <div class="customer-info">
                            <h4>👤 Customer Details</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            </div>
                            <p style="margin-top: 10px;"><strong>📍 Shipping Address:</strong> 
                               <?php echo htmlspecialchars($order['shipping_address'] ?: 'Not provided'); ?>
                            </p>
                        </div>

                        <!-- Products in Order -->
                        <h4>📦 Your Products in this Order</h4>
                        <?php if (isset($order_details[$order['order_id']])): ?>
                            <?php 
                            $artisan_total = 0;
                            foreach ($order_details[$order['order_id']] as $detail): 
                                $artisan_total += $detail['subtotal'];
                            ?>
                                <div class="product-item">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong>🎨 <?php echo htmlspecialchars($detail['product_name']); ?></strong>
                                            <br>
                                            <small>Quantity: <?php echo $detail['quantity']; ?> × Rs. <?php echo number_format($detail['unit_price'], 2); ?></small>
                                        </div>
                                        <div style="font-weight: bold; color: #28a745;">
                                            Rs. <?php echo number_format($detail['subtotal'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="order-total">
                                💰 Your Earnings from this Order: Rs. <?php echo number_format($artisan_total, 2); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Confirm Button -->
                        <form method="POST" style="margin-top: 25px;">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <button type="submit" name="confirm_order" class="confirm-btn" 
                                    onclick="return confirm('Are you sure you want to confirm this order? This will change the status to Processing.')">
                                ✅ CONFIRM ORDER
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="back-btn">← Back to Main Dashboard</a>
        </div>
    </div>

    <script>
        // Auto-hide messages after 3 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => msg.remove(), 500);
            });
        }, 3000);

        // Add loading state to confirm buttons
        document.querySelectorAll('.confirm-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.form.checkValidity()) {
                    this.innerHTML = '⏳ Confirming...';
                    this.disabled = true;
                }
            });
        });
    </script>
</body>
</html>