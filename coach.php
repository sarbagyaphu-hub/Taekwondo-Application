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

function calculateAge(?string $dob): ?int {
    if (!$dob) return null;
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

    return null;
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
        'Poom 1', 'Poom 2', 'Poom 3',
        'Dan 1', 'Dan 2', 'Dan 3', 'Dan 4', 'Dan 5', 'Dan 6', 'Dan 7', 'Dan 8', 'Dan 9'
    ];
}

function allBelts(): array {
    return array_merge(getColorBelts(), getAdvancedBelts());
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
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Verified',
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
| SAFE COLUMN MIGRATIONS
|--------------------------------------------------------------------------
*/
$coachMigrations = [
    'club_address'   => "ALTER TABLE coaches ADD COLUMN club_address VARCHAR(255) NULL AFTER remarks",
    'contact_number' => "ALTER TABLE coaches ADD COLUMN contact_number VARCHAR(80) NULL AFTER club_address",
];
foreach ($coachMigrations as $column => $sql) {
    if (!columnExists($pdo, 'coaches', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

$playerMigrations = [
    'gender'          => "ALTER TABLE players ADD COLUMN gender VARCHAR(20) NULL AFTER age",
    'weight_category' => "ALTER TABLE players ADD COLUMN weight_category VARCHAR(100) NULL AFTER weight_kg",
    'age_category'    => "ALTER TABLE players ADD COLUMN age_category VARCHAR(100) NULL AFTER weight_category",
    'club_address'    => "ALTER TABLE players ADD COLUMN club_address VARCHAR(255) NULL AFTER club_name",
    'status'          => "ALTER TABLE players ADD COLUMN status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active' AFTER participated_games",
];
foreach ($playerMigrations as $column => $sql) {
    if (!columnExists($pdo, 'players', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}
/*
|--------------------------------------------------------------------------
| SAFE COLUMN MIGRATIONS FOR COACH-RELATED TABLES
|--------------------------------------------------------------------------
*/
$playerGradingsMigrations = [
    'coach_id' => "ALTER TABLE player_gradings ADD COLUMN coach_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER player_id",
];
foreach ($playerGradingsMigrations as $column => $sql) {
    if (tableExists($pdo, 'player_gradings') && !columnExists($pdo, 'player_gradings', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

$coachPlayerNoticesMigrations = [
    'coach_id' => "ALTER TABLE coach_player_notices ADD COLUMN coach_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id",
];
foreach ($coachPlayerNoticesMigrations as $column => $sql) {
    if (tableExists($pdo, 'coach_player_notices') && !columnExists($pdo, 'coach_player_notices', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

$tournamentMigrations = [
    'coach_id' => "ALTER TABLE tournaments ADD COLUMN coach_id INT UNSIGNED NULL AFTER id",
];
foreach ($tournamentMigrations as $column => $sql) {
    if (tableExists($pdo, 'tournaments') && !columnExists($pdo, 'tournaments', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

$adminAlertsMigrations = [
    'coach_id' => "ALTER TABLE admin_alerts ADD COLUMN coach_id INT UNSIGNED NULL AFTER id",
];
foreach ($adminAlertsMigrations as $column => $sql) {
    if (tableExists($pdo, 'admin_alerts') && !columnExists($pdo, 'admin_alerts', $column)) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
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
    SELECT id, coach_name, institution_name, email, registration_type, status, association_registered_number, club_address, contact_number, password_hash
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
                $stmt = $pdo->prepare("UPDATE players SET club_name = ? WHERE club_name = ?");
                $stmt->execute([$institutionName, $oldInstitution]);
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

            if (!password_verify($currentPassword, $currentCoach['password_hash'])) {
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
            $clubAddress = trim($_POST['club_address'] ?? ($currentCoach['club_address'] ?? ''));
            $contactNumber = trim($_POST['contact_number'] ?? '');

            if ($fullName === '' || $dob === '' || $gender === '' || $weight === '' || $beltRank === '') {
                throw new RuntimeException('Please fill all required player fields.');
            }

            $age = calculateAge($dob);
            $weightFloat = (float)$weight;
            $ageCategory = deriveAgeCategory($age);
            $weightCategory = deriveWeightCategory($gender, $weightFloat);
            $playerCode = generatePlayerCode($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO players
                (player_code, full_name, dob, age, gender, weight_kg, weight_category, age_category, belt_rank, country_name, club_name, club_address, contact_number, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
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
                $contactNumber
            ]);

            setFlash('success', "Player created successfully. Player ID: {$playerCode}");
            redirect('coach.php?section=playersSection');
        }

        if ($action === 'update_player_basic') {
            $playerId = (int)($_POST['player_id'] ?? 0);
            $weight = trim($_POST['weight_kg'] ?? '');
            $beltRank = trim($_POST['belt_rank'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');

            $stmt = $pdo->prepare("
                SELECT id, gender, dob
                FROM players
                WHERE id = ? AND club_name = ?
                LIMIT 1
            ");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found for this coach.');
            }

            $age = calculateAge($player['dob']);
            $ageCategory = deriveAgeCategory($age);
            $weightFloat = $weight !== '' ? (float)$weight : null;
            $weightCategory = deriveWeightCategory((string)$player['gender'], $weightFloat);

            $stmt = $pdo->prepare("
                UPDATE players
                SET weight_kg = ?, weight_category = ?, age_category = ?, belt_rank = ?, contact_number = ?
                WHERE id = ? AND club_name = ?
            ");
            $stmt->execute([$weightFloat, $weightCategory, $ageCategory, $beltRank, $contactNumber, $playerId, $currentClubName]);

            setFlash('success', 'Player profile updated successfully.');
            redirect('coach.php?section=playersSection');
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
            $isAdvanced = in_array($previousBelt, ['Half Black','Black','Poom 1','Poom 2','Poom 3','Dan 1','Dan 2','Dan 3','Dan 4','Dan 5','Dan 6','Dan 7','Dan 8','Dan 9'], true);

            if (!$isAdvanced) {
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
                $playerId,
                $currentCoachId,
                $gradingType,
                $previousBelt,
                $newBelt,
                $basic,
                $kicking,
                $poomsae,
                $breaking,
                $sparring,
                $selfDefence,
                $oneStep,
                $flyingKick,
                $punch,
                $total,
                $resultStatus,
                $promotionType
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

            if ($tournamentName === '' || $arenaCount <= 0 || $eventMode === '') {
                throw new RuntimeException('Tournament name, arena count, and event mode are required.');
            }

            $poomsaeEnabled = in_array($eventMode, ['Poomsae','Both'], true) ? 1 : 0;
            $kyorugiEnabled = in_array($eventMode, ['Kyorugi','Both'], true) ? 1 : 0;

            $feePoomsae = $entryFeePoomsae !== '' ? (float)$entryFeePoomsae : null;
            $feeKyorugi = $entryFeeKyorugi !== '' ? (float)$entryFeeKyorugi : null;
            $feeBoth = $entryFeeBoth !== '' ? (float)$entryFeeBoth : null;

            if ($eventMode === 'Both' && $feePoomsae !== null && $feeKyorugi !== null && $feeBoth !== null) {
                if ($feeBoth >= ($feePoomsae + $feeKyorugi)) {
                    throw new RuntimeException('Both-event discounted fee must be less than the sum of individual fees.');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO tournaments
                (coach_id, tournament_name, host_club, host_coach, event_scope, poomsae_enabled, kyorugi_enabled, arena_count, entry_fee_poomsae, entry_fee_kyorugi, entry_fee_both_discount, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NULL)
            ");
            $stmt->execute([
                $currentCoachId,
                $tournamentName,
                $currentClubName,
                $currentCoach['coach_name'],
                $eventMode,
                $poomsaeEnabled,
                $kyorugiEnabled,
                $arenaCount,
                $feePoomsae,
                $feeKyorugi,
                $feeBoth
            ]);

            setFlash('success', 'Tournament hosting application submitted successfully.');
            redirect('coach.php?section=tournamentSection');
        }

        if ($action === 'delete_request' || $action === 'ban_request') {
            $playerId = (int)($_POST['player_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            if ($playerId <= 0 || $reason === '') {
                throw new RuntimeException('Player and reason are required.');
            }

            $stmt = $pdo->prepare("SELECT id, full_name FROM players WHERE id = ? AND club_name = ? LIMIT 1");
            $stmt->execute([$playerId, $currentClubName]);
            $player = $stmt->fetch();

            if (!$player) {
                throw new RuntimeException('Player not found.');
            }

            $alertType = $action === 'delete_request' ? 'Delete Request' : 'Ban Request';
            $title = $alertType . ' - ' . $player['full_name'];

            $stmt = $pdo->prepare("
                INSERT INTO admin_alerts (coach_id, player_id, alert_type, title, reason_text, status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$currentCoachId, $playerId, $alertType, $title, $reason]);

            $newStatus = $action === 'delete_request' ? 'Deleted' : 'Banned';
            $stmt = $pdo->prepare("UPDATE players SET status = ? WHERE id = ? AND club_name = ?");
            $stmt->execute([$newStatus, $playerId, $currentClubName]);

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
$selectedPlayerId = (int)($_GET['player'] ?? 0);

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

$selectedPlayer = null;
foreach ($players as $p) {
    if ((int)$p['id'] === $selectedPlayerId) {
        $selectedPlayer = $p;
        break;
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
    *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;}
    :root{
      --panel:rgba(255,255,255,0.06);
      --border:rgba(255,255,255,0.12);
      --white:#ffffff;
      --soft:#cfcfcf;
      --red:#d90429;
      --blue:#1565ff;
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
    .form-grid,.button-row,.card-grid,.link-grid{display:grid;gap:14px;}
    .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px;}
    .button-row{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px;}
    .card-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    .link-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    .form-group{display:grid;gap:8px;}
    .form-group.full{grid-column:1 / -1;}
    label{font-weight:bold;font-size:.95rem;}
    input,select,textarea{width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;}
    textarea{min-height:120px;resize:vertical;padding-top:12px;}
    .btn{min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;transition:.25s ease;color:var(--white);}
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue));}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .btn-danger{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .mini-card{padding:16px;margin-bottom:18px;}
    .mini-card h3{margin-bottom:10px;}
    .mini-card p{color:var(--soft);line-height:1.6;margin-bottom:12px;}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border);}
    table{width:100%;border-collapse:collapse;min-width:860px;background:rgba(255,255,255,.04);}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top;}
    th{background:rgba(255,255,255,.06);font-size:.95rem;}
    td{font-size:.94rem;line-height:1.5;}
    .status-chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:bold;}
    .status-active{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .status-banned{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .status-deleted{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#f2f2f2;}
    .status-pending{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0;}
    .status-verified{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .status-rejected{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .notice-card,.id-card{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;}
    .notice-card{margin-bottom:14px;}
    .result-box{margin-top:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid var(--border);line-height:1.6;color:var(--soft);white-space:pre-wrap;}
    .link-btn{display:block;text-decoration:none;text-align:center;padding:16px;border-radius:16px;font-weight:bold;color:#fff;background:rgba(255,255,255,.06);border:1px solid var(--border);}
    .id-preview-wrap{display:flex;justify-content:center;}
    .id-card{width:360px;max-width:100%;background:radial-gradient(circle at 18% 20%, rgba(217,4,41,.18), transparent 28%), radial-gradient(circle at 80% 80%, rgba(21,101,255,.18), transparent 28%), linear-gradient(145deg,#0c0c0c,#111927,#0d0d0d);color:#fff;border-radius:22px;padding:18px;position:relative;overflow:hidden;}
    .id-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
    .id-logo{font-weight:bold;font-size:1rem;color:#e7c35a;}
    .id-badge{padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.08);font-size:.8rem;}
    .id-name{font-size:1.4rem;font-weight:bold;margin-bottom:8px;}
    .id-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;}
    .id-item{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:10px;}
    .id-item h5{color:var(--soft);font-size:.8rem;margin-bottom:4px;}
    .id-item p{font-size:.92rem;word-break:break-word;}
    .print-actions{margin-top:16px;text-align:center;}
    @media print{
      body *{visibility:hidden !important;}
      .id-card, .id-card *{visibility:visible !important;}
      .id-card{position:absolute;left:0;top:0;width:85.6mm;height:54mm;box-shadow:none;}
    }
    @media (max-width:1100px){
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .form-grid,.button-row,.card-grid,.link-grid{grid-template-columns:1fr;}
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
    <button id="menuToggle">☰ Open Coach Menu</button>
  </div>

  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-inner">
        <div class="brand">
          <h2>Hi Coach,</h2>
          <p>Manage players, grading, tournament hosting, notices, profile, and athlete cards from one complete coach dashboard.</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="coach.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'profileSection' ? 'active' : '' ?>" href="coach.php?section=profileSection">👤 Profile</a>
          <a class="<?= $activeSection === 'playersSection' ? 'active' : '' ?>" href="coach.php?section=playersSection">🥋 Players</a>
          <a class="<?= $activeSection === 'gradingSection' ? 'active' : '' ?>" href="coach.php?section=gradingSection">📝 Grading</a>
          <a class="<?= $activeSection === 'tournamentSection' ? 'active' : '' ?>" href="coach.php?section=tournamentSection">🏆 Tournament Hosting</a>
          <a class="<?= $activeSection === 'noticesSection' ? 'active' : '' ?>" href="coach.php?section=noticesSection">📢 Player Notices</a>
          <a class="<?= $activeSection === 'resourcesSection' ? 'active' : '' ?>" href="coach.php?section=resourcesSection">🌐 Resources</a>
          <a class="<?= $activeSection === 'idCardSection' ? 'active' : '' ?>" href="coach.php?section=idCardSection">🪪 Player Card</a>
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
          <p><?= e((string)$currentCoach['institution_name']) ?> · <?= e((string)$currentCoach['registration_type']) ?> Coach Dashboard</p>
        </div>
        <div class="badge">Verified Coach</div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Registered Players</h3>
          <div class="big"><?= e((string)$totalPlayers) ?></div>
          <p>Players linked to this coach.</p>
        </div>
        <div class="stat-card">
          <h3>Color Belts</h3>
          <div class="big"><?= e((string)$totalColorBelts) ?></div>
          <p>Color belt players under this coach.</p>
        </div>
        <div class="stat-card">
          <h3>Black / Half Black</h3>
          <div class="big"><?= e((string)$totalBlackBelts) ?></div>
          <p>Advanced belt stage players.</p>
        </div>
        <div class="stat-card">
          <h3>Dan Holders</h3>
          <div class="big"><?= e((string)$totalDanHolders) ?></div>
          <p>Players holding Dan rank.</p>
        </div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Coach Overview</h2>
        <p class="section-desc">A complete overview of player management, grading, notices, tournament hosting, profile management, and official resources.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Profile Management</h3>
            <p>Edit coach details, club name, club address, contact number, and change password.</p>
            <a class="btn btn-primary" href="coach.php?section=profileSection" style="display:inline-block;text-decoration:none;">Open Profile</a>
          </div>

          <div class="mini-card">
            <h3>Create and Manage Players</h3>
            <p>Create player accounts, update player data, update belt ranks, and prepare athlete cards.</p>
            <a class="btn btn-primary" href="coach.php?section=playersSection" style="display:inline-block;text-decoration:none;">Open Players</a>
          </div>

          <div class="mini-card">
            <h3>Grading and Results</h3>
            <p>Enter grading marks, calculate totals, pass/fail, and auto-update promotion levels.</p>
            <a class="btn btn-primary" href="coach.php?section=gradingSection" style="display:inline-block;text-decoration:none;">Open Grading</a>
          </div>

          <div class="mini-card">
            <h3>Host Tournament</h3>
            <p>Apply to host tournaments with arena count, mode selection, and entry fees.</p>
            <a class="btn btn-primary" href="coach.php?section=tournamentSection" style="display:inline-block;text-decoration:none;">Open Tournament Hosting</a>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'profileSection' ? 'active' : '' ?>">
        <h2>Coach Profile</h2>
        <p class="section-desc">Update your coach profile and change your password.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Edit Profile</h3>
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
                  <input type="text" name="club_address" value="<?= e((string)($currentCoach['club_address'] ?? '')) ?>">
                </div>
                <div class="form-group">
                  <label>Contact Number</label>
                  <input type="text" name="contact_number" value="<?= e((string)($currentCoach['contact_number'] ?? '')) ?>">
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input type="email" value="<?= e((string)$currentCoach['email']) ?>" readonly>
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Save Profile</button>
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
                  <label>Current Password</label>
                  <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                  <label>New Password</label>
                  <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                  <label>Confirm New Password</label>
                  <input type="password" name="confirm_password" required>
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Change Password</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'playersSection' ? 'active' : '' ?>">
        <h2>Players</h2>
        <p class="section-desc">Create player accounts, update player details, request delete or ban, and maintain athlete records.</p>

        <div class="mini-card">
          <h3>Create Player Account</h3>
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
                  <option value="">Select gender</option>
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
                <label>Country Name</label>
                <input type="text" name="country_name" value="Nepal">
              </div>
              <div class="form-group">
                <label>Club Name</label>
                <input type="text" name="club_name" value="<?= e($currentClubName) ?>">
              </div>
              <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number">
              </div>
              <div class="form-group full">
                <label>Club Address</label>
                <input type="text" name="club_address" value="<?= e((string)($currentCoach['club_address'] ?? '')) ?>">
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Create Player</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>Player List</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Age</th>
                  <th>Weight</th>
                  <th>Age Category</th>
                  <th>Weight Category</th>
                  <th>Belt</th>
                  <th>Status</th>
                  <th>Contact</th>
                  <th>Update</th>
                  <th>Delete/Ban</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$players): ?>
                  <tr><td colspan="11">No players created yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($players as $player): ?>
                    <tr>
                      <td><?= e((string)$player['player_code']) ?></td>
                      <td><?= e((string)$player['full_name']) ?></td>
                      <td><?= e((string)$player['age']) ?></td>
                      <td><?= e((string)$player['weight_kg']) ?></td>
                      <td><?= e((string)$player['age_category']) ?></td>
                      <td><?= e((string)$player['weight_category']) ?></td>
                      <td><?= e((string)$player['belt_rank']) ?></td>
                      <td>
                        <span class="status-chip <?= $player['status'] === 'Active' ? 'status-active' : ($player['status'] === 'Banned' ? 'status-banned' : 'status-deleted') ?>">
                          <?= e((string)$player['status']) ?>
                        </span>
                      </td>
                      <td><?= e((string)$player['contact_number']) ?></td>
                      <td style="min-width:260px;">
                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="action" value="update_player_basic">
                          <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                          <div style="display:grid;gap:8px;">
                            <input type="number" step="0.01" name="weight_kg" value="<?= e((string)$player['weight_kg']) ?>" placeholder="Weight">
                            <select name="belt_rank">
                              <?php foreach (allBelts() as $belt): ?>
                                <option value="<?= e($belt) ?>" <?= (string)$player['belt_rank'] === $belt ? 'selected' : '' ?>><?= e($belt) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <input type="text" name="contact_number" value="<?= e((string)$player['contact_number']) ?>" placeholder="Contact">
                            <button class="btn btn-success" type="submit">Update</button>
                          </div>
                        </form>
                      </td>
                      <td style="min-width:260px;">
                        <form method="post" style="margin-bottom:10px;">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="action" value="delete_request">
                          <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                          <textarea name="reason" placeholder="Reason to delete" required></textarea>
                          <button class="btn btn-danger" type="submit" style="margin-top:8px;width:100%;">Delete Request</button>
                        </form>
                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="action" value="ban_request">
                          <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                          <textarea name="reason" placeholder="Reason to ban" required></textarea>
                          <button class="btn btn-secondary" type="submit" style="margin-top:8px;width:100%;">Ban Player</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'gradingSection' ? 'active' : '' ?>">
        <h2>Grading</h2>
        <p class="section-desc">Enter grading marks, calculate pass/fail, and generate promotion results. Color belts use 100 marks. Advanced belts use 700 marks.</p>

        <div class="mini-card">
          <h3>Grade Player</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="grade_player">

            <div class="form-grid">
              <div class="form-group full">
                <label>Select Player</label>
                <select name="player_id" required>
                  <option value="">Select active player</option>
                  <?php foreach ($players as $player): ?>
                    <?php if (($player['status'] ?? '') === 'Active'): ?>
                      <option value="<?= (int)$player['id'] ?>">
                        <?= e((string)$player['full_name']) ?> - <?= e((string)$player['belt_rank']) ?>
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group"><label>Basic</label><input type="number" step="0.01" name="marks_basic" value="0"></div>
              <div class="form-group"><label>Kicking</label><input type="number" step="0.01" name="marks_kicking" value="0"></div>
              <div class="form-group"><label>Poomsae</label><input type="number" step="0.01" name="marks_poomsae" value="0"></div>
              <div class="form-group"><label>Breaking</label><input type="number" step="0.01" name="marks_breaking" value="0"></div>
              <div class="form-group"><label>Sparring</label><input type="number" step="0.01" name="marks_sparring" value="0"></div>
              <div class="form-group"><label>Self Defence</label><input type="number" step="0.01" name="marks_self_defence" value="0"></div>
              <div class="form-group"><label>One Step Sparring</label><input type="number" step="0.01" name="marks_one_step" value="0"></div>
              <div class="form-group"><label>Flying Kick</label><input type="number" step="0.01" name="marks_flying_kick" value="0"></div>
              <div class="form-group"><label>Punch</label><input type="number" step="0.01" name="marks_punch" value="0"></div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Save Grading Result</button>
            </div>
          </form>

          <div class="result-box">Color Belt Grading Guide:
Basic 25, Kicking 25, Poomsae 30, Breaking 10, Sparring 10 = 100
Pass Marks: 50
80 and above = Double Belt Promotion

Advanced Belt Guide:
Basic 100, Kicking 100, Poomsae 100, Self Defence 100, One Step 100, Breaking 100, Flying Kick + Punch 100 = 700</div>
        </div>

        <div class="mini-card">
          <h3>Recent Grading Results</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Player ID</th>
                  <th>Previous Belt</th>
                  <th>New Belt</th>
                  <th>Total</th>
                  <th>Result</th>
                  <th>Promotion</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$gradingHistory): ?>
                  <tr><td colspan="7">No grading result recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($gradingHistory as $g): ?>
                    <tr>
                      <td><?= e((string)$g['created_at']) ?></td>
                      <td><?= e((string)$g['player_id']) ?></td>
                      <td><?= e((string)$g['previous_belt']) ?></td>
                      <td><?= e((string)$g['new_belt']) ?></td>
                      <td><?= e((string)$g['total_marks']) ?></td>
                      <td><?= e((string)$g['result_status']) ?></td>
                      <td><?= e((string)$g['promotion_type']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'tournamentSection' ? 'active' : '' ?>">
        <h2>Tournament Hosting</h2>
        <p class="section-desc">Apply to host a tournament. Admin will review and verify or reject your request.</p>

        <div class="mini-card">
          <h3>Host Tournament Application</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="host_tournament">

            <div class="form-grid">
              <div class="form-group"><label>Tournament Name</label><input type="text" name="tournament_name" required></div>
              <div class="form-group"><label>Number of Arenas</label><input type="number" name="arena_count" min="1" required></div>
              <div class="form-group">
                <label>Event Mode</label>
                <select name="event_mode" required>
                  <option value="">Select mode</option>
                  <option value="Poomsae">Poomsae</option>
                  <option value="Kyorugi">Kyorugi</option>
                  <option value="Both">Both Poomsae and Kyorugi</option>
                </select>
              </div>
              <div class="form-group"><label>Entry Fee Poomsae</label><input type="number" step="0.01" name="entry_fee_poomsae"></div>
              <div class="form-group"><label>Entry Fee Kyorugi</label><input type="number" step="0.01" name="entry_fee_kyorugi"></div>
              <div class="form-group"><label>Both Event Discounted Fee</label><input type="number" step="0.01" name="entry_fee_both_discount"></div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Submit Tournament Request</button>
            </div>
          </form>
        </div>

        <div class="mini-card">
          <h3>My Tournament Applications</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament</th>
                  <th>Arenas</th>
                  <th>Mode</th>
                  <th>Poomsae Fee</th>
                  <th>Kyorugi Fee</th>
                  <th>Both Fee</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$coachTournaments): ?>
                  <tr><td colspan="8">No tournament application submitted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($coachTournaments as $t): ?>
                    <tr>
                      <td><?= e((string)$t['tournament_name']) ?></td>
                      <td><?= e((string)$t['arena_count']) ?></td>
                      <td><?= e((string)$t['event_scope']) ?></td>
                      <td><?= e((string)$t['entry_fee_poomsae']) ?></td>
                      <td><?= e((string)$t['entry_fee_kyorugi']) ?></td>
                      <td><?= e((string)$t['entry_fee_both_discount']) ?></td>
                      <td><span class="status-chip <?= $t['status'] === 'Pending' ? 'status-pending' : ($t['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>"><?= e((string)$t['status']) ?></span></td>
                      <td><?= e((string)$t['remarks']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'noticesSection' ? 'active' : '' ?>">
        <h2>Player Notices</h2>
        <p class="section-desc">Publish notices to your players and review system notices from admin.</p>

        <div class="card-grid">
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

          <div class="mini-card">
            <h3>Latest Player Notices</h3>
            <?php if (!$coachNotices): ?>
              <div class="notice-card"><h4>No notices yet</h4><p>No coach notice has been published yet.</p></div>
            <?php else: ?>
              <?php foreach ($coachNotices as $n): ?>
                <div class="notice-card">
                  <h4><?= e((string)$n['title']) ?></h4>
                  <p><?= nl2br(e((string)$n['message'])) ?></p>
                  <p style="margin-top:10px;color:var(--soft);font-size:.88rem;">Published: <?= e((string)$n['created_at']) ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="mini-card">
          <h3>Admin Notices</h3>
          <?php if (!$adminNotices): ?>
            <div class="notice-card"><h4>No admin notices</h4><p>No global or coach notice found.</p></div>
          <?php else: ?>
            <?php foreach ($adminNotices as $n): ?>
              <div class="notice-card">
                <h4><?= e((string)$n['title']) ?></h4>
                <p><?= nl2br(e((string)$n['message'])) ?></p>
                <p style="margin-top:10px;color:var(--soft);font-size:.88rem;">Published: <?= e((string)$n['created_at']) ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'resourcesSection' ? 'active' : '' ?>">
        <h2>Coach Resources</h2>
        <p class="section-desc">Open official taekwondo information, coaching resources, and systems.</p>

        <div class="link-grid">
          <?php foreach ($resourceLinks as $link): ?>
            <a class="link-btn" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= e($link['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'idCardSection' ? 'active' : '' ?>">
        <h2>Player Card</h2>
        <p class="section-desc">Select a player and print a stylish athlete ID card.</p>

        <form method="get" class="form-grid">
          <input type="hidden" name="section" value="idCardSection">
          <div class="form-group">
            <label>Select Player</label>
            <select name="player" onchange="this.form.submit()">
              <option value="">Select player</option>
              <?php foreach ($players as $player): ?>
                <option value="<?= (int)$player['id'] ?>" <?= $selectedPlayerId === (int)$player['id'] ? 'selected' : '' ?>>
                  <?= e((string)$player['full_name']) ?> - <?= e((string)$player['player_code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($selectedPlayer): ?>
          <div class="id-preview-wrap">
            <div class="id-card" id="printCard">
              <div class="id-top">
                <div class="id-logo">International Athlete Card</div>
                <div class="id-badge"><?= e((string)$selectedPlayer['player_code']) ?></div>
              </div>

              <div class="id-name"><?= e((string)$selectedPlayer['full_name']) ?></div>

              <div class="id-grid">
                <div class="id-item"><h5>Date of Birth</h5><p><?= e((string)$selectedPlayer['dob']) ?></p></div>
                <div class="id-item"><h5>Belt Rank</h5><p><?= e((string)$selectedPlayer['belt_rank']) ?></p></div>
                <div class="id-item"><h5>Club Name</h5><p><?= e((string)$selectedPlayer['club_name']) ?></p></div>
                <div class="id-item"><h5>Country</h5><p><?= e((string)$selectedPlayer['country_name']) ?></p></div>
                <div class="id-item"><h5>Contact Number</h5><p><?= e((string)$selectedPlayer['contact_number']) ?></p></div>
                <div class="id-item"><h5>Club Address</h5><p><?= e((string)$selectedPlayer['club_address']) ?></p></div>
              </div>
            </div>
          </div>

          <div class="print-actions">
            <button class="btn btn-primary" type="button" onclick="window.print()">Print Athlete Card</button>
          </div>
        <?php else: ?>
          <div class="result-box">Select a player to preview and print the athlete card.</div>
        <?php endif; ?>
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