<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once('db_connect.php');
    $manager = new ConnectionManager();
    $conn = $manager->connect();

    // Get and sanitize input
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['loginrole'] ?? '';

    // Basic validation
    if (empty($usernameOrEmail) || empty($password) || empty($role)) {
        echo "<script>
                alert('Please ensure all fields are filled.');
                window.location.href = 'login.php';
              </script>";
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE (username = :input OR email = :input) AND role = :role");
    $stmt->bindValue(':input', $usernameOrEmail);
    $stmt->bindValue(':role', $role);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            // Success case
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // redirect for user
            if ($role == 'user') {
            echo "<script>
                    window.location.href = 'events.php';
                  </script>";
            exit;
            // redirect for admin
            } else {
              echo "<script>
                    window.location.href = 'manage_events_admin.php';
                  </script>";
              exit;
            }
        } else {
            // wrong password
            echo "<script>
                    alert('Incorrect password. Please try again.');
                    window.location.href = 'login.php';
                  </script>";
            exit;
        }
    } else {
        // No user with role
        echo "<script>
                alert('No account found with that username/email and role.');
                window.location.href = 'login.php';
              </script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Omni • Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --bg:#141b4d; --ink:#0f143a; --muted:#70779e;
      --indigo:#5563DE; --gold:goldenrod; --panel:#fff;
      --field-r:.85rem; --tick:#22c55e;
    }

    body{ margin:0; background:var(--bg); }
    /* Overlay - center modal in viewport */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 13, 35, .65);
  display: flex;                      /* was grid */
  justify-content: center;
  align-items: center;                /* vertically center */
  z-index: 10;
  backdrop-filter: blur(2px);
  transition: opacity .3s ease;
  opacity: 1;
}

