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
        redirect('player.php');
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

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
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

function beltColor(string $belt): array {
    $map = [
        'White' => ['#ffffff', '#111111', 'White Belt'],
        'Yellow' => ['#facc15', '#111111', 'Yellow Belt'],
        'Half Green' => ['linear-gradient(90deg,#facc15 50%, #16a34a 50%)', '#ffffff', 'Half Green Belt'],
        'Green' => ['#16a34a', '#ffffff', 'Green Belt'],
        'Half Blue' => ['linear-gradient(90deg,#16a34a 50%, #2563eb 50%)', '#ffffff', 'Half Blue Belt'],
        'Blue' => ['#2563eb', '#ffffff', 'Blue Belt'],
        'Half Red' => ['linear-gradient(90deg,#2563eb 50%, #dc2626 50%)', '#ffffff', 'Half Red Belt'],
        'Red' => ['#dc2626', '#ffffff', 'Red Belt'],
        'Half Black' => ['linear-gradient(90deg,#dc2626 50%, #111111 50%)', '#ffffff', 'Half Black Belt'],
        'Black' => ['#111111', '#e7c35a', 'Black Belt'],
    ];

    if (str_starts_with($belt, 'Dan') || str_starts_with($belt, 'Poom')) {
        return ['#111111', '#e7c35a', $belt];
    }

    return $map[$belt] ?? ['#333333', '#ffffff', $belt];
}

