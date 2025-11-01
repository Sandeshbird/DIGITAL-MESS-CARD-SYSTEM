<?php
// views/dashboard/waiter/tables.php - Waiter Tables Management (Soft Orange Theme)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['username'] ?? 'Waiter';

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch tables
$tables = [];
try {
    $stmt = $db->prepare("SELECT * FROM tables ORDER BY t_name ASC");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tables: " . $e->getMessage());
}

// Count by status
$available_count = $occupied_count = $reserved_count = 0;
foreach ($tables as $table) {
    if ($table['status'] === 'available') $available_count++;
    elseif ($table['status'] === 'occupied') $occupied_count++;
    elseif ($table['status'] === 'reserved') $reserved_count++;
}

ob_start();
?>

<style>
:root {
  --brand: #ffb26b;
  --brand-dark: #f2a75a;
  --green: #45c17a;
  --orange: #ffb26b;
  --purple: #8b6ddf;
  --dark-bg: #1e1e1e;
  --dark-text: #f8f9fa;
}

/* ===== Base Layout ===== */
.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.04);
  background: #fff;
  color: #232323;
}
.page-title-box h4 {
  font-weight: 600;
  color: #333;
}

/* ===== Stat Cards ===== */
.stat-card {
  border-radius: 14px;
  padding: 1.5rem;
  color: #fff;
  text-align: center;
  box-shadow: 0 8px 22px rgba(0,0,0,0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(0,0,0,0.1);
}
.stat-card i {
  font-size: 2.2rem;
  margin-bottom: 0.5rem;
}
.stat-available {
  background: linear-gradient(45deg, #45c17a, #3aa46d);
}
.stat-occupied {
  background: linear-gradient(45deg, #ffb26b, #f2a75a);
}
.stat-reserved {
  background: linear-gradient(45deg, #8b6ddf, #6f42c1);
}

/* ===== Table ===== */
.table th {
  background: #f9fafb;
  color: #555;
  font-weight: 600;
  border-bottom: 2px solid #eee;
}
.table td {
  vertical-align: middle;
  padding: 0.75rem;
}
.table-hover tbody tr:hover {
  background: rgba(255,178,107,0.08);
}

/* ===== Buttons ===== */
.btn-brand {
  background: var(--brand);
  border: none;
  color: #fff;
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.2s ease;
}
.btn-brand:hover {
  background: var(--brand-dark);
  color: #fff;
}

/* ===== Dark Mode Support ===== */
[data-theme='dark'] body {
  background: var(--dark-bg);
  color: var(--dark-text);
}
[data-theme='dark'] .card {
  background: #2a2a2a;
  color: var(--dark-text);
  box-shadow: 0 0 0 1px rgba(255,255,255,0.05);
}
[data-theme='dark'] .page-title-box h4,
[data-theme='dark'] .card-header h5,
[data-theme='dark'] .card-header strong {
  color: #ffffff !important;
}
[data-theme='dark'] .table th {
  background: #2a2a2a;
  color: #eee;
}
[data-theme='dark'] .table td {
  color: #ddd;
}
[data-theme='dark'] .table-hover tbody tr:hover {
  background: rgba(255,178,107,0.12);
}
[data-theme='dark'] .text-muted {
  color: #aaa !important;
}
[data-theme='dark'] h4, [data-theme='dark'] h5, [data-theme='dark'] small, 
[data-theme='dark'] p, [data-theme='dark'] span {
  color: #fff !important;
}
</style>

<div class="container-fluid">

  <div class="page-title-box d-flex align-items-center justify-content-between">
    <h4 class="page-title mb-0">Table Management</h4>
    <a href="index.php" class="btn btn-sm btn-brand">
      <i class="bi bi-house me-1"></i> Dashboard
    </a>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card stat-available">
        <i class="bi bi-grid"></i>
        <h3 class="mb-0"><?= $available_count ?></h3>
        <small>Available Tables</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card stat-occupied">
        <i class="bi bi-people-fill"></i>
        <h3 class="mb-0"><?= $occupied_count ?></h3>
        <small>Occupied Tables</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card stat-reserved">
        <i class="bi bi-bookmark-check"></i>
        <h3 class="mb-0"><?= $reserved_count ?></h3>
        <small>Reserved Tables</small>
      </div>
    </div>
  </div>

  <!-- Table List -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bi bi-table me-2 text-muted"></i>All Tables</h5>
      <span class="badge bg-warning text-dark"><?= count($tables) ?> Tables</span>
    </div>
    <div class="card-body">
      <?php if (empty($tables)): ?>
        <div class="text-center py-5">
          <i class="bi bi-table" style="font-size: 3rem; color: #ccc;"></i>
          <h5 class="mt-3 mb-1">No Tables Found</h5>
          <p class="text-muted">Tables will appear here once added by admin.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>QR Code</th>
                <th>Table Name</th>
                <th>Status</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tables as $t): ?>
              <tr>
                <td><?= htmlspecialchars($t['t_qr']); ?></td>
                <td><strong>Table <?= htmlspecialchars($t['t_name']); ?></strong></td>
                <td>
                  <?php if ($t['status'] === 'available'): ?>
                    <span class="badge bg-success">Available</span>
                  <?php elseif ($t['status'] === 'occupied'): ?>
                    <span class="badge bg-warning text-dark">Occupied</span>
                  <?php elseif ($t['status'] === 'reserved'): ?>
                    <span class="badge bg-info text-dark">Reserved</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Unknown</span>
                  <?php endif; ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($t['created_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Table Status Guide -->
  <div class="card">
    <div class="card-header">
      <i class="bi bi-info-circle me-2 text-muted"></i><strong>Table Status Guide</strong>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="d-flex align-items-center mb-2">
            <span class="badge bg-success me-2">Available</span>
            <span>Table is free and ready for customers.</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-flex align-items-center mb-2">
            <span class="badge bg-warning text-dark me-2">Occupied</span>
            <span>Table has customers dining.</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-flex align-items-center mb-2">
            <span class="badge bg-info text-dark me-2">Reserved</span>
            <span>Table reserved for future use.</span>
          </div>
        </div>
      </div>
      <p class="mt-2 mb-0"><strong>Note:</strong> Table status updates automatically when orders are served or cancelled.</p>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include '../../layouts/app.php';
?>
