<?php
session_start(); // Start the session at the beginning of the script

// Logout handling
if (isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'restaurant');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Error message variable
$error_message = '';
$success_message = '';

// Check for messages in session and clear them after display
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add Category
    if (isset($_POST['add_category'])) {
        $name = $_POST['category_name'];
        $display_order = $_POST['display_order'];
        
        // Check if display order already exists
        $check_order = mysqli_query($conn, "SELECT * FROM categories WHERE display_order = $display_order");
        if (mysqli_num_rows($check_order) > 0) {
            $_SESSION['error_message'] = "A category with this display order already exists!";
        } else {
            $sql = "INSERT INTO categories (name, display_order) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $name, $display_order);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Category added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding category: " . mysqli_error($conn);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    // Add Menu Item
    elseif (isset($_POST['add_item'])) {
        $name = $_POST['item_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        
        $sql = "INSERT INTO menu_items (name, description, price, category_id) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssdi", $name, $description, $price, $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Menu item added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding menu item: " . mysqli_error($conn);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    // Update Order Status
    elseif (isset($_POST['update_order_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        
        $sql = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $order_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Order status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating order status: " . mysqli_error($conn);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Delete operations with confirmation handling
$confirm_delete = isset($_GET['confirm_delete']);
if (isset($_GET['delete_category']) && $confirm_delete) {
    $category_id = $_GET['delete_category'];
    
    // First, check if category has associated menu items
    $check_items = mysqli_query($conn, "SELECT * FROM menu_items WHERE category_id = $category_id");
    
    if (mysqli_num_rows($check_items) > 0) {
        $_SESSION['error_message'] = "Cannot delete category. It contains menu items!";
    } else {
        if (mysqli_query($conn, "DELETE FROM categories WHERE id = $category_id")) {
            $_SESSION['success_message'] = "Category deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting category: " . mysqli_error($conn);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['delete_item']) && $confirm_delete) {
    $item_id = $_GET['delete_item'];
    
    if (mysqli_query($conn, "DELETE FROM menu_items WHERE id = $item_id")) {
        $_SESSION['success_message'] = "Menu item deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting menu item: " . mysqli_error($conn);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch data
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY display_order");
$menu_items = mysqli_query($conn, "SELECT mi.*, c.name as category_name 
                                  FROM menu_items mi 
                                  JOIN categories c ON mi.category_id = c.id 
                                  ORDER BY c.display_order, mi.name");
$orders = mysqli_query($conn, "SELECT o.*, u.name as customer_name, 
                              GROUP_CONCAT(CONCAT(mi.name, ' (', oi.quantity, ')')) as items
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id
                              JOIN order_items oi ON o.id = oi.order_id 
                              JOIN menu_items mi ON oi.item_id = mi.id 
                              GROUP BY o.id 
                              ORDER BY o.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
    /* Previous styles remain the same */
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f9;
    }

    .container {
        width: 80%;
        margin: auto;
        overflow: hidden;
    }

    h1,
    h2 {
        color: #333;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    th,
    td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background-color: #333;
        color: white;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-control,
    select {
        width: 100%;
        padding: 10px;
    }

    .btn {
        padding: 10px 15px;
        border: none;
        color: white;
        cursor: pointer;
    }

    .btn-primary {
        background-color: #3498db;
    }

    .btn-danger {
        background-color: #e74c3c;
    }

    .btn-logout {
        background-color: #95a5a6;
        float: right;
        margin: 10px;
    }

    .status {
        padding: 5px;
        border-radius: 5px;
        color: white;
    }

    .status-pending {
        background-color: #f1c40f;
    }

    .status-completed {
        background-color: #2ecc71;
    }

    /* New styles for messages */
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
    }

    .success-message {
        background-color: #d4edda;
        color: #155724;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
    }

    .confirmation-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .confirmation-box {
        background: white;
        padding: 20px;
        border-radius: 5px;
        text-align: center;
    }
    </style>
    <script>
    function confirmDelete(type, id) {
        // Create confirmation overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirmation-overlay';
        overlay.innerHTML = `
            <div class="confirmation-box">
                <h3>Are you sure?</h3>
                <p>Do you want to delete this ${type}?</p>
                <button onclick="proceedDelete('${type}', ${id})" class="btn btn-danger">Yes, Delete</button>
                <button onclick="cancelDelete()" class="btn btn-primary">Cancel</button>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function proceedDelete(type, id) {
        // Redirect to delete with confirmation
        window.location.href = `?delete_${type}=${id}&confirm_delete=1`;
    }

    function cancelDelete() {
        // Remove the confirmation overlay
        document.querySelector('.confirmation-overlay').remove();
    }
    </script>
</head>

<body>
    <div class="container">
        <a href="?logout=true" class="btn btn-logout">Logout</a>
        <h1>Admin Dashboard</h1>

        <!-- Error and Success Messages -->
        <?php if(!empty($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if(!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Category Management -->
        <section id="categories">
            <h2>Categories</h2>
            <form method="post" class="form-group">
                <input type="text" name="category_name" placeholder="Category Name" class="form-control" required>
                <input type="number" name="display_order" placeholder="Display Order" class="form-control" required>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </form>

            <table>
                <tr>
                    <th>Name</th>
                    <th>Order</th>
                    <th>Action</th>
                </tr>
                <?php while($category = mysqli_fetch_assoc($categories)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo $category['display_order']; ?></td>
                    <td>
                        <button onclick="confirmDelete('category', <?php echo $category['id']; ?>)"
                            class="btn btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </section>

        <!-- Menu Items Management -->
        <section id="menu-items">
            <h2>Menu Items</h2>
            <form method="post" class="form-group">
                <input type="text" name="item_name" placeholder="Item Name" class="form-control" required>
                <textarea name="description" placeholder="Description" class="form-control" required></textarea>
                <input type="number" name="price" placeholder="Price" class="form-control" step="0.01" required>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php 
                mysqli_data_seek($categories, 0); 
                while($category = mysqli_fetch_assoc($categories)): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
            </form>

            <table>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
                <?php while($item = mysqli_fetch_assoc($menu_items)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>
                        <button onclick="confirmDelete('item', <?php echo $item['id']; ?>)"
                            class="btn btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </section>

        <!-- Order Management -->
        <section id="orders">
            <h2>Orders</h2>
            <table>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php while($order = mysqli_fetch_assoc($orders)): ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['items']); ?></td>
                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                    <td><span
                            class="status status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                    </td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="form-control">
                                <option value="pending" <?php if($order['status'] == 'pending') echo 'selected'; ?>>
                                    Pending</option>
                                <option value="completed" <?php if($order['status'] == 'completed') echo 'selected'; ?>>
                                    Completed</option>
                            </select>
                            <input type="hidden" name="update_order_status" value="1">
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </section>
    </div>
</body>

</html>