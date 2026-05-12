<?php
declare(strict_types=1);
session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONFIG
|--------------------------------------------------------------------------
*/
$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbUser = 'root';
$dbPass = '';
$dbName = 'taekwondo_system';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        setFlash('error', 'Invalid security token.');
        redirect('register.php');
    }
}

function getServerPdo(string $host, string $port, string $user, string $pass): PDO {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function getDbPdo(string $host, string $port, string $db, string $user, string $pass): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function validPassword(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

try {
    $serverPdo = getServerPdo($dbHost, $dbPort, $dbUser, $dbPass);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = getDbPdo($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
} catch (Throwable $e) {
    die(
        '<h2 style="font-family:Arial,sans-serif;">Database connection failed</h2>' .
        '<p style="font-family:Arial,sans-serif;">Please make sure Laragon MySQL is running.</p>' .
        '<pre style="font-family:monospace;background:#111;color:#fff;padding:12px;border-radius:8px;">' .
        e($e->getMessage()) .
        '</pre>'
    );
}

/*
|--------------------------------------------------------------------------
| CREATE TABLES
|--------------------------------------------------------------------------
*/
$schema = [
    "CREATE TABLE IF NOT EXISTS coaches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        registration_type ENUM('Club','School') NOT NULL DEFAULT 'Club',
        institution_name VARCHAR(190) NOT NULL,
        coach_name VARCHAR(190) NOT NULL,
        dob DATE NULL,
        dan_certificate_number VARCHAR(120) NULL,
        association_registered_number VARCHAR(120) NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        club_address VARCHAR(255) NULL,
        contact_number VARCHAR(80) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

$coachMigrations = [
    'club_address'   => "ALTER TABLE coaches ADD COLUMN club_address VARCHAR(255) NULL AFTER remarks",
    'contact_number' => "ALTER TABLE coaches ADD COLUMN contact_number VARCHAR(80) NULL AFTER club_address",
];

foreach ($coachMigrations as $column => $sql) {
    if (!columnExists($pdo, 'coaches', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

/*
|--------------------------------------------------------------------------
| REGISTER ACTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $registrationType = trim($_POST['registration_type'] ?? '');
        $institutionName = trim($_POST['institution_name'] ?? '');
        $coachName = trim($_POST['coach_name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $danCertificateNumber = trim($_POST['dan_certificate_number'] ?? '');
        $associationRegisteredNumber = trim($_POST['association_registered_number'] ?? '');
        $clubAddress = trim($_POST['club_address'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (
            $registrationType === '' ||
            $institutionName === '' ||
            $coachName === '' ||
            $dob === '' ||
            $danCertificateNumber === '' ||
            $email === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            throw new RuntimeException('Please fill all required fields.');
        }

        if (!in_array($registrationType, ['Club', 'School'], true)) {
            throw new RuntimeException('Invalid registration type.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException('Password and confirm password do not match.');
        }

        if (!validPassword($password)) {
            throw new RuntimeException('Password must be at least 8 characters and include uppercase, lowercase, number, and special symbol.');
        }

        $stmt = $pdo->prepare("SELECT id FROM coaches WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('This email is already registered.');
        }

        $stmt = $pdo->prepare("SELECT id FROM coaches WHERE institution_name = ? LIMIT 1");
        $stmt->execute([$institutionName]);
        if ($stmt->fetch()) {
            throw new RuntimeException('An account with the same club or school name already exists.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO coaches
            (registration_type, institution_name, coach_name, dob, dan_certificate_number, association_registered_number, email, password_hash, status, remarks, club_address, contact_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Waiting for admin review', ?, ?)
        ");
        $stmt->execute([
            $registrationType,
            $institutionName,
            $coachName,
            $dob,
            $danCertificateNumber,
            $associationRegisteredNumber,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $clubAddress,
            $contactNumber
        ]);

        setFlash('success', 'Registration submitted successfully. Please wait for admin verification.');
        redirect('register.php?submitted=1');

    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
        redirect('register.php');
    }
}

$flash = getFlash();
$submitted = isset($_GET['submitted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Coach Registration</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
    :root{
      --red:#d90429;
      --blue:#1565ff;
      --white:#ffffff;
      --soft:#cfcfcf;
      --border:rgba(255,255,255,.12);
      --panel:rgba(255,255,255,.06);
      --shadow:0 18px 45px rgba(0,0,0,.35);
    }
    body{
      min-height:100vh;
      background:linear-gradient(135deg,#020202,#09111f,#170407);
      color:var(--white);
      overflow-x:hidden;
    }
    .bg-orb{
      position:fixed;border-radius:50%;filter:blur(25px);opacity:.22;z-index:0;pointer-events:none;
      animation:float 10s ease-in-out infinite;
    }
    .orb1{width:260px;height:260px;background:var(--red);top:5%;left:5%}
    .orb2{width:320px;height:320px;background:var(--blue);bottom:5%;right:5%;animation-delay:2s}
    @keyframes float{
      0%,100%{transform:translateY(0) translateX(0)}
      50%{transform:translateY(-18px) translateX(15px)}
    }
    .page{
      position:relative;z-index:2;min-height:100vh;display:flex;align-items:center;justify-content:center;
      padding:24px;
    }
    .shell{
      width:100%;max-width:1100px;display:grid;grid-template-columns:380px 1fr;gap:20px;
    }
    .info,.card{
      background:var(--panel);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);
      backdrop-filter:blur(10px);
    }
    .info{padding:24px}
    .info h1{font-size:2rem;margin-bottom:10px}
    .info p{color:var(--soft);line-height:1.7;margin-bottom:18px}
    .req-box{
      background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:16px;margin-bottom:14px;
    }
    .req-box h3{margin-bottom:8px;font-size:1rem}
    .req-box p{margin:0;color:var(--soft);font-size:.94rem}
    .card{padding:24px}
    .card h2{font-size:1.6rem;margin-bottom:10px}
    .card .sub{color:var(--soft);line-height:1.6;margin-bottom:18px}
    .flash{
      margin-bottom:16px;padding:14px 16px;border-radius:16px;border:1px solid var(--border);line-height:1.6;
    }
    .flash-success{background:rgba(34,197,94,.12);color:#d8ffe4;border-color:rgba(34,197,94,.25)}
    .flash-error{background:rgba(217,4,41,.12);color:#ffd7de;border-color:rgba(217,4,41,.25)}
    .form-grid{
      display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:16px;
    }
    .form-group{display:grid;gap:8px}
    .form-group.full{grid-column:1 / -1}
    label{font-weight:bold;font-size:.95rem}
    input,select,textarea{
      width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);
      background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;
    }
    textarea{min-height:100px;resize:vertical}
    .btn-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:10px}
    .btn{
      min-height:50px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;
      color:var(--white);transition:.25s ease;text-decoration:none;display:flex;align-items:center;justify-content:center;
    }
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue))}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border)}
    .helper{
      margin-top:12px;color:var(--soft);font-size:.9rem;line-height:1.6;
    }
    .requirements-modal{
      position:fixed;inset:0;background:rgba(0,0,0,.68);display:flex;align-items:center;justify-content:center;
      z-index:99;padding:18px;
    }
    .requirements-box{
      width:100%;max-width:760px;background:#0f1118;border:1px solid var(--border);border-radius:24px;
      box-shadow:var(--shadow);padding:24px;
    }
    .requirements-box h2{margin-bottom:12px}
    .requirements-box p{color:var(--soft);line-height:1.7;margin-bottom:12px}
    .requirements-box .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:10px}
    .requirements-card{
      background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:16px;
    }
    .requirements-card h3{margin-bottom:8px}
    .requirements-card p{margin:0}
    .modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:18px}
    .hidden{display:none}
    @media (max-width:900px){
      .shell{grid-template-columns:1fr}
      .form-grid{grid-template-columns:1fr}
      .requirements-box .grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>

  <div id="requirementsModal" class="requirements-modal <?= $submitted ? 'hidden' : '' ?>">
    <div class="requirements-box">
      <h2>Coach Registration Requirements</h2>
      <p>Please review the required documents before continuing with registration.</p>

      <div class="grid">
        <div class="requirements-card">
          <h3>For Club Registration</h3>
          <p>Rule book of the club, Dan certificate number, and government-issued photo ID are required.</p>
        </div>

        <div class="requirements-card">
          <h3>For School Registration</h3>
          <p>Referral letter from school, Dan certificate number, and government-issued photo ID are required.</p>
        </div>
      </div>

      <div class="modal-actions">
        <a href="login.php" class="btn btn-secondary">Back to Login</a>
        <button type="button" class="btn btn-primary" onclick="continueRegistration()">Continue</button>
      </div>
    </div>
  </div>

  <div class="page">
    <div class="shell">
      <div class="info">
        <h1>Coach Registration</h1>
        <p>Submit your coach application for admin verification. Only verified coaches can log in and use the full management system.</p>

        <div class="req-box">
          <h3>Registration Flow</h3>
          <p>Register → Application goes to admin → Admin verifies or rejects → Verified coach can log in.</p>
        </div>

        <div class="req-box">
          <h3>Password Rule</h3>
          <p>Password must be at least 8 characters and include uppercase, lowercase, number, and special symbol.</p>
        </div>

        <div class="req-box">
          <h3>Important</h3>
          <p>Same email cannot be used more than once. Same club or school name cannot be used more than once.</p>
        </div>
      </div>

      <div class="card" id="registerCard">
        <h2>Register as Coach</h2>
        <p class="sub">Complete the application form carefully. Your account will remain pending until admin approval.</p>

        <?php if ($flash): ?>
          <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
            <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>

        <?php if ($submitted): ?>
          <div class="flash flash-success">
            Registration submitted successfully. Please wait for admin verification before logging in.
          </div>
          <div class="btn-row">
            <a href="login.php" class="btn btn-primary">Back to Login</a>
            <a href="register.php" class="btn btn-secondary">New Registration</a>
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-grid">
              <div class="form-group">
                <label>Registration Type</label>
                <select name="registration_type" required>
                  <option value="">Select type</option>
                  <option value="Club">Club</option>
                  <option value="School">School</option>
                </select>
              </div>

              <div class="form-group">
                <label>Club / School Name</label>
                <input type="text" name="institution_name" required>
              </div>

              <div class="form-group">
                <label>Coach Full Name</label>
                <input type="text" name="coach_name" required>
              </div>

              <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" required>
              </div>

              <div class="form-group">
                <label>Dan Certificate Number</label>
                <input type="text" name="dan_certificate_number" required>
              </div>

              <div class="form-group">
                <label>Association Registered Number</label>
                <input type="text" name="association_registered_number">
              </div>

              <div class="form-group full">
                <label>Club / School Address</label>
                <input type="text" name="club_address">
              </div>

              <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number">
              </div>

              <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
              </div>

              <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
              </div>

              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
              </div>
            </div>

            <div class="btn-row">
              <button class="btn btn-primary" type="submit">Submit Registration</button>
              <a href="login.php" class="btn btn-secondary">Back to Login</a>
            </div>

            <div class="helper">
              After submission, admin can review your application in the admin panel and verify or reject it with remarks.
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function continueRegistration() {
      const modal = document.getElementById('requirementsModal');
      if (modal) modal.classList.add('hidden');
    }
  </script>
</body>
</html>