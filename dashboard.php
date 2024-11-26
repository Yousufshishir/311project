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
    $payment_method = $_POST['payment_method']; // New payment method field
    
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
        // Insert into orders table with payment method
        $sql = "INSERT INTO orders (user_id, total_amount, status, payment_method) VALUES (?, ?, 'pending', ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ids", $user_id, $total_amount, $payment_method);
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

// Handle search and sort functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort parameters
$allowed_sort_columns = ['name', 'price', 'category_name'];
$sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'name';
$sort_order = in_array(strtolower($sort_order), ['asc', 'desc']) ? strtolower($sort_order) : 'asc';

$menu_query = "SELECT mi.*, c.name as category_name 
               FROM menu_items mi 
               JOIN categories c ON mi.category_id = c.id 
               WHERE mi.name LIKE ? OR mi.description LIKE ?
               ORDER BY $sort_by $sort_order";
$stmt = mysqli_prepare($conn, $menu_query);
$search_param = "%{$search_query}%";
mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt);
$menu_result = mysqli_stmt_get_result($stmt);
$menu_items = mysqli_fetch_all($menu_result, MYSQLI_ASSOC);

// Group menu items by category
$categorized_menu = [];
foreach ($menu_items as $item) {
    $categorized_menu[$item['category_name']][] = $item;
}

