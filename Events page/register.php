<?php
require_once('db_connect.php');
$manager = new ConnectionManager();
$conn = $manager->connect();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? "");
    $pass = $_POST['password'] ?? "";
    $confirmpass = $_POST['confirmPassword'] ?? "";
    $role = $_POST['regrole'];
    $email = $_POST["email"] ?? "";
    $year = $_POST["year"] ?? "";
    $school = $_POST["school"] ?? "";
    $major = $_POST["major"] ?? "";
    $club = $_POST["club"] ?? "";

    // Initialize error array
    $errors = [];

    // username empty?
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    // email empty?
    if (empty($email)) {
        $errors[] = "email is required.";
    }
    // Password empty?
    if (empty($pass)) {
        $errors[] = "Password is required.";
    }
    // Passwords match?
    if ($pass !== $confirmpass) {
        $errors[] = "Passwords do not match";
    }
    // club for admin?
    if ($role == "admin" && $club == "") {
        $errors[] = "club not specified";
    }
    // Username already exist?
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $errors[] = "Username already exist";
    }
    if (!empty($errors)) {
        // Display errors and link to register page
        echo "<h3>Registration Errors:</h3><ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul><a href='register.php'>Go back to Register</a>";
        exit; // stop execution if errors exist
    }

    // Hash password
    $password = password_hash($pass, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, year, school, major, club) VALUES (:username, :password, :role, :email, :year, :school, :major, :club)");
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':password', $password);
    $stmt->bindValue(':role', $role);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':year', $year);
    $stmt->bindValue(':school', $school);
    $stmt->bindValue(':major', $major);
    $stmt->bindValue(':club', $club);

    if ($stmt->execute()) {
        echo "<script>
        alert('Account created successfully!');
        window.location.href = 'Login.php';
        </script>";
       exit;
    } else {
        echo "Error: Username may already exist. <a href='register.php'>Try again</a>";
    }
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Omni • Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --bg:#141b4d; --ink:#0f143a; --muted:#70779e;
      --indigo:#5563DE; --gold:goldenrod; --panel:#fff;
      --field-r:.85rem; --tick:#22c55e; --ruleBad:#ef4444;
    }

    /* Page + overlay */
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

    /* Close button: perfectly centered X */
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

    /* Top switch */
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

    /* Inner width + spacing */
    .form-narrow{ max-width:560px; margin:0 auto; }
    .row.g-3{ --bs-gutter-y:1rem; --bs-gutter-x:.75rem; }
    .lead { color:var(--muted); margin:.2rem 0 1rem; text-align:center; }

    /* Centered User/Admin pill */
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
      position:absolute; inset:0 auto 0 0; width:50%;
      background:var(--indigo); border-radius:999px; z-index:1;
      box-shadow:0 6px 16px rgba(85,99,222,.35);
      transition: transform .28s cubic-bezier(.22,.61,.36,1);
    }
    #reg-admin:checked ~ .slider{ transform:translateX(100%); }
    #reg-user:checked  ~ label[for="reg-user"],
    #reg-admin:checked ~ label[for="reg-admin"]{ color:#fff; }

    /* Fields */
    .form-label{ font-weight:600; color:#1b214f; margin-bottom:.35rem; }
    .form-control,.form-select{ border-radius:var(--field-r); padding:.65rem .9rem; }
    .form-control:focus,.form-select:focus{
      border-color:var(--indigo)!important; box-shadow:0 0 0 .2rem rgba(85,99,222,.25)!important;
    }

    /* Tick centered at end of input */
    .field{ position:relative; }
    .tick{
      position:absolute; right:.75rem; top:50%; transform:translateY(-50%);
      width:22px; height:22px; border-radius:50%; border:2px solid var(--tick);
      color:var(--tick); display:none; align-items:center; justify-content:center;
      font-weight:800; font-size:.9rem; background:#eafff5;
    }
    .tick.show{ display:flex; }

    /* Password rules box */
    .pw-box{ display:none; }
    .pw-rules{
      background:#fff7f7; border:1px solid #ffd8d8; border-radius:.75rem;
      padding:.55rem .8rem; color:#7a1120; font-size:.95rem;
    }
    .pw-rules .rule{ display:none; gap:.45rem; align-items:center; }
    .pw-rules .dot{ width:8px; height:8px; border-radius:50%; background:#ef4444; }
    /* turn green when met */
    .pw-rules .rule.met{ color:#22c55e; }
    .pw-rules .rule.met .dot{ background:#22c55e; }

    /* Buttons & divider */
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

    /* Tooltips */
    .tooltip-inner{ background:#f2f2f2; color:#333; font-weight:500; }
    .bs-tooltip-auto .tooltip-arrow::before, .bs-tooltip-top .tooltip-arrow::before{ border-top-color:#f2f2f2!important; }
  </style>
</head>
<body>
  <div class="overlay" role="dialog" aria-modal="true" aria-labelledby="regTitle">
    <div class="modal-card">
      <button class="close-btn" aria-label="Close" id="closeRegister">×</button>

      <div class="form-narrow">
        <h1 id="regTitle" class="brand">Welcome!</h1>

        <!-- Role -->
        <form id="regForm" method="POST" novalidate>
        <div class="segment" role="radiogroup" aria-label="Register as">
          <input type="radio" id="reg-user" name="regrole" value="user" checked>
          <label for="reg-user"><i class="bi bi-person"></i> User</label>

          <input type="radio" id="reg-admin" name="regrole" value="admin">
          <label for="reg-admin"><i class="bi bi-shield-lock"></i> Admin</label>

          <span class="slider" aria-hidden="true"></span>
        </div>

          <div class="row g-3">
            <div class="col-md-6 field">
              <label class="form-label" for="username">Username</label>
              <input id="username" name="username" class="form-control"
                     title="3–24 characters; letters, numbers, dot or underscore"
                     minlength="3" maxlength="24" pattern="^[a-zA-Z0-9_\.]+$">
              <div class="tick" id="usernameTick">✓</div>
            </div>

            <div class="col-md-6 field">
              <label class="form-label" for="email">Email</label>
              <input id="email" class="form-control" type="email" name="email"
                title="Use a valid email (must include '@' and a domain, e.g. name@example.com)"
                pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" required>
            </div>

            <div class="col-md-6 field">
              <label class="form-label" for="password">Password</label>
              <input id="password" name="password" class="form-control" type="password"
                     title="Use 10+ chars with upper/lowercase, a number, a symbol, and no spaces"
                     autocomplete="new-password">
              <div class="tick" id="pwTick">✓</div>
              <div id="pwRulesBox" class="pw-box">
                <div class="pw-rules" aria-live="polite">
                  <div class="rule" data-rule="len"><span class="dot"></span> At least 10 characters</div>
                  <div class="rule" data-rule="upper"><span class="dot"></span> Uppercase letter (A–Z)</div>
                  <div class="rule" data-rule="lower"><span class="dot"></span> Lowercase letter (a–z)</div>
                  <div class="rule" data-rule="num"><span class="dot"></span> Number (0–9)</div>
                  <div class="rule" data-rule="sym"><span class="dot"></span> Symbol (!@#$…)</div>
                  <div class="rule" data-rule="space"><span class="dot"></span> No spaces</div>
                </div>
              </div>
            </div>

            <div class="col-md-6 field">
              <label class="form-label" for="confirmPassword">Confirm password</label>
              <input id="confirmPassword" name="confirmPassword" class="form-control" type="password"
                     title="Re-enter the same password" autocomplete="new-password">
              <div class="tick" id="cpwTick">✓</div>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="year">Year</label>
              <select id="year" class="form-select" title="Your current year of study" name="year">
                <option value="" selected disabled>Select</option>
                <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="school">School</label>
              <select id="school" class="form-select" title="Choose your SMU school" name="school">
                <option value="" selected disabled>Select</option>
                <option>School of Accountancy</option>
                <option>School of Business</option>
                <option>School of Economics</option>
                <option>School of Computing & Information Systems</option>
                <option>School of Law</option>
                <option>School of Social Sciences</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="major">Major</label>
              <input id="major" class="form-control" title="Your primary major" name="major">
            </div>

            <!-- Admin-only: Club officer -->
            <div id="adminFields" class="col-12 d-none">
              <label class="form-label" for="clubOffice">Club / Office (Club officer)</label>
              <input id="clubOffice" class="form-control" title="Your club or administrative office" name="club">
            </div>
          </div>

          <button class="btn btn-cta mt-3" type="submit">Create Account</button>

          <div class="my-3 divider">Or continue with</div>
          <button type="button" class="btn btn-google" title="Sign up with Google">
            <img src="https://www.gstatic.com/images/branding/product/1x/gsa_64dp.png" alt="Google"> Google
          </button>

          <p class="mt-3 text-center text-muted">
            Already registered? <a class="link" href="login.html">Sign in</a>
          </p>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Tooltips
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el));

    // Close popup smoothly
    const overlay = document.querySelector('.overlay');
    document.getElementById('closeRegister').addEventListener('click', () => {
      overlay.classList.add('fade-out');
      setTimeout(() => overlay.style.display = 'none', 300);
    });

    // Admin toggle
    const adminFields = document.getElementById('adminFields');
    const regAdmin = document.getElementById('reg-admin');
    const regUser  = document.getElementById('reg-user');
    const toggleAdmin = () => adminFields.classList.toggle('d-none', !regAdmin.checked);
    regAdmin.addEventListener('change', toggleAdmin);
    regUser.addEventListener('change', toggleAdmin);
    toggleAdmin();

    // Email validation helper (pattern ensures '@' + domain)
    const email = document.getElementById('email');
    email.addEventListener('input', () => {
      if (email.validity.typeMismatch || email.validity.patternMismatch) {
        email.setCustomValidity("Please enter a valid email (must include '@' and a domain).");
      } else {
        email.setCustomValidity("");
      }
    });

    // Password rules + ticks
    const pw      = document.getElementById('password');
    const cpw     = document.getElementById('confirmPassword');
    const pwBox   = document.getElementById('pwRulesBox');
    const pwTick  = document.getElementById('pwTick');
    const cpwTick = document.getElementById('cpwTick');

    const rules = {
      len: v => v.length >= 10,
      upper: v => /[A-Z]/.test(v),
      lower: v => /[a-z]/.test(v),
      num: v => /\d/.test(v),
      sym: v => /[^A-Za-z0-9\s]/.test(v),
      space: v => !/\s/.test(v),
    };

    function showUnmetRules(resultMap){
      const started = (pw.value || '').length > 0;
      const allGood = Object.values(resultMap).every(Boolean);
      pwBox.style.display = (!allGood && started) ? 'block' : 'none';

      for (const [key, ok] of Object.entries(resultMap)) {
        const row = pwBox.querySelector(`.rule[data-rule="${key}"]`);
        if (!row) continue;
        row.style.display = 'flex';           // show while evaluating
        row.classList.toggle('met', ok);      // turn green when met
        if (ok) {                             // then hide after a moment
          setTimeout(() => { row.style.display = 'none'; }, 600);
        }
      }
    }

    function validatePw(){
      const v = pw.value || '';
      const res = Object.fromEntries(Object.entries(rules).map(([k,fn]) => [k, fn(v)]));
      const allGood = Object.values(res).every(Boolean);

      pwTick.classList.toggle('show', allGood && v.length>0);
      showUnmetRules(res);

      const matches = v.length>0 && v === (cpw.value || '');
      cpwTick.classList.toggle('show', matches);
    }
    pw.addEventListener('input', validatePw);
    cpw.addEventListener('input', validatePw);
  </script>
</body>
</html>
