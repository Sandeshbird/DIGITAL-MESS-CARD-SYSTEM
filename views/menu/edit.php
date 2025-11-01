<?php
// views/menu/edit.php

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
$menu_id_to_edit = $_GET['id'] ?? null;

if (!$menu_id_to_edit) {
    header("Location: index.php"); // Redirect if no ID provided
    exit;
}

$menu_details = null;
$success_message = '';
$error_message = '';

// Fetch the specific menu item's details
try {
    $query = "SELECT id, menu_type, description, category, created_at FROM menu WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $menu_id_to_edit);
    $stmt->execute();

    $menu_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu_details) {
        $error_message = "Menu item not found.";
    }
} catch (PDOException $e) {
    $error_message = "Could not load menu item details. Please try again later.";
    error_log("Edit Menu Item page - fetch query error: " . $e->getMessage());
}

// Process form submission if menu item details were found
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $menu_details) {
    $menu_type = $_POST['menu_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';

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
            // Prepare SQL query to update the menu item
            $update_query = "UPDATE menu SET menu_type = :menu_type, description = :description, category = :category WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':menu_type', $menu_type);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':id', $menu_id_to_edit);

            if ($update_stmt->execute()) {
                $success_message = "Menu item updated successfully!";
                // Optionally, refetch the menu item details to show updated info
                 $stmt->execute(); // Re-execute the fetch query
                 $menu_details = $stmt->fetch(PDO::FETCH_ASSOC); // Update the local variable
            } else {
                $error_message = "Failed to update menu item. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Edit Menu Item error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Edit Menu Item</h2>
";

if ($error_message) {
     $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
     $content .= "<a href='index.php' class='btn btn-secondary'>Back to Menu List</a>";
} elseif ($menu_details) {
    $content .= "<p>Editing details for Menu Item ID: <strong>" . htmlspecialchars($menu_details['id']) . "</strong></p>";

    if ($success_message) {
        $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }

    $type_selected_veg = $menu_details['menu_type'] === 'Veg' ? 'selected' : '';
    $type_selected_nonveg = $menu_details['menu_type'] === 'Non-veg' ? 'selected' : '';
    $cat_selected_breakfast = $menu_details['category'] === 'Breakfast' ? 'selected' : '';
    $cat_selected_lunch = $menu_details['category'] === 'Lunch' ? 'selected' : '';
    $cat_selected_dinner = $menu_details['category'] === 'Dinner' ? 'selected' : '';

    $content .= "
    <form method='post' action=''>
        <div class='mb-3'>
            <label for='menu_id_display' class='form-label'>Menu Item ID (Read-only):</label>
            <input type='text' class='form-control-plaintext' id='menu_id_display' value='" . htmlspecialchars($menu_details['id']) . "' readonly>
        </div>
        <div class='row'>
            <div class='col-md-6 mb-3'>
                <label for='menu_type' class='form-label'>Menu Type:</label>
                <select class='form-select' id='menu_type' name='menu_type' required>
                    <option value='Veg' " . $type_selected_veg . ">Veg</option>
                    <option value='Non-veg' " . $type_selected_nonveg . ">Non-Veg</option>
                </select>
            </div>
            <div class='col-md-6 mb-3'>
                <label for='category' class='form-label'>Category:</label>
                <select class='form-select' id='category' name='category' required>
                    <option value='Breakfast' " . $cat_selected_breakfast . ">Breakfast</option>
                    <option value='Lunch' " . $cat_selected_lunch . ">Lunch</option>
                    <option value='Dinner' " . $cat_selected_dinner . ">Dinner</option>
                </select>
            </div>
        </div>
        <div class='mb-3'>
            <label for='description' class='form-label'>Description:</label>
            <textarea class='form-control' id='description' name='description' rows='3' required>" . htmlspecialchars($menu_details['description']) . "</textarea>
        </div>
        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Update Menu Item</button>
        </div>
    </form>
    <div class='mt-3'>
        <a href='index.php' class='btn btn-secondary'>Back to Menu List</a>
        <a href='view.php?id=" . $menu_details['id'] . "' class='btn btn-info'>View Menu Item Details</a>
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