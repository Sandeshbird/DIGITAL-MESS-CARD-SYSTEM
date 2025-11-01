<?php
// views/dashboard/waiter/scan_qr.php - Working QR Scanner (Camera + Manual Entry)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Waiter';

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle QR code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_qr'])) {
    $qr_code = trim($_POST['qr_code'] ?? '');

    if ($qr_code === '') {
        $message = 'Please enter or scan a QR code.';
        $message_type = 'danger';
    } elseif (strpos($qr_code, 'TEMP_') === 0) {
        $parts = explode('_', $qr_code);
        if (count($parts) >= 3) {
            $_SESSION['temp_qr'] = $qr_code;
            $_SESSION['qr_scanned_at'] = time();
            header("Location: orders.php");
            exit;
        } else {
            $message = 'Invalid QR code format.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Invalid QR code.';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Scan QR - Mess Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
      padding-top: 60px;
      font-family: 'Poppins', sans-serif;
    }
    .card { border: none; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.04); }
    .btn-brand { background: #ffb26b; color: #fff; border-radius: 8px; border:none; font-weight: 500; }
    .btn-brand:hover { background: #f2a75a; color:#fff; }
  </style>
</head>
<body>
  <nav class="navbar navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Mess Management - Waiter</a>
      <div class="navbar-nav ms-auto d-flex flex-row gap-3">
        <a class="nav-link text-white" href="index.php">Dashboard</a>
        <a class="nav-link text-white" href="../../../logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container mt-5 pt-4">
    <h4 class="mb-4">Scan User QR Code</h4>

    <?php if ($message): ?>
      <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-qr-code-scan me-2 text-muted"></i>Scan QR Code</h5>
      </div>
      <div class="card-body">
        <form method="POST">
          <div class="mb-3 position-relative">
            <label class="form-label">QR Code</label>
            <div class="input-group">
              <input type="text" name="qr_code" id="qrInput" class="form-control" placeholder="TEMP_XXXXXX" required>
              <button type="button" class="btn btn-outline-secondary" id="openCameraBtn">
                <i class="bi bi-camera"></i>
              </button>
            </div>
            <div class="form-text">Scan the QR or enter manually</div>
          </div>
          <button type="submit" name="scan_qr" class="btn btn-brand w-100">
            <i class="bi bi-qr-code-scan me-1"></i> Scan QR Code
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2 text-muted"></i>How to Use</h5>
      </div>
      <div class="card-body">
        <ol class="mb-2">
          <li>Click the <strong>Camera</strong> icon to open your device camera.</li>
          <li>Allow browser permission when prompted.</li>
          <li>Point the camera at the user’s QR code.</li>
          <li>Once detected, the QR will autofill into the input box.</li>
          <li>Click “Scan QR Code” to proceed.</li>
        </ol>
        <p class="mb-0"><strong>Note:</strong> Works only with valid temporary QR codes.</p>
      </div>
    </div>
  </div>

  <!-- Camera Modal -->
  <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Scan QR with Camera</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <video id="cameraPreview" autoplay playsinline style="width:100%; border-radius:8px; background:#000;"></video>
          <canvas id="captureCanvas" style="display:none;"></canvas>
          <button id="stopCameraBtn" class="btn btn-secondary w-100 mt-3" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i> Close Camera
          </button>
          <small class="text-muted d-block mt-2">QR codes will be detected automatically.</small>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>

  <script>
  (() => {
    const openCameraBtn = document.getElementById('openCameraBtn');
    const qrInput = document.getElementById('qrInput');
    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
    const video = document.getElementById('cameraPreview');
    const canvas = document.getElementById('captureCanvas');
    const ctx = canvas.getContext('2d');
    let stream = null, scanning = false;

    async function startCamera() {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = stream;
        video.play();
        scanning = true;
        requestAnimationFrame(scanFrame);
      } catch (err) {
        alert('Camera access denied or unavailable. Please use HTTPS or localhost.');
        modal.hide();
      }
    }

    function stopCamera() {
      scanning = false;
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
    }

    function scanFrame() {
      if (!scanning) return;
      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });
        if (code) {
          qrInput.value = code.data;
          stopCamera();
          modal.hide();
        }
      }
      if (scanning) requestAnimationFrame(scanFrame);
    }

    openCameraBtn.addEventListener('click', () => {
      modal.show();
      startCamera();
    });
    document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopCamera);
  })();
  </script>
</body>
</html>
