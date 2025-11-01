<?php
// views/tables/edit.php

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

// Get table record ID from query string
$table_id_to_edit = $_GET['id'] ?? null;

if (!$table_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$table_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific table record's details along with related user (customer) and card info
try {
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name, -- This is the CUSTOMER
               w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- This is the WAITER (from t.user_id)
               c.balance_credits as card_balance, c.total_credits as card_total
        FROM tabels t
        LEFT JOIN user u ON t.user_id = u.id -- Join to get CUSTOMER details (assuming t.user_id is customer - see note below)
        LEFT JOIN user w ON t.user_id = w.id -- Join to get WAITER details (using the same field t.user_id - this is ambiguous in the schema)
        LEFT JOIN card c ON t.card_id = c.id
        WHERE t.id = :id LIMIT 1
    ";
    // Note: The schema for 'tabels' has 'user_id' which likely refers to the WAITER assigned to the table/order, not the customer.
    // The customer is implicitly linked via the card. If we need the customer explicitly, we'd join card -> user.
    // Let's adjust the query assuming 't.user_id' is the waiter and we get customer via card.
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id as waiter_user_id, t.card_id, t.menu_ordered,
               cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name, -- Customer linked via card
               w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter linked via t.user_id
               c.balance_credits as card_balance, c.total_credits as card_total
        FROM tabels t
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
        LEFT JOIN user w ON t.user_id = w.id -- Get waiter via tabels.user_id
        WHERE t.id = :id LIMIT 1
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $table_id_to_edit);
    $stmt->execute();

    $table_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$table_details) {
        $error_message = "Table record not found.";
    } elseif ($user_role === 'waiter' && $table_details['waiter_user_id'] != $user_id) {
         // Waiters can only edit tables assigned to them
         $error_message = "Access denied. You can only edit tables assigned to you.";
         $table_details = null; // Clear details to prevent editing
    }
} catch (PDOException $e) {
    $error_message = "Could not load table record details. Please try again later.";
    error_log("Edit Table Record page - fetch query error: " . $e->getMessage());
}

