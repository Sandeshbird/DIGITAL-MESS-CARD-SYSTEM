<?php
// views/dashboard/admin/users.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php"); // Redirect to login if not authenticated as admin
    exit;
}

$user_name = $_SESSION['username'] ?? 'Admin';

// Include database configuration
require_once '../../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$users = [];
$error_message = '';

try {
    // Prepare SQL query to fetch all users (students, waiters, admins)
    $query = "
        SELECT id, first_name, last_name, email, username, role, gender, status, created_at
        FROM user
        ORDER BY created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error or handle it appropriately in production
    $error_message = "Could not load users. Please try again later.";
    error_log("Admin Users page query error: " . $e->getMessage()); // Log the actual error
}

// Prepare the specific content for this page
$content = "
    <h2>Manage Users</h2>
    <p>View and manage all registered users.</p>
";

// Display error message if query failed
if ($error_message) {
    $content .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
} else {
    // Check if users exist
    if (!empty($users)) {
        $content .= "
        <div class='table-responsive'>
            <table class='table table-striped table-hover'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($users as $user) {
            $status_badge = $user['status'] == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
            $role_badge = '';
            switch ($user['role']) {
                case 'admin':
                    $role_badge = '<span class="badge bg-primary">Admin</span>';
                    break;
                case 'waiter':
                    $role_badge = '<span class="badge bg-warning text-dark">Waiter</span>';
                    break;
                case 'user':
                default:
                    $role_badge = '<span class="badge bg-secondary">User</span>';
                    break;
            }
            $content .= "
                    <tr>
                        <td>" . htmlspecialchars($user['id']) . "</td>
                        <td>" . htmlspecialchars($user['first_name']) . "</td>
                        <td>" . htmlspecialchars($user['last_name']) . "</td>
                        <td>" . htmlspecialchars($user['email']) . "</td>
                        <td>" . htmlspecialchars($user['username']) . "</td>
                        <td>" . $role_badge . "</td>
                        <td>" . htmlspecialchars($user['gender']) . "</td>
                        <td>" . $status_badge . "</td>
                        <td>" . htmlspecialchars($user['created_at']) . "</td>
                        <td>
                            <!-- View details (maybe a modal or a dedicated view page) -->
                            <!-- <a href='view.php?id=" . $user['id'] . "' class='btn btn-sm btn-info'>View</a> -->
                            <!-- Edit user details (status, role?) -->
                            <a href='edit.php?id=" . $user['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                            <!-- Consider adding a delete button with confirmation -->
                            <!-- <a href='delete.php?id=" . $user['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this user?\")'>Delete</a> -->
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
include '../../layouts/app.php'; // Adjust path as needed to point to the layout file

?>