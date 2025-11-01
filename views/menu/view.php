<?php
// views/menu/view.php

// Include the authentication check and session details
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

// Get menu item ID from query string
$menu_id_to_view = $_GET['id'] ?? null;

if (!$menu_id_to_view) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$menu_details = null;
$error_message = '';

// Fetch the specific menu item's details
try {
    $query = "SELECT id, menu_type, description, category, created_at FROM menu WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $menu_id_to_view);
    $stmt->execute();

    $menu_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu_details) {
        $error_message = "Menu item not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load menu item details. Please try again later.";
    error_log("View Menu Item page - fetch query error: " . $e->getMessage());
}

// Prepare the specific content for this page
$content = "
    <h2>View Menu Item Details</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Menu List</a>";
} elseif ($menu_details) {
    $type_badge = $menu_details['menu_type'] === 'Veg' ? '<span class="badge bg-success">Veg</span>' : '<span class="badge bg-danger">Non-Veg</span>';
    $category_badge = '<span class="badge bg-info">' . htmlspecialchars($menu_details['category']) . '</span>';

    $content .= "
    <div class='card'>
        <div class='card-header'>
            <h5>Menu Item Information: ID " . htmlspecialchars($menu_details['id']) . "</h5>
        </div>
        <div class='card-body'>
            <table class='table table-borderless'>
                <tr>
                    <th scope='row'>Item ID:</th>
                    <td>" . htmlspecialchars($menu_details['id']) . "</td>
                </tr>
                <tr>
                    <th scope='row'>Type:</th>
                    <td>" . $type_badge . "</td>
                </tr>
                <tr>
                    <th scope='row'>Category:</th>
                    <td>" . $category_badge . "</td>
                </tr>
                <tr>
                    <th scope='row'>Description:</th>
                    <td>" . nl2br(htmlspecialchars($menu_details['description'])) . "</td> <!-- nl2br to preserve line breaks -->
                </tr>
                <tr>
                    <th scope='row'>Created At:</th>
                    <td>" . htmlspecialchars($menu_details['created_at']) . "</td>
                </tr>
            </table>
        </div>
    </div>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Menu List</a>
        <a href='edit.php?id=" . $menu_details['id'] . "' class='btn btn-warning'>Edit Menu Item</a>
    </div>
    ";
} else {
    // This case handles when the menu item ID was provided but the item wasn't found (error_message is set above)
    $content .= "<p>Unable to load menu item information.</p>";
    $content .= "<a href='index.php' class='btn btn-secondary'>Back to Menu List</a>";
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>