<?php
// views/menu/create.php

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

$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_type = $_POST['menu_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $created_at = date('Y-m-d H:i:s'); // Use current time

    // Basic validation
    $errors = [];
    if (empty($menu_type) || !in_array($menu_type, ['Veg', 'Non-veg'])) {
        $errors[] = "Please select a valid menu type (Veg/Non-veg).";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (empty($category) || !in_array($category, ['Breakfast', 'Lunch', 'Dinner'])) {
        $errors[] = "Please select a valid category (Breakfast/Lunch/Dinner).";
    }

    if (empty($errors)) {
        try {
            // Prepare SQL query to insert new menu item
            // Note: The schema does not include a 'price' field. You might want to add one for a complete system.
            $insert_query = "INSERT INTO menu (menu_type, description, category, created_at) VALUES (:menu_type, :description, :category, :created_at)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':menu_type', $menu_type);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':created_at', $created_at);

            if ($insert_stmt->execute()) {
                $new_item_id = $db->lastInsertId();
                $success_message = "Menu item created successfully! Item ID: $new_item_id";
                // Optionally, redirect to the menu list page after successful creation
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to create menu item. Please try again.";
            }
        } catch (PDOException $e) {
            // Log the error or handle it appropriately in production
            $error_message = "Database error. Please try again later.";
            error_log("Create Menu Item error: " . $e->getMessage()); // Log the actual error
        }
    } else {
        $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
    }
}

// Prepare the specific content for this page
$content = "
    <h2>Create New Menu Item</h2>
    <p>Add a new item to the menu.</p>
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
        <div class='mb-3'>
            <label for='menu_type' class='form-label'>Menu Type:</label>
            <select class='form-select' id='menu_type' name='menu_type' required>
                <option value='' " . (($_POST['menu_type'] ?? '') === '' ? 'selected' : '') . ">Select Type...</option>
                <option value='Veg' " . (($_POST['menu_type'] ?? '') === 'Veg' ? 'selected' : '') . ">Veg</option>
                <option value='Non-veg' " . (($_POST['menu_type'] ?? '') === 'Non-veg' ? 'selected' : '') . ">Non-Veg</option>
            </select>
        </div>
        <div class='mb-3'>
            <label for='category' class='form-label'>Category:</label>
            <select class='form-select' id='category' name='category' required>
                <option value='' " . (($_POST['category'] ?? '') === '' ? 'selected' : '') . ">Select Category...</option>
                <option value='Breakfast' " . (($_POST['category'] ?? '') === 'Breakfast' ? 'selected' : '') . ">Breakfast</option>
                <option value='Lunch' " . (($_POST['category'] ?? '') === 'Lunch' ? 'selected' : '') . ">Lunch</option>
                <option value='Dinner' " . (($_POST['category'] ?? '') === 'Dinner' ? 'selected' : '') . ">Dinner</option>
            </select>
        </div>
        <div class='mb-3'>
            <label for='description' class='form-label'>Description:</label>
            <textarea class='form-control' id='description' name='description' rows='3' required>" . htmlspecialchars($_POST['description'] ?? '') . "</textarea>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Create Menu Item</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Menu List</a>
    </div>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>