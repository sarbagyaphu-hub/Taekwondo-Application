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
| TABLES
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
        weight_category VARCHAR(100) NULL,
        age_category VARCHAR(100) NULL,
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
        status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS notices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        audience ENUM('All','Coaches','Players','Referees') NOT NULL DEFAULT 'All',
        created_by_admin_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_notice_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS tournaments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_name VARCHAR(255) NOT NULL,
        host_club VARCHAR(190) NOT NULL,
        host_coach VARCHAR(190) NOT NULL,
        arena_count INT NOT NULL DEFAULT 1,
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS tournament_applicants (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        applicant_name VARCHAR(255) NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        weight_category VARCHAR(100) NULL,
        age_category VARCHAR(100) NULL,
        club_name VARCHAR(190) NULL,
        status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_applicant_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS arena_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        arena_name VARCHAR(30) NOT NULL,
        referee_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_assignment_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        CONSTRAINT fk_assignment_referee FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

/*
|--------------------------------------------------------------------------
| DEFAULT DATA
|--------------------------------------------------------------------------
*/
function rowExists(PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1");
    $stmt->execute([$value]);
    return (bool)$stmt->fetchColumn();
}

if (!rowExists($pdo, 'admins', 'email', DEFAULT_ADMIN_EMAIL)) {
    $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
    $stmt->execute([DEFAULT_ADMIN_EMAIL, password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
}

$coachCount = (int)$pdo->query("SELECT COUNT(*) FROM coaches")->fetchColumn();
if ($coachCount === 0) {
    $passwordHash = password_hash('Coach@123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO coaches
        (registration_type, institution_name, coach_name, dob, dan_certificate_number, association_registered_number, email, password_hash, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['Club', 'Tiger Dojang', 'Ram Bahadur', '1988-02-10', 'DAN-1001', 'ASSOC-001', 'ram@demo.com', $passwordHash, 'Pending', null]);
    $stmt->execute(['School', 'Sunrise School', 'Sita Karki', '1990-05-15', 'DAN-1002', 'ASSOC-002', 'sita@demo.com', $passwordHash, 'Pending', null]);
    $stmt->execute(['Club', 'Everest TKD Club', 'Hari Gautam', '1987-09-20', 'DAN-1003', 'ASSOC-003', 'hari@demo.com', $passwordHash, 'Pending', null]);
}

$playerCount = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
if ($playerCount === 0) {
    $players = [
        ['P001','Aarav Shrestha',15,48,'Male -58kg','Juniors','Blue','Nepal','Tiger Dojang','9800000001','player1@nta.com',4,2,1,18],
        ['P002','Sujal Karki',16,50,'Male -58kg','Juniors','Green','Nepal','Everest TKD Club','9800000002','player2@nta.com',2,1,0,11],
        ['P003','Anish Rai',20,67,'Male -68kg','Adults','Red','Nepal','Dragon Taekwondo','9800000003','player3@nta.com',5,2,1,22],
        ['P004','Rabin Thapa',21,79,'Male -80kg','Adults','Half Black','Nepal','Tiger Dojang','9800000004','player4@nta.com',3,1,2,17],
        ['P005','Pratiksha Lama',13,46,'Female -49kg','Cadets','Yellow','Nepal','Himalayan Club','9800000005','player5@nta.com',1,2,3,12],
        ['P006','Sanjana Gurung',16,56,'Female -57kg','Juniors','1 Dan','Nepal','Everest TKD Club','9800000006','player6@nta.com',6,0,1,24],
        ['P007','Riya Tamang',19,63,'Female -67kg','Adults','Blue','Nepal','Kathmandu Warriors','9800000007','player7@nta.com',2,2,2,15],
        ['P008','Nima Sherpa',35,68,'Female +67kg','Veterans','Black','Nepal','Mountain Poomsae','9800000008','player8@nta.com',4,1,1,20]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO players
        (player_code, full_name, age, weight_kg, weight_category, age_category, belt_rank, country_name, club_name, contact_number, email, password_hash, gold_last_90_days, silver_count, bronze_count, participated_games)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($players as $p) {
        $stmt->execute([
            $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10],
            password_hash('Player@123', PASSWORD_DEFAULT),
            $p[11], $p[12], $p[13], $p[14]
        ]);
    }
}

$refCount = (int)$pdo->query("SELECT COUNT(*) FROM referees")->fetchColumn();
if ($refCount === 0) {
    $refs = [
        ['Referee','One','REF001','National','ref1@nta.com'],
        ['Referee','Two','REF002','National','ref2@nta.com'],
        ['Referee','Three','REF003','District','ref3@nta.com'],
        ['Referee','Four','REF004','Provincial','ref4@nta.com'],
        ['Referee','Five','REF005','National','ref5@nta.com'],
        ['Referee','Six','REF006','International','ref6@nta.com'],
        ['Referee','Seven','REF007','District','ref7@nta.com'],
        ['Referee','Eight','REF008','Provincial','ref8@nta.com'],
        ['Referee','Nine','REF009','National','ref9@nta.com'],
        ['Referee','Ten','REF010','National','ref10@nta.com'],
        ['Referee','Eleven','REF011','District','ref11@nta.com'],
        ['Referee','Twelve','REF012','International','ref12@nta.com'],
        ['Referee','Thirteen','REF013','Provincial','ref13@nta.com'],
        ['Referee','Fourteen','REF014','National','ref14@nta.com'],
        ['Referee','Fifteen','REF015','District','ref15@nta.com'],
        ['Referee','Sixteen','REF016','International','ref16@nta.com']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO referees (first_name, last_name, referee_code, level, email, password_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($refs as $r) {
        $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], password_hash(DEFAULT_REFEREE_PASSWORD, PASSWORD_DEFAULT)]);
    }
}

$tournamentCount = (int)$pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
if ($tournamentCount === 0) {
    $stmt = $pdo->prepare("
        INSERT INTO tournaments (tournament_name, host_club, host_coach, arena_count, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['Kathmandu Open Championship', 'Tiger Dojang', 'Ram Bahadur', 2, 'Pending', null]);
    $stmt->execute(['National Poomsae Cup', 'Everest TKD Club', 'Sita Karki', 3, 'Pending', null]);
}

$applicantCount = (int)$pdo->query("SELECT COUNT(*) FROM tournament_applicants")->fetchColumn();
if ($applicantCount === 0) {
    $tournaments = $pdo->query("SELECT id, tournament_name FROM tournaments")->fetchAll();
    $map = [];
    foreach ($tournaments as $t) {
        $map[$t['tournament_name']] = (int)$t['id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO tournament_applicants
        (tournament_id, applicant_name, event_type, weight_category, age_category, club_name, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (isset($map['Kathmandu Open Championship'])) {
        $tid = $map['Kathmandu Open Championship'];
        $stmt->execute([$tid, 'Aarav Shrestha', 'Kyorugi', 'Male -58kg', 'Juniors', 'Tiger Dojang', 'Pending']);
        $stmt->execute([$tid, 'Sujal Karki', 'Kyorugi', 'Male -58kg', 'Juniors', 'Everest TKD Club', 'Pending']);
        $stmt->execute([$tid, 'Anish Rai', 'Kyorugi', 'Male -68kg', 'Adults', 'Dragon Taekwondo', 'Pending']);
        $stmt->execute([$tid, 'Sanjana Gurung', 'Poomsae Individual', null, 'Juniors', 'Everest TKD Club', 'Pending']);
        $stmt->execute([$tid, 'Riya Tamang / Kripa Shah', 'Poomsae Pair', null, 'Adults', 'Kathmandu Warriors', 'Pending']);
        $stmt->execute([$tid, 'Young Tigers Team A', 'Poomsae Group', null, 'Cadets', 'Young Tigers', 'Pending']);
    }

    if (isset($map['National Poomsae Cup'])) {
        $tid = $map['National Poomsae Cup'];
        $stmt->execute([$tid, 'Nima Sherpa', 'Poomsae Individual', null, 'Veterans', 'Mountain Poomsae', 'Pending']);
        $stmt->execute([$tid, 'Pair Everest A', 'Poomsae Pair', null, 'Juniors', 'Everest TKD Club', 'Pending']);
        $stmt->execute([$tid, 'Group Tiger Elite', 'Poomsae Group', null, 'Adults', 'Tiger Dojang', 'Pending']);
    }
}

/*
|--------------------------------------------------------------------------
| ADMIN SESSION CHECK
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
            $row = $stmt->fetch();

            if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
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

            $stmt = $pdo->prepare("UPDATE coaches SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$status, $remarks ?: ($status === 'Verified' ? 'Application verified by admin.' : 'Application rejected by admin.'), $coachId]);

            setFlash('success', "Coach application {$status} successfully.");
            redirect('admin.php?section=coachSection');
        }

        if ($action === 'publish_notice') {
            $title = trim($_POST['notice_title'] ?? '');
            $message = trim($_POST['notice_message'] ?? '');

            if ($title === '' || $message === '') {
                throw new RuntimeException('Notice title and message are required.');
            }

            $stmt = $pdo->prepare("INSERT INTO notices (title, message, audience, created_by_admin_id) VALUES (?, ?, 'All', ?)");
            $stmt->execute([$title, $message, $currentAdminId]);

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
                INSERT INTO referees (first_name, last_name, referee_code, level, email, password_hash)
                VALUES (?, ?, ?, ?, ?, ?)
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
                throw new RuntimeException('Invalid OCR format. Each referee needs 5 rows.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO referees (first_name, last_name, referee_code, level, email, password_hash)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $created = 0;
            for ($i = 0; $i < count($clean); $i += 5) {
                $chunk = array_slice($clean, $i, 5);
                if (count($chunk) !== 5) {
                    continue;
                }
                [$firstName, $lastName, $refCode, $level, $email] = $chunk;
                $stmt->execute([$firstName, $lastName, $refCode, $level, $email, password_hash(DEFAULT_REFEREE_PASSWORD, PASSWORD_DEFAULT)]);
                $created++;
            }

            setFlash('success', "Bulk referee creation completed. {$created} referee record(s) inserted.");
            redirect('admin.php?section=refereeSection');
        }

        if ($action === 'verify_tournament' || $action === 'reject_tournament') {
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $action === 'verify_tournament' ? 'Verified' : 'Rejected';

            $stmt = $pdo->prepare("UPDATE tournaments SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$status, $remarks ?: ($status === 'Verified' ? 'Tournament verified by admin.' : 'Tournament rejected by admin.'), $tournamentId]);

            setFlash('success', "Tournament {$status} successfully.");
            redirect('admin.php?section=tournamentSection');
        }

        if ($action === 'verify_applicant' || $action === 'reject_applicant') {
            $applicantId = (int)($_POST['applicant_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $status = $action === 'verify_applicant' ? 'Verified' : 'Rejected';
            $selectedTournamentId = (int)($_POST['selected_tournament_id'] ?? 0);

            $stmt = $pdo->prepare("UPDATE tournament_applicants SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$status, $remarks ?: ($status === 'Verified' ? 'Applicant verified by admin.' : 'Applicant rejected by admin.'), $applicantId]);

            redirect('admin.php?section=tournamentSection&tournament=' . $selectedTournamentId);
        }

        if ($action === 'assign_referees') {
            $tournamentId = (int)($_POST['assignment_tournament_id'] ?? 0);
            $selectedReferees = $_POST['referee_ids'] ?? [];

            if ($tournamentId <= 0) {
                throw new RuntimeException('Please select a verified tournament.');
            }
            if (!is_array($selectedReferees) || count($selectedReferees) === 0) {
                throw new RuntimeException('Please select referees first.');
            }

            $stmt = $pdo->prepare("SELECT arena_count, status FROM tournaments WHERE id = ? LIMIT 1");
            $stmt->execute([$tournamentId]);
            $tournament = $stmt->fetch();

            if (!$tournament || $tournament['status'] !== 'Verified') {
                throw new RuntimeException('Tournament must be verified before assigning referees.');
            }

            $arenaCount = (int)$tournament['arena_count'];
            $required = $arenaCount * 8;
            $selectedReferees = array_map('intval', $selectedReferees);

            if (count($selectedReferees) < $required) {
                throw new RuntimeException("Not enough referees selected. {$arenaCount} arenas need {$required} referees.");
            }

            shuffle($selectedReferees);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM arena_assignments WHERE tournament_id = ?");
            $stmt->execute([$tournamentId]);

            $insert = $pdo->prepare("INSERT INTO arena_assignments (tournament_id, arena_name, referee_id) VALUES (?, ?, ?)");
            $index = 0;

            for ($i = 0; $i < $arenaCount; $i++) {
                $arenaName = 'Arena ' . chr(65 + $i);
                for ($j = 0; $j < 8; $j++) {
                    $insert->execute([$tournamentId, $arenaName, $selectedReferees[$index]]);
                    $index++;
                }
            }

            $pdo->commit();
            setFlash('success', 'Referees assigned successfully.');
            redirect('admin.php?section=tournamentSection&tournament=' . $tournamentId);
        }

        if ($action === 'generate_tiesheet') {
            $_SESSION['tiesheet_request'] = [
                'tournament_id' => (int)($_POST['tiesheet_tournament_id'] ?? 0),
                'event_type' => trim($_POST['event_type'] ?? '')
            ];
            redirect('admin.php?section=tournamentSection&tournament=' . (int)($_POST['tiesheet_tournament_id'] ?? 0));
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
| SIMPLE COLUMN MIGRATIONS FOR OLD TABLES
|--------------------------------------------------------------------------
*/
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

if (!columnExists($pdo, 'players', 'status')) {
    $pdo->exec("
        ALTER TABLE players
        ADD COLUMN status ENUM('Active','Banned','Deleted') NOT NULL DEFAULT 'Active'
        AFTER participated_games
    ");
}

if (!columnExists($pdo, 'players', 'weight_category')) {
    $pdo->exec("
        ALTER TABLE players
        ADD COLUMN weight_category VARCHAR(100) NULL
        AFTER weight_kg
    ");
}

if (!columnExists($pdo, 'players', 'age_category')) {
    $pdo->exec("
        ALTER TABLE players
        ADD COLUMN age_category VARCHAR(100) NULL
        AFTER weight_category
    ");
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
$totalClubs = (int)$pdo->query("SELECT COUNT(DISTINCT institution_name) FROM coaches")->fetchColumn();
$pendingApplications = (int)$pdo->query("SELECT COUNT(*) FROM coaches WHERE status = 'Pending'")->fetchColumn();

$selectedWeight = trim($_GET['weight'] ?? '');
$selectedAge = trim($_GET['age'] ?? '');

$where = ["status = 'Active'"];
$params = [];

if ($selectedWeight !== '') {
    $where[] = "weight_category = ?";
    $params[] = $selectedWeight;
}
if ($selectedAge !== '') {
    $where[] = "age_category = ?";
    $params[] = $selectedAge;
}

$sqlPlayers = "
    SELECT full_name, weight_category, age_category, club_name, gold_last_90_days
    FROM players
    WHERE " . implode(' AND ', $where) . "
    ORDER BY gold_last_90_days DESC, full_name ASC
";
$stmt = $pdo->prepare($sqlPlayers);
$stmt->execute($params);
$players = $stmt->fetchAll();

$coaches = $pdo->query("
    SELECT id, coach_name, registration_type, institution_name, email, status, remarks
    FROM coaches
    ORDER BY created_at DESC
")->fetchAll();

$recentNotices = $pdo->query("
    SELECT title, message, created_at
    FROM notices
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

$tournaments = $pdo->query("
    SELECT id, tournament_name, host_club, host_coach, arena_count, status, remarks
    FROM tournaments
    ORDER BY created_at DESC
")->fetchAll();

$verifiedTournaments = $pdo->query("
    SELECT id, tournament_name, arena_count
    FROM tournaments
    WHERE status = 'Verified'
    ORDER BY tournament_name ASC
")->fetchAll();

$tournamentApplicants = [];
if ($selectedTournamentId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, applicant_name, event_type, weight_category, age_category, club_name, status, remarks
        FROM tournament_applicants
        WHERE tournament_id = ?
        ORDER BY event_type, applicant_name
    ");
    $stmt->execute([$selectedTournamentId]);
    $tournamentApplicants = $stmt->fetchAll();
}

$referees = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, referee_code, level
    FROM referees
    ORDER BY full_name ASC
")->fetchAll();

$assignmentPreview = [];
if ($selectedTournamentId > 0) {
    $stmt = $pdo->prepare("
        SELECT aa.arena_name, CONCAT(r.first_name, ' ', r.last_name) AS referee_name, r.referee_code, r.level
        FROM arena_assignments aa
        INNER JOIN referees r ON r.id = aa.referee_id
        WHERE aa.tournament_id = ?
        ORDER BY aa.arena_name, aa.id
    ");
    $stmt->execute([$selectedTournamentId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $assignmentPreview[$row['arena_name']][] = $row;
    }
}

$tiesheetText = '';
if (!empty($_SESSION['tiesheet_request'])) {
    $req = $_SESSION['tiesheet_request'];
    if ((int)$req['tournament_id'] === $selectedTournamentId && !empty($req['event_type'])) {
        $eventType = $req['event_type'];

        $stmt = $pdo->prepare("
            SELECT t.tournament_name, ta.applicant_name, ta.event_type, ta.weight_category, ta.age_category, ta.club_name
            FROM tournament_applicants ta
            INNER JOIN tournaments t ON t.id = ta.tournament_id
            WHERE ta.tournament_id = ? AND ta.status = 'Verified' AND ta.event_type = ?
            ORDER BY ta.age_category, ta.weight_category, ta.applicant_name
        ");
        $stmt->execute([$selectedTournamentId, $eventType]);
        $apps = $stmt->fetchAll();

        if ($apps) {
            $tiesheetText .= "Tiesheet for " . $apps[0]['tournament_name'] . "\n";
            $tiesheetText .= "Event: {$eventType}\n\n";

            if ($eventType === 'Kyorugi') {
                $grouped = [];
                foreach ($apps as $app) {
                    $key = ($app['age_category'] ?: '-') . ' | ' . ($app['weight_category'] ?: '-');
                    $grouped[$key][] = $app;
                }

                $groupIndex = 1;
                foreach ($grouped as $groupName => $members) {
                    $tiesheetText .= "Group {$groupIndex}: {$groupName}\n";
                    foreach ($members as $i => $member) {
                        $tiesheetText .= ($i + 1) . ". {$member['applicant_name']} - {$member['club_name']}\n";
                    }
                    $tiesheetText .= "\n";
                    $groupIndex++;
                }
            } else {
                $grouped = [];
                foreach ($apps as $app) {
                    $key = $app['age_category'] ?: '-';
                    $grouped[$key][] = $app;
                }

                $poolIndex = 1;
                foreach ($grouped as $poolName => $members) {
                    $prefix = $eventType === 'Poomsae Pair' ? 'Pair Pool' : ($eventType === 'Poomsae Group' ? 'Group Pool' : 'Pool');
                    $tiesheetText .= "{$prefix} {$poolIndex}: {$poolName}\n";
                    foreach ($members as $i => $member) {
                        $tiesheetText .= ($i + 1) . ". {$member['applicant_name']} - {$member['club_name']}\n";
                    }
                    $tiesheetText .= "\n";
                    $poolIndex++;
                }
            }
        } else {
            $tiesheetText = 'No verified applicants found for the selected event type.';
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
    .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px;}
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
    .filters,.form-grid,.button-row{display:grid;gap:14px;}
    .filters{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px;}
    .form-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px;}
    .form-group{display:grid;gap:8px;}
    .form-group.full{grid-column:1 / -1;}
    label{font-weight:bold;font-size:.95rem;}
    input,select,textarea{
      width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);
      background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;
    }
    textarea{min-height:120px;resize:vertical;padding-top:12px;}
    .button-row{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:8px;}
    .btn{
      min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;
      transition:.25s ease;color:var(--white);
    }
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue));}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .btn-danger{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}
    .mini-card{padding:16px;margin-bottom:18px;}
    .mini-card h3{margin-bottom:8px;}
    .mini-card p{color:var(--soft);line-height:1.5;margin-bottom:14px;}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border);}
    table{width:100%;border-collapse:collapse;min-width:760px;background:rgba(255,255,255,.04);}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top;}
    th{background:rgba(255,255,255,.06);font-size:.95rem;}
    td{font-size:.94rem;line-height:1.5;}
    .rank-badge{
      display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(245,158,11,.18);
      border:1px solid rgba(245,158,11,.3);color:#ffe7b0;font-weight:bold;font-size:.82rem;
    }
    .status-chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:bold;}
    .status-pending{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.3);color:#ffe7b0;}
    .status-verified{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
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
    .assignment-block h4{margin-bottom:10px;}
    .assignment-block ul{padding-left:18px;color:var(--soft);line-height:1.8;}
    @media (max-width:1100px){
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
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
          <p>Manage players, coaches, clubs, referees, notices, and tournaments from one central panel.</p>
        </div>
        <div class="admin-badge">Logged in as <?= e($currentAdmin['email']) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <h3><?= e((string)$totalPlayers) ?></h3>
          <p>Total Registered Players</p>
        </div>
        <div class="stat-card">
          <h3><?= e((string)$totalCoaches) ?></h3>
          <p>Total Registered Coaches</p>
        </div>
        <div class="stat-card">
          <h3><?= e((string)$totalClubs) ?></h3>
          <p>Total Registered Clubs</p>
        </div>
        <div class="stat-card">
          <h3><?= e((string)$pendingApplications) ?></h3>
          <p>Pending Coach Applications</p>
        </div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Overview</h2>
        <p class="section-desc">This admin page is connected with PHP and MySQL and is ready for real data handling.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Coach Management</h3>
            <p>Review coach applications, verify or reject with remarks, and maintain application flow.</p>
            <a class="btn btn-primary" href="admin.php?section=coachSection" style="display:inline-block;text-decoration:none;">Open Coach Applications</a>
          </div>

          <div class="mini-card">
            <h3>Player Data Ranking</h3>
            <p>Filter players by Olympic weight category and age category, then rank them by gold medals from the last 90 days.</p>
            <a class="btn btn-primary" href="admin.php?section=playerSection" style="display:inline-block;text-decoration:none;">Open Player Data</a>
          </div>

          <div class="mini-card">
            <h3>Referee ID Generation</h3>
            <p>Create referee IDs one by one or generate many at once using OCR-style bulk text input.</p>
            <a class="btn btn-primary" href="admin.php?section=refereeSection" style="display:inline-block;text-decoration:none;">Open Referee IDs</a>
          </div>

          <div class="mini-card">
            <h3>Admin Account Security</h3>
            <p>Change the default admin password and manage secure login credentials.</p>
            <a class="btn btn-primary" href="admin.php?section=accountSection" style="display:inline-block;text-decoration:none;">Open Admin Account</a>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'playerSection' ? 'active' : '' ?>">
        <h2>Player Data and Ranking</h2>
        <p class="section-desc">Select Olympic weight category and age category. Ranking is shown according to highest gold medals achieved from the last 90 days.</p>

        <form method="get" class="filters">
          <input type="hidden" name="section" value="playerSection">

          <div class="form-group">
            <label>Olympic Weight Category</label>
            <select name="weight">
              <option value="">Select Olympic Weight Category</option>
              <?php foreach (['Male -58kg','Male -68kg','Male -80kg','Male +80kg','Female -49kg','Female -57kg','Female -67kg','Female +67kg'] as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $selectedWeight === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Age Category</label>
            <select name="age">
              <option value="">Select Age Category</option>
              <?php foreach (['Children','Cadets','Juniors','Adults','Veterans'] as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $selectedAge === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">Show Ranked Players</button>
          </div>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Rank</th>
                <th>Player Name</th>
                <th>Weight Category</th>
                <th>Age Category</th>
                <th>Club</th>
                <th>Gold Medals (Last 90 Days)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$players): ?>
                <tr><td colspan="6">No player found for selected Olympic weight category and age category.</td></tr>
              <?php else: ?>
                <?php foreach ($players as $index => $player): ?>
                  <tr>
                    <td><span class="rank-badge">#<?= $index + 1 ?></span></td>
                    <td><?= e($player['full_name']) ?></td>
                    <td><?= e((string)$player['weight_category']) ?></td>
                    <td><?= e((string)$player['age_category']) ?></td>
                    <td><?= e((string)$player['club_name']) ?></td>
                    <td><?= e((string)$player['gold_last_90_days']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section <?= $activeSection === 'refereeSection' ? 'active' : '' ?>">
        <h2>Create Referee IDs</h2>
        <p class="section-desc">Create referee IDs one by one or in bulk. Default password for every created referee is <strong>Referee@123</strong>.</p>

        <div class="card-grid">
          <div class="mini-card">
            <h3>Create One by One</h3>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="create_single_referee">

              <div class="form-grid">
                <div class="form-group">
                  <label>First Name</label>
                  <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                  <label>Last Name</label>
                  <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                  <label>Referee ID</label>
                  <input type="text" name="referee_code" required>
                </div>
                <div class="form-group">
                  <label>Referee Level</label>
                  <select name="level" required>
                    <option value="">Select referee level</option>
                    <option value="District">District</option>
                    <option value="Provincial">Provincial</option>
                    <option value="National">National</option>
                    <option value="International">International</option>
                  </select>
                </div>
                <div class="form-group full">
                  <label>Email Address</label>
                  <input type="email" name="email" required>
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Create Referee ID</button>
              </div>
            </form>
          </div>

          <div class="mini-card">
            <h3>Bulk Create from OCR Text</h3>
            <p>Paste OCR text in this format for each referee:
              <br>1st row: First Name
              <br>2nd row: Last Name
              <br>3rd row: Referee ID
              <br>4th row: Referee Level
              <br>5th row: Email Address
            </p>

            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="create_bulk_referees">

              <div class="form-group">
                <label>OCR Text</label>
                <textarea name="bulk_text" required></textarea>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Create Bulk Referee IDs</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'coachSection' ? 'active' : '' ?>">
        <h2>Coach Applications</h2>
        <p class="section-desc">Admin can review coach registration applications, verify or reject them, and add remarks for each decision.</p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Coach Name</th>
                <th>Institution Type</th>
                <th>Club / School</th>
                <th>Email</th>
                <th>Status</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($coaches as $coach): ?>
                <tr>
                  <td><?= e($coach['coach_name']) ?></td>
                  <td><?= e($coach['registration_type']) ?></td>
                  <td><?= e($coach['institution_name']) ?></td>
                  <td><?= e($coach['email']) ?></td>
                  <td>
                    <span class="status-chip <?= $coach['status'] === 'Pending' ? 'status-pending' : ($coach['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                      <?= e($coach['status']) ?>
                    </span>
                  </td>
                  <td style="min-width:260px;">
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="coach_id" value="<?= (int)$coach['id'] ?>">
                      <textarea name="remarks"><?= e((string)$coach['remarks']) ?></textarea>
                  </td>
                  <td style="min-width:190px;">
                      <div style="display:grid;gap:10px;">
                        <button class="btn btn-success" type="submit" name="action" value="verify_coach">Verify</button>
                        <button class="btn btn-danger" type="submit" name="action" value="reject_coach">Reject</button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section <?= $activeSection === 'tournamentSection' ? 'active' : '' ?>">
        <h2>Tournament Management</h2>
        <p class="section-desc">Manage tournament host applications, verify applicants, assign referees to arenas, and create tiesheets for Kyorugi and Poomsae.</p>

        <div class="mini-card">
          <h3>Tournament Host Applications</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tournament Name</th>
                  <th>Host Club / School</th>
                  <th>Host Coach</th>
                  <th>No. of Arenas</th>
                  <th>Status</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tournaments as $tournament): ?>
                  <tr>
                    <td><?= e($tournament['tournament_name']) ?></td>
                    <td><?= e($tournament['host_club']) ?></td>
                    <td><?= e($tournament['host_coach']) ?></td>
                    <td><?= (int)$tournament['arena_count'] ?></td>
                    <td>
                      <span class="status-chip <?= $tournament['status'] === 'Pending' ? 'status-pending' : ($tournament['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                        <?= e($tournament['status']) ?>
                      </span>
                    </td>
                    <td style="min-width:240px;">
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="tournament_id" value="<?= (int)$tournament['id'] ?>">
                        <textarea name="remarks"><?= e((string)$tournament['remarks']) ?></textarea>
                    </td>
                    <td style="min-width:190px;">
                        <div style="display:grid;gap:10px;">
                          <button class="btn btn-success" type="submit" name="action" value="verify_tournament">Verify</button>
                          <button class="btn btn-danger" type="submit" name="action" value="reject_tournament">Reject</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mini-card">
          <h3>Approved Tournament Applicant Verification</h3>

          <form method="get" class="form-grid">
            <input type="hidden" name="section" value="tournamentSection">
            <div class="form-group">
              <label>Select Approved Tournament</label>
              <select name="tournament" onchange="this.form.submit()">
                <option value="">Select Tournament</option>
                <?php foreach ($verifiedTournaments as $vt): ?>
                  <option value="<?= (int)$vt['id'] ?>" <?= $selectedTournamentId === (int)$vt['id'] ? 'selected' : '' ?>>
                    <?= e($vt['tournament_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Player Name</th>
                  <th>Event Type</th>
                  <th>Weight Category</th>
                  <th>Age Category</th>
                  <th>Club</th>
                  <th>Status</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$selectedTournamentId): ?>
                  <tr><td colspan="8">No tournament selected.</td></tr>
                <?php elseif (!$tournamentApplicants): ?>
                  <tr><td colspan="8">No applicants found for this tournament.</td></tr>
                <?php else: ?>
                  <?php foreach ($tournamentApplicants as $applicant): ?>
                    <tr>
                      <td><?= e($applicant['applicant_name']) ?></td>
                      <td><?= e($applicant['event_type']) ?></td>
                      <td><?= e((string)$applicant['weight_category']) ?></td>
                      <td><?= e((string)$applicant['age_category']) ?></td>
                      <td><?= e((string)$applicant['club_name']) ?></td>
                      <td>
                        <span class="status-chip <?= $applicant['status'] === 'Pending' ? 'status-pending' : ($applicant['status'] === 'Verified' ? 'status-verified' : 'status-rejected') ?>">
                          <?= e($applicant['status']) ?>
                        </span>
                      </td>
                      <td style="min-width:240px;">
                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                          <input type="hidden" name="applicant_id" value="<?= (int)$applicant['id'] ?>">
                          <input type="hidden" name="selected_tournament_id" value="<?= (int)$selectedTournamentId ?>">
                          <textarea name="remarks"><?= e((string)$applicant['remarks']) ?></textarea>
                      </td>
                      <td style="min-width:190px;">
                          <div style="display:grid;gap:10px;">
                            <button class="btn btn-success" type="submit" name="action" value="verify_applicant">Verify</button>
                            <button class="btn btn-danger" type="submit" name="action" value="reject_applicant">Reject</button>
                          </div>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mini-card">
          <h3>Assign Referees to Arenas</h3>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="assign_referees">

            <div class="form-grid">
              <div class="form-group">
                <label>Select Tournament</label>
                <select name="assignment_tournament_id" required>
                  <option value="">Select Tournament</option>
                  <?php foreach ($verifiedTournaments as $vt): ?>
                    <option value="<?= (int)$vt['id'] ?>" <?= $selectedTournamentId === (int)$vt['id'] ? 'selected' : '' ?>>
                      <?= e($vt['tournament_name']) ?> (<?= (int)$vt['arena_count'] ?> arenas)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Choose Referees</label>
                <select name="referee_ids[]" multiple style="min-height:180px;" required>
                  <?php foreach ($referees as $ref): ?>
                    <option value="<?= (int)$ref['id'] ?>">
                      <?= e($ref['full_name']) ?> - <?= e($ref['referee_code']) ?> - <?= e($ref['level']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Assign Referees Randomly</button>
            </div>
          </form>

          <?php if ($assignmentPreview): ?>
            <?php foreach ($assignmentPreview as $arenaName => $members): ?>
              <div class="assignment-block">
                <h4><?= e($arenaName) ?></h4>
                <ul>
                  <?php foreach ($members as $member): ?>
                    <li><?= e($member['referee_name']) ?> | <?= e($member['referee_code']) ?> | <?= e($member['level']) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="mini-card">
          <h3>Tiesheet Creation</h3>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="generate_tiesheet">

            <div class="form-grid">
              <div class="form-group">
                <label>Select Tournament</label>
                <select name="tiesheet_tournament_id" required>
                  <option value="">Select Tournament</option>
                  <?php foreach ($verifiedTournaments as $vt): ?>
                    <option value="<?= (int)$vt['id'] ?>" <?= $selectedTournamentId === (int)$vt['id'] ? 'selected' : '' ?>>
                      <?= e($vt['tournament_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Select Event Type</label>
                <select name="event_type" required>
                  <option value="">Select Event Type</option>
                  <option value="Kyorugi">Kyorugi</option>
                  <option value="Poomsae Individual">Poomsae Individual</option>
                  <option value="Poomsae Pair">Poomsae Pair</option>
                  <option value="Poomsae Group">Poomsae Group</option>
                </select>
              </div>
            </div>

            <div class="button-row">
              <button class="btn btn-primary" type="submit">Create Tiesheet</button>
            </div>
          </form>

          <?php if ($tiesheetText !== ''): ?>
            <div class="result-box"><?= e($tiesheetText) ?></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'noticeSection' ? 'active' : '' ?>">
        <h2>Publish Notice to All</h2>
        <p class="section-desc">Publish notices that will be visible to all users after backend connection.</p>

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

        <div class="notice-list">
          <?php if (!$recentNotices): ?>
            <div class="notice-card"><h4>No notices yet</h4><p>No notice has been published yet.</p></div>
          <?php else: ?>
            <?php foreach ($recentNotices as $notice): ?>
              <div class="notice-card">
                <h4><?= e($notice['title']) ?></h4>
                <p><?= nl2br(e($notice['message'])) ?></p>
                <p style="margin-top:10px;font-size:.88rem;">Published: <?= e($notice['created_at']) ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'accountSection' ? 'active' : '' ?>">
        <h2>Admin Account Settings</h2>
        <p class="section-desc">Default admin account is created automatically. You can change the password here and use the new password from the login page.</p>

        <div class="mini-card">
          <div class="form-grid">
            <div class="form-group full">
              <label>Admin Email</label>
              <input type="email" value="<?= e($currentAdmin['email']) ?>" readonly>
            </div>
          </div>

          <form method="post" style="margin-bottom:18px;">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="change_admin_password">

            <div class="form-grid">
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

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="reset_admin_default">

            <div class="button-row">
              <button class="btn btn-secondary" type="submit">Reset to Default</button>
            </div>
          </form>

          <div class="result-box">
Default admin credentials:
Email: <?= e(DEFAULT_ADMIN_EMAIL) ?>
Password: <?= e(DEFAULT_ADMIN_PASSWORD) ?>
          </div>
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
