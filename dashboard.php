<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'restaurant');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Process order submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $user_id = $_SESSION['user_id'];
    $items = $_POST['items'];
    $quantities = $_POST['quantities'];
    $total_amount = $_POST['total_amount'];
    
    // Check if any items are selected
    $has_items = false;
    foreach ($quantities as $qty) {
        if ($qty > 0) {
            $has_items = true;
            break;
        }
    }
    
    if (!$has_items) {
        $_SESSION['message'] = "Please select at least one item to place an order.";
        $_SESSION['message_type'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert into orders table
        $sql = "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "id", $user_id, $total_amount);
        mysqli_stmt_execute($stmt);
        
        $order_id = mysqli_insert_id($conn);
        
        // Insert order items
        $sql = "INSERT INTO order_items (order_id, item_id, quantity) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        foreach ($items as $index => $item_id) {
            if ($quantities[$index] > 0) {
                mysqli_stmt_bind_param($stmt, "iii", $order_id, $item_id, $quantities[$index]);
                mysqli_stmt_execute($stmt);
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['message'] = "Order placed successfully!";
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = "Error placing order: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch menu items with categories
$menu_query = "SELECT mi.*, c.name as category_name 
               FROM menu_items mi 
               JOIN categories c ON mi.category_id = c.id 
               ORDER BY c.display_order, mi.name";
$menu_result = mysqli_query($conn, $menu_query);
$menu_items = mysqli_fetch_all($menu_result, MYSQLI_ASSOC);

// Group menu items by category
$categorized_menu = [];
foreach ($menu_items as $item) {
    $categorized_menu[$item['category_name']][] = $item;
}

// Fetch user's order history
$user_id = $_SESSION['user_id'];
$history_query = "SELECT o.*, GROUP_CONCAT(mi.name) as items 
                 FROM orders o 
                 JOIN order_items oi ON o.id = oi.order_id 
                 JOIN menu_items mi ON oi.item_id = mi.id 
                 WHERE o.user_id = ? 
                 GROUP BY o.id 
                 ORDER BY o.created_at DESC 
                 LIMIT 5";
$stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);
$order_history = mysqli_fetch_all($history_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Dashboard</title>
    <style>
    body {
        font-family: 'Arial', sans-serif;
        margin: 0;
        padding: 20px;
        background-image: url('background.jpg');
        background-size: cover;
        background-attachment: fixed;
        background-position: center;
        background-repeat: no-repeat;
    }

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        background-color: rgba(255, 255, 255, 0.95);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .welcome-text {
        font-size: 24px;
        color: #333;
    }

    .logout-btn {
        background-color: #FF6347;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
    }

    .menu-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .menu-item {
        background-color: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .menu-item:hover {
        transform: translateY(-5px);
    }

    .menu-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .menu-item h3 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .price {
        color: #FF6347;
        font-weight: bold;
        font-size: 18px;
        margin-bottom: 10px;
    }

    .quantity-input {
        width: 60px;
        padding: 5px;
        margin-right: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .order-history {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .order-history h2 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #FF6347;
        padding-bottom: 10px;
    }

    .order-item {
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .message {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .place-order-btn {
        background-color: #4CAF50;
        color: white;
        padding: 15px 30px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
        transition: background-color 0.2s;
    }

    .place-order-btn:hover {
        background-color: #45a049;
    }

    .cart-total {
        font-size: 24px;
        font-weight: bold;
        margin: 20px 0;
        color: #333;
        padding: 15px;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .category-title {
        margin: 30px 0 20px 0;
        color: #333;
        border-bottom: 2px solid #FF6347;
        padding-bottom: 10px;
        font-size: 24px;
    }

    .welcome-subtitle {
        font-size: 16px;
        color: #666;
        margin-top: 5px;
    }
    </style>
    <script>
    function updateTotal() {
        let total = 0;
        const items = document.querySelectorAll('.menu-item');

        items.forEach(item => {
            const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(item.querySelector('.price').dataset.price);
            total += quantity * price;
        });

        document.getElementById('cart-total').textContent = total.toFixed(2);
        document.getElementById('total_amount').value = total.toFixed(2);
    }

    // Initialize total on page load
    window.onload = function() {
        updateTotal();
    };
    </script>
</head>

<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="welcome-text">
                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                <div class="welcome-subtitle">
                    Ready to explore our delicious menu?
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <?php
        if (isset($_SESSION['message'])) {
            $messageClass = ($_SESSION['message_type'] == 'success') ? 'success' : 'error';
            echo '<div class="message ' . $messageClass . '">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>

        <form method="post" onsubmit="return confirm('Confirm your order?');">
            <?php foreach ($categorized_menu as $category => $items): ?>
            <h2 class="category-title">
                <?php echo htmlspecialchars($category); ?>
            </h2>
            <div class="menu-container">
                <?php foreach ($items as $item): ?>
                <div class="menu-item">
                    <img src="images/<?php echo strtolower(str_replace(' ', '_', $item['name'])); ?>.jpg"
                        alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="price" data-price="<?php echo $item['price']; ?>">
                        $<?php echo number_format($item['price'], 2); ?>
                    </div>
                    <input type="number" name="quantities[]" class="quantity-input" value="0" min="0"
                        onchange="updateTotal()">
                    <input type="hidden" name="items[]" value="<?php echo $item['id']; ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div class="cart-total">
                Total: $<span id="cart-total">0.00</span>
                <input type="hidden" name="total_amount" id="total_amount" value="0">
            </div>

            <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
        </form>

        <div class="order-history">
            <h2>Recent Orders</h2>
            <?php if (empty($order_history)): ?>
            <p>No recent orders found.</p>
            <?php else: ?>
            <?php foreach ($order_history as $order): ?>
            <div class="order-item">
                <p>Order #<?php echo $order['id']; ?> -
                    <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?><br>
                    Items: <?php echo htmlspecialchars($order['items']); ?><br>
                    Total: $<?php echo number_format($order['total_amount'], 2); ?><br>
                    Status: <?php echo ucfirst($order['status']); ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>