<?php
declare(strict_types=1);
session_start();

/*  
|--------------------------------------------------------------------------
| DATABASE CONFIG
|--------------------------------------------------------------------------
| Laragon default:
| host = 127.0.0.1
| user = root
| pass = ''
|--------------------------------------------------------------------------
*/
$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbUser = 'root';
$dbPass = '';
$dbName = 'taekwondo_system';

const DEFAULT_ADMIN_EMAIL = 'taekwondoadmin@nta.com';
const DEFAULT_ADMIN_PASSWORD = 'Admin@123';

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
| CREATE TABLES IF NOT EXISTS
|--------------------------------------------------------------------------
*/
$schema = [
    "CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

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
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Verified',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS referees (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        referee_code VARCHAR(80) NOT NULL UNIQUE,
        level VARCHAR(80) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS players (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_code VARCHAR(80) NOT NULL UNIQUE,
        full_name VARCHAR(190) NOT NULL,
        dob DATE NULL,
        age INT NULL,
        weight_kg DECIMAL(6,2) NULL,
        belt_rank VARCHAR(100) NULL,
        country_name VARCHAR(120) NULL,
        club_name VARCHAR(190) NULL,
        contact_number VARCHAR(80) NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        gold_last_90_days INT NOT NULL DEFAULT 0,
        silver_count INT NOT NULL DEFAULT 0,
        bronze_count INT NOT NULL DEFAULT 0,
        participated_games INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

/*
|--------------------------------------------------------------------------
| AUTO INSERT DEFAULT ADMIN + DEMO USERS
|--------------------------------------------------------------------------
*/
function rowExists(PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1");
    $stmt->execute([$value]);
    return (bool)$stmt->fetchColumn();
}

if (!rowExists($pdo, 'admins', 'email', DEFAULT_ADMIN_EMAIL)) {
    $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
    $stmt->execute([
        DEFAULT_ADMIN_EMAIL,
        password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT)
    ]);
}

if (!rowExists($pdo, 'coaches', 'email', 'coach@nta.com')) {
    $stmt = $pdo->prepare("
        INSERT INTO coaches
        (registration_type, institution_name, coach_name, dob, dan_certificate_number, association_registered_number, email, password_hash, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Club',
        'Tiger Dojang',
        'Ram Bahadur',
        '1988-02-10',
        'DAN-1001',
        'ASSOC-001',
        'coach@nta.com',
        password_hash('Coach@123', PASSWORD_DEFAULT),
        'Verified',
        'Demo verified coach account'
    ]);
}

if (!rowExists($pdo, 'referees', 'email', 'referee@nta.com')) {
    $stmt = $pdo->prepare("
        INSERT INTO referees
        (first_name, last_name, referee_code, level, email, password_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Suresh',
        'Adhikari',
        'REF001',
        'National',
        'referee@nta.com',
        password_hash('Referee@123', PASSWORD_DEFAULT)
    ]);
}

if (!rowExists($pdo, 'players', 'email', 'player@nta.com')) {
    $stmt = $pdo->prepare("
        INSERT INTO players
        (player_code, full_name, dob, age, weight_kg, belt_rank, country_name, club_name, contact_number, email, password_hash, gold_last_90_days, silver_count, bronze_count, participated_games)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'P001',
        'Aarav Shrestha',
        '2010-03-15',
        15,
        48,
        'Blue',
        'Nepal',
        'Tiger Dojang',
        '9800000001',
        'player@nta.com',
        password_hash('Player@123', PASSWORD_DEFAULT),
        4,
        2,
        1,
        18
    ]);
}

