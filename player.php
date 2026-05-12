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
        'White'      => ['#ffffff', '#111111', 'White Belt'],
        'Yellow'     => ['#facc15', '#111111', 'Yellow Belt'],
        'Half Green' => ['linear-gradient(90deg,#facc15 50%, #16a34a 50%)', '#ffffff', 'Half Green Belt'],
        'Green'      => ['#16a34a', '#ffffff', 'Green Belt'],
        'Half Blue'  => ['linear-gradient(90deg,#16a34a 50%, #2563eb 50%)', '#ffffff', 'Half Blue Belt'],
        'Blue'       => ['#2563eb', '#ffffff', 'Blue Belt'],
        'Half Red'   => ['linear-gradient(90deg,#2563eb 50%, #dc2626 50%)', '#ffffff', 'Half Red Belt'],
        'Red'        => ['#dc2626', '#ffffff', 'Red Belt'],
        'Half Black' => ['linear-gradient(90deg,#dc2626 50%, #111111 50%)', '#ffffff', 'Half Black Belt'],
        'Black'      => ['#111111', '#e7c35a', 'Black Belt'],
    ];

    if (str_starts_with($belt, 'Dan') || str_starts_with($belt, 'Poom')) {
        return ['#111111', '#e7c35a', $belt];
    }

    return $map[$belt] ?? ['#333333', '#ffffff', $belt];
}