function beltGuidance(string $belt): array {
    $guides = [
        'White' => [
            'poomsae' => 'Basic stances, walking stance, front stance, ready position, simple movement drilling.',
            'maki' => 'Low block, middle block basic line practice.'
        ],
        'Yellow' => [
            'poomsae' => 'Taegeuk 1 Jang foundation and movement control.',
            'maki' => 'Low block, middle block, outer block basics.'
        ],
        'Half Green' => [
            'poomsae' => 'Taegeuk 1 Jang polishing and preparation for Taegeuk 2 Jang.',
            'maki' => 'Low block, middle block, knife-hand basics.'
        ],
        'Green' => [
            'poomsae' => 'Taegeuk 2 Jang with rhythm and balance.',
            'maki' => 'Middle block, outer block, inner block improvement.'
        ],
        'Half Blue' => [
            'poomsae' => 'Taegeuk 2 Jang completion and transition to Taegeuk 3 Jang.',
            'maki' => 'High block, middle block, stronger chamber work.'
        ],
        'Blue' => [
            'poomsae' => 'Taegeuk 3 Jang with power control and clear lines.',
            'maki' => 'High block, outer block, inner block with proper hip use.'
        ],
        'Half Red' => [
            'poomsae' => 'Taegeuk 3 Jang and preparation for Taegeuk 4 Jang.',
            'maki' => 'High block and advanced timing on defensive motion.'
        ],
        'Red' => [
            'poomsae' => 'Taegeuk 4 Jang and performance sharpness.',
            'maki' => 'All standard blocks with accuracy and reaction drills.'
        ],
        'Half Black' => [
            'poomsae' => 'Advanced color belt poomsae, pre-black-belt refinement.',
            'maki' => 'Advanced block combinations and self-defence transitions.'
        ],
        'Black' => [
            'poomsae' => 'Koryo and higher discipline-based poomsae preparation.',
            'maki' => 'Precision defence, timing, countering, and black belt control.'
        ],
    ];

    if (str_starts_with($belt, 'Poom 1') || str_starts_with($belt, 'Dan 1')) {
        return ['poomsae' => 'Koryo focus with advanced performance control.', 'maki' => 'Black belt standard defensive control and timing.'];
    }
    if (str_starts_with($belt, 'Poom 2') || str_starts_with($belt, 'Dan 2')) {
        return ['poomsae' => 'Keumgang preparation and stronger stance work.', 'maki' => 'Higher-level reaction blocking and practical defence.'];
    }
    if (str_starts_with($belt, 'Poom 3') || str_starts_with($belt, 'Dan 3')) {
        return ['poomsae' => 'Taebaek refinement and leadership standard technique.', 'maki' => 'Technical defensive excellence and senior-level control.'];
    }

    return $guides[$belt] ?? ['poomsae' => 'Continue poomsae practice according to current rank.', 'maki' => 'Continue blocking drills according to current rank.'];
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
    "CREATE TABLE IF NOT EXISTS players (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_code VARCHAR(80) NOT NULL UNIQUE,
        full_name VARCHAR(190) NOT NULL,
        dob DATE NULL,
        age INT NULL,
        gender VARCHAR(20) NULL,
        weight_kg DECIMAL(6,2) NULL,
        weight_category VARCHAR(100) NULL,
        age_category VARCHAR(100) NULL,
        belt_rank VARCHAR(100) NULL,
        country_name VARCHAR(120) NULL,
        club_name VARCHAR(190) NULL,
        club_address VARCHAR(255) NULL,
        contact_number VARCHAR(80) NULL,
        email VARCHAR(190) NULL UNIQUE,
        password_hash VARCHAR(255) NULL,
        gold_last_90_days INT NOT NULL DEFAULT 0,
        silver_count INT NOT NULL DEFAULT 0,
        bronze_count INT NOT NULL DEFAULT 0,
        participated_games INT NOT NULL DEFAULT 0,
        status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active',
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
        club_address VARCHAR(255) NULL,
        contact_number VARCHAR(80) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS notices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        audience ENUM('All','Coaches','Players','Referees','CoachPlayers') NOT NULL DEFAULT 'All',
        created_by_admin_id INT UNSIGNED NULL,
        created_by_coach_id INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS coach_player_notices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS player_leave_applications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL,
        topic VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        leave_date DATE NOT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        coach_remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS player_transfer_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL,
        current_club_name VARCHAR(190) NOT NULL,
        requested_club_name VARCHAR(190) NOT NULL,
        requested_club_contact VARCHAR(120) NULL,
        reason_text TEXT NOT NULL,
        status ENUM('Pending','Reviewed') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS player_weight_updates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL,
        weight_kg DECIMAL(6,2) NOT NULL,
        recorded_month VARCHAR(20) NOT NULL,
        recorded_year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS admin_alerts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id INT UNSIGNED NULL,
        player_id INT UNSIGNED NULL,
        alert_type ENUM('Delete Request','Ban Request','Transfer Request','General') NOT NULL DEFAULT 'General',
        title VARCHAR(255) NOT NULL,
        reason_text TEXT NULL,
        status ENUM('Pending','Reviewed') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

/*
|--------------------------------------------------------------------------
| SAFE MIGRATIONS
|--------------------------------------------------------------------------
*/
$playerMigrations = [
    'gender' => "ALTER TABLE players ADD COLUMN gender VARCHAR(20) NULL AFTER age",
    'weight_category' => "ALTER TABLE players ADD COLUMN weight_category VARCHAR(100) NULL AFTER weight_kg",
    'age_category' => "ALTER TABLE players ADD COLUMN age_category VARCHAR(100) NULL AFTER weight_category",
    'club_address' => "ALTER TABLE players ADD COLUMN club_address VARCHAR(255) NULL AFTER club_name",
    'status' => "ALTER TABLE players ADD COLUMN status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active' AFTER participated_games",
];
foreach ($playerMigrations as $column => $sql) {
    if (!columnExists($pdo, 'players', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

$coachMigrations = [
    'club_address' => "ALTER TABLE coaches ADD COLUMN club_address VARCHAR(255) NULL AFTER remarks",
    'contact_number' => "ALTER TABLE coaches ADD COLUMN contact_number VARCHAR(80) NULL AFTER club_address",
];
foreach ($coachMigrations as $column => $sql) {
    if (!columnExists($pdo, 'coaches', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

/*
|--------------------------------------------------------------------------
| ENSURE DEMO PLAYER EXISTS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT id FROM players WHERE email = ? LIMIT 1");
$stmt->execute(['player@nta.com']);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("
        INSERT INTO players
        (player_code, full_name, dob, age, gender, weight_kg, weight_category, age_category, belt_rank, country_name, club_name, club_address, contact_number, email, password_hash, gold_last_90_days, silver_count, bronze_count, participated_games, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
    ");
    $stmt->execute([
        'PLY00001',
        'Aarav Shrestha',
        '2010-03-15',
        16,
        'Male',
        48.00,
        'Male -58kg',
        'Juniors',
        'Blue',
        'Nepal',
        'Tiger Dojang',
        'Kathmandu, Nepal',
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
| SESSION CHECK
|--------------------------------------------------------------------------
*/
if (($_SESSION['taekwondo_logged_in'] ?? false) !== true || ($_SESSION['taekwondo_role'] ?? '') !== 'Player') {
    redirect('login.php');
}

$currentPlayerId = (int)($_SESSION['taekwondo_user_id'] ?? 0);
if ($currentPlayerId <= 0) {
    redirect('login.php');
}

$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
$stmt->execute([$currentPlayerId]);
$currentPlayer = $stmt->fetch();

if (!$currentPlayer) {
    session_destroy();
    redirect('login.php');
}

if (($currentPlayer['status'] ?? '') === 'Deleted') {
    die('<h2 style="font-family:Arial;">This player account is no longer active.</h2>');
}

$playerClub = (string)($currentPlayer['club_name'] ?? '');

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'logout') {
            session_destroy();
            redirect('login.php');
        }

        if ($action === 'submit_leave') {
            $topic = trim($_POST['topic'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $leaveDate = trim($_POST['leave_date'] ?? '');

            if ($topic === '' || $description === '' || $leaveDate === '') {
                throw new RuntimeException('Please complete all leave application fields.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO player_leave_applications (player_id, topic, description, leave_date, status)
                VALUES (?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$currentPlayerId, $topic, $description, $leaveDate]);

            setFlash('success', 'Leave application submitted successfully.');
            redirect('player.php?section=leaveSection');
        }

        if ($action === 'submit_transfer') {
            $requestedClub = trim($_POST['requested_club_name'] ?? '');
            $requestedClubContact = trim($_POST['requested_club_contact'] ?? '');
            $reason = trim($_POST['reason_text'] ?? '');

            if ($requestedClub === '' || $reason === '') {
                throw new RuntimeException('Requested club name and reason are required.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO player_transfer_requests
                (player_id, current_club_name, requested_club_name, requested_club_contact, reason_text, status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$currentPlayerId, $playerClub, $requestedClub, $requestedClubContact, $reason]);

            $stmt = $pdo->prepare("
                INSERT INTO admin_alerts (player_id, alert_type, title, reason_text, status)
                VALUES (?, 'Transfer Request', ?, ?, 'Pending')
            ");
            $stmt->execute([
                $currentPlayerId,
                'Transfer Request - ' . (string)$currentPlayer['full_name'],
                "Current Club: {$playerClub}\nRequested Club: {$requestedClub}\nRequested Club Contact: {$requestedClubContact}\nReason: {$reason}"
            ]);

            $pdo->commit();

            setFlash('success', 'Transfer request submitted successfully.');
            redirect('player.php?section=transferSection');
        }

        if ($action === 'update_weight') {
            $weight = trim($_POST['weight_kg'] ?? '');
            if ($weight === '') {
                throw new RuntimeException('Weight is required.');
            }

            $weightFloat = (float)$weight;
            $month = date('F');
            $year = (int)date('Y');
            $gender = (string)($currentPlayer['gender'] ?? '');
            $weightCategory = deriveWeightCategory($gender, $weightFloat);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO player_weight_updates (player_id, weight_kg, recorded_month, recorded_year)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$currentPlayerId, $weightFloat, $month, $year]);

            $stmt = $pdo->prepare("
                UPDATE players
                SET weight_kg = ?, weight_category = ?
                WHERE id = ?
            ");
            $stmt->execute([$weightFloat, $weightCategory, $currentPlayerId]);

            $pdo->commit();

            setFlash('success', 'Monthly weight updated successfully.');
            redirect('player.php?section=weightSection');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
        redirect('player.php');
    }
}

/*
|--------------------------------------------------------------------------
| VIEW DATA
|--------------------------------------------------------------------------
*/
$flash = getFlash();
$activeSection = $_GET['section'] ?? 'dashboardSection';

$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
$stmt->execute([$currentPlayerId]);
$currentPlayer = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT *
    FROM player_leave_applications
    WHERE player_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$currentPlayerId]);
$leaveApplications = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM player_transfer_requests
    WHERE player_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$currentPlayerId]);
$transferRequests = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM player_weight_updates
    WHERE player_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$currentPlayerId]);
$weightUpdates = $stmt->fetchAll();

$coachNotices = [];
if ($playerClub !== '') {
    $stmt = $pdo->prepare("
        SELECT cp.*
        FROM coach_player_notices cp
        INNER JOIN coaches c ON c.id = cp.coach_id
        WHERE c.institution_name = ?
        ORDER BY cp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$playerClub]);
    $coachNotices = $stmt->fetchAll();
}

$adminNotices = [];
if (tableExists($pdo, 'notices')) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM notices
        WHERE audience IN ('All','Players')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $adminNotices = $stmt->fetchAll();
}

$gold = (int)($currentPlayer['gold_last_90_days'] ?? 0);
$silver = (int)($currentPlayer['silver_count'] ?? 0);
$bronze = (int)($currentPlayer['bronze_count'] ?? 0);
$games = (int)($currentPlayer['participated_games'] ?? 0);

$gradingCountdown = 100;
$belt = (string)($currentPlayer['belt_rank'] ?? 'White');
[$beltBg, $beltTextColor, $beltLabel] = beltColor($belt);
$guidance = beltGuidance($belt);

$qrPayload = json_encode([
    'player_id' => (string)($currentPlayer['player_code'] ?? ''),
    'full_name' => (string)($currentPlayer['full_name'] ?? ''),
    'dob' => (string)($currentPlayer['dob'] ?? ''),
    'belt_rank' => $belt,
    'club_name' => (string)($currentPlayer['club_name'] ?? ''),
    'country_name' => (string)($currentPlayer['country_name'] ?? ''),
    'contact_number' => (string)($currentPlayer['contact_number'] ?? ''),
], JSON_UNESCAPED_UNICODE);

$weightLabels = [];
$weightValues = [];
foreach ($weightUpdates as $row) {
    $weightLabels[] = $row['recorded_month'] . ' ' . $row['recorded_year'];
    $weightValues[] = (float)$row['weight_kg'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Player Dashboard</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
    :root{
      --panel:rgba(255,255,255,0.06);
      --border:rgba(255,255,255,0.12);
      --white:#ffffff;
      --soft:#cfcfcf;
      --red:#d90429;
      --blue:#1565ff;
      --gold:#e7c35a;
      --green:#22c55e;
      --shadow:0 18px 45px rgba(0,0,0,0.35);
    }
    body{min-height:100vh;background:linear-gradient(135deg,#020202,#09111f,#170407);color:var(--white);overflow-x:hidden;}
    .bg-orb{position:fixed;border-radius:50%;filter:blur(25px);opacity:.22;z-index:0;pointer-events:none;animation:float 10s ease-in-out infinite;}
    .orb1{width:260px;height:260px;background:var(--red);top:5%;left:5%;}
    .orb2{width:320px;height:320px;background:var(--blue);bottom:5%;right:5%;animation-delay:2s;}
    @keyframes float{0%,100%{transform:translateY(0) translateX(0);}50%{transform:translateY(-18px) translateX(15px);}}
    .mobile-top{display:none;padding:14px;position:sticky;top:0;z-index:30;background:rgba(0,0,0,0.65);backdrop-filter:blur(8px);border-bottom:1px solid var(--border);}
    .mobile-top button{width:100%;min-height:46px;border:1px solid var(--border);background:rgba(255,255,255,0.06);color:var(--white);border-radius:12px;font-weight:bold;cursor:pointer;}
    .app{position:relative;z-index:2;display:grid;grid-template-columns:290px 1fr;min-height:100vh;}
    .sidebar{background:rgba(0,0,0,0.45);border-right:1px solid var(--border);backdrop-filter:blur(12px);position:sticky;top:0;height:100vh;display:flex;flex-direction:column;min-height:0;}
    .sidebar-inner{display:flex;flex-direction:column;height:100%;min-height:0;padding:24px 18px;gap:18px;}
    .brand{padding:16px;background:var(--panel);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);flex:0 0 auto;}
    .brand h2{font-size:1.25rem;margin-bottom:8px;}
    .brand p{color:var(--soft);line-height:1.6;font-size:.92rem;}
    .nav{display:grid;gap:10px;overflow-y:auto;flex:1 1 auto;min-height:0;padding-right:4px;}
    .nav a,.nav button{width:100%;text-align:left;padding:14px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--white);border-radius:14px;cursor:pointer;transition:.25s ease;font-weight:bold;text-decoration:none;display:block;}
    .nav a:hover,.nav a.active,.nav button:hover{background:linear-gradient(135deg,rgba(217,4,41,.15),rgba(21,101,255,.15));border-color:rgba(255,255,255,.2);transform:translateX(3px);}
    .nav-footer{flex:0 0 auto;padding-top:6px;border-top:1px solid rgba(255,255,255,.08);}
    .main{padding:24px;}
    .topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
    .title h1{font-size:2rem;margin-bottom:8px;}
    .title p{color:var(--soft);line-height:1.6;}
    .badge{padding:12px 16px;border-radius:999px;background:linear-gradient(to right,rgba(217,4,41,.16),rgba(21,101,255,.16));border:1px solid var(--border);font-weight:bold;}
    .flash{margin-bottom:16px;padding:14px 16px;border-radius:16px;border:1px solid var(--border);line-height:1.6;}
    .flash-success{background:rgba(34,197,94,.12);color:#d8ffe4;border-color:rgba(34,197,94,.25);}
    .flash-error{background:rgba(217,4,41,.12);color:#ffd7de;border-color:rgba(217,4,41,.25);}
    .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px;}
    .stat-card,.section,.mini-card{background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:var(--shadow);}
    .stat-card{padding:20px;}
    .stat-card h3{font-size:1rem;margin-bottom:8px;color:var(--soft);}
    .stat-card .big{font-size:2rem;font-weight:bold;margin-bottom:6px;}
    .stat-card p{color:var(--soft);line-height:1.5;}
    .section{display:none;padding:22px;margin-bottom:20px;}
    .section.active{display:block;}
    .section h2{margin-bottom:10px;font-size:1.5rem;}
    .section-desc{color:var(--soft);line-height:1.6;margin-bottom:18px;}
    .form-grid,.button-row,.card-grid{display:grid;gap:14px;}
    .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px;}
    .button-row{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px;}
    .card-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    .form-group{display:grid;gap:8px;}
    .form-group.full{grid-column:1 / -1;}
    label{font-weight:bold;font-size:.95rem;}
    input,select,textarea{width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;}
    textarea{min-height:120px;resize:vertical;padding-top:12px;}
    .btn{min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;transition:.25s ease;color:var(--white);}
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue));}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .mini-card{padding:16px;margin-bottom:18px;}
    .mini-card h3{margin-bottom:10px;}
    .mini-card p{color:var(--soft);line-height:1.6;margin-bottom:12px;}
    .status-chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:bold;}
    .status-pending{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0;}
    .status-approved{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .status-rejected{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border);}
    table{width:100%;border-collapse:collapse;min-width:760px;background:rgba(255,255,255,.04);}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top;}
    th{background:rgba(255,255,255,.06);font-size:.95rem;}
    td{font-size:.94rem;line-height:1.5;}
    .notice-card{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;margin-bottom:14px;}
    .belt-bar{padding:16px;border-radius:20px;margin-bottom:18px;border:1px solid rgba(255,255,255,.12);}
    .belt-bar h3{margin-bottom:8px;}
    .belt-bar p{line-height:1.6;}
    .chart-card canvas{width:100%;height:320px;display:block;background:rgba(255,255,255,.03);border-radius:14px;border:1px solid rgba(255,255,255,.06);}
    .id-preview-wrap{display:flex;justify-content:center;}
    .id-card{width:380px;max-width:100%;background:radial-gradient(circle at 18% 20%, rgba(217,4,41,.18), transparent 28%), radial-gradient(circle at 80% 80%, rgba(21,101,255,.18), transparent 28%), linear-gradient(145deg,#0c0c0c,#111927,#0d0d0d);color:#fff;border:1px solid var(--border);border-radius:22px;padding:18px;position:relative;overflow:hidden;}
    .id-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:12px;}
    .id-logo{font-weight:bold;font-size:1rem;color:var(--gold);}
    .id-badge{padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.08);font-size:.8rem;}
    .id-name{font-size:1.35rem;font-weight:bold;margin-bottom:8px;}
    .id-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;}
    .id-item{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:10px;}
    .id-item h5{color:var(--soft);font-size:.8rem;margin-bottom:4px;}
    .id-item p{font-size:.92rem;word-break:break-word;}
    #qrcode{margin-top:14px;background:#fff;padding:10px;border-radius:12px;display:inline-block;}
    .print-actions{margin-top:16px;text-align:center;}
    @media print{
      body *{visibility:hidden !important;}
      .id-card, .id-card *{visibility:visible !important;}
      .id-card{position:absolute;left:0;top:0;width:85.6mm;height:auto;box-shadow:none;}
    }
    @media (max-width:1100px){
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .form-grid,.button-row,.card-grid{grid-template-columns:1fr;}
    }
    @media (max-width:900px){
      .app{grid-template-columns:1fr;}
      .mobile-top{display:block;}
      .sidebar{position:fixed;left:0;top:61px;width:290px;height:calc(100vh - 61px);transform:translateX(-100%);transition:.3s ease;z-index:20;}
      .sidebar.open{transform:translateX(0);}
      .main{padding:16px;}
    }
    @media (max-width:640px){
      .stats-grid{grid-template-columns:1fr;}
      .title h1{font-size:1.6rem;}
      .section{padding:16px;border-radius:18px;}
    }
  </style>