/*
|--------------------------------------------------------------------------
| LOGIN HANDLER
|--------------------------------------------------------------------------
*/
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = trim($_POST['role'] ?? 'Admin');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        setFlash('error', 'Please enter your email and password.');
        redirect('login.php');
    }

    $roleMap = [
        'Admin' => [
            'table' => 'admins',
            'email_col' => 'email',
            'password_col' => 'password_hash',
            'id_col' => 'id',
            'name_sql' => 'email AS display_name',
            'redirect' => 'admin.php'
        ],
        'Coach' => [
            'table' => 'coaches',
            'email_col' => 'email',
            'password_col' => 'password_hash',
            'id_col' => 'id',
            'name_sql' => 'coach_name AS display_name',
            'redirect' => 'coach.php',
            'extra_where' => " AND status = 'Verified'"
        ],
        'Referee' => [
            'table' => 'referees',
            'email_col' => 'email',
            'password_col' => 'password_hash',
            'id_col' => 'id',
            'name_sql' => "CONCAT(first_name, ' ', last_name) AS display_name",
            'redirect' => 'referee.php'
        ],
        'Player' => [
            'table' => 'players',
            'email_col' => 'email',
            'password_col' => 'password_hash',
            'id_col' => 'id',
            'name_sql' => 'full_name AS display_name',
            'redirect' => 'player.php'
        ],
    ];

    if (!isset($roleMap[$role])) {
        setFlash('error', 'Invalid role selected.');
        redirect('login.php');
    }

    $cfg = $roleMap[$role];
    $extraWhere = $cfg['extra_where'] ?? '';

    $sql = "SELECT {$cfg['id_col']} AS user_id, {$cfg['email_col']} AS user_email, {$cfg['password_col']} AS user_password, {$cfg['name_sql']}
            FROM {$cfg['table']}
            WHERE {$cfg['email_col']} = ? {$extraWhere}
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['user_password'])) {
        setFlash('error', 'Invalid credentials for selected role.');
        redirect('login.php');
    }

    $_SESSION['taekwondo_logged_in'] = true;
    $_SESSION['taekwondo_role'] = $role;
    $_SESSION['taekwondo_user_id'] = (int)$user['user_id'];
    $_SESSION['taekwondo_user_email'] = $user['user_email'];
    $_SESSION['taekwondo_user_name'] = $user['display_name'];

    if ($role === 'Admin') {
        $_SESSION['taekwondo_admin_id'] = (int)$user['user_id'];
        $_SESSION['taekwondo_admin_email'] = $user['user_email'];
    }

    redirect($cfg['redirect']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Taekwondo Management System</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
    :root{
      --black:#050505;
      --white:#ffffff;
      --red:#d90429;
      --blue:#1565ff;
      --soft-white:#d9d9d9;
      --glass:rgba(255,255,255,0.08);
      --border:rgba(255,255,255,0.12);
      --shadow:0 20px 50px rgba(0,0,0,0.45);
    }
    body{
      min-height:100vh;
      background:linear-gradient(135deg,#020202,#0b1020,#180307);
      color:var(--white);
      position:relative;
      overflow-x:hidden;
      overflow-y:auto;
    }
    .bg-animation{position:fixed;inset:0;overflow:hidden;z-index:0;pointer-events:none;}
    .orb{position:absolute;border-radius:50%;filter:blur(20px);opacity:.35;animation:floatOrb 12s infinite ease-in-out;}
    .orb1{width:280px;height:280px;background:var(--red);top:8%;left:6%;}
    .orb2{width:320px;height:320px;background:var(--blue);bottom:8%;right:6%;animation-delay:2s;}
    .orb3{width:220px;height:220px;background:#ffffff;top:55%;left:40%;opacity:.12;animation-delay:4s;}
    @keyframes floatOrb{
      0%,100%{transform:translateY(0) translateX(0) scale(1);}
      25%{transform:translateY(-20px) translateX(15px) scale(1.05);}
      50%{transform:translateY(25px) translateX(-10px) scale(.95);}
      75%{transform:translateY(-10px) translateX(-20px) scale(1.02);}
    }
    .hidden{display:none!important;}
    .splash-screen{
      position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;
      background:linear-gradient(145deg,#000000,#091120,#190507);animation:splashFadeOut 1s ease forwards;animation-delay:2.8s;
    }
    .splash-content{text-align:center;padding:30px 20px;animation:splashZoom 1.2s ease;width:100%;max-width:900px;}
    .splash-content h1{font-size:clamp(1.8rem,4vw,3.2rem);line-height:1.25;text-transform:uppercase;letter-spacing:2px;text-shadow:0 0 20px rgba(255,255,255,.12);}
    .splash-content .red{color:var(--red);}
    .splash-content .blue{color:var(--blue);}
    .splash-bar{width:min(220px,60%);height:5px;margin:18px auto 0;border-radius:50px;background:linear-gradient(to right,var(--red),#ffffff,var(--blue));animation:barPulse 1.6s infinite;}
    @keyframes splashZoom{from{opacity:0;transform:scale(.82);}to{opacity:1;transform:scale(1);}}
    @keyframes splashFadeOut{to{opacity:0;visibility:hidden;pointer-events:none;}}
    @keyframes barPulse{0%,100%{transform:scaleX(.75);}50%{transform:scaleX(1.05);}}
    .main-screen{position:relative;z-index:2;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
    .main-wrapper{width:100%;max-width:1240px;display:grid;grid-template-columns:1.08fr .92fr;gap:24px;}
    .panel{background:var(--glass);border:1px solid var(--border);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-radius:28px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
    .left-panel{padding:38px;}
    .system-badge{
      display:inline-block;padding:8px 14px;border-radius:999px;border:1px solid rgba(255,255,255,.15);
      color:var(--soft-white);font-size:.86rem;margin-bottom:20px;background:rgba(255,255,255,.04);line-height:1.4;
    }
    .left-panel h2{font-size:clamp(1.8rem,3vw,2.5rem);line-height:1.2;margin-bottom:14px;}
    .left-panel h2 .red{color:var(--red);}
    .left-panel h2 .blue{color:var(--blue);}
    .left-panel p{color:var(--soft-white);line-height:1.7;margin-bottom:28px;max-width:90%;font-size:1rem;}
    .feature-strip{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;}
    .feature-chip{
      padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);
      color:var(--soft-white);font-size:.9rem;line-height:1.2;
    }
    .role-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;}
    .role-card{
      border-radius:22px;padding:24px 18px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
      cursor:pointer;transition:.35s ease;position:relative;overflow:hidden;text-align:center;min-height:170px;
      display:flex;flex-direction:column;justify-content:center;
    }
    .role-card::before{
      content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(217,4,41,.10),rgba(21,101,255,.10));
      opacity:0;transition:.35s ease;
    }
    .role-card:hover::before,.role-card.active::before{opacity:1;}
    .role-card:hover{transform:translateY(-8px);border-color:rgba(255,255,255,.18);}
    .role-card.active{border-color:rgba(21,101,255,.55);box-shadow:0 0 0 2px rgba(217,4,41,.15);}
    .role-card>*{position:relative;z-index:2;}
    .role-icon{font-size:2.6rem;margin-bottom:12px;display:block;}
    .role-title{font-size:1.08rem;font-weight:bold;margin-bottom:6px;}
    .role-subtitle{color:var(--soft-white);font-size:.9rem;line-height:1.5;word-wrap:break-word;}
    .right-panel{padding:36px 30px;display:flex;flex-direction:column;justify-content:center;}
    .login-top h3{font-size:clamp(1.5rem,3vw,2rem);margin-bottom:8px;}
    .login-top p{color:var(--soft-white);line-height:1.6;margin-bottom:20px;}
    .selected-role{
      display:flex;align-items:center;gap:14px;padding:15px 16px;border-radius:18px;background:rgba(255,255,255,.05);
      border:1px solid rgba(255,255,255,.08);margin-bottom:22px;flex-wrap:wrap;
    }
    .selected-role-icon{font-size:2rem;flex-shrink:0;}
    .selected-role-text h4{font-size:1rem;margin-bottom:4px;word-break:break-word;}
    .selected-role-text span{color:var(--soft-white);font-size:.9rem;line-height:1.5;word-break:break-word;}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;font-weight:bold;margin-bottom:8px;font-size:.95rem;}
    .form-group input{
      width:100%;padding:14px 15px;border-radius:14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);
      color:var(--white);outline:none;font-size:.96rem;transition:.3s ease;min-height:48px;
    }
    .form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(21,101,255,.15);}
    .form-group input::placeholder{color:#a6a6a6;}
    .password-wrap{position:relative;}
    .password-wrap input{padding-right:52px;}
    .toggle-password{
      position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:transparent;
      color:var(--soft-white);cursor:pointer;font-size:1.1rem;width:36px;height:36px;border-radius:10px;
    }
    .btn-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;}
    .btn{
      border:none;border-radius:14px;padding:14px 18px;font-weight:bold;cursor:pointer;transition:.3s ease;font-size:.96rem;min-height:50px;
    }
    .btn-primary{flex:1 1 180px;background:linear-gradient(to right,var(--red),var(--blue));color:white;}
    .btn-primary:hover{transform:translateY(-2px);opacity:.96;}
    .btn-secondary{flex:1 1 140px;background:rgba(255,255,255,.05);color:white;border:1px solid rgba(255,255,255,.12);}
    .btn-secondary:hover{background:rgba(255,255,255,.09);}
    .login-note{
      margin-top:18px;padding:14px;border-radius:16px;background:rgba(21,101,255,.10);border:1px solid rgba(21,101,255,.18);
      color:#d9e7ff;line-height:1.6;font-size:.92rem;
    }
    .demo-note{margin-top:12px;color:var(--soft-white);font-size:.88rem;line-height:1.8;}
    .error-box{
      margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(217,4,41,.10);border:1px solid rgba(217,4,41,.25);
      color:#ffd8de;line-height:1.5;font-size:.92rem;
    }
    .success-box{
      margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(34,197,94,.10);border:1px solid rgba(34,197,94,.25);
      color:#d8ffe4;line-height:1.5;font-size:.92rem;
    }
    @media (max-width:1100px){
      .main-wrapper{grid-template-columns:1fr;}
      .left-panel p{max-width:100%;}
    }
    @media (max-width:768px){
      .main-screen{padding:18px;}
      .left-panel,.right-panel{padding:22px 18px;}
      .role-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
      .btn-row{flex-direction:column;}
      .btn-primary,.btn-secondary{width:100%;flex:1 1 auto;}
    }
    @media (max-width:540px){
      .main-screen{padding:14px;}
      .left-panel,.right-panel{padding:18px 14px;border-radius:20px;}
      .role-grid{grid-template-columns:1fr;}
      .role-card{min-height:150px;}
      .selected-role{align-items:flex-start;}
      .splash-content h1{letter-spacing:1px;}
    }
  </style>
</head>
<body>
  <div class="bg-animation">
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
  </div>

  <div class="splash-screen">
    <div class="splash-content">
      <h1>
        Welcome to <span class="red">modern era</span><br>
        of <span class="blue">taekwondo</span>
      </h1>
      <div class="splash-bar"></div>
    </div>
  </div>

  <section class="main-screen">
    <div class="main-wrapper">
      <div class="panel left-panel">
        <div class="system-badge">Taekwondo Player Management + Poomsae Referee Application</div>

        <h2>
          Powering the <span class="red">next generation</span> of
          <span class="blue">taekwondo management</span>
        </h2>

        <p>
          Manage players, coaches, referees, and administration in one modern system.
          Select your role to enter the secure portal.
        </p>

        <div class="feature-strip">
          <div class="feature-chip">Player Records</div>
          <div class="feature-chip">Coach Registration</div>
          <div class="feature-chip">Referee Tools</div>
          <div class="feature-chip">Admin Control</div>
        </div>

        <div class="role-grid">
          <div class="role-card active" data-role="Admin">
            <span class="role-icon">🛡️</span>
            <div class="role-title">Admin</div>
            <div class="role-subtitle">System access, approvals, and management</div>
          </div>

          <div class="role-card" data-role="Player">
            <span class="role-icon">🥋</span>
            <div class="role-title">Player</div>
            <div class="role-subtitle">Athlete profile, records, and progress</div>
          </div>

          <div class="role-card" data-role="Coach">
            <span class="role-icon">📋</span>
            <div class="role-title">Coach</div>
            <div class="role-subtitle">Training, player supervision, and registration</div>
          </div>

          <div class="role-card" data-role="Referee">
            <span class="role-icon">🏅</span>
            <div class="role-title">Referee</div>
            <div class="role-subtitle">Poomsae judging and scoring access</div>
          </div>
        </div>
      </div>

      <div class="panel right-panel">
        <div class="login-top">
          <h3 id="loginTitle">Admin Login</h3>
          <p id="loginDesc">Enter your credentials to access the secure management dashboard.</p>
        </div>

        <div class="selected-role">
          <div class="selected-role-icon" id="selectedRoleIcon">🛡️</div>
          <div class="selected-role-text">
            <h4 id="selectedRoleLabel">Selected Role: Admin</h4>
            <span id="selectedRoleHint">Authorized system control panel</span>
          </div>
        </div>

        <form id="loginForm" method="post">
          <input type="hidden" name="role" id="roleInput" value="Admin">

          <div class="form-group">
            <label for="loginEmail">Email Address</label>
            <input type="email" id="loginEmail" name="email" placeholder="Enter your email" required>
          </div>

          <div class="form-group">
            <label for="loginPassword">Password</label>
            <div class="password-wrap">
              <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
              <button type="button" class="toggle-password" id="togglePassword">👁️</button>
            </div>
          </div>

          <div class="btn-row">
            <button type="submit" class="btn btn-primary">Login</button>
            <button type="button" class="btn btn-secondary hidden" id="registerOpenBtn">Register</button>
          </div>
        </form>

        <?php if ($flash): ?>
          <div class="<?= $flash['type'] === 'error' ? 'error-box' : 'success-box' ?>">
            <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>

        <div class="login-note">
          Only <strong>Coach</strong> can create a new account. Select the coach role to open the registration form.
        </div>

        <div class="demo-note">
          <strong>Default Admin:</strong> taekwondoadmin@nta.com / Admin@123<br>
          <strong>Demo Coach:</strong> coach@nta.com / Coach@123<br>
          <strong>Demo Referee:</strong> referee@nta.com / Referee@123<br>
          <strong>Demo Player:</strong> player@nta.com / Player@123
        </div>
      </div>
    </div>
  </section>

  <script>
    const roleCards = document.querySelectorAll(".role-card");
    const loginTitle = document.getElementById("loginTitle");
    const loginDesc = document.getElementById("loginDesc");
    const selectedRoleIcon = document.getElementById("selectedRoleIcon");
    const selectedRoleLabel = document.getElementById("selectedRoleLabel");
    const selectedRoleHint = document.getElementById("selectedRoleHint");
    const registerOpenBtn = document.getElementById("registerOpenBtn");
    const roleInput = document.getElementById("roleInput");
    const loginPassword = document.getElementById("loginPassword");
    const togglePassword = document.getElementById("togglePassword");

    let currentRole = "Admin";

    const roleData = {
      Admin: {
        icon: "🛡️",
        desc: "Enter your credentials to access the secure management dashboard.",
        hint: "Authorized system control panel"
      },
      Player: {
        icon: "🥋",
        desc: "Access your athlete profile, taekwondo records, and performance information.",
        hint: "Athlete account access"
      },
      Coach: {
        icon: "📋",
        desc: "Manage players, training records, and register as a coach through the secure portal.",
        hint: "Coach account access"
      },
      Referee: {
        icon: "🏅",
        desc: "Access poomsae referee tools, judging support, and scoring panel.",
        hint: "Referee control access"
      }
    };

    function updateRoleUI(role) {
      currentRole = role;
      roleInput.value = role;
      loginTitle.textContent = role + " Login";
      loginDesc.textContent = roleData[role].desc;
      selectedRoleIcon.textContent = roleData[role].icon;
      selectedRoleLabel.textContent = "Selected Role: " + role;
      selectedRoleHint.textContent = roleData[role].hint;

      if (role === "Coach") {
        registerOpenBtn.classList.remove("hidden");
      } else {
        registerOpenBtn.classList.add("hidden");
      }
    }

    roleCards.forEach(card => {
      card.addEventListener("click", () => {
        roleCards.forEach(item => item.classList.remove("active"));
        card.classList.add("active");
        updateRoleUI(card.dataset.role);
      });
    });

    togglePassword.addEventListener("click", () => {
      if (loginPassword.type === "password") {
        loginPassword.type = "text";
        togglePassword.textContent = "🙈";
      } else {
        loginPassword.type = "password";
        togglePassword.textContent = "👁️";
      }
    });

    registerOpenBtn.addEventListener("click", () => {
      if (currentRole === "Coach") {
        window.location.href = "register.php";
      }
    });

    updateRoleUI("Admin");
  </script>
</body>
</html>