function poomsaeGuide(string $belt): array {
    $guides = [
        'White' => [
            'poomsae_en' => 'Taegeuk 1 Jang',
            'poomsae_kr' => '태극 1장',
            'blocks' => [
                ['Low Block', 'Arae Makgi', '아래막기'],
                ['Middle Block', 'Momtong Makgi', '몸통막기'],
            ],
            'summary' => 'Basic stances, front stance, ready posture, line movement, and foundation for Taegeuk 1 Jang.'
        ],
        'Yellow' => [
            'poomsae_en' => 'Taegeuk 2 Jang',
            'poomsae_kr' => '태극 2장',
            'blocks' => [
                ['Low Block', 'Arae Makgi', '아래막기'],
                ['Middle Block', 'Momtong Makgi', '몸통막기'],
                ['High Block', 'Olgul Makgi', '얼굴막기'],
            ],
            'summary' => 'Build rhythm, balance, and accuracy through Taegeuk 2 Jang.'
        ],
        'Half Green' => [
            'poomsae_en' => 'Taegeuk 2 Jang',
            'poomsae_kr' => '태극 2장',
            'blocks' => [
                ['Low Block', 'Arae Makgi', '아래막기'],
                ['High Block', 'Olgul Makgi', '얼굴막기'],
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
            ],
            'summary' => 'Refine Taegeuk 2 Jang and prepare transition into stronger defensive timing.'
        ],
        'Green' => [
            'poomsae_en' => 'Taegeuk 3 Jang',
            'poomsae_kr' => '태극 3장',
            'blocks' => [
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
                ['Inner Block', 'An Makgi', '안막기'],
                ['High Block', 'Olgul Makgi', '얼굴막기'],
            ],
            'summary' => 'Improve power control and directional confidence through Taegeuk 3 Jang.'
        ],
        'Half Blue' => [
            'poomsae_en' => 'Taegeuk 3 Jang',
            'poomsae_kr' => '태극 3장',
            'blocks' => [
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
                ['Inner Block', 'An Makgi', '안막기'],
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
            ],
            'summary' => 'Polish Taegeuk 3 Jang with stronger chambers and sharper guard timing.'
        ],
        'Blue' => [
            'poomsae_en' => 'Taegeuk 4 Jang',
            'poomsae_kr' => '태극 4장',
            'blocks' => [
                ['High Block', 'Olgul Makgi', '얼굴막기'],
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
            ],
            'summary' => 'Focus on line clarity, rhythm, and stronger application in Taegeuk 4 Jang.'
        ],
        'Half Red' => [
            'poomsae_en' => 'Taegeuk 4 Jang',
            'poomsae_kr' => '태극 4장',
            'blocks' => [
                ['High Block', 'Olgul Makgi', '얼굴막기'],
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
                ['Inner Block', 'An Makgi', '안막기'],
            ],
            'summary' => 'Complete Taegeuk 4 Jang with cleaner transitions and advanced control.'
        ],
        'Red' => [
            'poomsae_en' => 'Taegeuk 5 Jang',
            'poomsae_kr' => '태극 5장',
            'blocks' => [
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
                ['High Block', 'Olgul Makgi', '얼굴막기'],
            ],
            'summary' => 'Sharpen power, timing, and performance discipline through Taegeuk 5 Jang.'
        ],
        'Half Black' => [
            'poomsae_en' => 'Taegeuk 6 Jang',
            'poomsae_kr' => '태극 6장',
            'blocks' => [
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
                ['Double Knife-hand Block', 'Batangson/Sonnal Defense', '손날막기 응용'],
                ['High Block', 'Olgul Makgi', '얼굴막기'],
            ],
            'summary' => 'Pre-black-belt refinement with higher precision and stronger technical control.'
        ],
        'Black' => [
            'poomsae_en' => 'Koryo',
            'poomsae_kr' => '고려',
            'blocks' => [
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
                ['Mountain Block', 'San Makgi', '산막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
            ],
            'summary' => 'Black-belt preparation emphasizes Koryo and disciplined advanced defensive technique.'
        ],
        'Poom 1' => [
            'poomsae_en' => 'Koryo',
            'poomsae_kr' => '고려',
            'blocks' => [
                ['Mountain Block', 'San Makgi', '산막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
            ],
            'summary' => 'Koryo-based poom focus with junior black-belt refinement.'
        ],
        'Poom 2' => [
            'poomsae_en' => 'Keumgang',
            'poomsae_kr' => '금강',
            'blocks' => [
                ['Diamond Block', 'Keumgang Makgi', '금강막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
                ['Outer Block', 'Bakkat Makgi', '바깥막기'],
            ],
            'summary' => 'Build stronger stance stability and technical maturity for Keumgang.'
        ],
        'Poom 3' => [
            'poomsae_en' => 'Taebaek',
            'poomsae_kr' => '태백',
            'blocks' => [
                ['Mountain Block', 'San Makgi', '산막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
                ['Knife-hand Guarding Block', 'Sonnal Kodureo Makgi', '손날거들어막기'],
            ],
            'summary' => 'Higher-level junior black-belt technique and Taebaek refinement.'
        ],
        'Dan 1' => [
            'poomsae_en' => 'Koryo',
            'poomsae_kr' => '고려',
            'blocks' => [
                ['Mountain Block', 'San Makgi', '산막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
                ['Knife-hand Block', 'Sonnal Makgi', '손날막기'],
            ],
            'summary' => 'First dan requires strong Koryo performance and black-belt defensive precision.'
        ],
        'Dan 2' => [
            'poomsae_en' => 'Keumgang',
            'poomsae_kr' => '금강',
            'blocks' => [
                ['Diamond Block', 'Keumgang Makgi', '금강막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
                ['Inner Block', 'An Makgi', '안막기'],
            ],
            'summary' => 'Second dan builds deeper power, stance, and Keumgang control.'
        ],
        'Dan 3' => [
            'poomsae_en' => 'Taebaek',
            'poomsae_kr' => '태백',
            'blocks' => [
                ['Mountain Block', 'San Makgi', '산막기'],
                ['Knife-hand Guarding Block', 'Sonnal Kodureo Makgi', '손날거들어막기'],
                ['Palm Block', 'Batangson Makgi', '바탕손막기'],
            ],
            'summary' => 'Third dan requires leadership-level Taebaek quality and technical maturity.'
        ],
    ];

    return $guides[$belt] ?? [
        'poomsae_en' => 'Continue Current Poomsae',
        'poomsae_kr' => '현재 품새 지속',
        'blocks' => [
            ['Low Block', 'Arae Makgi', '아래막기'],
            ['Middle Block', 'Momtong Makgi', '몸통막기'],
        ],
        'summary' => 'Continue current poomsae and blocking drills according to your rank.'
    ];
}

function tournamentFeeLabel(array $tournament, string $eventType): string {
    if ($eventType === 'Kyorugi') {
        return isset($tournament['entry_fee_kyorugi']) && $tournament['entry_fee_kyorugi'] !== null
            ? (string)$tournament['entry_fee_kyorugi']
            : 'N/A';
    }
    if (in_array($eventType, ['Poomsae Individual','Poomsae Pair','Poomsae Group'], true)) {
        return isset($tournament['entry_fee_poomsae']) && $tournament['entry_fee_poomsae'] !== null
            ? (string)$tournament['entry_fee_poomsae']
            : 'N/A';
    }
    return 'N/A';
}

function gradingCountdownDemo(): int {
    return 100;
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
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
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

if (!columnExists($pdo, 'tournament_applicants', 'player_id')) {
    try { $pdo->exec("ALTER TABLE tournament_applicants ADD COLUMN player_id INT UNSIGNED NULL AFTER tournament_id"); } catch (Throwable $e) {}
}
if (!columnExists($pdo, 'admin_alerts', 'transfer_request_id')) {
    try { $pdo->exec("ALTER TABLE admin_alerts ADD COLUMN transfer_request_id INT UNSIGNED NULL AFTER player_id"); } catch (Throwable $e) {}
}
if (!columnExists($pdo, 'admin_alerts', 'admin_remarks')) {
    try { $pdo->exec("ALTER TABLE admin_alerts ADD COLUMN admin_remarks TEXT NULL AFTER status"); } catch (Throwable $e) {}
}
if (!columnExists($pdo, 'player_transfer_requests', 'admin_remarks')) {
    try { $pdo->exec("ALTER TABLE player_transfer_requests ADD COLUMN admin_remarks TEXT NULL AFTER status"); } catch (Throwable $e) {}
}

try {
    $pdo->exec("
        ALTER TABLE admin_alerts
        MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Reviewed') NOT NULL DEFAULT 'Pending'
    ");
} catch (Throwable $e) {}

try {
    $pdo->exec("
        ALTER TABLE player_transfer_requests
        MODIFY COLUMN status ENUM('Pending','Reviewed','Approved','Rejected') NOT NULL DEFAULT 'Pending'
    ");
} catch (Throwable $e) {}

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

            $stmt = $pdo->prepare("
                SELECT id
                FROM player_transfer_requests
                WHERE player_id = ? AND status = 'Pending'
                LIMIT 1
            ");
            $stmt->execute([$currentPlayerId]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A transfer request is already pending.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO player_transfer_requests
                (player_id, current_club_name, requested_club_name, requested_club_contact, reason_text, status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$currentPlayerId, $playerClub, $requestedClub, $requestedClubContact, $reason]);
            $transferRequestId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO admin_alerts (player_id, transfer_request_id, alert_type, title, reason_text, status)
                VALUES (?, ?, 'Transfer Request', ?, ?, 'Pending')
            ");
            $stmt->execute([
                $currentPlayerId,
                $transferRequestId,
                'Transfer Request - ' . (string)$currentPlayer['full_name'],
                "Current Club: {$playerClub}\nRequested Club: {$requestedClub}\nRequested Club Contact: {$requestedClubContact}\nReason: {$reason}"
            ]);

            $pdo->commit();

            setFlash('success', 'Transfer request submitted successfully.');
            redirect('player.php?section=transferSection');
        }

        if ($action === 'update_weight') {
            $weight = trim($_POST['weight_kg'] ?? '');

            if ($weight === '' || !is_numeric($weight)) {
                throw new RuntimeException('Please enter a valid weight.');
            }

            $weightFloat = round((float)$weight, 2);

            if ($weightFloat <= 0) {
                throw new RuntimeException('Weight must be greater than 0.');
            }

            $month = date('F');
            $year = (int)date('Y');

            $stmt = $pdo->prepare("SELECT id, gender FROM players WHERE id = ? LIMIT 1");
            $stmt->execute([$currentPlayerId]);
            $latestPlayer = $stmt->fetch();

            if (!$latestPlayer) {
                throw new RuntimeException('Player account not found.');
            }

            $gender = strtolower(trim((string)($latestPlayer['gender'] ?? '')));
            $weightCategory = null;

            if ($gender === 'male') {
                if ($weightFloat <= 58) $weightCategory = 'Male -58kg';
                elseif ($weightFloat <= 68) $weightCategory = 'Male -68kg';
                elseif ($weightFloat <= 80) $weightCategory = 'Male -80kg';
                else $weightCategory = 'Male +80kg';
            } elseif ($gender === 'female') {
                if ($weightFloat <= 49) $weightCategory = 'Female -49kg';
                elseif ($weightFloat <= 57) $weightCategory = 'Female -57kg';
                elseif ($weightFloat <= 67) $weightCategory = 'Female -67kg';
                else $weightCategory = 'Female +67kg';
            } else {
                $weightCategory = 'Unspecified';
            }

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

        if ($action === 'register_tournament') {
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            $eventType = trim($_POST['event_type'] ?? '');

            if ($tournamentId <= 0 || $eventType === '') {
                throw new RuntimeException('Tournament and event type are required.');
            }

            if (($currentPlayer['status'] ?? '') !== 'Active') {
                throw new RuntimeException('Only active players can register for tournaments.');
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM tournaments
                WHERE id = ? AND status = 'Verified'
                LIMIT 1
            ");
            $stmt->execute([$tournamentId]);
            $tournament = $stmt->fetch();

            if (!$tournament) {
                throw new RuntimeException('Selected tournament is not available for registration.');
            }

            $allowed = false;
            if ($eventType === 'Kyorugi' && (int)$tournament['kyorugi_enabled'] === 1) {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM tournament_categories
                    WHERE tournament_id = ?
                      AND event_type = 'Kyorugi'
                      AND age_category = ?
                      AND weight_category = ?
                    LIMIT 1
                ");
                $stmt->execute([$tournamentId, $currentPlayer['age_category'], $currentPlayer['weight_category']]);
                $allowed = (bool)$stmt->fetchColumn();
            } elseif (in_array($eventType, ['Poomsae Individual','Poomsae Pair','Poomsae Group'], true) && (int)$tournament['poomsae_enabled'] === 1) {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM tournament_categories
                    WHERE tournament_id = ?
                      AND event_type = ?
                      AND age_category = ?
                    LIMIT 1
                ");
                $stmt->execute([$tournamentId, $eventType, $currentPlayer['age_category']]);
                $allowed = (bool)$stmt->fetchColumn();
            }

            if (!$allowed) {
                throw new RuntimeException('You do not match any hosted category for this tournament event.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM tournament_applicants
                WHERE tournament_id = ? AND player_id = ? AND event_type = ?
                LIMIT 1
            ");
            $stmt->execute([$tournamentId, $currentPlayerId, $eventType]);
            if ($stmt->fetch()) {
                throw new RuntimeException('You are already registered for this event in this tournament.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO tournament_applicants
                (tournament_id, player_id, applicant_name, event_type, weight_category, age_category, club_name, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $tournamentId,
                $currentPlayerId,
                (string)$currentPlayer['full_name'],
                $eventType,
                (string)$currentPlayer['weight_category'],
                (string)$currentPlayer['age_category'],
                (string)$currentPlayer['club_name'],
                'Waiting for admin applicant approval'
            ]);

            setFlash('success', 'Tournament registration submitted successfully.');
            redirect('player.php?section=tournamentSection');
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

$stmt = $pdo->prepare("
    SELECT *
    FROM tournaments
    WHERE status = 'Verified'
    ORDER BY created_at DESC
");
$stmt->execute();
$availableTournaments = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT ta.*, t.tournament_name, t.entry_fee_poomsae, t.entry_fee_kyorugi, t.entry_fee_both_discount
    FROM tournament_applicants ta
    INNER JOIN tournaments t ON t.id = ta.tournament_id
    WHERE ta.player_id = ?
    ORDER BY ta.created_at DESC
");
$stmt->execute([$currentPlayerId]);
$myTournamentRegistrations = $stmt->fetchAll();

$gold = (int)($currentPlayer['gold_last_90_days'] ?? 0);
$silver = (int)($currentPlayer['silver_count'] ?? 0);
$bronze = (int)($currentPlayer['bronze_count'] ?? 0);
$games = (int)($currentPlayer['participated_games'] ?? 0);

$gradingCountdown = gradingCountdownDemo();
$belt = (string)($currentPlayer['belt_rank'] ?? 'White');
[$beltBg, $beltTextColor, $beltLabel] = beltColor($belt);
$guide = poomsaeGuide($belt);

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
    select option{background:#ffffff;color:#111111;}
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
    .status-approved,.status-verified{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
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
    .fee-box{padding:12px 14px;border-radius:14px;background:rgba(255,255,255,.05);border:1px solid var(--border);margin-top:10px;color:var(--soft);line-height:1.7;}
    .guide-list{display:grid;gap:12px;}
    .guide-item{padding:14px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);}
    .guide-item strong{color:#fff;}
    .tkd-hero{position:relative;min-height:260px;border-radius:22px;border:1px solid var(--border);background:radial-gradient(circle at 25% 20%, rgba(217,4,41,.18), transparent 26%),radial-gradient(circle at 80% 75%, rgba(21,101,255,.18), transparent 28%),linear-gradient(145deg,#0b0b0b,#111927,#0f0f0f);overflow:hidden;}
    .tkd-stage{position:absolute;inset:0;}
    .tkd-stage::before{content:"";position:absolute;left:0;right:0;bottom:34px;height:2px;background:rgba(255,255,255,.12);}
    .fighter{position:absolute;left:50%;bottom:36px;width:16px;height:88px;background:linear-gradient(#fff,#e8e8e8);border-radius:10px;transform-origin:center bottom;animation:fighterMove 2.8s ease-in-out infinite;}
    .fighter::before{content:"";position:absolute;top:-24px;left:-6px;width:28px;height:28px;border-radius:50%;background:#f2d2b6;}
    .fighter::after{content:"";position:absolute;bottom:-2px;left:-10px;width:36px;height:8px;background:#ffffff;border-radius:20px;box-shadow:0 10px 0 0 #ffffff;}
    .arm-left,.arm-right,.leg-left,.leg-right{position:absolute;background:#ffffff;border-radius:20px;}
    .arm-left{width:12px;height:46px;left:-12px;top:14px;transform-origin:top right;animation:armLeft 2.8s ease-in-out infinite;}
    .arm-right{width:12px;height:52px;right:-12px;top:8px;transform-origin:top left;animation:armRight 2.8s ease-in-out infinite;}
    .leg-left{width:12px;height:54px;left:1px;bottom:-42px;transform-origin:top center;animation:legLeft 2.8s ease-in-out infinite;}
    .leg-right{width:12px;height:74px;right:1px;bottom:-58px;transform-origin:top center;animation:legRight 2.8s ease-in-out infinite;}
    .fighter-label{position:absolute;left:20px;top:20px;color:var(--soft);font-size:.95rem;line-height:1.7;max-width:280px;}
    @keyframes fighterMove{
      0%,100%{transform:translateX(-50%) rotate(0deg);}
      20%{transform:translateX(-50%) rotate(-4deg);}
      40%{transform:translateX(-48%) rotate(2deg);}
      60%{transform:translateX(-52%) rotate(-1deg);}
      80%{transform:translateX(-50%) rotate(5deg);}
    }
    @keyframes armLeft{
      0%,100%{transform:rotate(25deg);}
      30%{transform:rotate(-25deg);}
      60%{transform:rotate(45deg);}
    }
    @keyframes armRight{
      0%,100%{transform:rotate(-35deg);}
      30%{transform:rotate(35deg);}
      60%{transform:rotate(-80deg);}
    }
    @keyframes legLeft{
      0%,100%{transform:rotate(8deg);}
      40%{transform:rotate(-18deg);}
      70%{transform:rotate(16deg);}
    }
    @keyframes legRight{
      0%,100%{transform:rotate(-10deg);}
      35%{transform:rotate(62deg);}
      60%{transform:rotate(-36deg);}
    }
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
      .fighter-label{font-size:.82rem;max-width:180px;}
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
          <h2>Welcome Athlete</h2>
          <p>Track your rank, poomsae guidance, tournament registration, leave requests, transfer requests, monthly weight, notices, and athlete ID.</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="player.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'guidanceSection' ? 'active' : '' ?>" href="player.php?section=guidanceSection">🥋 Belt Guidance</a>
          <a class="<?= $activeSection === 'tournamentSection' ? 'active' : '' ?>" href="player.php?section=tournamentSection">🏆 Tournament Registration</a>
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
          <p>Days remaining until next grading window.</p>
        </div>
        <div class="stat-card">
          <h3>Participated Games</h3>
          <div class="big"><?= e((string)$games) ?></div>
          <p>Total games recorded in profile.</p>
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
        <p class="section-desc">Your athlete dashboard with belt, medals, performance progress, and continuous taekwondo animation.</p>

        <div class="belt-bar" style="background:<?= str_contains($beltBg, 'linear-gradient') ? $beltBg : e($beltBg) ?>; color:<?= e($beltTextColor) ?>;">
          <h3><?= e($beltLabel) ?></h3>
          <p>Your current belt rank is <?= e($belt) ?>. Current poomsae: <strong><?= e($guide['poomsae_en']) ?></strong> / <strong><?= e($guide['poomsae_kr']) ?></strong></p>
        </div>

        <div class="mini-card">
          <h3>Continuous Taekwondo Motion</h3>
          <div class="tkd-hero">
            <div class="fighter-label">
              Modern taekwondo athlete zone<br>
              Continuous action animation for player dashboard<br>
              Focus: discipline, movement, control
            </div>
            <div class="tkd-stage">
              <div class="fighter">
                <div class="arm-left"></div>
                <div class="arm-right"></div>
                <div class="leg-left"></div>
                <div class="leg-right"></div>
              </div>
            </div>
          </div>
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
        <p class="section-desc">Your poomsae and block guidance in English and Korean according to current belt.</p>

        <div class="mini-card">
          <h3>Current Belt Preparation</h3>
          <div class="result-box">Current Belt: <?= e($belt) ?>

Poomsae (English): <?= e($guide['poomsae_en']) ?>
Poomsae (Korean): <?= e($guide['poomsae_kr']) ?>

Preparation Summary:
<?= e($guide['summary']) ?></div>
        </div>

        <div class="mini-card">
          <h3>Blocking Techniques</h3>
          <div class="guide-list">
            <?php foreach ($guide['blocks'] as $block): ?>
              <div class="guide-item">
                <strong><?= e($block[0]) ?></strong><br>
                Romanized: <?= e($block[1]) ?><br>
                Korean: <?= e($block[2]) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'tournamentSection' ? 'active' : '' ?>">
        <h2>Tournament Registration</h2>
        <p class="section-desc">View verified tournaments, see event fees, and register yourself for matching hosted categories.</p>

        <div class="mini-card">
          <h3>Register for Tournament</h3>

          <?php if (!$availableTournaments): ?>
            <div class="result-box">No verified tournaments available right now.</div>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="register_tournament">

              <div class="form-grid">
                <div class="form-group">
                  <label>Select Tournament</label>
                  <select name="tournament_id" id="tournamentSelect" required onchange="updateTournamentFees()">
                    <option value="">Select tournament</option>
                    <?php foreach ($availableTournaments as $t): ?>
                      <option
                        value="<?= (int)$t['id'] ?>"
                        data-poomsae="<?= e((string)($t['entry_fee_poomsae'] ?? '')) ?>"
                        data-kyorugi="<?= e((string)($t['entry_fee_kyorugi'] ?? '')) ?>"
                        data-both="<?= e((string)($t['entry_fee_both_discount'] ?? '')) ?>"
                        data-poomsae-enabled="<?= (int)$t['poomsae_enabled'] ?>"
                        data-kyorugi-enabled="<?= (int)$t['kyorugi_enabled'] ?>"
                      >
                        <?= e((string)$t['tournament_name']) ?> - <?= e((string)$t['event_scope']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label>Select Event Type</label>
                  <select name="event_type" id="eventTypeSelect" required onchange="updateTournamentFees()">
                    <option value="">Select event type</option>
                    <option value="Kyorugi">Kyorugi</option>
                    <option value="Poomsae Individual">Poomsae Individual</option>
                    <option value="Poomsae Pair">Poomsae Pair</option>
                    <option value="Poomsae Group">Poomsae Group</option>
                  </select>
                </div>
              </div>

              <div class="fee-box" id="feeDisplay">
                Select tournament and event type to see the fee.
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Register Tournament</button>
              </div>

              <div class="helper">
                Registration only works if your age category and weight category match the hosted category of the selected event.
              </div>
            </form>
          <?php endif; ?>
        </div>

        <div class="mini-card">
          <h3>Available Tournament Fee List</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament</th>
                  <th>Mode</th>
                  <th>Poomsae Fee</th>
                  <th>Kyorugi Fee</th>
                  <th>Both Discount Fee</th>
                  <th>Host Club</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$availableTournaments): ?>
                  <tr><td colspan="6">No verified tournaments available.</td></tr>
                <?php else: ?>
                  <?php foreach ($availableTournaments as $t): ?>
                    <tr>
                      <td><?= e((string)$t['tournament_name']) ?></td>
                      <td><?= e((string)$t['event_scope']) ?></td>
                      <td><?= e((string)($t['entry_fee_poomsae'] ?? 'N/A')) ?></td>
                      <td><?= e((string)($t['entry_fee_kyorugi'] ?? 'N/A')) ?></td>
                      <td><?= e((string)($t['entry_fee_both_discount'] ?? 'N/A')) ?></td>
                      <td><?= e((string)$t['host_club']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mini-card">
          <h3>My Tournament Registrations</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament</th>
                  <th>Event Type</th>
                  <th>Weight Category</th>
                  <th>Age Category</th>
                  <th>Fee</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$myTournamentRegistrations): ?>
                  <tr><td colspan="7">You have not registered for any tournament yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($myTournamentRegistrations as $reg): ?>
                    <tr>
                      <td><?= e((string)$reg['tournament_name']) ?></td>
                      <td><?= e((string)$reg['event_type']) ?></td>
                      <td><?= e((string)$reg['weight_category']) ?></td>
                      <td><?= e((string)$reg['age_category']) ?></td>
                      <td><?= e(tournamentFeeLabel($reg, (string)$reg['event_type'])) ?></td>
                      <td>
                        <span class="status-chip <?= $reg['status'] === 'Pending' ? 'status-pending' : ($reg['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                          <?= e((string)$reg['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$reg['remarks']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'leaveSection' ? 'active' : '' ?>">
        <h2>Leave Application</h2>
        <p class="section-desc">Submit leave applications to your coach. Coach can approve or reject them later.</p>

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
        <p class="section-desc">Request transfer to another club by submitting the requested club and reason.</p>

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
                <label>Requested Club Contact</label>
                <input type="text" name="requested_club_contact">
              </div>

              <div class="form-group full">
                <label>Reason</label>
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
                  <th>Requested Contact</th>
                  <th>Reason</th>
                  <th>Status</th>
                  <th>Admin Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$transferRequests): ?>
                  <tr><td colspan="6">No transfer requests submitted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($transferRequests as $tr): ?>
                    <tr>
                      <td><?= e((string)$tr['current_club_name']) ?></td>
                      <td><?= e((string)$tr['requested_club_name']) ?></td>
                      <td><?= e((string)$tr['requested_club_contact']) ?></td>
                      <td><?= e((string)$tr['reason_text']) ?></td>
                      <td>
                        <span class="status-chip <?= $tr['status'] === 'Pending' ? 'status-pending' : ($tr['status'] === 'Approved' ? 'status-approved' : 'status-rejected') ?>">
                          <?= e((string)$tr['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$tr['admin_remarks']) ?></td>
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
        <p class="section-desc">Update your current monthly weight. Your weight category updates automatically.</p>

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

              <div class="form-group">
                <label>Current Weight Category</label>
                <input type="text" value="<?= e((string)$currentPlayer['weight_category']) ?>" readonly>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Update Monthly Weight</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Weight History</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Month</th>
                  <th>Year</th>
                  <th>Weight</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$weightUpdates): ?>
                  <tr><td colspan="3">No monthly weight updates recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($weightUpdates as $update): ?>
                    <tr>
                      <td><?= e((string)$update['recorded_month']) ?></td>
                      <td><?= e((string)$update['recorded_year']) ?></td>
                      <td><?= e((string)$update['weight_kg']) ?></td>
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
        <p class="section-desc">View notices published by your coach and admin.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Coach Notices</h3>
            <?php if (!$coachNotices): ?>
              <div class="result-box">No coach notices found.</div>
            <?php else: ?>
              <?php foreach ($coachNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <div style="color:var(--soft);font-size:.85rem;"><?= e((string)$notice['created_at']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mini-card">
            <h3>Admin Notices</h3>
            <?php if (!$adminNotices): ?>
              <div class="result-box">No admin notices found.</div>
            <?php else: ?>
              <?php foreach ($adminNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <div style="color:var(--soft);font-size:.85rem;"><?= e((string)$notice['created_at']) ?></div>
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
          <div class="id-card" id="athleteCard">
            <div class="id-top">
              <div class="id-logo">NEPAL TAEKWONDO</div>
              <div class="id-badge">ATHLETE ID</div>
            </div>

            <div class="id-name"><?= e((string)$currentPlayer['full_name']) ?></div>

            <div class="id-grid">
              <div class="id-item">
                <h5>Player ID</h5>
                <p><?= e((string)$currentPlayer['player_code']) ?></p>
              </div>
              <div class="id-item">
                <h5>Date of Birth</h5>
                <p><?= e((string)$currentPlayer['dob']) ?></p>
              </div>
              <div class="id-item">
                <h5>Belt Rank</h5>
                <p><?= e((string)$currentPlayer['belt_rank']) ?></p>
              </div>
              <div class="id-item">
                <h5>Country</h5>
                <p><?= e((string)$currentPlayer['country_name']) ?></p>
              </div>
              <div class="id-item">
                <h5>Club</h5>
                <p><?= e((string)$currentPlayer['club_name']) ?></p>
              </div>
              <div class="id-item">
                <h5>Contact Number</h5>
                <p><?= e((string)$currentPlayer['contact_number']) ?></p>
              </div>
            </div>

            <div id="qrcode"></div>
          </div>
        </div>

        <div class="print-actions">
          <button class="btn btn-primary" onclick="window.print()">Print Athlete ID</button>
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

    const medalCanvas = document.getElementById("medalChart");
    const weightCanvas = document.getElementById("weightChart");

    function drawBarChart(canvas, labels, values, title) {
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
        ctx.fillText("No data available.", padding, h / 2);
        return;
      }

      const maxVal = Math.max(...values, 1);
      const barWidth = (w - padding * 2) / values.length * 0.6;
      const gap = (w - padding * 2) / values.length * 0.4;

      values.forEach((val, idx) => {
        const x = padding + idx * (barWidth + gap) + gap / 2;
        const barHeight = ((h - padding * 2) * val) / maxVal;
        const y = h - padding - barHeight;

        ctx.fillStyle = "rgba(231,195,90,0.92)";
        ctx.fillRect(x, y, barWidth, barHeight);

        ctx.fillStyle = "#ffffff";
        ctx.font = "12px Arial";
        ctx.fillText(String(val), x + 6, y - 8);

        ctx.fillStyle = "#cfcfcf";
        ctx.fillText(labels[idx], x, h - 20);
      });

      ctx.fillStyle = "#ffffff";
      ctx.font = "bold 16px Arial";
      ctx.fillText(title, padding, 26);
    }

    function drawLineChart(canvas, labels, values, title) {
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
        ctx.fillText("No weight history yet.", padding, h / 2);
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
        ctx.fillText(String(val), x - 10, y - 10);

        if (labels[idx]) {
          ctx.fillStyle = "#cfcfcf";
          ctx.fillText(labels[idx], x - 22, h - 20);
        }
      });

      ctx.fillStyle = "#ffffff";
      ctx.font = "bold 16px Arial";
      ctx.fillText(title, padding, 26);
    }

    drawBarChart(
      medalCanvas,
      ["Gold", "Silver", "Bronze"],
      [<?= $gold ?>, <?= $silver ?>, <?= $bronze ?>],
      "Medal Breakdown"
    );

    drawLineChart(
      weightCanvas,
      <?= json_encode($weightLabels, JSON_UNESCAPED_UNICODE) ?>,
      <?= json_encode($weightValues, JSON_UNESCAPED_UNICODE) ?>,
      "Weight Progress"
    );

    const qrPayload = <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE) ?>;
    if (document.getElementById("qrcode")) {
      new QRCode(document.getElementById("qrcode"), {
        text: qrPayload,
        width: 120,
        height: 120
      });
    }

    function updateTournamentFees() {
      const tournamentSelect = document.getElementById("tournamentSelect");
      const eventTypeSelect = document.getElementById("eventTypeSelect");
      const feeDisplay = document.getElementById("feeDisplay");

      if (!tournamentSelect || !eventTypeSelect || !feeDisplay) return;

      const selectedOption = tournamentSelect.options[tournamentSelect.selectedIndex];
      const eventType = eventTypeSelect.value;

      if (!selectedOption || !selectedOption.value || !eventType) {
        feeDisplay.textContent = "Select tournament and event type to see the fee.";
        return;
      }

      const poomsaeFee = selectedOption.dataset.poomsae || "N/A";
      const kyorugiFee = selectedOption.dataset.kyorugi || "N/A";
      const bothFee = selectedOption.dataset.both || "N/A";

      let fee = "N/A";
      if (eventType === "Kyorugi") fee = kyorugiFee;
      if (eventType === "Poomsae Individual" || eventType === "Poomsae Pair" || eventType === "Poomsae Group") fee = poomsaeFee;

      feeDisplay.innerHTML =
        "Selected Event Fee: <strong>" + fee + "</strong><br>" +
        "Poomsae Fee: " + poomsaeFee + "<br>" +
        "Kyorugi Fee: " + kyorugiFee + "<br>" +
        "Both Discount Fee: " + bothFee;
    }
  </script>
</body>
</html>
