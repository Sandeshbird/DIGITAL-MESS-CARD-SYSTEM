<?php
// views/tables/view.php

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
$table_id_to_view = $_GET['id'] ?? null;

if (!$table_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$table_details = null;
$error_message = '';

// Fetch the specific table record's details along with related user (customer via card) and card info
try {
    // Adjusted query: tabels.user_id is the waiter, customer is linked via the card's user_id
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id as waiter_user_id, t.card_id, t.menu_ordered,
               cu.username as customer_username, cu.first_name as customer_first_name, cu.last_name as customer_last_name, -- Customer linked via card
               w.username as waiter_username, w.first_name as waiter_first_name, w.last_name as waiter_last_name, -- Waiter linked via tabels.user_id
               c.balance_credits as card_balance, c.total_credits as card_total, c.c_status as card_status
        FROM tabels t
        LEFT JOIN card c ON t.card_id = c.id
        LEFT JOIN user cu ON c.user_id = cu.id -- Get customer via card's user_id
        LEFT JOIN user w ON t.user_id = w.id -- Get waiter via tabels.user_id
        WHERE t.id = :id LIMIT 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $table_id_to_view);
    $stmt->execute();

    $table_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$table_details) {
        $error_message = "Table record not found.";
    } elseif ($user_role === 'waiter' && $table_details['waiter_user_id'] != $user_id) {
         // Waiters can only view tables assigned to them
         $error_message = "Access denied. You can only view tables assigned to you.";
         $table_details = null; // Clear details to prevent viewing
    }
} catch (PDOException $e) {
    $error_message = "Could not load table record details. Please try again later.";
    error_log("View Table Record page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Table Record Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Tables List</a>";
} elseif ($table_details) {
    $waiter_name_display = $table_details['waiter_first_name'] ? htmlspecialchars($table_details['waiter_first_name'] . ' ' . $table_details['wait_name']) : htmlspecialchars($table_details['waiter_username']);
    $customer_name_display = $table_details['customer_first_name'] ? htmlspecialchars($table_details['customer_first_name'] . ' ' . $table_details['customer_last_name']) : htmlspecialchars($table_details['customer_username']);
    $customer_link = $table_details['card_id'] ? "<a href='../../views/users/view.php?id=" . $table_details['card_id'] . "'>" . $customer_name_display . "</a>" : 'N/A (Customer info via card)';
    $card_link = $table_details['card_id'] ? "<a href='../../views/cards/view.php?id=" . $table_details['card_id'] . "'>Card #" . $table_details['card_id'] . " (Status: " . $table_details['card_status'] . ")</a>" : 'N/A';
    $card_balance_display = $table_details['card_balance'] !== null ? '₹' . number_format($table_details['card_balance'], 2) : 'N/A';
    $card_total_display = $table_details['card_total'] !== null ? '₹' . number_format($table_details['card_total'], 2) : 'N/A';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Table Record Information: ID " . htmlspecialchars($table_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Record ID:</th>
                    <td>" . htmlspecialchars($table_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Table Name:</th>
                    <td>" . htmlspecialchars($table_details['t_name']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>QR Code ID:</th>
                    <td>" . htmlspecialchars($table_details['t_qr']) . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Seat/Switch Number:</th>
                    <td>" . htmlspecialchars($table_details['t_s_w']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Assigned Waiter:</th>
                    <td>" . htmlspecialchars($waiter_name_display) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Customer (via Card):</th>
                    <td>" . $customer_link . "</td>
                </tr>
                <tr>
                    <th scope='row'>Card Used:</th>
                    <td>" . $card_link . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Balance (at time of order):</th>
                    <td>" . $card_balance_display . "</td>
                </tr>
                 <tr>
                    <th scope='row'>Card Total Credits (at time of order):</th>
                    <td>" . $card_total_display . "</td>
                </tr>
                <tr>
                    <th scope='row'>Order Time:</th>
                    <td>" . htmlspecialchars($table_details['time']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Transaction Mode:</th>
                    <td>" . htmlspecialchars($table_details['transaction_mode']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Menu Ordered:</th>
                    <td>" . nl2br(htmlspecialchars($table_details['menu_ordered'])) . "</td> <!-- nl2br to preserve line breaks if any -->
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Tables List</a>
        <a href='edit.php?id=" . $table_details['id'] . "' class='btn btn-warning'>Edit Table Record</a>
    </div>
    ";
} else {
    // This case handles when the table record ID was provided but the record wasn't found or access denied
    $content .= "<p>Unable to load table record information or access denied.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Tables List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>