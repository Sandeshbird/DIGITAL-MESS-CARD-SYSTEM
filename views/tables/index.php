<?php
// views/tables/index.php

// Include the authentication check and session details
// This page should typically be accessible by admins and waiters
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

$tables = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all table records with related user and card info
    // Joining with user and card tables for more context
    $query = "
        SELECT t.id, t.t_name, t.t_qr, t.t_s_w, t.time, t.transaction_mode, t.user_id, t.card_id, t.menu_ordered,
               u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name,
               c.balance_credits as card_balance, c.total_credits as card_total
        FROM tabels t
        LEFT JOIN user u ON t.user_id = u.id
        LEFT JOIN card c ON t.card_id = c.id
    ";

    // Waiters might only see tables assigned to them (where user_id matches their ID)
    // Admins see all tables
    if ($user_role === 'waiter') {
         $query .= " WHERE t.user_id = :user_id "; // Filter for waiter's assigned tables
         $stmt = $db->prepare($query);
         $stmt->bindParam(':user_id', $user_id);
    } else { // Admin
         $query .= " ORDER BY t.time DESC "; // Order by time for admin
         $stmt = $db->prepare($query);
    }

    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load table records. Please try again later.";
    error_log("Tables index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Tables</h2>
    <p>View " . ($user_role === 'admin' ? 'all' : 'your assigned') . " table records and orders.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new table record/order (maybe for admin, or for waiter to take a new order)
    if ($user_role === 'admin') {
        $content .= "
        <div class='mb-3'>
            <a href='create.php' class='btn btn-success'>Add New Table Record</a>
        </div>
        ";
    } elseif ($user_role === 'waiter') {
         $content .= "
        <div class='mb-3'>
            <a href='../../views/dashboard/waiter/take_order.php' class='btn btn-success'>Take New Order</a> <!-- Link to the waiter's order-taking page -->
        </div>
        ";
    }


    // Check if table records exist
    if (!empty($tables)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Table Name</th>
                        <th>QR Code ID</th>
                        <th>Assigned Waiter (User)</th>
                        <th>Card Used</th>
                        <th>Order Time</th>
                        <th>Transaction Mode</th>
                        <th>Menu Ordered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($tables as $table) {
            $user_name_display = $table['user_first_name'] ? htmlspecialchars($table['user_first_name'] . ' ' . $table['user_last_name']) : htmlspecialchars($table['user_username']);
            $user_link = $table['user_id'] ? "<a href='../../views/users/view.php?id=" . $table['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
            $card_link = $table['card_id'] ? "<a href='../../views/cards/view.php?id=" . $table['card_id'] . "'>Card #" . $table['card_id'] . "</a>" : 'N/A';
            // Truncate long menu descriptions for display in the table
            $menu_display = strlen($table['menu_ordered']) > 100 ? substr(htmlspecialchars($table['menu_ordered']), 0, 97) . '...' : htmlspecialchars($table['menu_ordered']);

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($table['id']) . "</td>
                        <td>" . htmlspecialchars($table['t_name']) . "</td>
                        <td>" . htmlspecialchars($table['t_qr']) . "</td>
                        <td>" . $user_link . "</td>
                        <td>" . $card_link . "</td>
                        <td>" . htmlspecialchars($table['time']) . "</td>
                        <td>" . htmlspecialchars($table['transaction_mode']) . "</td>
                        <td>" . $menu_display . "</td>
                        <td>
                            <a href='view.php?id=" . $table['id'] . "' class='btn btn-sm btn-info'>View</a>
                            <a href='edit.php?id=" . $table['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <!-- Delete button might be restricted to admin only -->
                            " . ($user_role === 'admin' ? "<a href='delete.php?id=" . $table['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete table record ID " . $table['id'] . "? This action cannot be undone.\")'>Delete</a>" : "") . "
                        </td>
                    </tr>
            ";
        }

        $content .= "
                </tbody>
            </table>
        </div>
        ";
    } else {
        $content .= "<p>No table records found.</p>";
    }
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>