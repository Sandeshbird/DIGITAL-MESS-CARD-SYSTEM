<?php
// views/dashboard/waiter/select_menu.php - Simplest Menu Selector
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}
$waiter_id = $_SESSION['user_id'];
$waiter_name = $_SESSION['username'] ?? 'Waiter';
require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();
$message = '';
$message_type = '';

// Check if order user is set
if (!isset($_SESSION['order_user_id']) || !isset($_SESSION['order_plan_id'])) {
    header("Location: scan_qr.php");
    exit;
}

$order_user_id = $_SESSION['order_user_id'];
$order_plan_id = $_SESSION['order_plan_id'];
$order_user_name = $_SESSION['order_user_name'] ?? 'User';
$order_plan_name = $_SESSION['order_plan_name'] ?? 'Plan';

// Get menu items
$menu_items = [];
try {
    $menu_stmt = $db->prepare("SELECT * FROM menu ORDER BY category ASC, name ASC");
    $menu_stmt->execute();
    $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $menu_items = [];
}

// Group menu items by category
$menu_by_category = [];
foreach ($menu_items as $item) {
    $category = $item['category'];
    if (!isset($menu_by_category[$category])) {
        $menu_by_category[$category] = [];
    }
    $menu_by_category[$category][] = $item;
}

// Handle menu selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_menu'])) {
    $selected_items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $special_instructions = $_POST['special_instructions'] ?? '';
    
    if (empty($selected_items)) {
        $message = 'Please select at least one menu item.';
        $message_type = 'danger';
    } else {
        try {
            // Calculate total amount
            $total_amount = 0;
            foreach ($selected_items as $item_id) {
                $quantity = (int)($quantities[$item_id] ?? 1);
                foreach ($menu_items as $item) {
                    if ($item['id'] == $item_id) {
                        $total_amount += $item['price'] * $quantity;
                        break;
                    }
                }
            }
            
            // Create order
            $order_stmt = $db->prepare("INSERT INTO orders (table_id, user_id, total_amount, status, order_time) VALUES (?, ?, ?, 'pending', NOW())");
            $order_result = $order_stmt->execute([1, $order_user_id, $total_amount]); // Using table ID 1 for now
            
            if ($order_result) {
                $order_id = $db->lastInsertId();
                
                // Insert order items
                foreach ($selected_items as $item_id) {
                    $quantity = (int)($quantities[$item_id] ?? 1);
                    $instructions = $special_instructions[$item_id] ?? '';
                    
                    foreach ($menu_items as $item) {
                        if ($item['id'] == $item_id) {
                            $item_stmt = $db->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price, special_instructions) VALUES (?, ?, ?, ?, ?)");
                            $item_stmt->execute([$order_id, $item_id, $quantity, $item['price'], $instructions]);
                            break;
                        }
                    }
                }
                
                // Assign order to waiter
                $waiter_stmt = $db->prepare("INSERT INTO waiter_orders (waiter_id, order_id, status, assigned_at) VALUES (?, ?, 'preparing', NOW())");
                $waiter_stmt->execute([$waiter_id, $order_id]);
                
                // Clear session order data
                unset($_SESSION['order_user_id']);
                unset($_SESSION['order_plan_id']);
                unset($_SESSION['order_user_name']);
                unset($_SESSION['order_plan_name']);
                
                $message = 'Order placed successfully! Order ID: ' . $order_id;
                $message_type = 'success';
            } else {
                $message = 'Failed to place order.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred.';
            $message_type = 'danger';
            error_log("Order placement error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Menu - Mess Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding-top: 56px; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Mess Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">Dashboard</a>
                <a class="nav-link text-white" href="../../../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">Select Menu for <?php echo htmlspecialchars($order_user_name); ?></h4>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Select Menu Items</h5>
                                <span class="badge bg-primary"><?php echo count($menu_items); ?> Items</span>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="accordion" id="menuAccordion">
                                        <?php foreach ($menu_by_category as $category => $items): ?>
                                            <?php $category_id = strtolower(str_replace(' ', '-', $category)); ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading<?php echo $category_id; ?>">
                                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $category_id; ?>" aria-expanded="true" aria-controls="collapse<?php echo $category_id; ?>">
                                                        <i class="bi bi-<?php echo ($category === 'Breakfast' ? 'egg-fry' : ($category === 'Lunch' ? 'cup-straw' : 'moon')); ?> me-2"></i>
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </button>
                                                </h2>
                                                <div id="collapse<?php echo $category_id; ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo $category_id; ?>" data-bs-parent="#menuAccordion">
                                                    <div class="accordion-body">
                                                        <div class="table-responsive">
                                                            <table class="table table-hover">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Select</th>
                                                                        <th>Item</th>
                                                                        <th>Price</th>
                                                                        <th>Quantity</th>
                                                                        <th>Instructions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($items as $item): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <input type="checkbox" name="items[]" value="<?php echo $item['id']; ?>" class="form-check-input">
                                                                            </td>
                                                                            <td>
                                                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                                                                <br><span class="badge <?php echo $item['menu_type'] === 'Non-veg' ? 'bg-danger' : 'bg-success'; ?>"><?php echo htmlspecialchars($item['menu_type']); ?></span>
                                                                            </td>
                                                                            <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                                                            <td>
                                                                                <input type="number" name="quantities[<?php echo $item['id']; ?>]" min="1" max="4" value="1" class="form-control form-control-sm" style="width: 80px;">
                                                                            </td>
                                                                            <td>
                                                                                <textarea name="special_instructions[<?php echo $item['id']; ?>]" class="form-control form-control-sm" rows="1" placeholder="Special instructions"></textarea>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" name="select_menu" class="btn btn-success btn-lg">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Place Order
                                        </button>
                                        <a href="scan_qr.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left me-1"></i>
                                            Back to Scan QR
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Order Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>User:</strong> <?php echo htmlspecialchars($order_user_name); ?></p>
                                        <p><strong>Plan:</strong> <?php echo htmlspecialchars($order_plan_name); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Items Selected:</strong> <span id="itemCount">0</span></p>
                                        <p><strong>Total Amount:</strong> ₹<span id="totalAmount">0.00</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update item count and total amount
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]');
            const quantities = document.querySelectorAll('input[name^="quantities["]');
            const itemCountElement = document.getElementById('itemCount');
            const totalAmountElement = document.getElementById('totalAmount');
            
            function updateTotals() {
                let itemCount = 0;
                let totalAmount = 0;
                
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const itemId = checkbox.value;
                        const quantityInput = document.querySelector(`input[name="quantities[${itemId}]"]`);
                        const quantity = parseInt(quantityInput.value) || 1;
                        const priceElement = checkbox.closest('tr').querySelector('td:nth-child(3)');
                        const price = parseFloat(priceElement.textContent.replace('₹', '')) || 0;
                        
                        itemCount += quantity;
                        totalAmount += price * quantity;
                    }
                });
                
                itemCountElement.textContent = itemCount;
                totalAmountElement.textContent = totalAmount.toFixed(2);
            }
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateTotals);
            });
            
            quantities.forEach(input => {
                input.addEventListener('input', updateTotals);
            });
            
            // Initial update
            updateTotals();
        });
    </script>
</body>
</html>