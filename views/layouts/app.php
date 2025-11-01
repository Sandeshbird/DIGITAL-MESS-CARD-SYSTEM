<?php
// views/layouts/app.php — Final Layout with Dark Mode (Dim Orange Theme)

// Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$user_name = $_SESSION['username'] ?? 'User';

$current_page = basename($_SERVER['PHP_SELF']);
$full_path = $_SERVER['SCRIPT_NAME'];
$base_url = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mess Management — <?= htmlspecialchars(ucfirst($user_role)) ?> Dashboard</title>

<!-- CSS Libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
:root {
  --brand: #ffb26b;
  --brand-dark: #f29b50;
  --bg: #f8f9fb;
  --text: #222;
  --muted: #6c757d;
  --sidebar-width: 280px;
  --sidebar-mini: 72px;
}

body {
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  color: var(--text);
  padding-top: 64px;
  transition: background 0.4s ease, color 0.4s ease;
}

/* Dark mode vars */
body.dark-mode {
  --bg: #1e1e1e;
  --text: #e6e6e6;
  --muted: #b5b5b5;
  background: var(--bg);
  color: var(--text);
}

/* Navbar */
.navbar-fixed {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 64px;
  background: linear-gradient(90deg, var(--brand) 0%, var(--brand-dark) 100%);
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  z-index: 1100;
}
.navbar-brand {
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.navbar-brand:hover { color: #fff; }
.navbar .dropdown-menu {
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Sidebar */
.sidebar {
  position: fixed;
  top: 64px; left: 0;
  height: calc(100vh - 64px);
  width: var(--sidebar-width);
  background: #fff;
  border-right: 1px solid #eaeaea;
  box-shadow: 2px 0 6px rgba(0,0,0,0.04);
  overflow-y: auto;
  transition: width .3s ease, transform .3s ease, background .3s ease;
  z-index: 1050;
}
body.dark-mode .sidebar {
  background: #262626;
  border-color: #333;
}
.sidebar.collapsed { width: var(--sidebar-mini); }
.sidebar .brand-area {
  display: flex;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid #eee;
}
.sidebar .brand-text {
  font-weight: 600;
  color: var(--text);
  margin-left: .5rem;
}
.sidebar.collapsed .brand-text,
.sidebar.collapsed h6 { display: none !important; }

/* Nav links */
.sidebar .nav {
  padding: .5rem 0 1rem;
}
.sidebar .nav-link {
  display: flex;
  align-items: center;
  gap: .75rem;
  color: var(--muted);
  padding: .65rem 1rem;
  margin: .25rem .5rem;
  border-radius: 8px;
  text-decoration: none;
  transition: all .2s ease;
}
.sidebar .nav-link i {
  width: 26px;
  text-align: center;
}
.sidebar .nav-link:hover {
  background: rgba(255,178,107,0.15);
  color: var(--brand-dark);
}
.sidebar .nav-link.active {
  background: rgba(255,178,107,0.25);
  color: var(--brand-dark);
  font-weight: 600;
  border-left: 3px solid var(--brand-dark);
}

/* Main */
.main-content {
  margin-left: var(--sidebar-width);
  padding: 1.75rem;
  transition: margin-left .3s ease;
}
.sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-mini); }

/* Dark mode specific */
body.dark-mode .navbar-fixed {
  background: linear-gradient(90deg, #ff9f45 0%, #ff7b00 100%);
}
body.dark-mode .sidebar .nav-link:hover {
  background: rgba(255,178,107,0.25);
}
body.dark-mode .card, body.dark-mode .page-title-box {
  background: #2b2b2b;
  box-shadow: 0 0 10px rgba(255,255,255,0.05);
}

/* Cards */
.page-title-box {
  background: #fff;
  border-left: 6px solid var(--brand);
  padding: 1.25rem;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  margin-bottom: 1.25rem;
}
.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  transition: all .25s ease;
}
.card:hover { transform: translateY(-3px); }

/* Buttons */
.btn-brand {
  background: var(--brand);
  color: #fff;
  border-radius: 8px;
  border: none;
  padding: .55rem 1rem;
}
.btn-brand:hover { background: var(--brand-dark); color: #fff; }

/* Mobile */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.active { transform: translateX(0); }
  .main-content { margin-left: 0; }
}
</style>
</head>
<body>

<!-- Navbar -->
<header class="navbar-fixed d-flex align-items-center justify-content-between px-3">
  <div class="d-flex align-items-center">
    <button id="sidebarToggle" class="btn btn-link text-white me-2" style="font-size:1.3rem;"><i class="bi bi-list"></i></button>
    <a class="navbar-brand" href="<?= 
        ($user_role==='admin' ? '../dashboard/admin/index.php' :
        ($user_role==='waiter' ? '../dashboard/waiter/index.php' : 'index.php')) ?>">
      <i class="bi bi-shop-window"></i> Mess Management
    </a>
  </div>

  <div class="d-flex align-items-center gap-3">
    <!-- Dark Mode Toggle -->
    <button id="themeToggle" class="btn btn-link text-white" style="font-size:1.3rem;">
      <i class="bi bi-moon"></i>
    </button>

    <!-- Profile -->
    <div class="dropdown">
      <a href="#" class="text-white dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user_name) ?>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="../../../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- Sidebar -->
<aside id="appSidebar" class="sidebar">
  <div class="brand-area">
    <i class="bi bi-shop text-warning" style="font-size:1.25rem;color:var(--brand)"></i>
    <span class="brand-text">Mess Management</span>
  </div>

  <h6 class="px-3 mt-3 mb-2 text-uppercase fw-bold" style="color:#999;">Navigation</h6>
  <ul class="nav flex-column">
    <li><a class="nav-link <?= $current_page==='index.php'?'active':'' ?>" href="<?= 
        ($user_role==='admin' ? '../dashboard/admin/index.php' :
        ($user_role==='waiter' ? '../dashboard/waiter/index.php' : 'index.php')) ?>">
        <i class="bi bi-speedometer2"></i><span class="label">Dashboard</span></a></li>

    <?php if ($user_role==='waiter'): ?>
      <li><a class="nav-link <?= strpos($full_path,'orders')!==false?'active':'' ?>" href="../dashboard/waiter/orders.php"><i class="bi bi-list-check"></i><span class="label">Orders</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'tables')!==false?'active':'' ?>" href="../dashboard/waiter/tables.php"><i class="bi bi-grid"></i><span class="label">Tables</span></a></li>
    <?php endif; ?>

    <?php if ($user_role==='admin'): ?>
      <li><a class="nav-link <?= strpos($full_path,'users')!==false?'active':'' ?>" href="../dashboard/admin/users.php"><i class="bi bi-people"></i><span class="label">Users</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'menu')!==false?'active':'' ?>" href="../dashboard/admin/menu.php"><i class="bi bi-list"></i><span class="label">Menu</span></a></li>
      <li><a class="nav-link <?= strpos($full_path,'transactions')!==false?'active':'' ?>" href="../dashboard/admin/transactions.php"><i class="bi bi-receipt"></i><span class="label">Transactions</span></a></li>
    <?php endif; ?>
  </ul>
</aside>

<!-- Main -->
<main class="main-content fade-in" id="mainContent">
  <?= $content ?? ''; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('appSidebar');
const toggle = document.getElementById('sidebarToggle');
const themeToggle = document.getElementById('themeToggle');

// Sidebar toggle
toggle.addEventListener('click', () => {
  if (window.innerWidth <= 768) {
    sidebar.classList.toggle('active');
  } else {
    sidebar.classList.toggle('collapsed');
  }
});

// Close sidebar when clicking outside (mobile)
document.addEventListener('click', e => {
  if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
    sidebar.classList.remove('active');
  }
});

// Dark mode persistence
function setTheme(dark) {
  if (dark) {
    document.body.classList.add('dark-mode');
    themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
    localStorage.setItem('theme', 'dark');
  } else {
    document.body.classList.remove('dark-mode');
    themeToggle.innerHTML = '<i class="bi bi-moon"></i>';
    localStorage.setItem('theme', 'light');
  }
}

// Initialize theme
if (localStorage.getItem('theme') === 'dark') {
  setTheme(true);
}

// Toggle on click
themeToggle.addEventListener('click', () => {
  setTheme(!document.body.classList.contains('dark-mode'));
});
</script>
</body>
</html>