</head>
<body>
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>

  <div class="mobile-top">
    <button id="menuToggle">☰ Open Player Menu</button>
  </div>

  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-inner">
        <div class="brand">
          <h2>Welcome Athlete,</h2>
          <p>Track your rank, grading preparation, notices, leave requests, transfer requests, monthly weight updates, and athlete ID.</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="player.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'guidanceSection' ? 'active' : '' ?>" href="player.php?section=guidanceSection">🥋 Belt Guidance</a>
          <a class="<?= $activeSection === 'leaveSection' ? 'active' : '' ?>" href="player.php?section=leaveSection">📝 Leave Application</a>
          <a class="<?= $activeSection === 'transferSection' ? 'active' : '' ?>" href="player.php?section=transferSection">🔄 Transfer Request</a>
          <a class="<?= $activeSection === 'weightSection' ? 'active' : '' ?>" href="player.php?section=weightSection">⚖️ Monthly Weight</a>
          <a class="<?= $activeSection === 'noticesSection' ? 'active' : '' ?>" href="player.php?section=noticesSection">📢 Notices</a>
          <a class="<?= $activeSection === 'idCardSection' ? 'active' : '' ?>" href="player.php?section=idCardSection">🪪 Athlete ID</a>
        </div>

        <div class="nav-footer">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-secondary" type="submit" style="width:100%;">↩ Logout / Back to Login</button>
          </form>
        </div>
      </div>
    </aside>

    <main class="main">
      <div class="topbar">
        <div class="title">
          <h1>Welcome, <?= e((string)$currentPlayer['full_name']) ?></h1>
          <p><?= e((string)$currentPlayer['club_name']) ?> · <?= e((string)$currentPlayer['country_name']) ?> · Player ID: <?= e((string)$currentPlayer['player_code']) ?></p>
        </div>
        <div class="badge"><?= e((string)$belt) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Grading Countdown</h3>
          <div class="big"><?= e((string)$gradingCountdown) ?></div>
          <p>Days remaining until the next grading window.</p>
        </div>
        <div class="stat-card">
          <h3>Participated Games</h3>
          <div class="big"><?= e((string)$games) ?></div>
          <p>Total games recorded in your profile.</p>
        </div>
        <div class="stat-card">
          <h3>Current Weight</h3>
          <div class="big"><?= e((string)$currentPlayer['weight_kg']) ?></div>
          <p>Weight category: <?= e((string)$currentPlayer['weight_category']) ?></p>
        </div>
        <div class="stat-card">
          <h3>Medal Count</h3>
          <div class="big"><?= e((string)($gold + $silver + $bronze)) ?></div>
          <p>Gold <?= e((string)$gold) ?> · Silver <?= e((string)$silver) ?> · Bronze <?= e((string)$bronze) ?></p>
        </div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Player Overview</h2>
        <p class="section-desc">Your athlete dashboard with belt, guidance, medals, and performance progress.</p>

        <div class="belt-bar" style="background:<?= str_contains($beltBg, 'linear-gradient') ? $beltBg : e($beltBg) ?>; color:<?= e($beltTextColor) ?>;">
          <h3><?= e($beltLabel) ?></h3>
          <p>Your current belt rank is <?= e($belt) ?>.</p>
        </div>

        <div class="card-grid">
          <div class="mini-card chart-card">
            <h3>Medal Graph</h3>
            <canvas id="medalChart" width="700" height="320"></canvas>
          </div>

          <div class="mini-card chart-card">
            <h3>Weight Progress</h3>
            <canvas id="weightChart" width="700" height="320"></canvas>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'guidanceSection' ? 'active' : '' ?>">
        <h2>Belt Guidance</h2>
        <p class="section-desc">Practice information according to your current belt rank and preparation flow.</p>

        <div class="mini-card">
          <h3>Current Belt Preparation</h3>
          <div class="result-box">Current Belt: <?= e($belt) ?>