// Fetch user's order history with more details
$user_id = $_SESSION['user_id'];
$history_query = "SELECT o.id, o.total_amount, o.status, o.created_at, o.payment_method,
                         GROUP_CONCAT(CONCAT(mi.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items 
                 FROM orders o 
                 JOIN order_items oi ON o.id = oi.order_id 
                 JOIN menu_items mi ON oi.item_id = mi.id 
                 WHERE o.user_id = ? 
                 GROUP BY o.id 
                 ORDER BY o.created_at DESC 
                 LIMIT 10";
$stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);
$order_history = mysqli_fetch_all($history_result, MYSQLI_ASSOC);

// Personalized welcome message
$first_name = explode(' ', $_SESSION['user_name'])[0];
$time_of_day = date('H');
$greeting = 'Good ';
if ($time_of_day < 12) {
    $greeting .= 'Morning';
} elseif ($time_of_day < 18) {
    $greeting .= 'Afternoon';
} else {
    $greeting .= 'Evening';
}
$welcome_message = "$greeting, " . htmlspecialchars($first_name) . " Enjoy your Food!!";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Culinary Canvas - Order Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary-color: #6A5ACD;
        --secondary-color: #4CAF50;
        --bg-color: #f8f9fa;
        --text-color: #2c3e50;
        --card-bg: #ffffff;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        color: var(--text-color);
        line-height: 1.6;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--card-bg);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .welcome-message {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-color);
    }

    .search-sort-container {
        display: flex;
        gap: 15px;
        background: #f1f3f5;
        padding: 10px;
        border-radius: 10px;
    }

    .search-input,
    .sort-select {
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
    }

    .menu-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .menu-item {
        background: var(--card-bg);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .menu-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .menu-item img {
        width: 100%;
        height: 250px;
        object-fit: cover;
    }

    .menu-item-content {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .menu-item h3 {
        margin-bottom: 10px;
        color: var(--primary-color);
        font-weight: 600;
    }

    .price {
        color: var(--secondary-color);
        font-weight: bold;
        font-size: 18px;
        margin-top: auto;
    }

    .quantity-input {
        width: 80px;
        padding: 8px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        text-align: center;
        margin-top: 10px;
    }

    .place-order-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 20px;
        align-self: center;
    }

    .order-history {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        margin-top: 30px;
    }

    .order-history h2 {
        margin-bottom: 20px;
        color: var(--primary-color);
    }

    .order-item {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
        gap: 15px;
    }

    .order-item-details {
        display: flex;
        flex-direction: column;
    }

    .order-item-summary {
        text-align: right;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .payment-methods {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin: 20px 0;
    }

    .payment-method {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        padding: 15px;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .payment-method img {
        width: 80px;
        height: 80px;
        object-fit: contain;
        margin-bottom: 10px;
    }

    .payment-method input[type="radio"] {
        display: none;
    }

    .payment-method.selected,
    .payment-method:hover {
        border-color: var(--primary-color);
        background-color: #f1f3f5;
    }

    .payment-method.selected::before {
        content: '✓';
        position: absolute;
        top: 10px;
        right: 10px;
        color: var(--primary-color);
        font-weight: bold;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="welcome-message">
                <?php echo $welcome_message; ?>
            </div>

            <form method="GET" class="search-sort-container">
                <input type="search" name="search" placeholder="Search dishes..." class="search-input"
                    value="<?php echo htmlspecialchars($search_query); ?>">

                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                    <option value="price" <?php echo $sort_by == 'price' ? 'selected' : ''; ?>>Sort by Price</option>
                    <option value="category_name" <?php echo $sort_by == 'category_name' ? 'selected' : ''; ?>>Sort by
                        Category</option>
                </select>

                <select name="order" class="sort-select" onchange="this.form.submit()">
                    <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </form>

            <a href="logout.php" style="
                background-color: var(--primary-color);
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 50px;
            ">Logout</a>
        </div>

        <form method="post" onsubmit="return validateOrder();">
            <?php if (empty($categorized_menu)): ?>
            <div class="no-results">
                <h2>No dishes found 🍽️</h2>
                <p>Try a different search or explore our menu.</p>
            </div>
            <?php else: ?>
            <?php foreach ($categorized_menu as $category => $items): ?>
            <h2 style="
                        margin: 30px 0 20px;
                        color: var(--primary-color);
                        border-bottom: 3px solid var(--primary-color);
                        padding-bottom: 10px;
                        font-size: 24px;
                    "><?php echo htmlspecialchars($category); ?></h2>
            <div class="menu-container">
                <?php foreach ($items as $item): ?>
                <div class="menu-item">
                    <img src="images/<?php echo strtolower(str_replace(' ', '_', $item['name'])); ?>.jpg"
                        alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="menu-item-content">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                        <div class="price" data-price="<?php echo $item['price']; ?>">
                            $<?php echo number_format($item['price'], 2); ?>
                        </div>
                        <input type="number" name="quantities[]" class="quantity-input" value="0" min="0"
                            onchange="updateTotal()">
                        <input type="hidden" name="items[]" value="<?php echo $item['id']; ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Payment Method Selection -->
            <div style="
                background: var(--card-bg);
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
                margin: 20px 0;
            ">
                <h2 style="
                    text-align: center;
                    color: var(--primary-color);
                    margin-bottom: 20px;
                ">Select Payment Method</h2>

                <div class="payment-methods">
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="bkash" required>
                        <img src="images/bkash.png" alt="bKash">
                        <span>bKash</span>
                    </label>

                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="nagad" required>
                        <img src="images/nagad.png" alt="Nagad">
                        <span>Nagad</span>
                    </label>

                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="card" required>
                        <img src="images/card.png" alt="Credit/Debit Card">
                        <span>Credit/Debit Card</span>
                    </label>
                </div>
            </div>

            <div class="cart-total" style="
                background: var(--card-bg);
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
                text-align: center;
                margin: 20px 0;
                font-size: 24px;
                font-weight: bold;
            ">
                Total: $<span id="cart-total">0.00</span>
                <input type="hidden" name="total_amount" id="total_amount" value="0">
            </div>

            <div style="text-align: center;">
                <button type="submit" name="place_order" class="place-order-btn">Confirm Order</button>
            </div>
        </form>

        <!-- Order History Section -->
        <div class="order-history">
            <h2>Your Recent Orders</h2>
            <?php if (empty($order_history)): ?>
            <p>You haven't placed any orders yet.</p>
            <?php else: ?>
            <?php foreach ($order_history as $order): ?>
            <div class="order-item">
                <div class="order-item-details">
                    <strong>Order #<?php echo $order['id']; ?></strong>
                    <p><?php echo $order['items']; ?></p>
                </div>
                <div class="order-item-summary">
                    <span class="order-total">
                        Total: $<?php echo number_format($order['total_amount'], 2); ?>
                    </span>
                    <small class="order-date">
                        Date: <?php echo date('F j, Y H:i', strtotime($order['created_at'])); ?>
                    </small>
                    <span class="badge" style="
                        padding: 5px 10px;
                        border-radius: 20px;
                        font-size: 0.8em;
                        margin-top: 5px;
                        background-color: <?php 
                            switch($order['status']) {
                                case 'pending':
                                    echo '#FFC107'; // Amber
                                    break;
                                case 'completed':
                                    echo '#28A745'; // Green
                                    break;
                                case 'cancelled':
                                    echo '#DC3545'; // Red
                                    break;
                                default:
                                    echo '#6C757D'; // Gray
                            }
                        ?>;
                        color: white;
                    ">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <span class="payment-method" style="
                        padding: 5px 10px;
                        border-radius: 20px;
                        font-size: 0.8em;
                        margin-top: 5px;
                        background-color: #6A5ACD;
                        color: white;
                    ">
                        <?php echo strtoupper($order['payment_method']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

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

        function validateOrder() {
            // Check if any items are selected
            const items = document.querySelectorAll('.quantity-input');
            const hasItems = Array.from(items).some(item => parseInt(item.value) > 0);

            // Check if payment method is selected
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            const paymentSelected = Array.from(paymentMethods).some(method => method.checked);

            if (!hasItems) {
                alert('Please select at least one item to place an order.');
                return false;
            }

            if (!paymentSelected) {
                alert('Please select a payment method.');
                return false;
            }

            return confirm('Confirm your order?');
        }

        // Add event listeners to payment methods for visual selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove(
                    'selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Initialize total on page load
        window.onload = function() {
            updateTotal();
        };
        </script>
</body>

</html>