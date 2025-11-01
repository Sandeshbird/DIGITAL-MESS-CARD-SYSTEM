<?php
// views/users/index.php

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

$users = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all users
    $query = "SELECT id, first_name, last_name, email, ph_no, username, status, role, gender, created_at FROM user ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load users. Please try again later.";
    error_log("Users index page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Users</h2>
    <p>View, edit, and manage all registered users.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Add a button to create a new user
    $content .= "
    <div class='mb-3'>
        <a href='create.php' class='btn btn-success'>Add New User</a>
    </div>
    ";

    // Check if users exist
    if (!empty($users)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Gender</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($users as $user) {
            $status_badge = $user['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($user['id']) . "</td>
                        <td>" . htmlspecialchars($user['first_name']) . " " . htmlspecialchars($user['last_name']) . "</td>
                        <td>" . htmlspecialchars($user['username']) . "</td>
                        <td>" . htmlspecialchars($user['email']) . "</td>
                        <td>" . htmlspecialchars($user['ph_no']) . "</td>
                        <td>" . htmlspecialchars($user['role']) . "</td>
                        <td>" . $status_badge . "</td>
                        <td>" . htmlspecialchars($user['gender']) . "</td>
                        <td>" . htmlspecialchars($user['created_at']) . "</td>
                        <td>
                            <a href='view.php?id=" . $user['id'] . "' class='btn btn-sm btn-info'>View</a>
                            <a href='edit.php?id=" . $user['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <a href='delete.php?id=" . $user['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete user " . addslashes(htmlspecialchars($user['username'])) . "? This action cannot be undone.\")'>Delete</a>
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
        $content .= "<p>No users found.</p>";
    }
}

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>