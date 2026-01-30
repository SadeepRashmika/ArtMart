<?php
// customer/cart.php
require_once '../config/database.php';
require_once '../config/session.php';

// Ensure user is logged in as customer
requireRole('customer');

$database = new Database();
$db = $database->getConnection();

// Get customer_id
$stmt = $db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['error'] = "Customer profile not found.";
    header("Location: ../index.php");
    exit();
}

$customer_id = $customer['customer_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: cart.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'remove') {
            $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
            if ($cart_item_id > 0) {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND customer_id = ?");
                $stmt->execute([$cart_item_id, $customer_id]);
                $_SESSION['success'] = "Item removed from cart.";
            }
        } elseif ($action === 'clear') {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            $_SESSION['success'] = "Cart cleared.";
        } elseif ($action === 'checkout') {
            // Handle checkout process
            $shipping_address = trim($_POST['shipping_address'] ?? '');
            
            if (empty($shipping_address)) {
                throw new Exception("Shipping address is required for checkout.");
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Get cart items with current prices and stock
            $stmt = $db->prepare("
                SELECT ci.cart_item_id, ci.quantity, ci.product_id,
                       p.product_name, p.price, p.stock, p.artisan_id,
                       a.name as artisan_name, a.email as artisan_email
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.product_id
                JOIN artisans a ON p.artisan_id = a.artisan_id
                WHERE ci.customer_id = ? AND p.status = 'active'
            ");
            $stmt->execute([$customer_id]);
            $checkout_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($checkout_items)) {
                throw new Exception("Your cart is empty.");
            }
            
            // Validate stock availability
            foreach ($checkout_items as $item) {
                if ($item['quantity'] > $item['stock']) {
                    throw new Exception("Insufficient stock for " . $item['product_name'] . ". Available: " . $item['stock']);
                }
            }
            
            // Calculate total
            $order_total = 0;
            foreach ($checkout_items as $item) {
                $order_total += $item['price'] * $item['quantity'];
            }
            
            // Add shipping if applicable
            $shipping_cost = $order_total > 50 ? 0 : 5.99;
            $order_total += $shipping_cost;
            
            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (customer_id, total_amount, shipping_address, status) 
                VALUES (?, ?, ?, 'Pending')
            ");
            $stmt->execute([$customer_id, $order_total, $shipping_address]);
            $order_id = $db->lastInsertId();
            
            // Add order details and update stock
            foreach ($checkout_items as $item) {
                // Insert order detail
                $stmt = $db->prepare("
                    INSERT INTO order_details (order_id, product_id, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $subtotal = $item['price'] * $item['quantity'];
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $subtotal]);
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear cart
            $stmt = $db->prepare("DELETE FROM cart_items WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            
            // Commit transaction
            $db->commit();
            
            // Store order info for confirmation page
            $_SESSION['order_success'] = [
                'order_id' => $order_id,
                'total' => $order_total,
                'items' => $checkout_items,
                'shipping_address' => $shipping_address
            ];
            
            header("Location: order_confirmation.php");
            exit();
        }
    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        if (isset($db)) $db->rollback();
        $_SESSION['error'] = "Database error occurred during checkout.";
        error_log("Checkout error: " . $e->getMessage());
    }
    
    header("Location: cart.php");
    exit();
}

// Get cart items with artisan information
$stmt = $db->prepare("
    SELECT ci.cart_item_id, ci.quantity, ci.added_at,
           p.product_id, p.product_name, p.description, p.price, p.image_url, p.stock,
           a.artisan_id, a.name as artisan_name, a.email as artisan_email,
           c.category_name
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    JOIN artisans a ON p.artisan_id = a.artisan_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE ci.customer_id = ? AND p.status = 'active'
    ORDER BY ci.added_at DESC
");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$artisans_info = [];
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    
    // Collect unique artisan information
    if (!isset($artisans_info[$item['artisan_id']])) {
        $artisans_info[$item['artisan_id']] = [
            'name' => $item['artisan_name'],
            'email' => $item['artisan_email'],
            'products' => []
        ];
    }
    $artisans_info[$item['artisan_id']]['products'][] = $item;
}

$shipping = $subtotal > 50 ? 0 : 5.99; // Free shipping over $50
$total = $subtotal + $shipping;

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto auto auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item-details h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .item-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        .cart-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .summary-row.total {
            font-weight: bold;
            font-size: 1.2rem;
            border-top: 1px solid #ddd;
            padding-top: 0.5rem;
            margin-top: 1rem;
        }
        .artisan-section {
            background: #e8f5e8;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .artisan-header {
            font-weight: bold;
            color: #155724;
            margin-bottom: 0.5rem;
        }
        .checkout-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 2px solid #28a745;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            height: 80px;
        }
        .checkout-btn {
            background: #28a745;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .checkout-btn:hover {
            background: #218838;
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
                        <li><a href="cart.php">Cart (<?php echo count($cart_items); ?>)</a></li>
                        <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>🛒 Your Shopping Cart</h1>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <!-- Empty Cart -->
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h2>Your cart is empty</h2>
                    <p>Discover amazing handmade products from our talented artisans!</p>
                    <a href="../index.php" class="btn">Continue Shopping</a>
                </div>
            <?php else: ?>
                <!-- Display Artisans Information -->
                <div class="card">
                    <h2>🎨 Your Order Includes Products From:</h2>
                    <?php foreach ($artisans_info as $artisan_id => $artisan_data): ?>
                        <div class="artisan-section">
                            <div class="artisan-header">
                                👨‍🎨 <?php echo htmlspecialchars($artisan_data['name']); ?>
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">
                                Contact: <?php echo htmlspecialchars($artisan_data['email']); ?>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <strong>Products in your cart:</strong>
                                <?php foreach ($artisan_data['products'] as $product): ?>
                                    <span style="background: #fff; padding: 2px 8px; margin: 2px; border-radius: 12px; font-size: 0.8rem; display: inline-block;">
                                        <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo $product['quantity']; ?>x)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Items -->
                <div class="card">
                    <h2>Items in Your Cart (<?php echo count($cart_items); ?>)</h2>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <!-- Product Image with better error handling -->