Poomsae to Prepare:
<?= e($guidance['poomsae']) ?>

Maki / Blocking to Prepare:
<?= e($guidance['maki']) ?></div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'leaveSection' ? 'active' : '' ?>">
        <h2>Leave Application</h2>
        <p class="section-desc">Submit leave applications to your coach. Coach can review and grant leave later.</p>

        <div class="mini-card">
          <h3>Submit Leave Application</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="submit_leave">

            <div class="form-grid">
              <div class="form-group full">
                <label>Application Topic</label>
                <input type="text" name="topic" required>
              </div>
              <div class="form-group full">
                <label>Description</label>
                <textarea name="description" required></textarea>
              </div>
              <div class="form-group">
                <label>Leave Date</label>
                <input type="date" name="leave_date" required>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Submit Leave Application</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>My Leave Applications</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Topic</th>
                  <th>Date</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Coach Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$leaveApplications): ?>
                  <tr><td colspan="5">No leave applications submitted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($leaveApplications as $leave): ?>
                    <tr>
                      <td><?= e((string)$leave['topic']) ?></td>
                      <td><?= e((string)$leave['leave_date']) ?></td>
                      <td><?= e((string)$leave['description']) ?></td>
                      <td>
                        <span class="status-chip <?= $leave['status'] === 'Pending' ? 'status-pending' : ($leave['status'] === 'Approved' ? 'status-approved' : 'status-rejected') ?>">
                          <?= e((string)$leave['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$leave['coach_remarks']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'transferSection' ? 'active' : '' ?>">
        <h2>Transfer Request</h2>
        <p class="section-desc">Request transfer to another club by submitting club details and reason.</p>

        <div class="mini-card">
          <h3>Submit Transfer Request</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="submit_transfer">

            <div class="form-grid">
              <div class="form-group">
                <label>Requested Club Name</label>
                <input type="text" name="requested_club_name" required>
              </div>
              <div class="form-group">
                <label>Requested Club Contact Number</label>
                <input type="text" name="requested_club_contact">
              </div>
              <div class="form-group full">
                <label>Reason to Transfer</label>
                <textarea name="reason_text" required></textarea>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Submit Transfer Request</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>My Transfer Requests</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Current Club</th>
                  <th>Requested Club</th>
                  <th>Requested Club Contact</th>
                  <th>Reason</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$transferRequests): ?>
                  <tr><td colspan="5">No transfer request submitted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($transferRequests as $tr): ?>
                    <tr>
                      <td><?= e((string)$tr['current_club_name']) ?></td>
                      <td><?= e((string)$tr['requested_club_name']) ?></td>
                      <td><?= e((string)$tr['requested_club_contact']) ?></td>
                      <td><?= e((string)$tr['reason_text']) ?></td>
                      <td><span class="status-chip status-pending"><?= e((string)$tr['status']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'weightSection' ? 'active' : '' ?>">
        <h2>Monthly Weight Update</h2>
        <p class="section-desc">Keep your weight updated regularly to stay ready for category-based competition management.</p>

        <div class="mini-card">
          <h3>Update Weight</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_weight">

            <div class="form-grid">
              <div class="form-group">
                <label>Current Weight (kg)</label>
                <input type="number" step="0.01" name="weight_kg" value="<?= e((string)$currentPlayer['weight_kg']) ?>" required>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Save Monthly Weight</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Weight Update History</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Year</th>
                  <th>Weight</th>
                  <th>Recorded At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$weightUpdates): ?>
                  <tr><td colspan="4">No monthly weight updates yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($weightUpdates as $w): ?>
                    <tr>
                      <td><?= e((string)$w['recorded_month']) ?></td>
                      <td><?= e((string)$w['recorded_year']) ?></td>
                      <td><?= e((string)$w['weight_kg']) ?></td>
                      <td><?= e((string)$w['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'noticesSection' ? 'active' : '' ?>">
        <h2>Notices</h2>
        <p class="section-desc">View notices from your coach and system-wide player notices from admin.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Coach Notices</h3>
            <?php if (!$coachNotices): ?>
              <div class="notice-card"><h4>No coach notices</h4><p>No notices from your coach yet.</p></div>
            <?php else: ?>
              <?php foreach ($coachNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <p style="margin-top:10px;color:var(--soft);font-size:.88rem;">Published: <?= e((string)$notice['created_at']) ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mini-card">
            <h3>Admin / System Notices</h3>
            <?php if (!$adminNotices): ?>
              <div class="notice-card"><h4>No system notices</h4><p>No player notice found yet.</p></div>
            <?php else: ?>
              <?php foreach ($adminNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <p style="margin-top:10px;color:var(--soft);font-size:.88rem;">Published: <?= e((string)$notice['created_at']) ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'idCardSection' ? 'active' : '' ?>">
        <h2>Athlete ID Card</h2>
        <p class="section-desc">Printable athlete ID card with QR code.</p>

        <div class="id-preview-wrap">
          <div class="id-card" id="printCard">
            <div class="id-top">
              <div class="id-logo">International Athlete ID</div>
              <div class="id-badge"><?= e((string)$currentPlayer['player_code']) ?></div>
            </div>

            <div class="id-name"><?= e((string)$currentPlayer['full_name']) ?></div>

            <div class="id-grid">
              <div class="id-item"><h5>Date of Birth</h5><p><?= e((string)$currentPlayer['dob']) ?></p></div>
              <div class="id-item"><h5>Belt Rank</h5><p><?= e((string)$currentPlayer['belt_rank']) ?></p></div>
              <div class="id-item"><h5>Club Name</h5><p><?= e((string)$currentPlayer['club_name']) ?></p></div>
              <div class="id-item"><h5>Country</h5><p><?= e((string)$currentPlayer['country_name']) ?></p></div>
              <div class="id-item"><h5>Contact Number</h5><p><?= e((string)$currentPlayer['contact_number']) ?></p></div>
              <div class="id-item"><h5>Participated Games</h5><p><?= e((string)$games) ?></p></div>
            </div>

            <div id="qrcode"></div>
          </div>
        </div>

        <div class="print-actions">
          <button class="btn btn-primary" type="button" onclick="window.print()">Print Athlete ID</button>
        </div>
      </section>
    </main>
  </div>

  <script>
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    if (menuToggle) {
      menuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("open");
      });
    }

    const qrText = <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE) ?>;
    const qrEl = document.getElementById("qrcode");
    if (qrEl && typeof QRCode !== "undefined") {
      new QRCode(qrEl, {
        text: qrText,
        width: 100,
        height: 100
      });
    }

    function drawBarChart(canvasId, labels, values) {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      const ctx = canvas.getContext("2d");
      const w = canvas.width;
      const h = canvas.height;
      const padding = 50;

      ctx.clearRect(0, 0, w, h);

      const maxVal = Math.max(...values, 1);
      const barWidth = 90;
      const gap = 60;

      labels.forEach((label, i) => {
        const x = padding + i * (barWidth + gap);
        const barHeight = ((h - padding * 2) * values[i]) / maxVal;
        const y = h - padding - barHeight;

        ctx.fillStyle = "rgba(255,255,255,0.8)";
        ctx.fillRect(x, y, barWidth, barHeight);

        ctx.fillStyle = "#ffffff";
        ctx.font = "14px Arial";
        ctx.fillText(String(values[i]), x + 25, y - 8);

        ctx.fillStyle = "#cfcfcf";
        ctx.fillText(label, x + 5, h - 20);
      });
    }

    function drawLineChart(canvasId, labels, values) {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return;
      const ctx = canvas.getContext("2d");
      const w = canvas.width;
      const h = canvas.height;
      const padding = 50;

      ctx.clearRect(0, 0, w, h);

      ctx.strokeStyle = "rgba(255,255,255,0.14)";
      ctx.lineWidth = 1;
      for (let i = 0; i <= 5; i++) {
        const y = padding + ((h - padding * 2) / 5) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(w - padding, y);
        ctx.stroke();
      }

      if (!values.length) {
        ctx.fillStyle = "#cfcfcf";
        ctx.font = "18px Arial";
        ctx.fillText("No weight updates yet.", padding, h / 2);
        return;
      }

      const minVal = Math.min(...values);
      const maxVal = Math.max(...values, minVal + 1);
      const stepX = values.length > 1 ? (w - padding * 2) / (values.length - 1) : 0;

      ctx.beginPath();
      ctx.lineWidth = 3;
      ctx.strokeStyle = "#ffffff";

      values.forEach((val, idx) => {
        const x = padding + stepX * idx;
        const y = h - padding - ((val - minVal) / (maxVal - minVal)) * (h - padding * 2);
        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();

      values.forEach((val, idx) => {
        const x = padding + stepX * idx;
        const y = h - padding - ((val - minVal) / (maxVal - minVal)) * (h - padding * 2);

        ctx.beginPath();
        ctx.arc(x, y, 5, 0, Math.PI * 2);
        ctx.fillStyle = "#e7c35a";
        ctx.fill();

        ctx.fillStyle = "#ffffff";
        ctx.font = "12px Arial";
        ctx.fillText(String(val), x - 8, y - 10);
        ctx.fillStyle = "#cfcfcf";
        ctx.fillText(labels[idx] || "", x - 22, h - 20);
      });
    }

    drawBarChart("medalChart", ["Gold", "Silver", "Bronze"], [<?= $gold ?>, <?= $silver ?>, <?= $bronze ?>]);
    drawLineChart(
      "weightChart",
      <?= json_encode($weightLabels, JSON_UNESCAPED_UNICODE) ?>,
      <?= json_encode($weightValues, JSON_UNESCAPED_UNICODE) ?>
    );
  </script>
</body>
</html>