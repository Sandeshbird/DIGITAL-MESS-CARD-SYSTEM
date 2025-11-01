<?php
// views/tables/create.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'waiter')) {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin/waiter
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'] ?? 'User';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Fetch list of users (customers) to assign the table/order to
$users = [];
try {
    $user_query = "SELECT id, first_name, last_name, username FROM user WHERE role = 'user' ORDER BY username"; // Only fetch regular users
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load users. Please try again later.";
    error_log("Create Table Record page - Users query error: " . $e->getMessage());
}

// Fetch list of cards for the selected user (or maybe user enters card ID?)
$cards = [];
if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $customer_id = $_POST['user_id'];
    try {
        $card_query = "SELECT id, c_status, balance_credits FROM card WHERE user_id = :user_id AND c_status = 'Active' ORDER BY created_at DESC"; // Only fetch active cards for the user
        $card_stmt = $db->prepare($card_query);
        $card_stmt->bindParam(':user_id', $customer_id);
        $card_stmt->execute();
        $cards = $card_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message .= " Could not load cards for the selected user. ";
        error_log("Create Table Record page - Cards query error: " . $e->getMessage());
    }
}

// Fetch list of menu items for the order
$menu_items = [];
try {
    $menu_query = "SELECT id, category, description, menu_type FROM menu ORDER BY category, menu_type, description";
    $menu_stmt = $db->prepare($menu_query);
    $menu_stmt->execute();
    $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load menu items. ";
    error_log("Create Table Record page - Menu query error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_name = $_POST['table_name'] ?? null;
    $t_qr = (int)($_POST['t_qr'] ?? 0); // QR code ID (numeric)
    $t_s_w = (int)($_POST['t_s_w'] ?? 0); // Seat/switch number (numeric, purpose unclear from schema)
    $user_id_assigned = $_POST['user_id'] ?? null; // The customer
    $card_id = $_POST['card_id'] ?? null;
    $transaction_mode = $_POST['transaction_mode'] ?? 'Card'; // Default
    $ordered_items = $_POST['ordered_items'] ?? []; // Array of selected menu item IDs
    $quantities = $_POST['quantities'] ?? []; // Array of quantities for each item

    // Basic validation
    $errors = [];
    if (empty($table_name)) {
        $errors[] = "Please select a table name.";
    }
    if (empty($user_id_assigned) || $user_id_assigned == 0) {
        $errors[] = "Please select a customer.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a valid card for the customer.";
    }
    if (empty($ordered_items) || count($ordered_items) === 0) {
        $errors[] = "Please select at least one menu item.";
    }

    // Validate quantities and build the order description string
    $order_description_parts = [];
    // Note: The schema does not have an explicit 'item price' in the 'menu' table.
    // Calculating the total cost for the order or checking against the card balance is complex without this.
    // We'll just list the items and quantities for now.
    if (!empty($ordered_items)) {
        foreach ($ordered_items as $index => $item_id) {
            $quantity = intval($quantities[$index] ?? 1); // Default quantity to 1 if not provided or invalid
            if ($quantity <= 0) {
                $errors[] = "Quantity for item ID $item_id must be greater than 0.";
                continue;
            }
            // Find the item in the fetched menu list to get its description for the order string
            $found_item = null;
            foreach ($menu_items as $menu_item) {
                if ($menu_item['id'] == $item_id) {
                    $found_item = $menu_item;
                    break;
                }
            }
            if ($found_item) {
                 $order_description_parts[] = $found_item['description'] . " (x$quantity)";
            } else {
                $errors[] = "Invalid menu item ID selected: $item_id";
            }
        }
    }

    $order_description = implode(', ', $order_description_parts);

    if (empty($errors)) {
        try {
            // Prepare SQL query to insert new table record (order)
            // Note: The 'time' field will default to the current timestamp on insert.
            // The 'user_id' field in 'tabels' likely represents the *waiter* assigned to the table/order.
            // We use the logged-in user's ID for this.
            $insert_query = "
                INSERT INTO tabels (t_name, t_qr, t_s_w, time, transaction_mode, user_id, card_id, menu_ordered)
                VALUES (:t_name, :t_qr, :t_s_w, NOW(), :transaction_mode, :waiter_user_id, :card_id, :menu_ordered)
            ";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':t_name', $table_name);
            $insert_stmt->bindParam(':t_qr', $t_qr, PDO::PARAM_INT);
            $insert_stmt->bindParam(':t_s_w', $t_s_w, PDO::PARAM_INT);
            $insert_stmt->bindParam(':transaction_mode', $transaction_mode);
            $insert_stmt->bindParam(':waiter_user_id', $user_id); // The logged-in user (admin/waiter) is the waiter assigning/taking the order
            $insert_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':menu_ordered', $order_description);

            if ($insert_stmt->execute()) {
                $new_table_record_id = $db->lastInsertId();
                $success_message = "Table record (order) created successfully! Record ID: $new_table_record_id";
                // Optionally, redirect to the tables list page after successful creation
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to create table record. Please try again.";
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create Table Record error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Record Table Assignment/Order</h2>
    <p>Assign a table to a customer and record their order.</p>
";

// Display success or error messages if set
if ($success_message) {
    $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
if ($error_message) {
    $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
}

$content .= "
    <form method='post' action=''>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='table_name' class='form-label'>Select Table Name:</label>
                <select class='form-select' id='table_name' name='table_name' required>
                    <option value=''>Choose...</option>
                    <option value='A' " . (($_POST['table_name'] ?? '') === 'A' ? 'selected' : '') . ">Table A</option>
                    <option value='B' " . (($_POST['table_name'] ?? '') === 'B' ? 'selected' : '') . ">Table B</option>
                    <option value='C' " . (($_POST['table_name'] ?? '') === 'C' ? 'selected' : '') . ">Table C</option>
                    <option value='D' " . (($_POST['table_name'] ?? '') === 'D' ? 'selected' : '') . ">Table D</option>
                    <option value='E' " . (($_POST['table_name'] ?? '') === 'E' ? 'selected' : '') . ">Table E</option>
                    <option value='F' " . (($_POST['table_name'] ?? '') === 'F' ? 'selected' : '') . ">Table F</option>
                    <option value='G' " . (($_POST['table_name'] ?? '') === 'G' ? 'selected' : '') . ">Table G</option>
                    <option value='H' " . (($_POST['table_name'] ?? '') === 'H' ? 'selected' : '') . ">Table H</option>
                    <option value='I' " . (($_POST['table_name'] ?? '') === 'I' ? 'selected' : '') . ">Table I</option>
                    <option value='J' " . (($_POST['table_name'] ?? '') === 'J' ? 'selected' : '') . ">Table J</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='t_qr' class='form-label'>QR Code ID (e.g., Table A = 1, B = 2...):</label>
                <input type='number' class='form-control' id='t_qr' name='t_qr' value='" . htmlspecialchars($_POST['t_qr'] ?? ord(strtoupper($_POST['table_name'] ?? ''))) . "' min='0' required>
                <small class='form-text text-muted'>Typically corresponds to the table name.</small>
            </div>
        </div>
         <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='t_s_w' class='form-label'>Seat/Switch Number (if applicable):</label>
                <input type='number' class='form-control' id='t_s_w' name='t_s_w' value='" . htmlspecialchars($_POST['t_s_w'] ?? 0) . "' min='0'>
                <small class='form-text text-muted'>Purpose of this field is unclear from schema.</small>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                    <option value='Card' " . (($_POST['transaction_mode'] ?? 'Card') === 'Card' ? 'selected' : '') . ">Card</option>
                    <option value='Cash' " . (($_POST['transaction_mode'] ?? 'Card') === 'Cash' ? 'selected' : '') . ">Cash</option>
                    <option value='UPI' " . (($_POST['transaction_mode'] ?? 'Card') === 'UPI' ? 'selected' : '') . ">UPI</option>
                </select>
            </div>
        </div>
        <div class='row mb-3'>
            <div class='col-md-6'>
                <label for='user_id' class='form-label'>Select Customer:</label>
                <select class='form-select' id='user_id' name='user_id' onchange='fetchCards(this.value)' required>
                    <option value='0'>Choose Customer...</option>
        ";

        foreach ($users as $user) {
             $selected_attr = ($_POST['user_id'] ?? null) == $user['id'] ? 'selected' : '';
             $content .= "<option value='" . $user['id'] . "' " . $selected_attr . ">" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') . "</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6'>
                <label for='card_id' class='form-label'>Select Card:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        // Populate cards based on the selected customer (from POST or fetched via JS)
        $selected_customer_id = $_POST['user_id'] ?? null;
        if ($selected_customer_id && !empty($cards)) {
             foreach ($cards as $card) {
                 $selected_attr = ($_POST['card_id'] ?? null) == $card['id'] ? 'selected' : '';
                 $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (Balance: ₹" . number_format($card['balance_credits'], 2) . ", Status: Active)</option>";
             }
        }

        $content .= "
                </select>
            </div>
        </div>

        <div class='mb-3'>
            <h5>Select Menu Items Ordered:</h5>
            <div class='row'>
        ";

        if (!empty($menu_items)) {
            $current_category = '';
            foreach ($menu_items as $index => $item) {
                // Group by category
                if ($item['category'] !== $current_category) {
                    if ($current_category !== '') {
                        $content .= "</div></div>"; // Close previous category div
                    }
                    $current_category = $item['category'];
                    $content .= "<div class='col-md-12 mt-3'><h6>$current_category</h6><div class='row'>"; // Open new category div
                }

                $item_type_badge = $item['menu_type'] === 'Veg' ? 'bg-success' : 'bg-danger';
                $item_type_text = $item['menu_type'];
                $content .= "
                    <div class='col-md-6 col-lg-4 mb-2'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' value='" . $item['id'] . "' id='item_" . $item['id'] . "' name='ordered_items[]' onchange='toggleQuantityInput(" . $item['id'] . ")'>
                            <label class='form-check-label' for='item_" . $item['id'] . "'>
                                " . htmlspecialchars($item['description']) . " <span class='badge " . $item_type_badge . "'>" . $item_type_text . "</span>
                            </label>
                            <input type='number' class='form-control form-control-sm mt-1 d-none' id='qty_" . $item['id'] . "' name='quantities[]' min='1' value='1' style='width: 60px; display: inline-block;'>
                        </div>
                    </div>
                ";
            }
            if ($current_category !== '') {
                 $content .= "</div></div>"; // Close the last category div
            }
        } else {
            $content .= "<p class='text-muted'>No menu items available.</p>";
        }

        $content .= "
            </div>
        </div>

        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Record Table Assignment/Order</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Tables List</a>
    </div>

    <script>
        // Function to fetch cards via AJAX when customer is selected (optional, improves UX)
        function fetchCards(customerId) {
            if (customerId && customerId != 0) {
                // In a real app, you would make an AJAX call to a PHP script (e.g., fetch_cards.php)
                // that returns the cards for the selected customer.
                // For now, this relies on the page reload/POST mechanism above.
                // console.log('Fetching cards for customer ID: ' + customerId);
                // Example AJAX call (requires fetch_cards.php endpoint):
                /*
                fetch('../../api/tables/fetch_cards.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ customer_id: customerId }),
                })
                .then(response => response.json())
                .then(data => {
                    const cardSelect = document.getElementById('card_id');
                    cardSelect.innerHTML = '<option value=\"0\">Choose Card...</option>'; // Clear existing options
                    data.forEach(card => {
                        const option = document.createElement('option');
                        option.value = card.id;
                        option.textContent = `Card ID: \${card.id} (Balance: ₹\${card.balance_credits}, Status: Active)`;
                        cardSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching cards:', error));
                */
            }
        }

        // Function to show/hide quantity input based on checkbox
        function toggleQuantityInput(itemId) {
            const checkbox = document.getElementById('item_' .concat(itemId));
            const qtyInput = document.getElementById('qty_'.concat(itemId));
            if (checkbox.checked) {
                qtyInput.classList.remove('d-none');
            } else {
                qtyInput.classList.add('d-none');
                qtyInput.value = 1; // Reset to 1 when unchecked
            }
        }
    </script>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>