<div class="item-image">
    <?php if ($item['image_url']): ?>
        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 8px;">
            <span style="font-size: 2rem; color: #999;">📷</span>
        </div>
    <?php else: ?>
        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 8px;">
            <span style="font-size: 2rem; color: #999;">🎨</span>
        </div>
    <?php endif; ?>
</div>
                            <!-- Product Details -->
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p><strong>Artisan:</strong> <?php echo htmlspecialchars($item['artisan_name']); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category_name']); ?></p>
                                <p><strong>Price:</strong> Rs <?php echo number_format($item['price'], 2); ?> each</p>
                            </div>
                            
                            <!-- Quantity Display -->
                            <div style="text-align: center;">
                                <div style="font-weight: bold; font-size: 1.1rem;">
                                    Qty: <?php echo $item['quantity']; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #666;">
                                    Stock: <?php echo $item['stock']; ?>
                                </div>
                            </div>
                            
                            <!-- Item Total -->
                            <div style="font-weight: bold; color: #333; text-align: center;">
                                Rs <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                            
                            <!-- Remove Button -->
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button type="submit" class="btn" style="background: #dc3545; padding: 0.5rem;" 
                                        onclick="return confirm('Remove this item from cart?')">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cart_items); ?> items):</span>
                        <span>Rs <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'FREE'; ?></span>
                    </div>
                    <?php if ($shipping > 0): ?>
                        <div class="summary-row" style="font-size: 0.9rem; color: #666;">
                            <span>Free shipping on orders over $50</span>
                            <span></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>Rs <?php echo number_format($total, 2); ?></span>
                    </div>
                </div>

                <!-- Checkout Form -->
                <div class="checkout-form">
                    <h3>🚚 Checkout Information</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address *</label>
                            <textarea id="shipping_address" 
                                      name="shipping_address" 
                                      placeholder="Enter your complete shipping address..."
                                      required></textarea>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 0.5rem 0; color: #856404;">📦 Order Processing Information</h4>
                            <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                                After placing your order, our artisans will be notified and will contact you directly 
                                for payment arrangements and delivery coordination. Your order will be marked as "Pending" 
                                until confirmed.
                            </p>
                        </div>
                        
                        <button type="submit" class="checkout-btn" 
                                onclick="return confirm('Place order for $<?php echo number_format($total, 2); ?>?')">
                            🛒 Place Order - Rs <?php echo number_format($total, 2); ?>
                        </button>
                    </form>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <a href="../index.php" class="btn" style="background: #6c757d; flex: 1; text-align: center;">
                            Continue Shopping
                        </a>
                        <form method="POST" style="flex: 1; margin: 0;">
                            <input type="hidden" name="action" value="clear">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn" style="width: 100%; background: #dc3545;" 
                                    onclick="return confirm('Clear entire cart?')">Clear Cart</button>
                        </form>
                    </div>
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