/* Modal card - remove extra offsets */
.modal-card {
  background: var(--panel);
  border-radius: 1.25rem;
  border: 2px solid #eceefe;
  box-shadow: 0 22px 60px rgba(0, 0, 0, .35);
  position: relative;
  padding: 2rem 1.5rem 1.5rem;
  width: min(670px, 94vw);
}
    .overlay.fade-out{ opacity:0; pointer-events:none; }

    /* SOLID RED close button w/ white X (centered) */
    .close-btn{
      position:absolute; top:.85rem; left:.85rem;
      width:38px; height:38px; border-radius:50%;
      border:2px solid #cfd3ff; background:#fff; color:#4f59d9;
      display:flex; align-items:center; justify-content:center;
      font-size:1.3rem; line-height:1; cursor:pointer;
      transition:transform .06s ease, background .2s ease;
    }
    .close-btn:hover{ background:#f4f6ff; }
    .close-btn:active{ transform:scale(.98); }

    .top-switch {
  position: absolute;
  top: 1.5rem;       /* ⬅ increased from 0.85rem */
  right: 0.85rem;
  display: flex;
  gap: 0.5rem;
}

.brand {
  font-size: 2rem;
  font-weight: 800;
  color: var(--ink);
  text-align: center;
  margin: 0 0 1rem;   /* ⬅ increased from 1rem */
  padding-top: 1rem;    /* ⬅ added top padding for spacing below buttons */
}

    .switch-btn{
      background:transparent; border:2px solid var(--indigo); color:var(--indigo);
      padding:.4rem 1.1rem; border-radius:2rem; font-weight:600; transition:.2s;
    }
    .switch-btn.active, .switch-btn:hover{ background:var(--indigo); color:#fff; }

    .form-narrow{ max-width:560px; margin:0 auto; }

    /* Extra space under heading */
    .segment{
      position:relative; display:grid; grid-template-columns:1fr 1fr;
      background:#f4f6ff; border:2px solid var(--indigo); border-radius:999px; overflow:hidden;
      max-width:320px; width:100%; margin:0 auto 1rem;
    }
    .segment input{ display:none; }
    .segment label{
      position:relative; z-index:2; text-align:center; padding:.6rem 0; cursor:pointer;
      font-weight:700; color:var(--indigo); display:flex; align-items:center; justify-content:center; gap:.4rem;
    }
    .segment .slider{
      position:absolute; inset:0 auto 0 0; width:50%; background:var(--indigo);
      border-radius:999px; box-shadow:0 6px 16px rgba(85,99,222,.35); z-index:1;
      transition: transform .28s cubic-bezier(.22,.61,.36,1);
    }
    #login-admin:checked ~ .slider{ transform:translateX(100%); }
    #login-user:checked  ~ label[for="login-user"],
    #login-admin:checked ~ label[for="login-admin"]{ color:#fff; }

    .row.g-3{ --bs-gutter-y:1rem; --bs-gutter-x:.75rem; }
    .form-label{ font-weight:600; color:#1b214f; margin-bottom:.35rem; }
    .form-control{ border-radius:var(--field-r); padding:.65rem .9rem; }
    .form-control:focus{ border-color:var(--indigo)!important; box-shadow:0 0 0 .2rem rgba(85,99,222,.25)!important; }

    .btn-cta{ width:100%; padding:.9rem; border:none; border-radius:2rem; font-weight:800; background:var(--gold); color:#1a1a1a; }
    .btn-cta:hover{ background:#d6a21e; }
    .btn-google{
      display:flex; align-items:center; justify-content:center; gap:.6rem;
      width:100%; border:1px solid #e3e5ef; background:#fff;
      border-radius:2rem; padding:.8rem 1rem; font-weight:600; color:#1f2340; transition:box-shadow .2s ease;
    }
    .btn-google:hover{ box-shadow:0 6px 18px rgba(0,0,0,.08); }
    .btn-google img{ width:20px; height:20px; }

    .divider { display:flex; align-items:center; gap:.75rem; color:#8d93b5; font-size:.9rem; }
    .divider:before, .divider:after { content:""; flex:1; height:1px; background:#e9eaf6; }

    .tooltip-inner{ background:#f2f2f2; color:#333; font-weight:500; }
    .bs-tooltip-auto .tooltip-arrow::before, .bs-tooltip-top .tooltip-arrow::before{ border-top-color:#f2f2f2!important; }
  </style>
</head>
<body>
  <div class="overlay" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
    <div class="modal-card">
      <button class="close-btn" aria-label="Close" id="closeLogin">×</button>
      <div class="form-narrow">
        <h1 id="loginTitle" class="brand">Hello Again!</h1>

        <form method="POST" action="Login.php" novalidate>
        <div class="segment" role="radiogroup" aria-label="Login as">
          <input type="radio" id="login-user" name="loginrole" value="user" checked>
          <label for="login-user"><i class="bi bi-person"></i> User</label>

          <input type="radio" id="login-admin" name="loginrole" value="admin">
          <label for="login-admin"><i class="bi bi-shield-lock"></i> Admin</label>

          <span class="slider" aria-hidden="true"></span>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="loginEmail">Username/Email</label>
            <input id="loginEmail" name="username" type="email" class="form-control" title="Enter the email for your account.">
          </div>
          <div class="col-12">
            <label class="form-label" for="loginPwd">Password</label>
            <input id="loginPwd" type="password" name="password" class="form-control" title="Enter your password.">
          </div>
        </div>

        <button class="btn btn-cta my-3" type="submit">Login</button>
        </form>

        <div class="my-3 divider">Or continue with</div>
        <button type="button" class="btn btn-google" title="Continue with Google">
          <img src="https://www.gstatic.com/images/branding/product/1x/gsa_64dp.png" alt="Google"> Google
        </button>

        <p class="mt-3 text-center text-muted">
          Don’t have an account? <a class="link" href="register.php">Register</a>
        </p>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el));

    const overlay = document.querySelector('.overlay');
    document.getElementById('closeLogin').addEventListener('click', () => {
      overlay.classList.add('fade-out');
      setTimeout(() => overlay.style.display = 'none', 300);
    });
  </script>
</body>
</html>