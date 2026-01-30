<?php
// artisan/add_product.php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('artisan');

$database = new Database();
$db = $database->getConnection();

// Get artisan info
$query = "SELECT artisan_id FROM artisans WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
$cat_query = "SELECT * FROM categories ORDER BY category_name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = sanitize($_POST['product_name']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    
    // Handle image upload
    $image_path = "";
    $upload_error = "";
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];
        $file_tmp = $_FILES['product_image']['tmp_name'];
        $file_name = $_FILES['product_image']['name'];
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $upload_error = "Only JPEG, PNG, and GIF images are allowed";
        }
        // Validate file size
        elseif ($file_size > $max_size) {
            $upload_error = "File size must be less than 5MB";
        }
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = "../uploads/products/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $target_path)) {
                $image_path = "uploads/products/" . $unique_filename;
            } else {
                $upload_error = "Failed to upload image";
            }
        }
    }
    
    // Validation
    if (empty($product_name) || empty($description) || $price <= 0 || $stock < 0 || empty($category_id)) {
        $error = "Please fill in all required fields with valid values";
    } elseif (!empty($upload_error)) {
        $error = $upload_error;
    } else {
        try {
            $query = "INSERT INTO products (artisan_id, category_id, product_name, description, price, stock, image_url) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $artisan['artisan_id'], 
                $category_id, 
                $product_name, 
                $description, 
                $price, 
                $stock, 
                $image_path
            ]);
            
            $success = "Product added successfully!";
            // Clear form data
            $_POST = [];
        } catch (Exception $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - ArtMart</title>
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-upload-area:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .image-upload-area.dragover {
            border-color: #667eea;
            background: #e6f0ff;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #999;
            margin-bottom: 1rem;
        }
        
        .file-input {
            display: none;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin: 1rem auto;
            display: block;
        }
        
        .remove-image {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        
        .file-info {
            margin-top: 1rem;
            padding: 0.5rem;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 0.9rem;
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
                    <h3 style="margin-bottom: 0rem; margin-right:0rem; color: #2429c1ff;">ArtMart</h3>
                    <ul>
                        <!--<li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="products.php">My Products</a></li>
                        <li><a href="add_product.php" class="active">Add Product</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="profile.php">Profile</a></li>-->
                    </ul>
                </div>

                <!-- Main Content -->
                <div>
                    <h1 style="color: #333; margin-bottom: 2rem;">Add New Product</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="product_name">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" 
                                       value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category_id">Category *</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                                <?php echo (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="price">Price (Rs) *</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0.01" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock">Stock Quantity *</label>
                                    <input type="number" id="stock" name="stock" min="0" 
                                           value="<?php echo htmlspecialchars($_POST['stock'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="5" required 
                                          placeholder="Describe your product in detail..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Image Upload Section -->
                            <div class="form-group">
                                <label>Product Image</label>
                                <div class="image-upload-area" onclick="document.getElementById('product_image').click()">
                                    <div class="upload-icon">📷</div>
                                    <p><strong>Click to upload an image</strong></p>
                                    <p style="color: #666; font-size: 0.9rem;">
                                        Drag and drop or click to select<br>
                                        JPG, PNG, GIF up to 5MB
                                    </p>
                                    <input type="file" id="product_image" name="product_image" 
                                           class="file-input" accept="image/*" onchange="previewImage(this)">
                                </div>
                                
                                <!-- Image Preview -->
                                <div id="image-preview" style="display: none;">
                                    <img id="preview-img" class="image-preview" alt="Preview">
                                    <div class="file-info" id="file-info"></div>
                                    <button type="button" class="remove-image" onclick="removeImage()">Remove Image</button>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="submit" class="btn btn-success">Add Product</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Tips for Artisans -->
                    <div class="card" style="margin-top: 2rem; background: #f8f9fa;">
                        <h3 style="color: #333; margin-bottom: 1rem;">💡 Tips for Better Product Listings</h3>
                        <ul style="color: #666; line-height: 1.8;">
                            <li><strong>Use descriptive names:</strong> Include materials, size, and key features</li>
                            <li><strong>Write detailed descriptions:</strong> Mention crafting process, materials used, and care instructions</li>
                            <li><strong>Set competitive prices:</strong> Research similar products to price fairly</li>
                            <li><strong>Add high-quality images:</strong> Use well-lit photos showing different angles</li>
                            <li><strong>Manage stock carefully:</strong> Keep quantities updated to avoid overselling</li>
                            <li><strong>Image tips:</strong> Use good lighting, show multiple angles, and keep file size under 5MB</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 ArtMart - Artisan Panel</p>
        </div>
    </footer>

    <script>
        // Image upload functionality
        const uploadArea = document.querySelector('.image-upload-area');
        const fileInput = document.getElementById('product_image');
        const previewContainer = document.getElementById('image-preview');
        const previewImg = document.getElementById('preview-img');
        const fileInfo = document.getElementById('file-info');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                previewImage(fileInput);
            }
        });

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF images are allowed');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                    uploadArea.style.display = 'none';
                    
                    // Show file info
                    fileInfo.innerHTML = `
                        <strong>File:</strong> ${file.name}<br>
                        <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB<br>
                        <strong>Type:</strong> ${file.type}
                    `;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage() {
            fileInput.value = '';
            previewContainer.style.display = 'none';
            uploadArea.style.display = 'block';
            previewImg.src = '';
            fileInfo.innerHTML = '';
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const productName = document.getElementById('product_name').value.trim();
            const description = document.getElementById('description').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock').value);
            const categoryId = document.getElementById('category_id').value;
            
            if (!productName || !description || !categoryId) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return;
            }
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                return;
            }
            
            if (stock < 0) {
                e.preventDefault();
                alert('Stock cannot be negative');
                return;
            }
        });
    </script>
</body>
</html>