// Process form submission if table record details were found and user has permission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_details) {
    $table_name = $_POST['table_name'] ?? null;
    $t_qr = (int)($_POST['t_qr'] ?? 0);
    $t_s_w = (int)($_POST['t_s_w'] ?? 0);
    $new_waiter_user_id = $_POST['waiter_user_id'] ?? null; // The waiter assigned to this table/order
    $card_id = $_POST['card_id'] ?? null;
    $transaction_mode = $_POST['transaction_mode'] ?? 'Card';
    $ordered_items = $_POST['ordered_items'] ?? []; // Array of selected menu item IDs
    $quantities = $_POST['quantities'] ?? []; // Array of quantities for each item

    // Basic validation
    $errors = [];
    if (empty($table_name)) {
        $errors[] = "Please select a table name.";
    }
    if (empty($new_waiter_user_id) || $new_waiter_user_id == 0) {
        $errors[] = "Please select a waiter.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a valid card.";
    }
    if (empty($ordered_items) || count($ordered_items) === 0) {
        $errors[] = "Please select at least one menu item.";
    }

    // Validate quantities and rebuild the order description string
    $order_description_parts = [];
    if (!empty($ordered_items)) {
        foreach ($ordered_items as $index => $item_id) {
            $quantity = intval($quantities[$index] ?? 1);
            if ($quantity <= 0) {
                $errors[] = "Quantity for item ID $item_id must be greater than 0.";
                continue;
            }
            // In a real system, you'd fetch the description here or pass it differently.
            // For simplicity, we'll just use the ID. A better approach would be to pass descriptions from the form or fetch them.
            // Let's assume descriptions are passed or fetched implicitly.
            // We'll just build the string with IDs for now, or fetch them.
            // Fetch item descriptions based on IDs
            try {
                 $item_desc_query = "SELECT description FROM menu WHERE id = :item_id LIMIT 1";
                 $item_desc_stmt = $db->prepare($item_desc_query);
                 $item_desc_stmt->bindParam(':item_id', $item_id);
                 $item_desc_stmt->execute();
                 $item_row = $item_desc_stmt->fetch(PDO::FETCH_ASSOC);
                 if ($item_row) {
                      $order_description_parts[] = $item_row['description'] . " (x$quantity)";
                 } else {
                      $errors[] = "Invalid menu item ID found during update: $item_id";
                 }
            } catch (PDOException $e) {
                 $errors[] = "Error fetching item description for ID $item_id.";
                 error_log("Edit Table Record page - Item desc query error: " . $e->getMessage());
            }
        }
    }

    $order_description = implode(', ', $order_description_parts);

    if (empty($errors)) {
        try {
            // Prepare SQL query to update the table record
            $update_query = "
                UPDATE tabels SET t_name = :t_name, t_qr = :t_qr, t_s_w = :t_s_w, transaction_mode = :transaction_mode, user_id = :waiter_user_id, card_id = :card_id, menu_ordered = :menu_ordered WHERE id = :id
            ";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':t_name', $table_name);
            $update_stmt->bindParam(':t_qr', $t_qr, PDO::PARAM_INT);
            $update_stmt->bindParam(':t_s_w', $t_s_w, PDO::PARAM_INT);
            $update_stmt->bindParam(':transaction_mode', $transaction_mode);
            $update_stmt->bindParam(':waiter_user_id', $new_waiter_user_id, PDO::PARAM_INT); // Update the waiter assigned
            $update_stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':menu_ordered', $order_description);
            $update_stmt->bindParam(':id', $table_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Table record updated successfully!";
                // Optionally, refetch the table record details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $table_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update table record. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Table Record error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch lists of users (waiters), customers (via cards), and menu items for the edit form (only if initial fetch was successful and user has permission)
$waiters = [];
$customers = []; // This will be derived from users who own cards
$menu_items = [];

if ($table_details) {
    try {
        // Fetch all users with role 'waiter' for the waiter assignment dropdown
        $waiter_query = "SELECT id, first_name, last_name, username FROM user WHERE role = 'waiter' ORDER BY username";
        $waiter_stmt = $db->prepare($waiter_query);
        $waiter_stmt->execute();
        $waiters = $waiter_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all users linked via active cards to get potential customers
        // This joins user -> card and filters for active cards
        $customer_query = "
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.username
            FROM user u
            JOIN card c ON u.id = c.user_id
            WHERE c.c_status = 'Active'
            ORDER BY u.username
        ";
        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->execute();
        $customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all menu items for the order selection
        $menu_query = "SELECT id, category, description, menu_type FROM menu ORDER BY category, menu_type, description";
        $menu_stmt = $db->prepare($menu_query);
        $menu_stmt->execute();
        $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Could not load data for the form. Please try again later.";
        error_log("Edit Table Record page - Form data queries error: " . $e->getMessage());
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit Table Record</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Tables List</a>";
} elseif ($table_details) {
    $waiter_name_display = $table_details['waiter_first_name'] ? htmlspecialchars($table_details['waiter_first_name'] . ' ' . $table_details['waiter_last_name']) : htmlspecialchars($table_details['waiter_username']);
    $customer_name_display = $table_details['customer_first_name'] ? htmlspecialchars($table_details['customer_first_name'] . ' ' . $table_details['customer_last_name']) : htmlspecialchars($table_details['customer_username']);
    $card_link = "<a href='../../views/cards/view.php?id=" . $table_details['card_id'] . "'>Card #" . $table_details['card_id'] . "</a>";

    $content .= "<p>Editing details for Table Record ID: <strong>" . htmlspecialchars($table_details['id']) . "</strong>, Table: <strong>" . htmlspecialchars($table_details['t_name']) . "</strong>, Customer: <strong>" . $customer_name_display . "</strong>, Waiter: <strong>" . $waiter_name_display . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_cash = $table_details['transaction_mode'] === 'Cash' ? 'selected' : '';
    $type_selected_card = $table_details['transaction_mode'] === 'Card' ? 'selected' : '';
    $type_selected_upi = $table_details['transaction_mode'] === 'UPI' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='table_record_id_display' class='form-label'>Table Record ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='table_record_id_display' value='" . htmlspecialchars($table_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='table_name' class='form-label'>Table Name:</label>
                <select class='form-select' id='table_name' name='table_name' required>
                    <option value='A' " . ($table_details['t_name'] === 'A' ? 'selected' : '') . ">Table A</option>
                    <option value='B' " . ($table_details['t_name'] === 'B' ? 'selected' : '') . ">Table B</option>
                    <option value='C' " . ($table_details['t_name'] === 'C' ? 'selected' : '') . ">Table C</option>
                    <option value='D' " . ($table_details['t_name'] === 'D' ? 'selected' : '') . ">Table D</option>
                    <option value='E' " . ($table_details['t_name'] === 'E' ? 'selected' : '') . ">Table E</option>
                    <option value='F' " . ($table_details['t_name'] === 'F' ? 'selected' : '') . ">Table F</option>
                    <option value='G' " . ($table_details['t_name'] === 'G' ? 'selected' : '') . ">Table G</option>
                    <option value='H' " . ($table_details['t_name'] === 'H' ? 'selected' : '') . ">Table H</option>
                    <option value='I' " . ($table_details['t_name'] === 'I' ? 'selected' : '') . ">Table I</option>
                    <option value='J' " . ($table_details['t_name'] === 'J' ? 'selected' : '') . ">Table J</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='t_qr' class='form-label'>QR Code ID:</label>
                <input type='number' class='form-control' id='t_qr' name='t_qr' value='" . htmlspecialchars($table_details['t_qr']) . "' min='0' required>
            </div>
        </div>
         <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='t_s_w' class='form-label'>Seat/Switch Number:</label>
                <input type='number' class='form-control' id='t_s_w' name='t_s_w' value='" . htmlspecialchars($table_details['t_s_w']) . "' min='0'>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                    <option value='Cash' " . $type_selected_cash . ">Cash</option>
                    <option value='Card' " . $type_selected_card . ">Card</option>
                    <option value='UPI' " . $type_selected_upi . ">UPI</option>
                </select>
            </div>
        </div>
        <div class='row mb-3'>
            <div class='col-md-6'>
                <label for='waiter_user_id' class='form-label'>Assigned Waiter:</label>
                <select class='form-select' id='waiter_user_id' name='waiter_user_id' required>
                    <option value='0'>Choose Waiter...</option>
        ";

        foreach ($waiters as $waiter) {
             $selected_attr = $waiter['id'] == $table_details['waiter_user_id'] ? 'selected' : '';
             $content .= "<option value='" . $waiter['id'] . "' " . $selected_attr . ">" . htmlspecialchars($waiter['first_name'] . ' ' . $waiter['last_name'] . ' (' . $waiter['username'] . ')') . "</option>";
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6'>
                <label for='card_id' class='form-label'>Card Used:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        // Populate cards based on fetched customers (users with active cards)
        // This assumes card_id in the record corresponds to one of the active cards fetched earlier.
        // We'll list all active cards here for selection.
        try {
             $card_query_for_select = "
                 SELECT c.id, c.user_id, u.first_name, u.last_name, u.username, c.balance_credits
                 FROM card c
                 JOIN user u ON c.user_id = u.id
                 WHERE c.c_status = 'Active'
                 ORDER BY u.username, c.id
             ";
             $card_stmt_for_select = $db->prepare($card_query_for_select);
             $card_stmt_for_select->execute();
             $all_active_cards = $card_stmt_for_select->fetchAll(PDO::FETCH_ASSOC);

             foreach ($all_active_cards as $card) {
                 $selected_attr = $card['id'] == $table_details['card_id'] ? 'selected' : '';
                 $card_customer_name = htmlspecialchars($card['first_name'] . ' ' . $card['last_name'] . ' (' . $card['username'] . ')');
                 $content .= "<option value='" . $card['id'] . "' " . $selected_attr . ">Card ID: " . $card['id'] . " (Customer: $card_customer_name, Balance: ₹" . number_format($card['balance_credits'], 2) . ")</option>";
             }
        } catch (PDOException $e) {
             $error_message = "Could not load cards for selection. Please try again later.";
             error_log("Edit Table Record page - Cards for select query error: " . $e->getMessage());
             $content .= "<option value=''>Error loading cards</option>";
        }


        $content .= "
                </select>
            </div>
        </div>

        <div class='mb-3'>
            <h5>Update Ordered Menu Items:</h5>
            <div class='row'>
        ";

        if (!empty($menu_items)) {
            $current_category = '';
            // Pre-parse the existing menu_ordered string to identify selected items and quantities
            $existing_order_items = [];
            $order_parts = explode(', ', $table_details['menu_ordered']);
            foreach ($order_parts as $part) {
                 // Simple parsing: expects "Description (xQty)"
                 if (preg_match('/^(.+) \(x(\d+)\)$/', $part, $matches)) {
                     $desc = trim($matches[1]);
                     $qty = (int)$matches[2];
                     // Find the corresponding ID from the full menu list
                     foreach ($menu_items as $item) {
                         if ($item['description'] === $desc) {
                              $existing_order_items[$item['id']] = $qty;
                              break;
                         }
                     }
                 }
            }

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
                $is_checked = isset($existing_order_items[$item['id']]) ? 'checked' : '';
                $existing_quantity = $existing_order_items[$item['id']] ?? 1; // Default to 1 if not previously ordered
                $display_style = $is_checked ? 'display: inline-block;' : 'display: none;'; // Show quantity if checked initially

                $content .= "
                    <div class='col-md-6 col-lg-4 mb-2'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' value='" . $item['id'] . "' id='item_" . $item['id'] . "' name='ordered_items[]' onchange='toggleQuantityInput(" . $item['id'] . ")' $is_checked>
                            <label class='form-check-label' for='item_" . $item['id'] . "'>
                                " . htmlspecialchars($item['description']) . " <span class='badge " . $item_type_badge . "'>" . $item_type_text . "</span>
                            </label>
                            <input type='number' class='form-control form-control-sm mt-1' id='qty_" . $item['id'] . "' name='quantities[]' min='1' value='$existing_quantity' style='width: 60px; $display_style'>
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
            <button type='submit' class='btn btn-primary'>Update Table Record</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Tables List</a>
        <a href='view.php?id=" . $table_details['id'] . "' class='btn btn-info'>View Table Record Details</a>
    </div>

    <script>
        // Function to show/hide quantity input based on checkbox
        function toggleQuantityInput(itemId) {
            const checkbox = document.getElementById('item_' .concat(itemId));
            const qtyInput = document.getElementById('qty_'.concat(itemId));
            if (checkbox.checked) {
                qtyInput.style.display = 'inline-block'; // Show
            } else {
                qtyInput.style.display = 'none'; // Hide
                qtyInput.value = 1; // Reset to 1 when unchecked
            }
        }
    </script>
    ";
} else {
    // This case handles when the table record ID was provided but the record wasn't found or access denied
    $content .= "<p>Unable to load table record information or access denied.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Tables List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>