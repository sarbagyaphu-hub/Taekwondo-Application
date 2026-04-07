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

const DEFAULT_ADMIN_EMAIL = 'taekwondoadmin@nta.com';
const DEFAULT_ADMIN_PASSWORD = 'Admin@123';
const DEFAULT_REFEREE_PASSWORD = 'Referee@123';

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
        redirect('admin.php');
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

function rowExists(PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1");
    $stmt->execute([$value]);
    return (bool)$stmt->fetchColumn();
}

function ageCategories(): array {
    return ['Children', 'Cadets', 'Juniors', 'Adults', 'Veterans'];
}

function olympicWeightCategories(): array {
    return [
        'Male -58kg',
        'Male -68kg',
        'Male -80kg',
        'Male +80kg',
        'Female -49kg',
        'Female -57kg',
        'Female -67kg',
        'Female +67kg',
    ];
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
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        club_address VARCHAR(255) NULL,
        contact_number VARCHAR(80) NULL,
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
        points INT NOT NULL DEFAULT 0,
        total_games_scored INT NOT NULL DEFAULT 0,
        total_tournaments INT NOT NULL DEFAULT 0,
        refresher_days_left INT NOT NULL DEFAULT 365,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

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

    "CREATE TABLE IF NOT EXISTS player_gradings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL,
        coach_id INT UNSIGNED NOT NULL,
        grading_type ENUM('Color Belt','Advanced Belt') NOT NULL,
        previous_belt VARCHAR(100) NOT NULL,
        new_belt VARCHAR(100) NOT NULL,
        marks_basic DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_kicking DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_poomsae DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_breaking DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_sparring DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_self_defence DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_one_step DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_flying_kick DECIMAL(6,2) NOT NULL DEFAULT 0,
        marks_punch DECIMAL(6,2) NOT NULL DEFAULT 0,
        total_marks DECIMAL(8,2) NOT NULL,
        result_status ENUM('Pass','Fail') NOT NULL,
        promotion_type ENUM('Normal','Double','No Promotion') NOT NULL,
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

    "CREATE TABLE IF NOT EXISTS tournaments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id INT UNSIGNED NULL,
        tournament_name VARCHAR(255) NOT NULL,
        host_club VARCHAR(190) NOT NULL,
        host_coach VARCHAR(190) NOT NULL,
        event_scope VARCHAR(120) NULL,
        poomsae_enabled TINYINT(1) NOT NULL DEFAULT 0,
        kyorugi_enabled TINYINT(1) NOT NULL DEFAULT 0,
        arena_count INT NOT NULL DEFAULT 1,
        entry_fee_poomsae DECIMAL(10,2) NULL,
        entry_fee_kyorugi DECIMAL(10,2) NULL,
        entry_fee_both_discount DECIMAL(10,2) NULL,
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS tournament_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        age_category VARCHAR(100) NULL,
        weight_category VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tournament_categories_tournament (tournament_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS tournament_applicants (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        player_id INT UNSIGNED NULL,
        applicant_name VARCHAR(255) NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        weight_category VARCHAR(100) NULL,
        age_category VARCHAR(100) NULL,
        club_name VARCHAR(190) NULL,
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tournament_applicants_tournament (tournament_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS arena_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        arena_name VARCHAR(30) NOT NULL,
        referee_id INT UNSIGNED NOT NULL,
        event_type VARCHAR(120) NULL,
        age_category VARCHAR(100) NULL,
        weight_category VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_arena_assignments_tournament (tournament_id),
        INDEX idx_arena_assignments_referee (referee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS referee_scores (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referee_id INT UNSIGNED NOT NULL,
        tournament_id INT UNSIGNED NOT NULL,
        arena_name VARCHAR(30) NULL,
        applicant_id INT UNSIGNED NOT NULL,
        player_name VARCHAR(255) NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        age_category VARCHAR(100) NULL,
        weight_category VARCHAR(100) NULL,
        presentation_total DECIMAL(4,2) NOT NULL DEFAULT 6.00,
        accuracy_total DECIMAL(4,2) NOT NULL DEFAULT 4.00,
        presentation_minor_deduction DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        presentation_major_deduction DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        accuracy_minor_deduction DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        accuracy_major_deduction DECIMAL(4,2) NOT NULL DEFAULT 0.00,
        final_score DECIMAL(4,2) NOT NULL,
        scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ref_player_score (referee_id, tournament_id, applicant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS referee_tournament_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referee_id INT UNSIGNED NOT NULL,
        tournament_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ref_tournament (referee_id, tournament_id)
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
        status ENUM('Pending','Reviewed','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        admin_remarks TEXT NULL,
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
        transfer_request_id INT UNSIGNED NULL,
        alert_type ENUM('Delete Request','Ban Request','Transfer Request','General') NOT NULL DEFAULT 'General',
        title VARCHAR(255) NOT NULL,
        reason_text TEXT NULL,
        status ENUM('Pending','Approved','Rejected','Reviewed') NOT NULL DEFAULT 'Pending',
        admin_remarks TEXT NULL,
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
$migrations = [
    'coaches' => [
        'club_address' => "ALTER TABLE coaches ADD COLUMN club_address VARCHAR(255) NULL AFTER remarks",
        'contact_number' => "ALTER TABLE coaches ADD COLUMN contact_number VARCHAR(80) NULL AFTER club_address",
    ],
    'players' => [
        'gender' => "ALTER TABLE players ADD COLUMN gender VARCHAR(20) NULL AFTER age",
        'weight_category' => "ALTER TABLE players ADD COLUMN weight_category VARCHAR(100) NULL AFTER weight_kg",
        'age_category' => "ALTER TABLE players ADD COLUMN age_category VARCHAR(100) NULL AFTER weight_category",
        'club_address' => "ALTER TABLE players ADD COLUMN club_address VARCHAR(255) NULL AFTER club_name",
        'status' => "ALTER TABLE players ADD COLUMN status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active' AFTER participated_games",
    ],
    'referees' => [
        'points' => "ALTER TABLE referees ADD COLUMN points INT NOT NULL DEFAULT 0 AFTER password_hash",
        'total_games_scored' => "ALTER TABLE referees ADD COLUMN total_games_scored INT NOT NULL DEFAULT 0 AFTER points",
        'total_tournaments' => "ALTER TABLE referees ADD COLUMN total_tournaments INT NOT NULL DEFAULT 0 AFTER total_games_scored",
        'refresher_days_left' => "ALTER TABLE referees ADD COLUMN refresher_days_left INT NOT NULL DEFAULT 365 AFTER total_tournaments",
    ],
    'tournaments' => [
        'coach_id' => "ALTER TABLE tournaments ADD COLUMN coach_id INT UNSIGNED NULL AFTER id",
        'event_scope' => "ALTER TABLE tournaments ADD COLUMN event_scope VARCHAR(120) NULL AFTER host_coach",
        'poomsae_enabled' => "ALTER TABLE tournaments ADD COLUMN poomsae_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER event_scope",
        'kyorugi_enabled' => "ALTER TABLE tournaments ADD COLUMN kyorugi_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER poomsae_enabled",
        'entry_fee_poomsae' => "ALTER TABLE tournaments ADD COLUMN entry_fee_poomsae DECIMAL(10,2) NULL AFTER arena_count",
        'entry_fee_kyorugi' => "ALTER TABLE tournaments ADD COLUMN entry_fee_kyorugi DECIMAL(10,2) NULL AFTER entry_fee_poomsae",
        'entry_fee_both_discount' => "ALTER TABLE tournaments ADD COLUMN entry_fee_both_discount DECIMAL(10,2) NULL AFTER entry_fee_kyorugi",
        'remarks' => "ALTER TABLE tournaments ADD COLUMN remarks TEXT NULL AFTER status",
    ],
    'tournament_applicants' => [
        'player_id' => "ALTER TABLE tournament_applicants ADD COLUMN player_id INT UNSIGNED NULL AFTER tournament_id",
    ],
    'arena_assignments' => [
        'event_type' => "ALTER TABLE arena_assignments ADD COLUMN event_type VARCHAR(120) NULL AFTER referee_id",
        'age_category' => "ALTER TABLE arena_assignments ADD COLUMN age_category VARCHAR(100) NULL AFTER event_type",
        'weight_category' => "ALTER TABLE arena_assignments ADD COLUMN weight_category VARCHAR(100) NULL AFTER age_category",
    ],
    'admin_alerts' => [
        'transfer_request_id' => "ALTER TABLE admin_alerts ADD COLUMN transfer_request_id INT UNSIGNED NULL AFTER player_id",
        'admin_remarks' => "ALTER TABLE admin_alerts ADD COLUMN admin_remarks TEXT NULL AFTER status",
    ],
    'player_transfer_requests' => [
        'admin_remarks' => "ALTER TABLE player_transfer_requests ADD COLUMN admin_remarks TEXT NULL AFTER status",
    ],
];

foreach ($migrations as $table => $cols) {
    if (!tableExists($pdo, $table)) {
        continue;
    }
    foreach ($cols as $column => $sql) {
        if (!columnExists($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
            }
        }
    }
}

try {
    $pdo->exec("ALTER TABLE admin_alerts MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Reviewed') NOT NULL DEFAULT 'Pending'");
} catch (Throwable $e) {}

try {
    $pdo->exec("ALTER TABLE player_transfer_requests MODIFY COLUMN status ENUM('Pending','Reviewed','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
} catch (Throwable $e) {}

/*
|--------------------------------------------------------------------------
| DEFAULT DATA
|--------------------------------------------------------------------------
*/
if (!rowExists($pdo, 'admins', 'email', DEFAULT_ADMIN_EMAIL)) {
    $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
    $stmt->execute([DEFAULT_ADMIN_EMAIL, password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
}

if (!rowExists($pdo, 'coaches', 'email', 'coach@nta.com')) {
    $stmt = $pdo->prepare("
        INSERT INTO coaches
        (registration_type, institution_name, coach_name, dob, dan_certificate_number, association_registered_number, email, password_hash, status, remarks, club_address, contact_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        'Demo verified coach account',
        'Kathmandu, Nepal',
        '9800000100'
    ]);
}

if (!rowExists($pdo, 'referees', 'email', 'referee@nta.com')) {
    $stmt = $pdo->prepare("
        INSERT INTO referees
        (first_name, last_name, referee_code, level, email, password_hash, points, total_games_scored, total_tournaments, refresher_days_left)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Suresh',
        'Adhikari',
        'REF001',
        'National',
        'referee@nta.com',
        password_hash(DEFAULT_REFEREE_PASSWORD, PASSWORD_DEFAULT),
        120,
        36,
        18,
        210
    ]);
}

if (!rowExists($pdo, 'players', 'email', 'player@nta.com')) {
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
if (($_SESSION['taekwondo_logged_in'] ?? false) !== true || ($_SESSION['taekwondo_role'] ?? '') !== 'Admin') {
    redirect('login.php');
}

$currentAdminId = (int)($_SESSION['taekwondo_admin_id'] ?? 0);
if ($currentAdminId <= 0) {
    redirect('login.php');
}

$stmt = $pdo->prepare("SELECT id, email FROM admins WHERE id = ? LIMIT 1");
$stmt->execute([$currentAdminId]);
$currentAdmin = $stmt->fetch();

if (!$currentAdmin) {
    session_destroy();
    redirect('login.php');
}

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

        if ($action === 'change_admin_password') {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
            $stmt->execute([$currentAdminId]);
            $adminRow = $stmt->fetch();

            if (!$adminRow || !password_verify($currentPassword, $adminRow['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            if (strlen($newPassword) < 6) {
                throw new RuntimeException('New password must be at least 6 characters long.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirm password do not match.');
            }

            $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $currentAdminId]);

            setFlash('success', 'Admin password changed successfully.');
            redirect('admin.php?section=accountSection');
        }

        if ($action === 'reset_admin_default') {
            $stmt = $pdo->prepare("UPDATE admins SET email = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([DEFAULT_ADMIN_EMAIL, password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT), $currentAdminId]);
            $_SESSION['taekwondo_admin_email'] = DEFAULT_ADMIN_EMAIL;

            setFlash('success', 'Admin account reset to default successfully.');
            redirect('admin.php?section=accountSection');
        }

        if ($action === 'verify_coach' || $action === 'reject_coach') {
            $coachId = (int)($_POST['coach_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $action === 'verify_coach' ? 'Verified' : 'Rejected';

            $stmt = $pdo->prepare("SELECT id, status FROM coaches WHERE id = ? LIMIT 1");
            $stmt->execute([$coachId]);
            $coach = $stmt->fetch();

            if (!$coach) {
                throw new RuntimeException('Coach application not found.');
            }
            if ($coach['status'] !== 'Pending') {
                throw new RuntimeException('This coach application has already been processed.');
            }

            $stmt = $pdo->prepare("UPDATE coaches SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([
                $status,
                $remarks !== '' ? $remarks : ($status === 'Verified' ? 'Application verified by admin.' : 'Application rejected by admin.'),
                $coachId
            ]);

            setFlash('success', "Coach application {$status} successfully.");
            redirect('admin.php?section=coachSection');
        }

        if ($action === 'publish_notice') {
            $title = trim($_POST['notice_title'] ?? '');
            $message = trim($_POST['notice_message'] ?? '');
            $audience = trim($_POST['audience'] ?? 'All');

            if ($title === '' || $message === '') {
                throw new RuntimeException('Notice title and message are required.');
            }

            $allowedAudiences = ['All', 'Coaches', 'Players', 'Referees'];
            if (!in_array($audience, $allowedAudiences, true)) {
                $audience = 'All';
            }

            $stmt = $pdo->prepare("INSERT INTO notices (title, message, audience, created_by_admin_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $message, $audience, $currentAdminId]);

            setFlash('success', 'Notice published successfully.');
            redirect('admin.php?section=noticeSection');
        }

        if ($action === 'create_single_referee') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $refereeCode = trim($_POST['referee_code'] ?? '');
            $level = trim($_POST['level'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($firstName === '' || $lastName === '' || $refereeCode === '' || $level === '' || $email === '') {
                throw new RuntimeException('All referee fields are required.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO referees (first_name, last_name, referee_code, level, email, password_hash, points, total_games_scored, total_tournaments, refresher_days_left)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 365)
            ");
            $stmt->execute([$firstName, $lastName, $refereeCode, $level, $email, password_hash(DEFAULT_REFEREE_PASSWORD, PASSWORD_DEFAULT)]);

            setFlash('success', 'Referee created successfully with default password Referee@123.');
            redirect('admin.php?section=refereeSection');
        }

        if ($action === 'create_bulk_referees') {
            $bulkText = trim($_POST['bulk_text'] ?? '');
            if ($bulkText === '') {
                throw new RuntimeException('Bulk OCR text is required.');
            }

            $lines = preg_split('/\r\n|\r|\n/', $bulkText);
            $clean = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $clean[] = $line;
                }
            }

            if (count($clean) < 5) {
                throw new RuntimeException('Invalid OCR format. Every referee requires 5 lines.');
            }

            $insert = $pdo->prepare("
                INSERT INTO referees (first_name, last_name, referee_code, level, email, password_hash, points, total_games_scored, total_tournaments, refresher_days_left)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 365)
            ");

            $created = 0;
            for ($i = 0; $i < count($clean); $i += 5) {
                $chunk = array_slice($clean, $i, 5);
                if (count($chunk) !== 5) {
                    continue;
                }
                [$firstName, $lastName, $refCode, $level, $email] = $chunk;
                $insert->execute([$firstName, $lastName, $refCode, $level, $email, password_hash(DEFAULT_REFEREE_PASSWORD, PASSWORD_DEFAULT)]);
                $created++;
            }

            setFlash('success', "Bulk referee creation completed. {$created} referee record(s) inserted.");
            redirect('admin.php?section=refereeSection');
        }

        if ($action === 'verify_tournament' || $action === 'reject_tournament') {
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $action === 'verify_tournament' ? 'Verified' : 'Rejected';

            $stmt = $pdo->prepare("SELECT id, status FROM tournaments WHERE id = ? LIMIT 1");
            $stmt->execute([$tournamentId]);
            $tournament = $stmt->fetch();

            if (!$tournament) {
                throw new RuntimeException('Tournament not found.');
            }
            if ($tournament['status'] !== 'Pending') {
                throw new RuntimeException('This tournament has already been processed.');
            }

            $stmt = $pdo->prepare("UPDATE tournaments SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([
                $status,
                $remarks !== '' ? $remarks : ($status === 'Verified' ? 'Tournament verified by admin.' : 'Tournament rejected by admin.'),
                $tournamentId
            ]);

            setFlash('success', "Tournament {$status} successfully.");
            redirect('admin.php?section=tournamentSection&tournament=' . $tournamentId);
        }

        if ($action === 'verify_applicant' || $action === 'reject_applicant') {
            $applicantId = (int)($_POST['applicant_id'] ?? 0);
            $selectedTournamentId = (int)($_POST['selected_tournament_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $action === 'verify_applicant' ? 'Verified' : 'Rejected';

            $stmt = $pdo->prepare("
                SELECT ta.*, t.id AS tournament_exists
                FROM tournament_applicants ta
                INNER JOIN tournaments t ON t.id = ta.tournament_id
                WHERE ta.id = ? LIMIT 1
            ");
            $stmt->execute([$applicantId]);
            $applicant = $stmt->fetch();

            if (!$applicant) {
                throw new RuntimeException('Tournament applicant not found.');
            }
            if ($applicant['status'] !== 'Pending') {
                throw new RuntimeException('This applicant has already been processed.');
            }

            if ($status === 'Verified') {
                $eventType = (string)$applicant['event_type'];
                $ageCategory = (string)($applicant['age_category'] ?? '');
                $weightCategory = (string)($applicant['weight_category'] ?? '');
                $tournamentId = (int)$applicant['tournament_id'];

                $allowed = false;

                if ($eventType === 'Kyorugi') {
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM tournament_categories
                        WHERE tournament_id = ?
                          AND event_type = 'Kyorugi'
                          AND age_category = ?
                          AND weight_category = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$tournamentId, $ageCategory, $weightCategory]);
                    $allowed = (bool)$stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM tournament_categories
                        WHERE tournament_id = ?
                          AND event_type = ?
                          AND age_category = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$tournamentId, $eventType, $ageCategory]);
                    $allowed = (bool)$stmt->fetchColumn();
                }

                if (!$allowed) {
                    throw new RuntimeException('Applicant category does not match hosted tournament categories. Please fix category structure first.');
                }
            }

            $stmt = $pdo->prepare("UPDATE tournament_applicants SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([
                $status,
                $remarks !== '' ? $remarks : ($status === 'Verified' ? 'Applicant verified by admin.' : 'Applicant rejected by admin.'),
                $applicantId
            ]);

            setFlash('success', "Applicant {$status} successfully.");
            redirect('admin.php?section=tournamentSection&tournament=' . $selectedTournamentId);
        }

        if ($action === 'assign_referees') {
            $tournamentId = (int)($_POST['assignment_tournament_id'] ?? 0);
            $selectedReferees = $_POST['referee_ids'] ?? [];
            $arenaMode = trim($_POST['arena_mode'] ?? 'auto');

            if ($tournamentId <= 0) {
                throw new RuntimeException('Please select a verified tournament.');
            }
            if (!is_array($selectedReferees) || count($selectedReferees) === 0) {
                throw new RuntimeException('Please select referees first.');
            }

            $stmt = $pdo->prepare("SELECT id, arena_count, status FROM tournaments WHERE id = ? LIMIT 1");
            $stmt->execute([$tournamentId]);
            $tournament = $stmt->fetch();

            if (!$tournament || $tournament['status'] !== 'Verified') {
                throw new RuntimeException('Tournament must be verified before assigning referees.');
            }

            $categoriesStmt = $pdo->prepare("
                SELECT event_type, age_category, weight_category
                FROM tournament_categories
                WHERE tournament_id = ?
                ORDER BY event_type, age_category, weight_category
            ");
            $categoriesStmt->execute([$tournamentId]);
            $categories = $categoriesStmt->fetchAll();

            if (!$categories) {
                throw new RuntimeException('No tournament categories found. Coach must host category structure first.');
            }

            $selectedReferees = array_values(array_unique(array_map('intval', $selectedReferees)));
            $arenaCount = max(1, (int)$tournament['arena_count']);
            $requiredMinimum = $arenaCount * 2;

            if (count($selectedReferees) < $requiredMinimum) {
                throw new RuntimeException("Not enough referees selected. Minimum {$requiredMinimum} referees needed for {$arenaCount} arenas.");
            }

            shuffle($selectedReferees);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM arena_assignments WHERE tournament_id = ?");
            $stmt->execute([$tournamentId]);

            $insert = $pdo->prepare("
                INSERT INTO arena_assignments (tournament_id, arena_name, referee_id, event_type, age_category, weight_category)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $refIndex = 0;
            foreach ($categories as $idx => $cat) {
                $arenaNumber = ($idx % $arenaCount);
                $arenaName = 'Arena ' . chr(65 + $arenaNumber);

                $refsPerCategory = min(3, count($selectedReferees));
                for ($j = 0; $j < $refsPerCategory; $j++) {
                    $refId = $selectedReferees[($refIndex + $j) % count($selectedReferees)];
                    $insert->execute([
                        $tournamentId,
                        $arenaName,
                        $refId,
                        $cat['event_type'],
                        $cat['age_category'],
                        $cat['weight_category']
                    ]);
                }
                $refIndex++;
            }

            $pdo->commit();

            setFlash('success', 'Referees assigned successfully to tournament categories and arenas.');
            redirect('admin.php?section=tournamentSection&tournament=' . $tournamentId);
        }

        if ($action === 'generate_tiesheet') {
            $_SESSION['tiesheet_request'] = [
                'tournament_id' => (int)($_POST['tiesheet_tournament_id'] ?? 0),
                'event_type' => trim($_POST['event_type'] ?? ''),
                'age_category' => trim($_POST['age_category'] ?? ''),
                'weight_category' => trim($_POST['weight_category'] ?? '')
            ];
            redirect('admin.php?section=tournamentSection&tournament=' . (int)($_POST['tiesheet_tournament_id'] ?? 0));
        }

        if ($action === 'approve_alert' || $action === 'reject_alert') {
            $alertId = (int)($_POST['alert_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $decision = $action === 'approve_alert' ? 'Approved' : 'Rejected';

            $stmt = $pdo->prepare("SELECT * FROM admin_alerts WHERE id = ? LIMIT 1");
            $stmt->execute([$alertId]);
            $alert = $stmt->fetch();

            if (!$alert) {
                throw new RuntimeException('Admin request not found.');
            }
            if ($alert['status'] !== 'Pending') {
                throw new RuntimeException('This request has already been processed.');
            }

            $pdo->beginTransaction();

            if ($decision === 'Approved') {
                if ($alert['alert_type'] === 'Delete Request') {
                    $stmt = $pdo->prepare("UPDATE players SET status = 'Deleted' WHERE id = ?");
                    $stmt->execute([(int)$alert['player_id']]);
                } elseif ($alert['alert_type'] === 'Ban Request') {
                    $stmt = $pdo->prepare("UPDATE players SET status = 'Banned' WHERE id = ?");
                    $stmt->execute([(int)$alert['player_id']]);
                } elseif ($alert['alert_type'] === 'Transfer Request') {
                    $transferRequestId = (int)($alert['transfer_request_id'] ?? 0);

                    if ($transferRequestId > 0) {
                        $stmt = $pdo->prepare("SELECT * FROM player_transfer_requests WHERE id = ? LIMIT 1");
                        $stmt->execute([$transferRequestId]);
                        $tr = $stmt->fetch();

                        if ($tr) {
                            $stmt = $pdo->prepare("UPDATE players SET club_name = ? WHERE id = ?");
                            $stmt->execute([$tr['requested_club_name'], (int)$tr['player_id']]);

                            $stmt = $pdo->prepare("UPDATE player_transfer_requests SET status = 'Approved', admin_remarks = ? WHERE id = ?");
                            $stmt->execute([$remarks !== '' ? $remarks : 'Transfer approved by admin.', $transferRequestId]);
                        }
                    }
                }
            } else {
                if ($alert['alert_type'] === 'Transfer Request') {
                    $transferRequestId = (int)($alert['transfer_request_id'] ?? 0);
                    if ($transferRequestId > 0) {
                        $stmt = $pdo->prepare("UPDATE player_transfer_requests SET status = 'Rejected', admin_remarks = ? WHERE id = ?");
                        $stmt->execute([$remarks !== '' ? $remarks : 'Transfer rejected by admin.', $transferRequestId]);
                    }
                }
            }

            $stmt = $pdo->prepare("UPDATE admin_alerts SET status = ?, admin_remarks = ? WHERE id = ?");
            $stmt->execute([
                $decision,
                $remarks !== '' ? $remarks : ($decision === 'Approved' ? 'Approved by admin.' : 'Rejected by admin.'),
                $alertId
            ]);

            $pdo->commit();

            setFlash('success', 'Request processed successfully.');
            redirect('admin.php?section=requestSection');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
        redirect('admin.php');
    }
}

/*
|--------------------------------------------------------------------------
| VIEW DATA
|--------------------------------------------------------------------------
*/
$flash = getFlash();
$activeSection = $_GET['section'] ?? 'dashboardSection';
$selectedTournamentId = (int)($_GET['tournament'] ?? 0);

$totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'Active'")->fetchColumn();
$totalCoaches = (int)$pdo->query("SELECT COUNT(*) FROM coaches")->fetchColumn();
$totalReferees = (int)$pdo->query("SELECT COUNT(*) FROM referees")->fetchColumn();
$pendingCoachApplications = (int)$pdo->query("SELECT COUNT(*) FROM coaches WHERE status = 'Pending'")->fetchColumn();
$pendingTournamentApplicants = (int)$pdo->query("SELECT COUNT(*) FROM tournament_applicants WHERE status = 'Pending'")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM admin_alerts WHERE status = 'Pending'")->fetchColumn();
$pendingTournaments = (int)$pdo->query("SELECT COUNT(*) FROM tournaments WHERE status = 'Pending'")->fetchColumn();

$coaches = $pdo->query("
    SELECT id, coach_name, registration_type, institution_name, email, status, remarks, contact_number, club_address
    FROM coaches
    ORDER BY created_at DESC
")->fetchAll();

$referees = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, referee_code, level, email, points, total_games_scored
    FROM referees
    ORDER BY full_name ASC
")->fetchAll();

$recentNotices = $pdo->query("
    SELECT title, message, audience, created_at
    FROM notices
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

$tournaments = $pdo->query("
    SELECT t.*,
           c.coach_name AS linked_coach_name
    FROM tournaments t
    LEFT JOIN coaches c ON c.id = t.coach_id
    ORDER BY t.created_at DESC
")->fetchAll();

$verifiedTournaments = $pdo->query("
    SELECT id, tournament_name, arena_count, event_scope
    FROM tournaments
    WHERE status = 'Verified'
    ORDER BY tournament_name ASC
")->fetchAll();

$tournamentApplicants = [];
$tournamentCategories = [];
$assignmentPreview = [];
$leaveOverview = [];
$selectedTournamentMeta = null;

if ($selectedTournamentId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ? LIMIT 1");
    $stmt->execute([$selectedTournamentId]);
    $selectedTournamentMeta = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT id, applicant_name, event_type, weight_category, age_category, club_name, status, remarks
        FROM tournament_applicants
        WHERE tournament_id = ?
        ORDER BY event_type, age_category, weight_category, applicant_name
    ");
    $stmt->execute([$selectedTournamentId]);
    $tournamentApplicants = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT event_type, age_category, weight_category
        FROM tournament_categories
        WHERE tournament_id = ?
        ORDER BY event_type, age_category, weight_category
    ");
    $stmt->execute([$selectedTournamentId]);
    $tournamentCategories = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT aa.arena_name, aa.event_type, aa.age_category, aa.weight_category,
               CONCAT(r.first_name, ' ', r.last_name) AS referee_name, r.referee_code, r.level
        FROM arena_assignments aa
        INNER JOIN referees r ON r.id = aa.referee_id
        WHERE aa.tournament_id = ?
        ORDER BY aa.arena_name, aa.event_type, aa.age_category, aa.weight_category, referee_name
    ");
    $stmt->execute([$selectedTournamentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $assignmentPreview[$row['arena_name']][] = $row;
    }

    $stmt = $pdo->prepare("
        SELECT pla.id, p.full_name, p.club_name, pla.topic, pla.description, pla.leave_date, pla.status, pla.coach_remarks
        FROM player_leave_applications pla
        INNER JOIN players p ON p.id = pla.player_id
        WHERE p.club_name = (SELECT host_club FROM tournaments WHERE id = ? LIMIT 1)
        ORDER BY pla.created_at DESC
    ");
    $stmt->execute([$selectedTournamentId]);
    $leaveOverview = $stmt->fetchAll();
}

$adminAlerts = $pdo->query("
    SELECT a.*,
           p.full_name AS player_name,
           p.club_name AS player_club,
           c.coach_name,
           c.institution_name
    FROM admin_alerts a
    LEFT JOIN players p ON p.id = a.player_id
    LEFT JOIN coaches c ON c.id = a.coach_id
    ORDER BY a.created_at DESC, a.id DESC
")->fetchAll();

$players = $pdo->query("
    SELECT id, full_name, age_category, weight_category, belt_rank, club_name, gold_last_90_days, status
    FROM players
    ORDER BY created_at DESC
")->fetchAll();

$leaveApplicationsAll = $pdo->query("
    SELECT pla.*, p.full_name, p.club_name
    FROM player_leave_applications pla
    INNER JOIN players p ON p.id = pla.player_id
    ORDER BY pla.created_at DESC
")->fetchAll();

$tiesheetText = '';
if (!empty($_SESSION['tiesheet_request'])) {
    $req = $_SESSION['tiesheet_request'];
    if ((int)$req['tournament_id'] === $selectedTournamentId && !empty($req['event_type'])) {
        $eventType = $req['event_type'];
        $ageCategory = trim((string)($req['age_category'] ?? ''));
        $weightCategory = trim((string)($req['weight_category'] ?? ''));

        $sql = "
            SELECT t.tournament_name, ta.applicant_name, ta.event_type, ta.weight_category, ta.age_category, ta.club_name
            FROM tournament_applicants ta
            INNER JOIN tournaments t ON t.id = ta.tournament_id
            WHERE ta.tournament_id = ? AND ta.status = 'Verified' AND ta.event_type = ?
        ";
        $params = [$selectedTournamentId, $eventType];

        if ($ageCategory !== '') {
            $sql .= " AND ta.age_category = ?";
            $params[] = $ageCategory;
        }
        if ($weightCategory !== '') {
            $sql .= " AND ta.weight_category = ?";
            $params[] = $weightCategory;
        }

        $sql .= " ORDER BY ta.age_category, ta.weight_category, ta.applicant_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll();

        if ($apps) {
            $tiesheetText .= "Tiesheet for " . $apps[0]['tournament_name'] . "\n";
            $tiesheetText .= "Event Type: {$eventType}\n";
            if ($ageCategory !== '') {
                $tiesheetText .= "Age Category: {$ageCategory}\n";
            }
            if ($weightCategory !== '') {
                $tiesheetText .= "Weight Category: {$weightCategory}\n";
            }
            $tiesheetText .= "\n";

            foreach ($apps as $i => $member) {
                $tiesheetText .= ($i + 1) . ". {$member['applicant_name']} - {$member['club_name']}";
                if ($member['age_category']) {
                    $tiesheetText .= " | {$member['age_category']}";
                }
                if ($member['weight_category']) {
                    $tiesheetText .= " | {$member['weight_category']}";
                }
                $tiesheetText .= "\n";
            }
        } else {
            $tiesheetText = 'No verified applicants found for the selected tiesheet category.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Taekwondo Management</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
    :root{
      --panel:rgba(255,255,255,0.06);
      --border:rgba(255,255,255,0.12);
      --white:#ffffff;
      --soft:#cfcfcf;
      --red:#d90429;
      --blue:#1565ff;
      --green:#22c55e;
      --gold:#e7c35a;
      --shadow:0 18px 45px rgba(0,0,0,0.35);
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
    .orb1{width:260px;height:260px;background:var(--red);top:5%;left:5%;}
    .orb2{width:320px;height:320px;background:var(--blue);bottom:5%;right:5%;animation-delay:2s;}
    @keyframes float{
      0%,100%{transform:translateY(0) translateX(0);}
      50%{transform:translateY(-18px) translateX(15px);}
    }
    .mobile-top{
      display:none;padding:14px;position:sticky;top:0;z-index:30;background:rgba(0,0,0,0.65);
      backdrop-filter:blur(8px);border-bottom:1px solid var(--border);
    }
    .mobile-top button{
      width:100%;min-height:46px;border:1px solid var(--border);background:rgba(255,255,255,0.06);
      color:var(--white);border-radius:12px;font-weight:bold;cursor:pointer;
    }
    .app{position:relative;z-index:2;display:grid;grid-template-columns:290px 1fr;min-height:100vh;}
    .sidebar{
      background:rgba(0,0,0,0.45);border-right:1px solid var(--border);backdrop-filter:blur(12px);
      position:sticky;top:0;height:100vh;display:flex;flex-direction:column;min-height:0;
    }
    .sidebar-inner{display:flex;flex-direction:column;height:100%;min-height:0;padding:24px 18px;gap:18px;}
    .brand{
      padding:16px;background:var(--panel);border:1px solid var(--border);border-radius:18px;
      box-shadow:var(--shadow);flex:0 0 auto;
    }
    .brand h2{font-size:1.3rem;margin-bottom:8px;}
    .brand p{color:var(--soft);line-height:1.5;font-size:.92rem;}
    .nav{display:grid;gap:10px;overflow-y:auto;flex:1 1 auto;min-height:0;padding-right:4px;}
    .nav a,.nav button{
      width:100%;text-align:left;padding:14px;border:1px solid var(--border);background:rgba(255,255,255,.04);
      color:var(--white);border-radius:14px;cursor:pointer;transition:.25s ease;font-weight:bold;text-decoration:none;display:block;
    }
    .nav a:hover,.nav a.active,.nav button:hover{
      background:linear-gradient(135deg,rgba(217,4,41,.15),rgba(21,101,255,.15));
      border-color:rgba(255,255,255,.2);transform:translateX(3px);
    }
    .nav-footer{flex:0 0 auto;padding-top:6px;border-top:1px solid rgba(255,255,255,.08);}
    .main{padding:24px;}
    .topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
    .title h1{font-size:2rem;margin-bottom:6px;}
    .title p{color:var(--soft);line-height:1.6;}
    .admin-badge{
      padding:12px 16px;border-radius:999px;background:linear-gradient(to right,rgba(217,4,41,.16),rgba(21,101,255,.16));
      border:1px solid var(--border);font-weight:bold;
    }
    .flash{margin-bottom:16px;padding:14px 16px;border-radius:16px;border:1px solid var(--border);line-height:1.6;}
    .flash-success{background:rgba(34,197,94,.12);color:#d8ffe4;border-color:rgba(34,197,94,.25);}
    .flash-error{background:rgba(217,4,41,.12);color:#ffd7de;border-color:rgba(217,4,41,.25);}
    .stats-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:16px;margin-bottom:24px;}
    .stat-card,.section,.mini-card{
      background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:var(--shadow);
    }
    .stat-card{padding:20px;}
    .stat-card h3{font-size:2rem;margin-bottom:8px;}
    .stat-card p{color:var(--soft);line-height:1.5;}
    .section{display:none;padding:22px;margin-bottom:20px;}
    .section.active{display:block;}
    .section h2{margin-bottom:10px;font-size:1.5rem;}
    .section-desc{color:var(--soft);line-height:1.6;margin-bottom:18px;}
    .filters,.form-grid,.button-row,.card-grid{display:grid;gap:14px;}
    .filters{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px;}
    .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px;}
    .button-row{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px;}
    .card-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    .form-group{display:grid;gap:8px;}
    .form-group.full{grid-column:1 / -1;}
    label{font-weight:bold;font-size:.95rem;}
    input,select,textarea{
      width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);
      background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;
    }
    select option{background:#ffffff;color:#111111;}
    textarea{min-height:120px;resize:vertical;padding-top:12px;}
    .btn{
      min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;
      transition:.25s ease;color:var(--white);
    }
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue));}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .btn-danger{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .btn-warning{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0;}
    .mini-card{padding:16px;margin-bottom:18px;}
    .mini-card h3{margin-bottom:8px;}
    .mini-card p{color:var(--soft);line-height:1.5;margin-bottom:14px;}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border);}
    table{width:100%;border-collapse:collapse;min-width:760px;background:rgba(255,255,255,.04);}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top;}
    th{background:rgba(255,255,255,.06);font-size:.95rem;}
    td{font-size:.94rem;line-height:1.5;}
    .status-chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:bold;}
    .status-pending{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0;}
    .status-verified,.status-approved{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .status-rejected{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .notice-list{display:grid;gap:14px;margin-top:18px;}
    .notice-card{
      background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;
    }
    .notice-card h4{margin-bottom:8px;}
    .notice-card p{color:var(--soft);line-height:1.6;}
    .result-box{
      margin-top:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.05);
      border:1px solid var(--border);line-height:1.6;color:var(--soft);white-space:pre-wrap;
    }
    .assignment-block{
      margin-top:16px;padding:16px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid var(--border);
    }
    .assignment-block h4{margin-bottom:10px;color:var(--gold);}
    .assignment-block ul{padding-left:18px;color:var(--soft);line-height:1.8;}
    .action-stack{display:grid;gap:10px;min-width:260px;}
    .muted{color:var(--soft);}
    .subtle{font-size:.86rem;color:var(--soft);}
    @media (max-width:1250px){
      .stats-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
      .filters,.form-grid,.button-row,.card-grid{grid-template-columns:1fr;}
    }
    @media (max-width:900px){
      .app{grid-template-columns:1fr;}
      .mobile-top{display:block;}
      .sidebar{
        position:fixed;left:0;top:61px;width:290px;height:calc(100vh - 61px);
        transform:translateX(-100%);transition:.3s ease;z-index:20;
      }
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
    <button id="menuToggle">☰ Open Admin Menu</button>
  </div>

  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-inner">
        <div class="brand">
          <h2>Admin Panel</h2>
          <p>Taekwondo Player Management and Poomsae Referee Administration</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="admin.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'playerSection' ? 'active' : '' ?>" href="admin.php?section=playerSection">🥋 Player Data</a>
          <a class="<?= $activeSection === 'refereeSection' ? 'active' : '' ?>" href="admin.php?section=refereeSection">🏅 Referee IDs</a>
          <a class="<?= $activeSection === 'coachSection' ? 'active' : '' ?>" href="admin.php?section=coachSection">📋 Coach Applications</a>
          <a class="<?= $activeSection === 'requestSection' ? 'active' : '' ?>" href="admin.php?section=requestSection">🛂 Requests</a>
          <a class="<?= $activeSection === 'leaveSection' ? 'active' : '' ?>" href="admin.php?section=leaveSection">📝 Leave Overview</a>
          <a class="<?= $activeSection === 'tournamentSection' ? 'active' : '' ?>" href="admin.php?section=tournamentSection">🏆 Tournament Management</a>
          <a class="<?= $activeSection === 'noticeSection' ? 'active' : '' ?>" href="admin.php?section=noticeSection">📢 Publish Notice</a>
          <a class="<?= $activeSection === 'accountSection' ? 'active' : '' ?>" href="admin.php?section=accountSection">🔐 Admin Account</a>
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
          <h1>Admin Dashboard</h1>
          <p>Manage coaches, players, referees, tournaments, registrations, arenas, leaves, and notices from one control panel.</p>
        </div>
        <div class="admin-badge">Logged in as <?= e($currentAdmin['email']) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card"><h3><?= e((string)$totalPlayers) ?></h3><p>Active Players</p></div>
        <div class="stat-card"><h3><?= e((string)$totalCoaches) ?></h3><p>Registered Coaches</p></div>
        <div class="stat-card"><h3><?= e((string)$totalReferees) ?></h3><p>Total Referees</p></div>
        <div class="stat-card"><h3><?= e((string)$pendingCoachApplications) ?></h3><p>Pending Coach Applications</p></div>
        <div class="stat-card"><h3><?= e((string)$pendingTournaments) ?></h3><p>Pending Tournaments</p></div>
        <div class="stat-card"><h3><?= e((string)$pendingTournamentApplicants) ?></h3><p>Pending Tournament Entries</p></div>
        <div class="stat-card"><h3><?= e((string)$pendingRequests) ?></h3><p>Pending Admin Requests</p></div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Overview</h2>
        <p class="section-desc">This admin panel is the base of your full system. It controls coach verification, tournament approvals, player tournament approvals, referee assignment, requests, and notices.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Coach Approvals</h3>
            <p>Verify or reject coach applications with remarks.</p>
            <a class="btn btn-primary" href="admin.php?section=coachSection" style="display:inline-block;text-decoration:none;">Open Coach Section</a>
          </div>

          <div class="mini-card">
            <h3>Tournament Flow</h3>
            <p>Verify tournaments, review player registrations, assign referees and arenas, and generate tiesheets.</p>
            <a class="btn btn-primary" href="admin.php?section=tournamentSection" style="display:inline-block;text-decoration:none;">Open Tournament Section</a>
          </div>

          <div class="mini-card">
            <h3>Requests</h3>
            <p>Approve or reject player delete, ban, and transfer requests sent from the system.</p>
            <a class="btn btn-primary" href="admin.php?section=requestSection" style="display:inline-block;text-decoration:none;">Open Requests</a>
          </div>

          <div class="mini-card">
            <h3>Leave Overview</h3>
            <p>Admin can view all leaves for supervision, while actual leave approval remains a coach function.</p>
            <a class="btn btn-primary" href="admin.php?section=leaveSection" style="display:inline-block;text-decoration:none;">Open Leave Overview</a>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'playerSection' ? 'active' : '' ?>">
        <h2>Players</h2>
        <p class="section-desc">Admin can inspect overall player records and current status.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Player</th>
                <th>Club</th>
                <th>Age Category</th>
                <th>Weight Category</th>
                <th>Belt</th>
                <th>Gold (90 days)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$players): ?>
                <tr><td colspan="7">No player data found.</td></tr>
              <?php else: ?>
                <?php foreach ($players as $player): ?>
                  <tr>
                    <td><?= e((string)$player['full_name']) ?></td>
                    <td><?= e((string)$player['club_name']) ?></td>
                    <td><?= e((string)$player['age_category']) ?></td>
                    <td><?= e((string)$player['weight_category']) ?></td>
                    <td><?= e((string)$player['belt_rank']) ?></td>
                    <td><?= e((string)$player['gold_last_90_days']) ?></td>
                    <td>
                      <span class="status-chip <?= $player['status'] === 'Active' ? 'status-verified' : 'status-rejected' ?>">
                        <?= e((string)$player['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section <?= $activeSection === 'refereeSection' ? 'active' : '' ?>">
        <h2>Referee Management</h2>
        <p class="section-desc">Create referee IDs individually or in bulk. These accounts can log in and receive schedules after admin arena assignment.</p>

        <div class="mini-card">
          <h3>Create Single Referee</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_single_referee">

            <div class="form-grid">
              <div class="form-group"><label>First Name</label><input type="text" name="first_name" required></div>
              <div class="form-group"><label>Last Name</label><input type="text" name="last_name" required></div>
              <div class="form-group"><label>Referee Code</label><input type="text" name="referee_code" required></div>
              <div class="form-group"><label>Level</label><input type="text" name="level" required></div>
              <div class="form-group full"><label>Email</label><input type="email" name="email" required></div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Create Referee</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Bulk Referee Create</h3>
          <p>Paste OCR / text in this order for each referee: first name, last name, referee code, level, email.</p>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_bulk_referees">

            <div class="form-group">
              <label>Bulk OCR Text</label>
              <textarea name="bulk_text" required></textarea>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Create Bulk Referees</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Referee List</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Code</th>
                  <th>Level</th>
                  <th>Email</th>
                  <th>Points</th>
                  <th>Games</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$referees): ?>
                  <tr><td colspan="6">No referees found.</td></tr>
                <?php else: ?>
                  <?php foreach ($referees as $ref): ?>
                    <tr>
                      <td><?= e((string)$ref['full_name']) ?></td>
                      <td><?= e((string)$ref['referee_code']) ?></td>
                      <td><?= e((string)$ref['level']) ?></td>
                      <td><?= e((string)$ref['email']) ?></td>
                      <td><?= e((string)$ref['points']) ?></td>
                      <td><?= e((string)$ref['total_games_scored']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'coachSection' ? 'active' : '' ?>">
        <h2>Coach Applications</h2>
        <p class="section-desc">Review pending coach registrations and verify or reject them.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Coach</th>
                <th>Type</th>
                <th>Institution</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$coaches): ?>
                <tr><td colspan="8">No coach applications found.</td></tr>
              <?php else: ?>
                <?php foreach ($coaches as $coach): ?>
                  <tr>
                    <td><?= e((string)$coach['coach_name']) ?></td>
                    <td><?= e((string)$coach['registration_type']) ?></td>
                    <td><?= e((string)$coach['institution_name']) ?></td>
                    <td><?= e((string)$coach['email']) ?></td>
                    <td><?= e((string)$coach['contact_number']) ?></td>
                    <td>
                      <span class="status-chip <?= $coach['status'] === 'Pending' ? 'status-pending' : ($coach['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                        <?= e((string)$coach['status']) ?>
                      </span>
                    </td>
                    <td><?= e((string)$coach['remarks']) ?></td>
                    <td>
                      <?php if ($coach['status'] === 'Pending'): ?>
                        <div class="action-stack">
                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="coach_id" value="<?= (int)$coach['id'] ?>">
                            <input type="text" name="remarks" placeholder="Verification remark">
                            <input type="hidden" name="action" value="verify_coach">
                            <button class="btn btn-success" type="submit">Verify Coach</button>
                          </form>

                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="coach_id" value="<?= (int)$coach['id'] ?>">
                            <input type="text" name="remarks" placeholder="Rejection remark">
                            <input type="hidden" name="action" value="reject_coach">
                            <button class="btn btn-danger" type="submit">Reject Coach</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="subtle">Already processed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section <?= $activeSection === 'requestSection' ? 'active' : '' ?>">
        <h2>System Requests</h2>
        <p class="section-desc">Approve or reject delete, ban, and transfer requests raised inside the system.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Type</th>
                <th>Title</th>
                <th>Player / Coach</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Admin Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$adminAlerts): ?>
                <tr><td colspan="7">No admin requests found.</td></tr>
              <?php else: ?>
                <?php foreach ($adminAlerts as $alert): ?>
                  <tr>
                    <td><?= e((string)$alert['alert_type']) ?></td>
                    <td><?= e((string)$alert['title']) ?></td>
                    <td>
                      <?= e((string)($alert['player_name'] ?? '')) ?>
                      <?php if (!empty($alert['player_club'])): ?>
                        <div class="subtle"><?= e((string)$alert['player_club']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($alert['coach_name'])): ?>
                        <div class="subtle"><?= e((string)$alert['coach_name']) ?> - <?= e((string)$alert['institution_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= nl2br(e((string)$alert['reason_text'])) ?></td>
                    <td>
                      <span class="status-chip <?= $alert['status'] === 'Pending' ? 'status-pending' : ($alert['status'] === 'Approved' ? 'status-approved' : 'status-rejected') ?>">
                        <?= e((string)$alert['status']) ?>
                      </span>
                    </td>
                    <td><?= e((string)$alert['admin_remarks']) ?></td>
                    <td>
                      <?php if ($alert['status'] === 'Pending'): ?>
                        <div class="action-stack">
                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="alert_id" value="<?= (int)$alert['id'] ?>">
                            <input type="text" name="remarks" placeholder="Approval remark">
                            <input type="hidden" name="action" value="approve_alert">
                            <button class="btn btn-success" type="submit">Approve</button>
                          </form>

                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="alert_id" value="<?= (int)$alert['id'] ?>">
                            <input type="text" name="remarks" placeholder="Rejection remark">
                            <input type="hidden" name="action" value="reject_alert">
                            <button class="btn btn-danger" type="submit">Reject</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="subtle">Already processed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section <?= $activeSection === 'leaveSection' ? 'active' : '' ?>">
        <h2>Leave Overview</h2>
        <p class="section-desc">Coach approves leave requests. Admin sees all leave activity here for system-level monitoring.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Player</th>
                <th>Club</th>
                <th>Topic</th>
                <th>Description</th>
                <th>Date</th>
                <th>Status</th>
                <th>Coach Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$leaveApplicationsAll): ?>
                <tr><td colspan="7">No leave applications found.</td></tr>
              <?php else: ?>
                <?php foreach ($leaveApplicationsAll as $leave): ?>
                  <tr>
                    <td><?= e((string)$leave['full_name']) ?></td>
                    <td><?= e((string)$leave['club_name']) ?></td>
                    <td><?= e((string)$leave['topic']) ?></td>
                    <td><?= e((string)$leave['description']) ?></td>
                    <td><?= e((string)$leave['leave_date']) ?></td>
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
      </section>

      <section class="section <?= $activeSection === 'tournamentSection' ? 'active' : '' ?>">
        <h2>Tournament Management</h2>
        <p class="section-desc">Verify tournaments, review applicant approvals, assign referees to arenas and categories, and generate tiesheets.</p>

        <div class="mini-card">
          <h3>Tournament List</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament</th>
                  <th>Host Club</th>
                  <th>Host Coach</th>
                  <th>Mode</th>
                  <th>Arenas</th>
                  <th>Fees</th>
                  <th>Status</th>
                  <th>Remarks</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$tournaments): ?>
                  <tr><td colspan="9">No tournaments found.</td></tr>
                <?php else: ?>
                  <?php foreach ($tournaments as $tournament): ?>
                    <tr>
                      <td>
                        <a href="admin.php?section=tournamentSection&tournament=<?= (int)$tournament['id'] ?>" style="color:#fff;text-decoration:none;">
                          <?= e((string)$tournament['tournament_name']) ?>
                        </a>
                      </td>
                      <td><?= e((string)$tournament['host_club']) ?></td>
                      <td><?= e((string)$tournament['host_coach']) ?></td>
                      <td><?= e((string)$tournament['event_scope']) ?></td>
                      <td><?= e((string)$tournament['arena_count']) ?></td>
                      <td>
                        P: <?= e((string)($tournament['entry_fee_poomsae'] ?? 'N/A')) ?><br>
                        K: <?= e((string)($tournament['entry_fee_kyorugi'] ?? 'N/A')) ?><br>
                        Both: <?= e((string)($tournament['entry_fee_both_discount'] ?? 'N/A')) ?>
                      </td>
                      <td>
                        <span class="status-chip <?= $tournament['status'] === 'Pending' ? 'status-pending' : ($tournament['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                          <?= e((string)$tournament['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$tournament['remarks']) ?></td>
                      <td>
                        <?php if ($tournament['status'] === 'Pending'): ?>
                          <div class="action-stack">
                            <form method="post">
                              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                              <input type="hidden" name="tournament_id" value="<?= (int)$tournament['id'] ?>">
                              <input type="text" name="remarks" placeholder="Verification remark">
                              <input type="hidden" name="action" value="verify_tournament">
                              <button class="btn btn-success" type="submit">Verify</button>
                            </form>

                            <form method="post">
                              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                              <input type="hidden" name="tournament_id" value="<?= (int)$tournament['id'] ?>">
                              <input type="text" name="remarks" placeholder="Rejection remark">
                              <input type="hidden" name="action" value="reject_tournament">
                              <button class="btn btn-danger" type="submit">Reject</button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span class="subtle">Open details</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($selectedTournamentMeta): ?>
          <div class="mini-card">
            <h3>Selected Tournament: <?= e((string)$selectedTournamentMeta['tournament_name']) ?></h3>
            <p>
              Host Club: <?= e((string)$selectedTournamentMeta['host_club']) ?><br>
              Mode: <?= e((string)$selectedTournamentMeta['event_scope']) ?><br>
              Arena Count: <?= e((string)$selectedTournamentMeta['arena_count']) ?><br>
              Status: <?= e((string)$selectedTournamentMeta['status']) ?>
            </p>
          </div>

          <div class="mini-card">
            <h3>Hosted Categories</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Event Type</th>
                    <th>Age Category</th>
                    <th>Weight Category</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$tournamentCategories): ?>
                    <tr><td colspan="3">No hosted categories found for this tournament yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($tournamentCategories as $cat): ?>
                      <tr>
                        <td><?= e((string)$cat['event_type']) ?></td>
                        <td><?= e((string)$cat['age_category']) ?></td>
                        <td><?= e((string)$cat['weight_category']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mini-card">
            <h3>Tournament Applicants</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Event</th>
                    <th>Age</th>
                    <th>Weight</th>
                    <th>Club</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$tournamentApplicants): ?>
                    <tr><td colspan="8">No tournament applicants found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($tournamentApplicants as $applicant): ?>
                      <tr>
                        <td><?= e((string)$applicant['applicant_name']) ?></td>
                        <td><?= e((string)$applicant['event_type']) ?></td>
                        <td><?= e((string)$applicant['age_category']) ?></td>
                        <td><?= e((string)$applicant['weight_category']) ?></td>
                        <td><?= e((string)$applicant['club_name']) ?></td>
                        <td>
                          <span class="status-chip <?= $applicant['status'] === 'Pending' ? 'status-pending' : ($applicant['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                            <?= e((string)$applicant['status']) ?>
                          </span>
                        </td>
                        <td><?= e((string)$applicant['remarks']) ?></td>
                        <td>
                          <?php if ($applicant['status'] === 'Pending'): ?>
                            <div class="action-stack">
                              <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="applicant_id" value="<?= (int)$applicant['id'] ?>">
                                <input type="hidden" name="selected_tournament_id" value="<?= (int)$selectedTournamentId ?>">
                                <input type="text" name="remarks" placeholder="Approval remark">
                                <input type="hidden" name="action" value="verify_applicant">
                                <button class="btn btn-success" type="submit">Verify Applicant</button>
                              </form>

                              <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="applicant_id" value="<?= (int)$applicant['id'] ?>">
                                <input type="hidden" name="selected_tournament_id" value="<?= (int)$selectedTournamentId ?>">
                                <input type="text" name="remarks" placeholder="Rejection remark">
                                <input type="hidden" name="action" value="reject_applicant">
                                <button class="btn btn-danger" type="submit">Reject Applicant</button>
                              </form>
                            </div>
                          <?php else: ?>
                            <span class="subtle">Already processed</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <?php if ($selectedTournamentMeta['status'] === 'Verified'): ?>
            <div class="mini-card">
              <h3>Assign Referees and Arenas</h3>
              <p>Select referees. The system will distribute them across the tournament’s hosted categories and arenas.</p>

              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="assign_referees">
                <input type="hidden" name="assignment_tournament_id" value="<?= (int)$selectedTournamentId ?>">

                <div class="form-group">
                  <label>Arena Assignment Mode</label>
                  <select name="arena_mode">
                    <option value="auto">Auto by category and arena</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Select Referees</label>
                  <div class="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>Select</th>
                          <th>Name</th>
                          <th>Code</th>
                          <th>Level</th>
                          <th>Email</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!$referees): ?>
                          <tr><td colspan="5">No referees found.</td></tr>
                        <?php else: ?>
                          <?php foreach ($referees as $ref): ?>
                            <tr>
                              <td><input type="checkbox" name="referee_ids[]" value="<?= (int)$ref['id'] ?>"></td>
                              <td><?= e((string)$ref['full_name']) ?></td>
                              <td><?= e((string)$ref['referee_code']) ?></td>
                              <td><?= e((string)$ref['level']) ?></td>
                              <td><?= e((string)$ref['email']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="button-row">
                  <button class="btn btn-primary" type="submit">Assign Referees</button>
                </div>
              </form>
            </div>

            <div class="mini-card">
              <h3>Assigned Arena Preview</h3>
              <?php if (!$assignmentPreview): ?>
                <div class="result-box">No arena assignment yet for this tournament.</div>
              <?php else: ?>
                <?php foreach ($assignmentPreview as $arenaName => $list): ?>
                  <div class="assignment-block">
                    <h4><?= e($arenaName) ?></h4>
                    <ul>
                      <?php foreach ($list as $item): ?>
                        <li>
                          <?= e((string)$item['referee_name']) ?> (<?= e((string)$item['referee_code']) ?>, <?= e((string)$item['level']) ?>)
                          — <?= e((string)$item['event_type']) ?>
                          <?php if (!empty($item['age_category'])): ?> | <?= e((string)$item['age_category']) ?><?php endif; ?>
                          <?php if (!empty($item['weight_category'])): ?> | <?= e((string)$item['weight_category']) ?><?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="mini-card">
              <h3>Generate Tiesheet</h3>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="generate_tiesheet">
                <input type="hidden" name="tiesheet_tournament_id" value="<?= (int)$selectedTournamentId ?>">

                <div class="form-grid">
                  <div class="form-group">
                    <label>Event Type</label>
                    <select name="event_type" required>
                      <option value="">Select event</option>
                      <option value="Kyorugi">Kyorugi</option>
                      <option value="Poomsae Individual">Poomsae Individual</option>
                      <option value="Poomsae Pair">Poomsae Pair</option>
                      <option value="Poomsae Group">Poomsae Group</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label>Age Category</label>
                    <select name="age_category">
                      <option value="">All age categories</option>
                      <?php foreach (ageCategories() as $ageCat): ?>
                        <option value="<?= e($ageCat) ?>"><?= e($ageCat) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group full">
                    <label>Weight Category</label>
                    <select name="weight_category">
                      <option value="">All weight categories</option>
                      <?php foreach (olympicWeightCategories() as $weightCat): ?>
                        <option value="<?= e($weightCat) ?>"><?= e($weightCat) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="button-row">
                  <button class="btn btn-primary" type="submit">Generate Tiesheet</button>
                </div>
              </form>

              <?php if ($tiesheetText !== ''): ?>
                <div class="result-box"><?= e($tiesheetText) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="section <?= $activeSection === 'noticeSection' ? 'active' : '' ?>">
        <h2>Publish Notice</h2>
        <p class="section-desc">Publish global notices for coaches, players, referees, or everyone.</p>

        <div class="mini-card">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="publish_notice">

            <div class="form-grid">
              <div class="form-group full">
                <label>Notice Title</label>
                <input type="text" name="notice_title" required>
              </div>

              <div class="form-group">
                <label>Audience</label>
                <select name="audience" required>
                  <option value="All">All</option>
                  <option value="Coaches">Coaches</option>
                  <option value="Players">Players</option>
                  <option value="Referees">Referees</option>
                </select>
              </div>

              <div class="form-group full">
                <label>Notice Message</label>
                <textarea name="notice_message" required></textarea>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Publish Notice</button>
            </div>
          </form>
        </div>

        <div class="notice-list">
          <?php if (!$recentNotices): ?>
            <div class="result-box">No notices published yet.</div>
          <?php else: ?>
            <?php foreach ($recentNotices as $notice): ?>
              <div class="notice-card">
                <h4><?= e((string)$notice['title']) ?></h4>
                <p><?= nl2br(e((string)$notice['message'])) ?></p>
                <p class="subtle">Audience: <?= e((string)$notice['audience']) ?> | <?= e((string)$notice['created_at']) ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'accountSection' ? 'active' : '' ?>">
        <h2>Admin Account</h2>
        <p class="section-desc">Keep one main admin account and change the password whenever needed.</p>

        <div class="mini-card">
          <h3>Change Password</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="change_admin_password">

            <div class="form-grid">
              <div class="form-group full">
                <label>Email</label>
                <input type="email" value="<?= e((string)$currentAdmin['email']) ?>" readonly>
              </div>
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required>
              </div>
              <div class="form-group full">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Change Password</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Reset Default Admin</h3>
          <p>Default admin email: <?= e(DEFAULT_ADMIN_EMAIL) ?><br>Default password: <?= e(DEFAULT_ADMIN_PASSWORD) ?></p>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="reset_admin_default">
            <div class="button-row">
              <button class="btn btn-warning" type="submit">Reset Admin to Default</button>
            </div>
          </form>
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
  </script>
</body>
</html>
