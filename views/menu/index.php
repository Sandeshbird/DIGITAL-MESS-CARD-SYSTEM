<?php
// views/menu/index.php

// Include the authentication check and session details
// This page should typically be accessible only by admins
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as admin
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$menu_items = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all menu items
    $query = "SELECT id, menu_type, description, category, created_at FROM menu ORDER BY category, menu_type, created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load menu items. Please try again later.";
    error_log("Menu index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Menu</h2>
    <p>View, edit, and manage menu items.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new menu item
    $content .= "
    <div class='mb-3'>
        <a href='create.php' class='btn btn-success'>Add New Menu Item</a>
    </div>
    ";

    // Check if menu items exist
    if (!empty($menu_items)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($menu_items as $item) {
            $type_badge = $item['menu_type'] === 'Veg' ? '<span class="badge bg-success">Veg</span>' : '<span class="badge bg-danger">Non-Veg</span>';
            $category_badge = '<span class="badge bg-info">' . htmlspecialchars($item['category']) . '</span>';
            // Truncate long descriptions for display in the table
            $description_display = strlen($item['description']) > 100 ? substr(htmlspecialchars($item['description']), 0, 97) . '...' : htmlspecialchars($item['description']);

            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($item['id']) . "</td>
                        <td>" . $type_badge . "</td>
                        <td>" . $category_badge . "</td>
                        <td>" . $description_display . "</td>
                        <td>" . htmlspecialchars($item['created_at']) . "</td>
                        <td>
                            <a href='view.php?id=" . $item['id'] . "' class='btn btn-sm btn-info'>View</a>
                            <a href='edit.php?id=" . $item['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <a href='delete.php?id=" . $item['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete menu item \"" . addslashes(htmlspecialchars($item['description'])) . "\"?\")'>Delete</a>
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
        $content .= "<p>No menu items found.</p>";
    }
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>