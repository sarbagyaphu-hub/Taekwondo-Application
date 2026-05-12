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

const DEFAULT_PLAYER_PASSWORD = 'Player@123';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
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
        redirect('coach.php');
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

function calculateAge(?string $dob): ?int {
    if (!$dob) {
        return null;
    }
    try {
        $birth = new DateTime($dob);
        $today = new DateTime();
        return (int)$birth->diff($today)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function deriveAgeCategory(?int $age): ?string {
    if ($age === null) return null;
    if ($age >= 6 && $age <= 11) return 'Children';
    if ($age >= 12 && $age <= 14) return 'Cadets';
    if ($age >= 15 && $age <= 17) return 'Juniors';
    if ($age >= 18 && $age <= 34) return 'Adults';
    return 'Veterans';
}

function deriveWeightCategory(string $gender, ?float $weight): ?string {
    if ($weight === null) return null;
    $gender = strtolower(trim($gender));

    if ($gender === 'male') {
        if ($weight <= 58) return 'Male -58kg';
        if ($weight <= 68) return 'Male -68kg';
        if ($weight <= 80) return 'Male -80kg';
        return 'Male +80kg';
    }

    if ($gender === 'female') {
        if ($weight <= 49) return 'Female -49kg';
        if ($weight <= 57) return 'Female -57kg';
        if ($weight <= 67) return 'Female -67kg';
        return 'Female +67kg';
    }

    return 'Unspecified';
}

function getColorBelts(): array {
    return [
        'White',
        'Yellow',
        'Half Green',
        'Green',
        'Half Blue',
        'Blue',
        'Half Red',
        'Red',
        'Half Black',
        'Black'
    ];
}

function getAdvancedBelts(): array {
    return [
        'Poom 1','Poom 2','Poom 3',
        'Dan 1','Dan 2','Dan 3','Dan 4','Dan 5','Dan 6','Dan 7','Dan 8','Dan 9'
    ];
}

function allBelts(): array {
    return array_merge(getColorBelts(), getAdvancedBelts());
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

function nextColorBelt(string $currentBelt, bool $doublePromotion = false): string {
    $belts = getColorBelts();
    $index = array_search($currentBelt, $belts, true);
    if ($index === false) return $currentBelt;
    $step = $doublePromotion ? 2 : 1;
    $newIndex = min(count($belts) - 1, $index + $step);
    return $belts[$newIndex];
}

function generatePlayerCode(PDO $pdo): string {
    $latest = $pdo->query("SELECT id FROM players ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next = ((int)$latest) + 1;
    return 'PLY' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

function requestExists(PDO $pdo, int $playerId, string $alertType): bool {
    $stmt = $pdo->prepare("
        SELECT id
        FROM admin_alerts
        WHERE player_id = ? AND alert_type = ? AND status = 'Pending'
        LIMIT 1
    ");
    $stmt->execute([$playerId, $alertType]);
    return (bool)$stmt->fetchColumn();
}

function parseSimpleList(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return array_values(array_unique($out));
}

function parseKyorugiCategories(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $out = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) !== 2) continue;

        [$ageCategory, $weightCategory] = $parts;
        if ($ageCategory === '' || $weightCategory === '') continue;

        $out[] = [
            'age_category' => $ageCategory,
            'weight_category' => $weightCategory,
        ];
    }

    $unique = [];
    foreach ($out as $item) {
        $key = $item['age_category'] . '|' . $item['weight_category'];
        $unique[$key] = $item;
    }

    return array_values($unique);
}

function isAdvancedBelt(string $belt): bool {
    return in_array($belt, ['Half Black','Black','Poom 1','Poom 2','Poom 3','Dan 1','Dan 2','Dan 3','Dan 4','Dan 5','Dan 6','Dan 7','Dan 8','Dan 9'], true);
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

    "CREATE TABLE IF NOT EXISTS coach_player_notices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
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

    "CREATE TABLE IF NOT EXISTS player_leave_applications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL,
        topic VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        leave_date DATE NOT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        coach_remarks TEXT NULL,
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
        'email' => "ALTER TABLE players ADD COLUMN email VARCHAR(190) NULL UNIQUE AFTER contact_number",
        'password_hash' => "ALTER TABLE players ADD COLUMN password_hash VARCHAR(255) NULL AFTER email",
        'status' => "ALTER TABLE players ADD COLUMN status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active' AFTER participated_games",
    ],
    'player_gradings' => [
        'coach_id' => "ALTER TABLE player_gradings ADD COLUMN coach_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER player_id",
    ],
    'tournaments' => [
        'coach_id' => "ALTER TABLE tournaments ADD COLUMN coach_id INT UNSIGNED NULL AFTER id",
        'host_club' => "ALTER TABLE tournaments ADD COLUMN host_club VARCHAR(190) NOT NULL DEFAULT '' AFTER tournament_name",
        'host_coach' => "ALTER TABLE tournaments ADD COLUMN host_coach VARCHAR(190) NOT NULL DEFAULT '' AFTER host_club",
        'event_scope' => "ALTER TABLE tournaments ADD COLUMN event_scope VARCHAR(120) NULL AFTER host_coach",
        'poomsae_enabled' => "ALTER TABLE tournaments ADD COLUMN poomsae_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER event_scope",
        'kyorugi_enabled' => "ALTER TABLE tournaments ADD COLUMN kyorugi_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER poomsae_enabled",
        'arena_count' => "ALTER TABLE tournaments ADD COLUMN arena_count INT NOT NULL DEFAULT 1 AFTER kyorugi_enabled",
        'entry_fee_poomsae' => "ALTER TABLE tournaments ADD COLUMN entry_fee_poomsae DECIMAL(10,2) NULL AFTER arena_count",
        'entry_fee_kyorugi' => "ALTER TABLE tournaments ADD COLUMN entry_fee_kyorugi DECIMAL(10,2) NULL AFTER entry_fee_poomsae",
        'entry_fee_both_discount' => "ALTER TABLE tournaments ADD COLUMN entry_fee_both_discount DECIMAL(10,2) NULL AFTER entry_fee_kyorugi",
        'remarks' => "ALTER TABLE tournaments ADD COLUMN remarks TEXT NULL AFTER status",
    ],
    'tournament_applicants' => [
        'player_id' => "ALTER TABLE tournament_applicants ADD COLUMN player_id INT UNSIGNED NULL AFTER tournament_id",
    ],
    'admin_alerts' => [
        'transfer_request_id' => "ALTER TABLE admin_alerts ADD COLUMN transfer_request_id INT UNSIGNED NULL AFTER player_id",
        'admin_remarks' => "ALTER TABLE admin_alerts ADD COLUMN admin_remarks TEXT NULL AFTER status",
    ],
];

foreach ($migrations as $table => $cols) {
    if (!tableExists($pdo, $table)) continue;
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
    $pdo->exec("
        ALTER TABLE admin_alerts
        MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Reviewed') NOT NULL DEFAULT 'Pending'
    ");
} catch (Throwable $e) {
}

/*
|--------------------------------------------------------------------------
| DEMO COACH
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT id FROM coaches WHERE email = ? LIMIT 1");
$stmt->execute(['coach@nta.com']);
if (!$stmt->fetch()) {
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

/*
|--------------------------------------------------------------------------
| SESSION CHECK
|--------------------------------------------------------------------------
*/
if (($_SESSION['taekwondo_logged_in'] ?? false) !== true || ($_SESSION['taekwondo_role'] ?? '') !== 'Coach') {
    redirect('login.php');
}

$currentCoachId = (int)($_SESSION['taekwondo_user_id'] ?? 0);
if ($currentCoachId <= 0) {
    redirect('login.php');
}

$stmt = $pdo->prepare("
    SELECT id, coach_name, institution_name, email, registration_type, status,
           association_registered_number, club_address, contact_number, password_hash, remarks
    FROM coaches
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$currentCoachId]);
$currentCoach = $stmt->fetch();

if (!$currentCoach) {
    session_destroy();
    redirect('login.php');
}

if (($currentCoach['status'] ?? '') !== 'Verified') {
    die('<h2 style="font-family:Arial;">Coach account is not yet verified by admin.</h2>');
}

$currentClubName = (string)$currentCoach['institution_name'];

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

        if ($action === 'update_profile') {
            $coachName = trim($_POST['coach_name'] ?? '');
            $institutionName = trim($_POST['institution_name'] ?? '');
            $clubAddress = trim($_POST['club_address'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');

            if ($coachName === '' || $institutionName === '') {
                throw new RuntimeException('Coach name and club/institution name are required.');
            }

            $oldInstitution = $currentClubName;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE coaches
                SET coach_name = ?, institution_name = ?, club_address = ?, contact_number = ?
                WHERE id = ?
            ");
            $stmt->execute([$coachName, $institutionName, $clubAddress, $contactNumber, $currentCoachId]);

            if ($oldInstitution !== $institutionName) {
                $stmt = $pdo->prepare("UPDATE players SET club_name = ?, club_address = ? WHERE club_name = ?");
                $stmt->execute([$institutionName, $clubAddress, $oldInstitution]);
            }

            $pdo->commit();

            setFlash('success', 'Coach profile updated successfully.');
            redirect('coach.php?section=profileSection');
        }

        if ($action === 'change_password') {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Please fill all password fields.');
            }

            if (!password_verify($currentPassword, (string)$currentCoach['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            if (strlen($newPassword) < 6) {
                throw new RuntimeException('New password must be at least 6 characters long.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirm password do not match.');
            }

            $stmt = $pdo->prepare("UPDATE coaches SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $currentCoachId]);

            setFlash('success', 'Password changed successfully.');
            redirect('coach.php?section=profileSection');
        }

        if ($action === 'create_player') {
            $fullName = trim($_POST['full_name'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $weight = trim($_POST['weight_kg'] ?? '');
            $beltRank = trim($_POST['belt_rank'] ?? '');
            $countryName = trim($_POST['country_name'] ?? 'Nepal');
            $clubName = trim($_POST['club_name'] ?? $currentClubName);
            $clubAddress = trim($_POST['club_address'] ?? ((string)($currentCoach['club_address'] ?? '')));
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($fullName === '' || $dob === '' || $gender === '' || $weight === '' || $beltRank === '' || $email === '') {
                throw new RuntimeException('Please fill all required player fields including email.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid player email.');
            }

            $stmt = $pdo->prepare("SELECT id FROM players WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new RuntimeException('This player email is already used.');
            }

            $age = calculateAge($dob);
            $weightFloat = round((float)$weight, 2);
            $ageCategory = deriveAgeCategory($age);
            $weightCategory = deriveWeightCategory($gender, $weightFloat);
            $playerCode = generatePlayerCode($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO players
                (player_code, full_name, dob, age, gender, weight_kg, weight_category, age_category, belt_rank, country_name, club_name, club_address, contact_number, email, password_hash, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->execute([
                $playerCode,
                $fullName,
                $dob,
                $age,
                $gender,
                $weightFloat,
                $weightCategory,
                $ageCategory,
                $beltRank,
                $countryName,
                $clubName,
                $clubAddress,
                $contactNumber,
                $email,
                password_hash(DEFAULT_PLAYER_PASSWORD, PASSWORD_DEFAULT)
            ]);

            setFlash('success', "Player created successfully. Default password: " . DEFAULT_PLAYER_PASSWORD);
            redirect('coach.php?section=playersSection');
        }

        if ($action === 'update_player_basic') {
            $playerId = (int)($_POST['player_id'] ?? 0);
            $weight = trim($_POST['weight_kg'] ?? '');
            $beltRank = trim($_POST['belt_rank'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $email = trim($_POST['email'] ?? '');

            $stmt = $pdo->prepare("
                SELECT id, full_name, gender, dob, status
                FROM players
                WHERE id = ? AND club_name = ?
                LIMIT 1
            ");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found for this coach.');
            }

            if (($player['status'] ?? '') === 'Deleted') {
                throw new RuntimeException('Deleted player cannot be edited.');
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid player email.');
            }

            $stmt = $pdo->prepare("SELECT id FROM players WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->execute([$email, $playerId]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Another player already uses this email.');
            }

            $age = calculateAge((string)$player['dob']);
            $ageCategory = deriveAgeCategory($age);
            $weightFloat = $weight !== '' ? round((float)$weight, 2) : null;
            $weightCategory = deriveWeightCategory((string)$player['gender'], $weightFloat);

            $stmt = $pdo->prepare("
                UPDATE players
                SET weight_kg = ?, weight_category = ?, age_category = ?, belt_rank = ?, contact_number = ?, email = ?
                WHERE id = ? AND club_name = ?
            ");
            $stmt->execute([$weightFloat, $weightCategory, $ageCategory, $beltRank, $contactNumber, $email, $playerId, $currentClubName]);

            setFlash('success', "Player {$player['full_name']} updated successfully.");
            redirect('coach.php?section=playersSection&open_player=' . $playerId);
        }

        if ($action === 'grade_player') {
            $playerId = (int)($_POST['player_id'] ?? 0);
            $basic = (float)($_POST['marks_basic'] ?? 0);
            $kicking = (float)($_POST['marks_kicking'] ?? 0);
            $poomsae = (float)($_POST['marks_poomsae'] ?? 0);
            $breaking = (float)($_POST['marks_breaking'] ?? 0);
            $sparring = (float)($_POST['marks_sparring'] ?? 0);
            $selfDefence = (float)($_POST['marks_self_defence'] ?? 0);
            $oneStep = (float)($_POST['marks_one_step'] ?? 0);
            $flyingKick = (float)($_POST['marks_flying_kick'] ?? 0);
            $punch = (float)($_POST['marks_punch'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT id, full_name, belt_rank
                FROM players
                WHERE id = ? AND club_name = ? AND status = 'Active'
                LIMIT 1
            ");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found or not active.');
            }

            $previousBelt = (string)$player['belt_rank'];
            $advanced = isAdvancedBelt($previousBelt);

            if (!$advanced) {
                $total = $basic + $kicking + $poomsae + $breaking + $sparring;
                $gradingType = 'Color Belt';
                $pass = $total >= 50;
                $double = $total >= 80;
                $newBelt = $pass ? nextColorBelt($previousBelt, $double) : $previousBelt;
                $promotionType = !$pass ? 'No Promotion' : ($double ? 'Double' : 'Normal');
            } else {
                $total = $basic + $kicking + $poomsae + $selfDefence + $oneStep + $breaking + $flyingKick + $punch;
                $gradingType = 'Advanced Belt';
                $pass = $total >= 50;
                $newBelt = $previousBelt;
                $promotionType = $pass ? 'Normal' : 'No Promotion';
            }

            $resultStatus = $pass ? 'Pass' : 'Fail';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO player_gradings
                (player_id, coach_id, grading_type, previous_belt, new_belt,
                 marks_basic, marks_kicking, marks_poomsae, marks_breaking, marks_sparring,
                 marks_self_defence, marks_one_step, marks_flying_kick, marks_punch,
                 total_marks, result_status, promotion_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $playerId, $currentCoachId, $gradingType, $previousBelt, $newBelt,
                $basic, $kicking, $poomsae, $breaking, $sparring,
                $selfDefence, $oneStep, $flyingKick, $punch,
                $total, $resultStatus, $promotionType
            ]);

            if ($newBelt !== $previousBelt) {
                $stmt = $pdo->prepare("UPDATE players SET belt_rank = ? WHERE id = ? AND club_name = ?");
                $stmt->execute([$newBelt, $playerId, $currentClubName]);
            }

            $title = "Grading Result - " . $player['full_name'];
            $message = "Previous Belt: {$previousBelt}\nNew Belt: {$newBelt}\nTotal Marks: {$total}\nResult: {$resultStatus}\nPromotion: {$promotionType}";
            $stmt = $pdo->prepare("INSERT INTO coach_player_notices (coach_id, title, message) VALUES (?, ?, ?)");
            $stmt->execute([$currentCoachId, $title, $message]);

            $pdo->commit();

            setFlash('success', 'Grading saved successfully and result notice published.');
            redirect('coach.php?section=gradingSection');
        }

        if ($action === 'publish_notice') {
            $title = trim($_POST['notice_title'] ?? '');
            $message = trim($_POST['notice_message'] ?? '');

            if ($title === '' || $message === '') {
                throw new RuntimeException('Notice title and message are required.');
            }

            $stmt = $pdo->prepare("INSERT INTO coach_player_notices (coach_id, title, message) VALUES (?, ?, ?)");
            $stmt->execute([$currentCoachId, $title, $message]);

            setFlash('success', 'Notice published to players successfully.');
            redirect('coach.php?section=noticesSection');
        }

        if ($action === 'host_tournament') {
            $tournamentName = trim($_POST['tournament_name'] ?? '');
            $arenaCount = (int)($_POST['arena_count'] ?? 0);
            $eventMode = trim($_POST['event_mode'] ?? '');
            $entryFeePoomsae = trim($_POST['entry_fee_poomsae'] ?? '');
            $entryFeeKyorugi = trim($_POST['entry_fee_kyorugi'] ?? '');
            $entryFeeBoth = trim($_POST['entry_fee_both_discount'] ?? '');

            $poomsaeIndividualAgesText = trim($_POST['poomsae_individual_ages'] ?? '');
            $poomsaePairAgesText = trim($_POST['poomsae_pair_ages'] ?? '');
            $poomsaeGroupAgesText = trim($_POST['poomsae_group_ages'] ?? '');
            $kyorugiCategoriesText = trim($_POST['kyorugi_categories'] ?? '');

            if ($tournamentName === '') {
                throw new RuntimeException('Tournament name is required.');
            }
            if ($arenaCount <= 0) {
                throw new RuntimeException('Arena count must be at least 1.');
            }
            if (!in_array($eventMode, ['Poomsae', 'Kyorugi', 'Both'], true)) {
                throw new RuntimeException('Please select a valid tournament mode.');
            }

            $poomsaeEnabled = in_array($eventMode, ['Poomsae', 'Both'], true) ? 1 : 0;
            $kyorugiEnabled = in_array($eventMode, ['Kyorugi', 'Both'], true) ? 1 : 0;

            $feePoomsae = ($entryFeePoomsae !== '' && is_numeric($entryFeePoomsae)) ? round((float)$entryFeePoomsae, 2) : null;
            $feeKyorugi = ($entryFeeKyorugi !== '' && is_numeric($entryFeeKyorugi)) ? round((float)$entryFeeKyorugi, 2) : null;
            $feeBoth = ($entryFeeBoth !== '' && is_numeric($entryFeeBoth)) ? round((float)$entryFeeBoth, 2) : null;

            if ($eventMode === 'Poomsae' && $feePoomsae === null) {
                throw new RuntimeException('Please enter Poomsae entry fee.');
            }
            if ($eventMode === 'Kyorugi' && $feeKyorugi === null) {
                throw new RuntimeException('Please enter Kyorugi entry fee.');
            }
            if ($eventMode === 'Both') {
                if ($feePoomsae === null || $feeKyorugi === null || $feeBoth === null) {
                    throw new RuntimeException('Please enter all three fees for Both mode.');
                }
                if ($feeBoth >= ($feePoomsae + $feeKyorugi)) {
                    throw new RuntimeException('Both-event discounted fee must be less than the sum of Poomsae and Kyorugi fees.');
                }
            }

            $poomsaeIndividualAges = parseSimpleList($poomsaeIndividualAgesText);
            $poomsaePairAges = parseSimpleList($poomsaePairAgesText);
            $poomsaeGroupAges = parseSimpleList($poomsaeGroupAgesText);
            $kyorugiCategories = parseKyorugiCategories($kyorugiCategoriesText);

            if ($poomsaeEnabled === 1 && !$poomsaeIndividualAges && !$poomsaePairAges && !$poomsaeGroupAges) {
                throw new RuntimeException('For Poomsae or Both mode, enter at least one poomsae category age list.');
            }

            if ($kyorugiEnabled === 1 && !$kyorugiCategories) {
                throw new RuntimeException('For Kyorugi or Both mode, enter at least one Kyorugi category in Age|Weight format.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tournaments
                (coach_id, tournament_name, host_club, host_coach, event_scope, poomsae_enabled, kyorugi_enabled, arena_count, entry_fee_poomsae, entry_fee_kyorugi, entry_fee_both_discount, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $currentCoachId,
                $tournamentName,
                $currentClubName,
                (string)$currentCoach['coach_name'],
                $eventMode,
                $poomsaeEnabled,
                $kyorugiEnabled,
                $arenaCount,
                $feePoomsae,
                $feeKyorugi,
                $feeBoth,
                'Waiting for admin review'
            ]);

            $tournamentId = (int)$pdo->lastInsertId();

            $insertCategory = $pdo->prepare("
                INSERT INTO tournament_categories (tournament_id, event_type, age_category, weight_category)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($poomsaeIndividualAges as $ageCat) {
                $insertCategory->execute([$tournamentId, 'Poomsae Individual', $ageCat, null]);
            }
            foreach ($poomsaePairAges as $ageCat) {
                $insertCategory->execute([$tournamentId, 'Poomsae Pair', $ageCat, null]);
            }
            foreach ($poomsaeGroupAges as $ageCat) {
                $insertCategory->execute([$tournamentId, 'Poomsae Group', $ageCat, null]);
            }
            foreach ($kyorugiCategories as $cat) {
                $insertCategory->execute([$tournamentId, 'Kyorugi', $cat['age_category'], $cat['weight_category']]);
            }

            $pdo->commit();

            setFlash('success', 'Tournament hosting application submitted successfully.');
            redirect('coach.php?section=tournamentSection&tournament=' . $tournamentId);
        }

        if ($action === 'register_player_for_tournament') {
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            $playerId = (int)($_POST['player_id'] ?? 0);
            $eventType = trim($_POST['event_type'] ?? '');

            if ($tournamentId <= 0 || $playerId <= 0 || $eventType === '') {
                throw new RuntimeException('Tournament, player, and event type are required.');
            }

            $stmt = $pdo->prepare("
                SELECT id, tournament_name, status, poomsae_enabled, kyorugi_enabled
                FROM tournaments
                WHERE id = ? AND coach_id = ?
                LIMIT 1
            ");
            $stmt->execute([$tournamentId, $currentCoachId]);
            $tournament = $stmt->fetch();

            if (!$tournament) {
                throw new RuntimeException('Tournament not found for this coach.');
            }
            if ($tournament['status'] !== 'Verified') {
                throw new RuntimeException('Only verified tournaments can accept player registration.');
            }

            $stmt = $pdo->prepare("
                SELECT id, full_name, weight_category, age_category, club_name, status
                FROM players
                WHERE id = ? AND club_name = ?
                LIMIT 1
            ");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found for this coach.');
            }
            if ($player['status'] !== 'Active') {
                throw new RuntimeException('Only active players can be registered.');
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
                $stmt->execute([$tournamentId, $player['age_category'], $player['weight_category']]);
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
                $stmt->execute([$tournamentId, $eventType, $player['age_category']]);
                $allowed = (bool)$stmt->fetchColumn();
            }

            if (!$allowed) {
                throw new RuntimeException('This player does not match any hosted category for the selected event.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM tournament_applicants
                WHERE tournament_id = ? AND player_id = ? AND event_type = ?
                LIMIT 1
            ");
            $stmt->execute([$tournamentId, $playerId, $eventType]);
            if ($stmt->fetch()) {
                throw new RuntimeException('This player is already registered for this event in the selected tournament.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO tournament_applicants
                (tournament_id, player_id, applicant_name, event_type, weight_category, age_category, club_name, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $tournamentId,
                $playerId,
                $player['full_name'],
                $eventType,
                $player['weight_category'],
                $player['age_category'],
                $player['club_name'],
                'Waiting for admin applicant approval'
            ]);

            setFlash('success', $player['full_name'] . ' registered for tournament successfully.');
            redirect('coach.php?section=tournamentSection&tournament=' . $tournamentId);
        }

        if ($action === 'approve_leave' || $action === 'reject_leave') {
            $leaveId = (int)($_POST['leave_id'] ?? 0);
            $remarks = trim($_POST['coach_remarks'] ?? '');
            $newStatus = $action === 'approve_leave' ? 'Approved' : 'Rejected';

            $stmt = $pdo->prepare("
                SELECT la.id, la.status, p.club_name, p.full_name
                FROM player_leave_applications la
                INNER JOIN players p ON p.id = la.player_id
                WHERE la.id = ? AND p.club_name = ?
                LIMIT 1
            ");
            $stmt->execute([$leaveId, $currentClubName]);
            $leave = $stmt->fetch();

            if (!$leave) {
                throw new RuntimeException('Leave request not found for this coach.');
            }
            if ($leave['status'] !== 'Pending') {
                throw new RuntimeException('This leave request has already been processed.');
            }

            if ($newStatus === 'Rejected' && $remarks === '') {
                throw new RuntimeException('Please add a rejection remark for rejected leave.');
            }

            $stmt = $pdo->prepare("
                UPDATE player_leave_applications
                SET status = ?, coach_remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $newStatus,
                $remarks !== '' ? $remarks : ($newStatus === 'Approved' ? 'Leave approved by coach.' : 'Leave rejected by coach.'),
                $leaveId
            ]);

            setFlash('success', 'Leave request processed successfully.');
            redirect('coach.php?section=leaveSection');
        }

        if ($action === 'delete_request' || $action === 'ban_request') {
            $playerId = (int)($_POST['player_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            if ($playerId <= 0 || $reason === '') {
                throw new RuntimeException('Player and reason are required.');
            }

            $stmt = $pdo->prepare("SELECT id, full_name, status FROM players WHERE id = ? AND club_name = ? LIMIT 1");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found.');
            }
            if ($player['status'] === 'Deleted') {
                throw new RuntimeException('Deleted player cannot be requested again.');
            }

            $alertType = $action === 'delete_request' ? 'Delete Request' : 'Ban Request';

            if (requestExists($pdo, $playerId, $alertType)) {
                throw new RuntimeException('A pending ' . strtolower($alertType) . ' already exists for this player.');
            }

            $title = $alertType . ' - ' . $player['full_name'];

            $stmt = $pdo->prepare("
                INSERT INTO admin_alerts (coach_id, player_id, alert_type, title, reason_text, status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$currentCoachId, $playerId, $alertType, $title, $reason]);

            setFlash('success', $alertType . ' sent to admin successfully.');
            redirect('coach.php?section=playersSection');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
        redirect('coach.php');
    }
}

/*
|--------------------------------------------------------------------------
| VIEW DATA
|--------------------------------------------------------------------------
*/
$flash = getFlash();
$activeSection = $_GET['section'] ?? 'dashboardSection';
$openPlayerId = (int)($_GET['open_player'] ?? 0);
$selectedTournamentId = (int)($_GET['tournament'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM players WHERE club_name = ? ORDER BY created_at DESC");
$stmt->execute([$currentClubName]);
$players = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM coach_player_notices WHERE coach_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$currentCoachId]);
$coachNotices = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE coach_id = ? ORDER BY created_at DESC");
$stmt->execute([$currentCoachId]);
$coachTournaments = $stmt->fetchAll();

$adminNotices = [];
if (tableExists($pdo, 'notices')) {
    $stmt = $pdo->prepare("SELECT * FROM notices WHERE audience IN ('All','Coaches') ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $adminNotices = $stmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT * FROM player_gradings WHERE coach_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$currentCoachId]);
$gradingHistory = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT la.*, p.full_name
    FROM player_leave_applications la
    INNER JOIN players p ON p.id = la.player_id
    WHERE p.club_name = ?
    ORDER BY la.created_at DESC
");
$stmt->execute([$currentClubName]);
$leaveRequests = $stmt->fetchAll();

$selectedTournament = null;
$tournamentApplicants = [];
$tournamentCategories = [];
$activePlayers = [];
if ($selectedTournamentId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ? AND coach_id = ? LIMIT 1");
    $stmt->execute([$selectedTournamentId, $currentCoachId]);
    $selectedTournament = $stmt->fetch();

    if ($selectedTournament) {
        $stmt = $pdo->prepare("
            SELECT ta.*, p.full_name AS player_name
            FROM tournament_applicants ta
            LEFT JOIN players p ON p.id = ta.player_id
            WHERE ta.tournament_id = ?
            ORDER BY ta.created_at DESC, ta.applicant_name ASC
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
    }
}

$stmt = $pdo->prepare("
    SELECT id, full_name, weight_category, age_category, belt_rank, status
    FROM players
    WHERE club_name = ?
    ORDER BY full_name ASC
");
$stmt->execute([$currentClubName]);
$allClubPlayers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, full_name, weight_category, age_category, belt_rank
    FROM players
    WHERE club_name = ? AND status = 'Active'
    ORDER BY full_name ASC
");
$stmt->execute([$currentClubName]);
$activePlayers = $stmt->fetchAll();

$totalPlayers = 0;
$totalColorBelts = 0;
$totalBlackBelts = 0;
$totalDanHolders = 0;

foreach ($players as $p) {
    if (($p['status'] ?? '') !== 'Deleted') {
        $totalPlayers++;
    }

    $belt = (string)($p['belt_rank'] ?? '');
    if (in_array($belt, getColorBelts(), true) && !in_array($belt, ['Half Black','Black'], true)) {
        $totalColorBelts++;
    }
    if (in_array($belt, ['Half Black','Black'], true)) {
        $totalBlackBelts++;
    }
    if (str_starts_with($belt, 'Dan')) {
        $totalDanHolders++;
    }
}

$resourceLinks = [
    ['label' => 'World Taekwondo', 'url' => 'https://worldtaekwondo.org/'],
    ['label' => 'Kukkiwon', 'url' => 'https://www.kukkiwon.or.kr/'],
    ['label' => 'TCON', 'url' => 'https://www.tkdcon.net/'],
    ['label' => 'KMS', 'url' => 'https://kms.kukkiwon.or.kr/']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Coach Dashboard</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
    :root{
      --panel:rgba(255,255,255,.06);
      --border:rgba(255,255,255,.12);
      --white:#fff;
      --soft:#cfcfcf;
      --red:#d90429;
      --blue:#1565ff;
      --green:#22c55e;
      --gold:#e7c35a;
      --shadow:0 18px 45px rgba(0,0,0,.35);
    }
    body{min-height:100vh;background:linear-gradient(135deg,#020202,#09111f,#170407);color:var(--white);overflow-x:hidden}
    .bg-orb{position:fixed;border-radius:50%;filter:blur(25px);opacity:.22;z-index:0;pointer-events:none;animation:float 10s ease-in-out infinite}
    .orb1{width:260px;height:260px;background:var(--red);top:5%;left:5%}
    .orb2{width:320px;height:320px;background:var(--blue);bottom:5%;right:5%;animation-delay:2s}
    @keyframes float{0%,100%{transform:translateY(0) translateX(0)}50%{transform:translateY(-18px) translateX(15px)}}
    .mobile-top{display:none;padding:14px;position:sticky;top:0;z-index:30;background:rgba(0,0,0,.65);backdrop-filter:blur(8px);border-bottom:1px solid var(--border)}
    .mobile-top button{width:100%;min-height:46px;border:1px solid var(--border);background:rgba(255,255,255,.06);color:var(--white);border-radius:12px;font-weight:bold;cursor:pointer}
    .app{position:relative;z-index:2;display:grid;grid-template-columns:290px 1fr;min-height:100vh}
    .sidebar{background:rgba(0,0,0,.45);border-right:1px solid var(--border);backdrop-filter:blur(12px);position:sticky;top:0;height:100vh;display:flex;flex-direction:column;min-height:0}
    .sidebar-inner{display:flex;flex-direction:column;height:100%;min-height:0;padding:24px 18px;gap:18px}
    .brand{padding:16px;background:var(--panel);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow)}
    .brand h2{font-size:1.25rem;margin-bottom:8px}
    .brand p{color:var(--soft);line-height:1.6;font-size:.92rem}
    .nav{display:grid;gap:10px;overflow-y:auto;flex:1 1 auto;min-height:0;padding-right:4px}
    .nav a,.nav button{width:100%;text-align:left;padding:14px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--white);border-radius:14px;cursor:pointer;transition:.25s ease;font-weight:bold;text-decoration:none;display:block}
    .nav a:hover,.nav a.active,.nav button:hover{background:linear-gradient(135deg,rgba(217,4,41,.15),rgba(21,101,255,.15));border-color:rgba(255,255,255,.2);transform:translateX(3px)}
    .nav-footer{padding-top:6px;border-top:1px solid rgba(255,255,255,.08)}
    .main{padding:24px}
    .topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px}
    .title h1{font-size:2rem;margin-bottom:8px}
    .title p{color:var(--soft);line-height:1.6}
    .badge{padding:12px 16px;border-radius:999px;background:linear-gradient(to right,rgba(217,4,41,.16),rgba(21,101,255,.16));border:1px solid var(--border);font-weight:bold}
    .flash{margin-bottom:16px;padding:14px 16px;border-radius:16px;border:1px solid var(--border);line-height:1.6}
    .flash-success{background:rgba(34,197,94,.12);color:#d8ffe4;border-color:rgba(34,197,94,.25)}
    .flash-error{background:rgba(217,4,41,.12);color:#ffd7de;border-color:rgba(217,4,41,.25)}
    .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}
    .stat-card,.section,.mini-card{background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:var(--shadow)}
    .stat-card{padding:20px}
    .stat-card h3{font-size:1rem;margin-bottom:8px;color:var(--soft)}
    .stat-card .big{font-size:2rem;font-weight:bold;margin-bottom:6px}
    .stat-card p{color:var(--soft);line-height:1.5}
    .section{display:none;padding:22px;margin-bottom:20px}
    .section.active{display:block}
    .section h2{margin-bottom:10px;font-size:1.5rem}
    .section-desc{color:var(--soft);line-height:1.6;margin-bottom:18px}
    .form-grid,.button-row,.card-grid,.link-grid{display:grid;gap:14px}
    .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px}
    .button-row{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px}
    .card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .link-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .form-group{display:grid;gap:8px}
    .form-group.full{grid-column:1/-1}
    label{font-weight:bold;font-size:.95rem}
    input,select,textarea{width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem}
    select option{background:#fff;color:#111}
    textarea{min-height:120px;resize:vertical;padding-top:12px}
    .btn{min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;transition:.25s ease;color:var(--white)}
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue))}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border)}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4}
    .btn-danger{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada}
    .btn-warning{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0}
    .mini-card{padding:16px;margin-bottom:18px}
    .mini-card h3{margin-bottom:10px}
    .mini-card p{color:var(--soft);line-height:1.6;margin-bottom:12px}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border)}
    table{width:100%;border-collapse:collapse;min-width:820px;background:rgba(255,255,255,.04)}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}
    th{background:rgba(255,255,255,.06);font-size:.95rem}
    td{font-size:.94rem;line-height:1.5}
    .status-chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:bold}
    .status-active,.status-approved,.status-verified{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4}
    .status-banned{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada}
    .status-deleted{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#f2f2f2}
    .status-pending{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0}
    .status-rejected{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada}
    .notice-card,.id-card{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px}
    .notice-card{margin-bottom:14px}
    .result-box{margin-top:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid var(--border);line-height:1.6;color:var(--soft);white-space:pre-wrap}
    .link-btn{display:block;text-decoration:none;text-align:center;padding:16px;border-radius:16px;font-weight:bold;color:#fff;background:rgba(255,255,255,.06);border:1px solid var(--border)}
    details.player-accordion{border:1px solid rgba(255,255,255,.08);border-radius:18px;background:rgba(255,255,255,.04);margin-bottom:12px;overflow:hidden}
    details.player-accordion summary{list-style:none;cursor:pointer;padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}
    details.player-accordion summary::-webkit-details-marker{display:none}
    .player-summary-title{font-weight:bold}
    .player-summary-sub{color:var(--soft);font-size:.9rem;margin-top:4px}
    .player-body{padding:0 18px 18px}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.07);font-size:.82rem}
    .helper{font-size:.88rem;color:var(--soft);margin-top:8px}
    .muted{color:var(--soft)}
    .subtle{font-size:.86rem;color:var(--soft)}
    @media (max-width:1100px){
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .form-grid,.button-row,.card-grid,.link-grid{grid-template-columns:1fr}
    }
    @media (max-width:900px){
      .app{grid-template-columns:1fr}
      .mobile-top{display:block}
      .sidebar{position:fixed;left:0;top:61px;width:290px;height:calc(100vh - 61px);transform:translateX(-100%);transition:.3s ease;z-index:20}
      .sidebar.open{transform:translateX(0)}
      .main{padding:16px}
    }
    @media (max-width:640px){
      .stats-grid{grid-template-columns:1fr}
      .title h1{font-size:1.6rem}
      .section{padding:16px;border-radius:18px}
    }
  </style>
</head>
<body>
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>

  <div class="mobile-top">
    <button id="menuToggle">☰ Open Coach Menu</button>
  </div>

  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-inner">
        <div class="brand">
          <h2>Coach Dashboard</h2>
          <p>Manage players, grading, leave approvals, notices, tournaments, and registrations from one panel.</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="coach.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'profileSection' ? 'active' : '' ?>" href="coach.php?section=profileSection">👤 Coach Profile</a>
          <a class="<?= $activeSection === 'playersSection' ? 'active' : '' ?>" href="coach.php?section=playersSection">🥋 Players</a>
          <a class="<?= $activeSection === 'gradingSection' ? 'active' : '' ?>" href="coach.php?section=gradingSection">📚 Grading</a>
          <a class="<?= $activeSection === 'leaveSection' ? 'active' : '' ?>" href="coach.php?section=leaveSection">📝 Leave Management</a>
          <a class="<?= $activeSection === 'tournamentSection' ? 'active' : '' ?>" href="coach.php?section=tournamentSection">🏆 Tournament Hosting</a>
          <a class="<?= $activeSection === 'noticesSection' ? 'active' : '' ?>" href="coach.php?section=noticesSection">📢 Player Notices</a>
          <a class="<?= $activeSection === 'resourcesSection' ? 'active' : '' ?>" href="coach.php?section=resourcesSection">🌐 Resources</a>
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
          <h1>Welcome, <?= e((string)$currentCoach['coach_name']) ?></h1>
          <p><?= e((string)$currentCoach['institution_name']) ?> · <?= e((string)$currentCoach['registration_type']) ?> · Coach management panel</p>
        </div>
        <div class="badge"><?= e((string)$currentCoach['email']) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Players</h3>
          <div class="big"><?= e((string)$totalPlayers) ?></div>
          <p>All club players except deleted records.</p>
        </div>
        <div class="stat-card">
          <h3>Color Belts</h3>
          <div class="big"><?= e((string)$totalColorBelts) ?></div>
          <p>Current active color belt flow.</p>
        </div>
        <div class="stat-card">
          <h3>Half/Black</h3>
          <div class="big"><?= e((string)$totalBlackBelts) ?></div>
          <p>Pre-black and black-belt players.</p>
        </div>
        <div class="stat-card">
          <h3>Dan Holders</h3>
          <div class="big"><?= e((string)$totalDanHolders) ?></div>
          <p>Advanced dan-level holders in club.</p>
        </div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Coach Overview</h2>
        <p class="section-desc">This panel connects player creation, grading, leave approval, tournament hosting, notices, and admin request flow.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Player Management</h3>
            <p>Create players, assign email login, update weight and belt, and request delete or ban to admin.</p>
            <a class="btn btn-primary" href="coach.php?section=playersSection" style="display:inline-block;text-decoration:none;">Open Players</a>
          </div>

          <div class="mini-card">
            <h3>Grading System</h3>
            <p>Color-belt and advanced-belt grading flow with marks, results, and automatic notice publishing.</p>
            <a class="btn btn-primary" href="coach.php?section=gradingSection" style="display:inline-block;text-decoration:none;">Open Grading</a>
          </div>

          <div class="mini-card">
            <h3>Leave Approval</h3>
            <p>Approve or reject player leave requests with remarks. This is handled by coach, not admin.</p>
            <a class="btn btn-primary" href="coach.php?section=leaveSection" style="display:inline-block;text-decoration:none;">Open Leave Section</a>
          </div>

          <div class="mini-card">
            <h3>Tournament Hosting</h3>
            <p>Host tournament, define poomsae and kyorugi categories, and register players to verified events.</p>
            <a class="btn btn-primary" href="coach.php?section=tournamentSection" style="display:inline-block;text-decoration:none;">Open Tournament Section</a>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'profileSection' ? 'active' : '' ?>">
        <h2>Coach Profile</h2>
        <p class="section-desc">Update your profile and change password.</p>

        <div class="mini-card">
          <h3>Profile Details</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-grid">
              <div class="form-group">
                <label>Coach Name</label>
                <input type="text" name="coach_name" value="<?= e((string)$currentCoach['coach_name']) ?>" required>
              </div>

              <div class="form-group">
                <label>Club / Institution Name</label>
                <input type="text" name="institution_name" value="<?= e((string)$currentCoach['institution_name']) ?>" required>
              </div>

              <div class="form-group full">
                <label>Club Address</label>
                <input type="text" name="club_address" value="<?= e((string)$currentCoach['club_address']) ?>">
              </div>

              <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number" value="<?= e((string)$currentCoach['contact_number']) ?>">
              </div>

              <div class="form-group">
                <label>Association Registered Number</label>
                <input type="text" value="<?= e((string)$currentCoach['association_registered_number']) ?>" readonly>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Update Profile</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Change Password</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-grid">
              <div class="form-group full">
                <label>Email</label>
                <input type="email" value="<?= e((string)$currentCoach['email']) ?>" readonly>
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
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Change Password</button>
            </div>
          </form>
        </div>
      </section>

      <section class="section <?= $activeSection === 'playersSection' ? 'active' : '' ?>">
        <h2>Player Management</h2>
        <p class="section-desc">Create player accounts, edit player data, and send delete or ban requests to admin.</p>

        <div class="mini-card">
          <h3>Create New Player</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_player">

            <div class="form-grid">
              <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required>
              </div>

              <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" required>
              </div>

              <div class="form-group">
                <label>Gender</label>
                <select name="gender" required>
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>

              <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.01" name="weight_kg" required>
              </div>

              <div class="form-group">
                <label>Belt Rank</label>
                <select name="belt_rank" required>
                  <option value="">Select belt</option>
                  <?php foreach (allBelts() as $belt): ?>
                    <option value="<?= e($belt) ?>"><?= e($belt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Country</label>
                <input type="text" name="country_name" value="Nepal">
              </div>

              <div class="form-group full">
                <label>Club Name</label>
                <input type="text" name="club_name" value="<?= e($currentClubName) ?>" required>
              </div>

              <div class="form-group full">
                <label>Club Address</label>
                <input type="text" name="club_address" value="<?= e((string)$currentCoach['club_address']) ?>">
              </div>

              <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number">
              </div>

              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Create Player</button>
            </div>

            <div class="helper">Default player password will be: <strong><?= e(DEFAULT_PLAYER_PASSWORD) ?></strong></div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Club Players</h3>

          <?php if (!$allClubPlayers): ?>
            <div class="result-box">No players found for this club.</div>
          <?php else: ?>
            <?php foreach ($allClubPlayers as $player): ?>
              <details class="player-accordion" <?= $openPlayerId === (int)$player['id'] ? 'open' : '' ?>>
                <summary>
                  <div>
                    <div class="player-summary-title"><?= e((string)$player['full_name']) ?></div>
                    <div class="player-summary-sub">
                      <?= e((string)$player['age_category']) ?> · <?= e((string)$player['weight_category']) ?> · <?= e((string)$player['belt_rank']) ?>
                    </div>
                  </div>
                  <div>
                    <span class="status-chip <?= $player['status'] === 'Active' ? 'status-active' : ($player['status'] === 'Banned' ? 'status-banned' : 'status-deleted') ?>">
                      <?= e((string)$player['status']) ?>
                    </span>
                  </div>
                </summary>

                <div class="player-body">
                  <?php
                  $stmt = $pdo->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
                  $stmt->execute([(int)$player['id']]);
                  $fullPlayer = $stmt->fetch();
                  ?>

                  <?php if ($fullPlayer): ?>
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="action" value="update_player_basic">
                      <input type="hidden" name="player_id" value="<?= (int)$fullPlayer['id'] ?>">

                      <div class="form-grid">
                        <div class="form-group">
                          <label>Weight (kg)</label>
                          <input type="number" step="0.01" name="weight_kg" value="<?= e((string)$fullPlayer['weight_kg']) ?>">
                        </div>

                        <div class="form-group">
                          <label>Belt Rank</label>
                          <select name="belt_rank" required>
                            <?php foreach (allBelts() as $belt): ?>
                              <option value="<?= e($belt) ?>" <?= (string)$fullPlayer['belt_rank'] === $belt ? 'selected' : '' ?>>
                                <?= e($belt) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="form-group">
                          <label>Contact Number</label>
                          <input type="text" name="contact_number" value="<?= e((string)$fullPlayer['contact_number']) ?>">
                        </div>

                        <div class="form-group">
                          <label>Email</label>
                          <input type="email" name="email" value="<?= e((string)$fullPlayer['email']) ?>" required>
                        </div>
                      </div>

                      <div class="button-row">
                        <button class="btn btn-primary" type="submit">Update Player</button>
                      </div>
                    </form>

                    <?php if ((string)$fullPlayer['status'] !== 'Deleted'): ?>
                      <div class="form-grid" style="margin-top:16px;">
                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="action" value="ban_request">
                          <input type="hidden" name="player_id" value="<?= (int)$fullPlayer['id'] ?>">
                          <div class="form-group">
                            <label>Ban Request Reason</label>
                            <input type="text" name="reason" required>
                          </div>
                          <button class="btn btn-warning" type="submit" style="width:100%;">Send Ban Request to Admin</button>
                        </form>

                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="action" value="delete_request">
                          <input type="hidden" name="player_id" value="<?= (int)$fullPlayer['id'] ?>">
                          <div class="form-group">
                            <label>Delete Request Reason</label>
                            <input type="text" name="reason" required>
                          </div>
                          <button class="btn btn-danger" type="submit" style="width:100%;">Send Delete Request to Admin</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </details>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'gradingSection' ? 'active' : '' ?>">
        <h2>Grading System</h2>
        <p class="section-desc">Grade players and publish automatic result notice.</p>

        <div class="mini-card">
          <h3>Grade Player</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="grade_player">

            <div class="form-grid">
              <div class="form-group full">
                <label>Select Player</label>
                <select name="player_id" required>
                  <option value="">Select player</option>
                  <?php foreach ($activePlayers as $player): ?>
                    <option value="<?= (int)$player['id'] ?>">
                      <?= e((string)$player['full_name']) ?> - <?= e((string)$player['belt_rank']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group"><label>Basic</label><input type="number" step="0.01" name="marks_basic" value="0" required></div>
              <div class="form-group"><label>Kicking</label><input type="number" step="0.01" name="marks_kicking" value="0" required></div>
              <div class="form-group"><label>Poomsae</label><input type="number" step="0.01" name="marks_poomsae" value="0" required></div>
              <div class="form-group"><label>Breaking</label><input type="number" step="0.01" name="marks_breaking" value="0"></div>
              <div class="form-group"><label>Sparring</label><input type="number" step="0.01" name="marks_sparring" value="0"></div>
              <div class="form-group"><label>Self Defence</label><input type="number" step="0.01" name="marks_self_defence" value="0"></div>
              <div class="form-group"><label>One Step</label><input type="number" step="0.01" name="marks_one_step" value="0"></div>
              <div class="form-group"><label>Flying Kick</label><input type="number" step="0.01" name="marks_flying_kick" value="0"></div>
              <div class="form-group"><label>Punch</label><input type="number" step="0.01" name="marks_punch" value="0"></div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Save Grading</button>
            </div>

            <div class="helper">
              Color belt grading uses the basic five sections. Advanced belt grading uses the advanced total.
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Recent Grading History</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Player ID</th>
                  <th>Type</th>
                  <th>Previous Belt</th>
                  <th>New Belt</th>
                  <th>Total</th>
                  <th>Result</th>
                  <th>Promotion</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$gradingHistory): ?>
                  <tr><td colspan="8">No grading history found.</td></tr>
                <?php else: ?>
                  <?php foreach ($gradingHistory as $g): ?>
                    <tr>
                      <td><?= e((string)$g['player_id']) ?></td>
                      <td><?= e((string)$g['grading_type']) ?></td>
                      <td><?= e((string)$g['previous_belt']) ?></td>
                      <td><?= e((string)$g['new_belt']) ?></td>
                      <td><?= e((string)$g['total_marks']) ?></td>
                      <td><?= e((string)$g['result_status']) ?></td>
                      <td><?= e((string)$g['promotion_type']) ?></td>
                      <td><?= e((string)$g['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'leaveSection' ? 'active' : '' ?>">
        <h2>Leave Management</h2>
        <p class="section-desc">Coach approves or rejects player leave requests. This is the correct leave control section.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Player</th>
                <th>Topic</th>
                <th>Description</th>
                <th>Leave Date</th>
                <th>Status</th>
                <th>Coach Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$leaveRequests): ?>
                <tr><td colspan="7">No leave requests found.</td></tr>
              <?php else: ?>
                <?php foreach ($leaveRequests as $leave): ?>
                  <tr>
                    <td><?= e((string)$leave['full_name']) ?></td>
                    <td><?= e((string)$leave['topic']) ?></td>
                    <td><?= e((string)$leave['description']) ?></td>
                    <td><?= e((string)$leave['leave_date']) ?></td>
                    <td>
                      <span class="status-chip <?= $leave['status'] === 'Pending' ? 'status-pending' : ($leave['status'] === 'Approved' ? 'status-approved' : 'status-rejected') ?>">
                        <?= e((string)$leave['status']) ?>
                      </span>
                    </td>
                    <td><?= e((string)$leave['coach_remarks']) ?></td>
                    <td>
                      <?php if ($leave['status'] === 'Pending'): ?>
                        <div class="form-grid">
                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="approve_leave">
                            <input type="hidden" name="leave_id" value="<?= (int)$leave['id'] ?>">
                            <div class="form-group">
                              <input type="text" name="coach_remarks" placeholder="Approval remark">
                            </div>
                            <button class="btn btn-success" type="submit" style="width:100%;">Approve</button>
                          </form>

                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="reject_leave">
                            <input type="hidden" name="leave_id" value="<?= (int)$leave['id'] ?>">
                            <div class="form-group">
                              <input type="text" name="coach_remarks" placeholder="Rejection remark" required>
                            </div>
                            <button class="btn btn-danger" type="submit" style="width:100%;">Reject</button>
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

      <section class="section <?= $activeSection === 'tournamentSection' ? 'active' : '' ?>">
        <h2>Tournament Hosting</h2>
        <p class="section-desc">Host tournaments, define categories, and register your players into verified hosted events.</p>

        <div class="mini-card">
          <h3>Host Tournament</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="host_tournament">

            <div class="form-grid">
              <div class="form-group">
                <label>Tournament Name</label>
                <input type="text" name="tournament_name" required>
              </div>

              <div class="form-group">
                <label>Event Mode</label>
                <select name="event_mode" id="eventMode" required>
                  <option value="">Select mode</option>
                  <option value="Poomsae">Poomsae</option>
                  <option value="Kyorugi">Kyorugi</option>
                  <option value="Both">Both</option>
                </select>
              </div>

              <div class="form-group">
                <label>Arena Count</label>
                <input type="number" name="arena_count" min="1" value="1" required>
              </div>

              <div class="form-group">
                <label>Poomsae Fee</label>
                <input type="number" step="0.01" name="entry_fee_poomsae">
              </div>

              <div class="form-group">
                <label>Kyorugi Fee</label>
                <input type="number" step="0.01" name="entry_fee_kyorugi">
              </div>

              <div class="form-group">
                <label>Both Discount Fee</label>
                <input type="number" step="0.01" name="entry_fee_both_discount">
              </div>

              <div class="form-group full">
                <label>Poomsae Individual Age Categories</label>
                <textarea name="poomsae_individual_ages" placeholder="One per line&#10;Children&#10;Cadets&#10;Juniors"></textarea>
              </div>

              <div class="form-group full">
                <label>Poomsae Pair Age Categories</label>
                <textarea name="poomsae_pair_ages" placeholder="One per line&#10;Cadets&#10;Juniors"></textarea>
              </div>

              <div class="form-group full">
                <label>Poomsae Group Age Categories</label>
                <textarea name="poomsae_group_ages" placeholder="One per line&#10;Children&#10;Adults"></textarea>
              </div>

              <div class="form-group full">
                <label>Kyorugi Categories</label>
                <textarea name="kyorugi_categories" placeholder="One per line using AgeCategory|WeightCategory&#10;Juniors|Male -58kg&#10;Juniors|Male -68kg&#10;Adults|Female -57kg"></textarea>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Submit Tournament Hosting</button>
            </div>

            <div class="helper">
              Kyorugi must use Olympic weight categories. Format each Kyorugi line like:
              <strong>AgeCategory|WeightCategory</strong>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>My Tournaments</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament</th>
                  <th>Mode</th>
                  <th>Arenas</th>
                  <th>Poomsae Fee</th>
                  <th>Kyorugi Fee</th>
                  <th>Both Fee</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$coachTournaments): ?>
                  <tr><td colspan="8">No tournaments hosted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($coachTournaments as $t): ?>
                    <tr>
                      <td><a href="coach.php?section=tournamentSection&tournament=<?= (int)$t['id'] ?>" style="color:#fff;text-decoration:none;"><?= e((string)$t['tournament_name']) ?></a></td>
                      <td><?= e((string)$t['event_scope']) ?></td>
                      <td><?= e((string)$t['arena_count']) ?></td>
                      <td><?= e((string)($t['entry_fee_poomsae'] ?? 'N/A')) ?></td>
                      <td><?= e((string)($t['entry_fee_kyorugi'] ?? 'N/A')) ?></td>
                      <td><?= e((string)($t['entry_fee_both_discount'] ?? 'N/A')) ?></td>
                      <td>
                        <span class="status-chip <?= $t['status'] === 'Pending' ? 'status-pending' : ($t['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                          <?= e((string)$t['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$t['remarks']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($selectedTournament): ?>
          <div class="mini-card">
            <h3>Selected Tournament Details</h3>
            <p>
              <strong><?= e((string)$selectedTournament['tournament_name']) ?></strong><br>
              Mode: <?= e((string)$selectedTournament['event_scope']) ?><br>
              Status: <?= e((string)$selectedTournament['status']) ?><br>
              Arena Count: <?= e((string)$selectedTournament['arena_count']) ?>
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
                    <tr><td colspan="3">No categories defined.</td></tr>
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

          <?php if ($selectedTournament['status'] === 'Verified'): ?>
            <div class="mini-card">
              <h3>Register Player for Tournament</h3>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="register_player_for_tournament">
                <input type="hidden" name="tournament_id" value="<?= (int)$selectedTournament['id'] ?>">

                <div class="form-grid">
                  <div class="form-group">
                    <label>Select Player</label>
                    <select name="player_id" required>
                      <option value="">Select player</option>
                      <?php foreach ($activePlayers as $player): ?>
                        <option value="<?= (int)$player['id'] ?>">
                          <?= e((string)$player['full_name']) ?> - <?= e((string)$player['age_category']) ?> - <?= e((string)$player['weight_category']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group">
                    <label>Select Event Type</label>
                    <select name="event_type" required>
                      <option value="">Select event</option>
                      <?php
                      $eventTypes = [];
                      foreach ($tournamentCategories as $cat) {
                          $eventTypes[$cat['event_type']] = true;
                      }
                      foreach (array_keys($eventTypes) as $eventType):
                      ?>
                        <option value="<?= e($eventType) ?>"><?= e($eventType) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="button-row">
                  <button class="btn btn-primary" type="submit">Register Player</button>
                </div>
              </form>
            </div>
          <?php else: ?>
            <div class="result-box">This tournament must be verified by admin before player registration can happen.</div>
          <?php endif; ?>

          <div class="mini-card">
            <h3>Tournament Applicants</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Player</th>
                    <th>Event</th>
                    <th>Age</th>
                    <th>Weight</th>
                    <th>Club</th>
                    <th>Status</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$tournamentApplicants): ?>
                    <tr><td colspan="7">No applicants found for this tournament yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($tournamentApplicants as $app): ?>
                      <tr>
                        <td><?= e((string)$app['applicant_name']) ?></td>
                        <td><?= e((string)$app['event_type']) ?></td>
                        <td><?= e((string)$app['age_category']) ?></td>
                        <td><?= e((string)$app['weight_category']) ?></td>
                        <td><?= e((string)$app['club_name']) ?></td>
                        <td>
                          <span class="status-chip <?= $app['status'] === 'Pending' ? 'status-pending' : ($app['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                            <?= e((string)$app['status']) ?>
                          </span>
                        </td>
                        <td><?= e((string)$app['remarks']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <section class="section <?= $activeSection === 'noticesSection' ? 'active' : '' ?>">
        <h2>Player Notices</h2>
        <p class="section-desc">Send notices to your players and view recent admin notices for coaches.</p>

        <div class="mini-card">
          <h3>Publish Notice to Players</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="publish_notice">

            <div class="form-grid">
              <div class="form-group full">
                <label>Notice Title</label>
                <input type="text" name="notice_title" required>
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

        <div class="card-grid">
          <div class="mini-card">
            <h3>My Player Notices</h3>
            <?php if (!$coachNotices): ?>
              <div class="result-box">No player notices published yet.</div>
            <?php else: ?>
              <?php foreach ($coachNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <div class="subtle"><?= e((string)$notice['created_at']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="mini-card">
            <h3>Recent Admin Notices</h3>
            <?php if (!$adminNotices): ?>
              <div class="result-box">No admin notices found.</div>
            <?php else: ?>
              <?php foreach ($adminNotices as $notice): ?>
                <div class="notice-card">
                  <h4><?= e((string)$notice['title']) ?></h4>
                  <p><?= nl2br(e((string)$notice['message'])) ?></p>
                  <div class="subtle"><?= e((string)$notice['created_at']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'resourcesSection' ? 'active' : '' ?>">
        <h2>Resources</h2>
        <p class="section-desc">Quick official links for coach use.</p>

        <div class="link-grid">
          <?php foreach ($resourceLinks as $link): ?>
            <a class="link-btn" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= e($link['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="result-box" style="margin-top:18px;">
Coach Name: <?= e((string)$currentCoach['coach_name']) ?>

Institution: <?= e((string)$currentCoach['institution_name']) ?>
Status: <?= e((string)$currentCoach['status']) ?>

This dashboard is aligned with:
- admin tournament approval flow
- player leave request flow
- referee assignment flow
- player registration and grading flow
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
