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
        redirect('referee.php');
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

function rankInfo(int $games, int $tournaments): array {
    if ($games >= 35) {
        $rank = 'National Referee';
        $next = 'Highest domestic rank achieved';
    } elseif ($games >= 25) {
        $rank = 'Province Referee';
        $next = 'Next rank: National Referee at 35 games';
    } elseif ($games >= 15) {
        $rank = 'District Referee';
        $next = 'Next rank: Province Referee at 25 games';
    } else {
        $rank = 'Fresher';
        $next = 'Next rank: District Referee at 15 games';
    }

    $intl = $tournaments >= 60
        ? 'Eligible for International Referee Course'
        : 'International Course Eligibility at 60 tournaments';

    return [
        'rank' => $rank,
        'next' => $next,
        'international' => $intl
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
| CREATE TABLES IF NOT EXISTS
|--------------------------------------------------------------------------
*/
$schema = [
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
        CONSTRAINT fk_ref_applicant_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS arena_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT UNSIGNED NOT NULL,
        arena_name VARCHAR(30) NOT NULL,
        referee_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_ref_assignment_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        CONSTRAINT fk_ref_assignment_referee FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE
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
        UNIQUE KEY uniq_ref_player_score (referee_id, tournament_id, applicant_id),
        CONSTRAINT fk_score_referee FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE,
        CONSTRAINT fk_score_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        CONSTRAINT fk_score_applicant FOREIGN KEY (applicant_id) REFERENCES tournament_applicants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS referee_tournament_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referee_id INT UNSIGNED NOT NULL,
        tournament_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_ref_tournament (referee_id, tournament_id),
        CONSTRAINT fk_history_referee FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE,
        CONSTRAINT fk_history_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

/*
|--------------------------------------------------------------------------
| COLUMN MIGRATIONS FOR OLD TABLES
|--------------------------------------------------------------------------
*/
if (!columnExists($pdo, 'referees', 'points')) {
    $pdo->exec("ALTER TABLE referees ADD COLUMN points INT NOT NULL DEFAULT 0 AFTER password_hash");
}
if (!columnExists($pdo, 'referees', 'total_games_scored')) {
    $pdo->exec("ALTER TABLE referees ADD COLUMN total_games_scored INT NOT NULL DEFAULT 0 AFTER points");
}
if (!columnExists($pdo, 'referees', 'total_tournaments')) {
    $pdo->exec("ALTER TABLE referees ADD COLUMN total_tournaments INT NOT NULL DEFAULT 0 AFTER total_games_scored");
}
if (!columnExists($pdo, 'referees', 'refresher_days_left')) {
    $pdo->exec("ALTER TABLE referees ADD COLUMN refresher_days_left INT NOT NULL DEFAULT 365 AFTER total_tournaments");
}

/*
|--------------------------------------------------------------------------
| ENSURE DEMO REFEREE EXISTS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT id FROM referees WHERE email = ? LIMIT 1");
$stmt->execute(['referee@nta.com']);
if (!$stmt->fetch()) {
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

/*
|--------------------------------------------------------------------------
| SESSION CHECK
|--------------------------------------------------------------------------
*/
if (($_SESSION['taekwondo_logged_in'] ?? false) !== true || ($_SESSION['taekwondo_role'] ?? '') !== 'Referee') {
    redirect('login.php');
}

$currentRefereeId = (int)($_SESSION['taekwondo_user_id'] ?? 0);
if ($currentRefereeId <= 0) {
    redirect('login.php');
}

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, referee_code, level, email, password_hash, points, total_games_scored, total_tournaments, refresher_days_left
    FROM referees
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$currentRefereeId]);
$currentReferee = $stmt->fetch();

if (!$currentReferee) {
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

        if ($action === 'change_password') {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Please fill all password fields.');
            }

            $stmt = $pdo->prepare("SELECT password_hash FROM referees WHERE id = ? LIMIT 1");
            $stmt->execute([$currentRefereeId]);
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

            $stmt = $pdo->prepare("UPDATE referees SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $currentRefereeId]);

            setFlash('success', 'Password changed successfully.');
            redirect('referee.php?section=accountSection');
        }

        if ($action === 'save_score') {
            $tournamentId = (int)($_POST['tournament_id'] ?? 0);
            $applicantId = (int)($_POST['applicant_id'] ?? 0);
            $arenaName = trim($_POST['arena_name'] ?? '');
            $presentationMinor = (float)($_POST['presentation_minor'] ?? 0);
            $presentationMajor = (float)($_POST['presentation_major'] ?? 0);
            $accuracyMinor = (float)($_POST['accuracy_minor'] ?? 0);
            $accuracyMajor = (float)($_POST['accuracy_major'] ?? 0);

            if ($tournamentId <= 0 || $applicantId <= 0) {
                throw new RuntimeException('Please select a tournament and player first.');
            }

            $stmt = $pdo->prepare("
                SELECT ta.id, ta.applicant_name, ta.event_type, ta.age_category, ta.weight_category,
                       aa.arena_name
                FROM tournament_applicants ta
                LEFT JOIN arena_assignments aa
                  ON aa.tournament_id = ta.tournament_id
                 AND aa.referee_id = ?
                WHERE ta.id = ? AND ta.tournament_id = ? AND ta.status = 'Verified'
                LIMIT 1
            ");
            $stmt->execute([$currentRefereeId, $applicantId, $tournamentId]);
            $applicant = $stmt->fetch();

            if (!$applicant) {
                throw new RuntimeException('Selected applicant is not available for scoring.');
            }

            $presentationBase = 6.00;
            $accuracyBase = 4.00;

            $presentationScore = max(0, $presentationBase - $presentationMinor - $presentationMajor);
            $accuracyScore = max(0, $accuracyBase - $accuracyMinor - $accuracyMajor);
            $finalScore = round($presentationScore + $accuracyScore, 2);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO referee_scores
                (referee_id, tournament_id, arena_name, applicant_id, player_name, event_type, age_category, weight_category,
                 presentation_total, accuracy_total,
                 presentation_minor_deduction, presentation_major_deduction,
                 accuracy_minor_deduction, accuracy_major_deduction, final_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    arena_name = VALUES(arena_name),
                    player_name = VALUES(player_name),
                    event_type = VALUES(event_type),
                    age_category = VALUES(age_category),
                    weight_category = VALUES(weight_category),
                    presentation_total = VALUES(presentation_total),
                    accuracy_total = VALUES(accuracy_total),
                    presentation_minor_deduction = VALUES(presentation_minor_deduction),
                    presentation_major_deduction = VALUES(presentation_major_deduction),
                    accuracy_minor_deduction = VALUES(accuracy_minor_deduction),
                    accuracy_major_deduction = VALUES(accuracy_major_deduction),
                    final_score = VALUES(final_score),
                    scored_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $currentRefereeId,
                $tournamentId,
                $arenaName ?: ($applicant['arena_name'] ?? null),
                $applicantId,
                $applicant['applicant_name'],
                $applicant['event_type'],
                $applicant['age_category'],
                $applicant['weight_category'],
                $presentationScore,
                $accuracyScore,
                $presentationMinor,
                $presentationMajor,
                $accuracyMinor,
                $accuracyMajor,
                $finalScore
            ]);

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO referee_tournament_history (referee_id, tournament_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$currentRefereeId, $tournamentId]);

            $stmt = $pdo->prepare("
                UPDATE referees
                SET total_games_scored = (
                        SELECT COUNT(*) FROM referee_scores WHERE referee_id = ?
                    ),
                    total_tournaments = (
                        SELECT COUNT(*) FROM referee_tournament_history WHERE referee_id = ?
                    ),
                    points = (
                        SELECT COUNT(*) * 10 FROM referee_scores WHERE referee_id = ?
                    )
                WHERE id = ?
            ");
            $stmt->execute([$currentRefereeId, $currentRefereeId, $currentRefereeId, $currentRefereeId]);

            $pdo->commit();

            setFlash('success', 'Final result saved successfully.');
            redirect('referee.php?section=scoringSection&tournament=' . $tournamentId . '&applicant=' . $applicantId);
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
        redirect('referee.php');
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
$selectedApplicantId = (int)($_GET['applicant'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, referee_code, level, email, points, total_games_scored, total_tournaments, refresher_days_left
    FROM referees
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$currentRefereeId]);
$currentReferee = $stmt->fetch();

$totalGames = (int)$currentReferee['total_games_scored'];
$totalTournaments = (int)$currentReferee['total_tournaments'];
$currentPoints = (int)$currentReferee['points'];
$refresherDays = (int)$currentReferee['refresher_days_left'];
$rankData = rankInfo($totalGames, $totalTournaments);

$stmt = $pdo->prepare("
    SELECT rs.final_score, rs.scored_at
    FROM referee_scores rs
    WHERE rs.referee_id = ?
    ORDER BY rs.scored_at ASC
    LIMIT 20
");
$stmt->execute([$currentRefereeId]);
$scoreHistory = $stmt->fetchAll();

/* FIXED QUERY: removed DISTINCT + invalid ORDER BY issue */
$stmt = $pdo->prepare("
    SELECT
        MIN(aa.id) AS assignment_id,
        t.id,
        t.tournament_name,
        aa.arena_name,
        MAX(t.created_at) AS tournament_created_at
    FROM arena_assignments aa
    INNER JOIN tournaments t ON t.id = aa.tournament_id
    WHERE aa.referee_id = ? AND t.status = 'Verified'
    GROUP BY t.id, t.tournament_name, aa.arena_name
    ORDER BY tournament_created_at DESC, t.tournament_name ASC, aa.arena_name ASC
");
$stmt->execute([$currentRefereeId]);
$scheduleRows = $stmt->fetchAll();

$currentArena = '';
$currentTournamentName = '';
$applicants = [];
if ($selectedTournamentId > 0) {
    $stmt = $pdo->prepare("
        SELECT t.tournament_name, aa.arena_name
        FROM arena_assignments aa
        INNER JOIN tournaments t ON t.id = aa.tournament_id
        WHERE aa.referee_id = ? AND aa.tournament_id = ?
        LIMIT 1
    ");
    $stmt->execute([$currentRefereeId, $selectedTournamentId]);
    $meta = $stmt->fetch();

    if ($meta) {
        $currentArena = $meta['arena_name'];
        $currentTournamentName = $meta['tournament_name'];

        $stmt = $pdo->prepare("
            SELECT ta.id, ta.applicant_name, ta.event_type, ta.age_category, ta.weight_category, ta.club_name,
                   rs.final_score
            FROM tournament_applicants ta
            LEFT JOIN referee_scores rs
              ON rs.applicant_id = ta.id
             AND rs.tournament_id = ta.tournament_id
             AND rs.referee_id = ?
            WHERE ta.tournament_id = ? AND ta.status = 'Verified'
            ORDER BY ta.event_type, ta.age_category, ta.weight_category, ta.applicant_name
        ");
        $stmt->execute([$currentRefereeId, $selectedTournamentId]);
        $applicants = $stmt->fetchAll();
    }
}

$selectedApplicant = null;
foreach ($applicants as $app) {
    if ((int)$app['id'] === $selectedApplicantId) {
        $selectedApplicant = $app;
        break;
    }
}

$rulesLinks = [
    ['label' => 'World Taekwondo Rules', 'url' => 'https://worldtaekwondo.org/competition/competition-rules/'],
    ['label' => 'Kukkiwon', 'url' => 'https://www.kukkiwon.or.kr/'],
    ['label' => 'TCON', 'url' => 'https://www.tkdcon.net/'],
    ['label' => 'KMS', 'url' => 'https://kms.kukkiwon.or.kr/']
];

$chartLabels = [];
$chartValues = [];
foreach ($scoreHistory as $idx => $row) {
    $chartLabels[] = 'Game ' . ($idx + 1);
    $chartValues[] = (float)$row['final_score'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Referee Dashboard</title>
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
    .brand h2{font-size:1.25rem;margin-bottom:8px;}
    .brand p{color:var(--soft);line-height:1.6;font-size:.92rem;}
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
    .topbar{
      display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px;
    }
    .title h1{font-size:2rem;margin-bottom:8px;}
    .title p{color:var(--soft);line-height:1.6;}
    .badge{
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
    input,select,textarea{
      width:100%;min-height:48px;padding:13px 14px;border-radius:14px;border:1px solid var(--border);
      background:rgba(255,255,255,.05);color:var(--white);outline:none;font-size:.95rem;
    }
    .btn{
      min-height:48px;padding:12px 16px;border:none;border-radius:14px;cursor:pointer;font-weight:bold;
      transition:.25s ease;color:var(--white);
    }
    .btn-primary{background:linear-gradient(to right,var(--red),var(--blue));}
    .btn-secondary{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .btn-success{background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.3);color:#d8ffe4;}
    .btn-danger{background:rgba(217,4,41,.18);border:1px solid rgba(217,4,41,.3);color:#ffdada;}
    .mini-card{padding:16px;margin-bottom:18px;}
    .mini-card h3{margin-bottom:10px;}
    .mini-card p{color:var(--soft);line-height:1.6;margin-bottom:12px;}
    .table-wrap{overflow-x:auto;border-radius:18px;border:1px solid var(--border);}
    table{width:100%;border-collapse:collapse;min-width:760px;background:rgba(255,255,255,.04);}
    th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top;}
    th{background:rgba(255,255,255,.06);font-size:.95rem;}
    td{font-size:.94rem;line-height:1.5;}
    .result-box{
      margin-top:16px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.05);
      border:1px solid var(--border);line-height:1.6;color:var(--soft);white-space:pre-wrap;
    }
    .schedule-card{
      background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;margin-bottom:14px;
    }
    .schedule-card h4{margin-bottom:8px;}
    .schedule-card p{color:var(--soft);line-height:1.6;margin-bottom:10px;}
    .score-layout{display:grid;grid-template-columns:1.15fr .85fr;gap:18px;}
    .score-block{
      background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;
    }
    .score-header{
      display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;
    }
    .score-number{
      font-size:2.1rem;font-weight:bold;
    }
    .deduction-grid{
      display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:12px;
    }
    .deduction-card{
      background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:16px;
    }
    .deduction-card h4{margin-bottom:10px;}
    .deduction-row{
      display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;
    }
    .deduction-row button,.tiny-btn{
      min-height:52px;border:none;border-radius:14px;font-weight:bold;cursor:pointer;color:#fff;
    }
    .minor-btn{background:rgba(21,101,255,.22);border:1px solid rgba(21,101,255,.35);}
    .major-btn{background:rgba(217,4,41,.22);border:1px solid rgba(217,4,41,.35);}
    .tiny-btn{background:rgba(255,255,255,.07);border:1px solid var(--border);}
    .score-summary{
      display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px;
    }
    .sum-box{
      background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px;text-align:center;
    }
    .sum-box h5{margin-bottom:6px;color:var(--soft);}
    .sum-box .value{font-size:1.5rem;font-weight:bold;}
    .display-panel{
      min-height:260px;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;
      background:
        radial-gradient(circle at 20% 20%, rgba(217,4,41,.18), transparent 28%),
        radial-gradient(circle at 80% 80%, rgba(21,101,255,.18), transparent 28%),
        linear-gradient(145deg,#0a0a0a,#111927,#0d0d0d);
      border:1px solid rgba(255,255,255,.1);
      border-radius:20px;
      overflow:hidden;
      position:relative;
    }
    .display-panel::before,
    .display-panel::after{
      content:"";
      position:absolute;
      border-radius:50%;
      opacity:.35;
      filter:blur(1px);
      animation:kickFloat 3.2s ease-in-out infinite;
    }
    .display-panel::before{
      width:140px;height:140px;border:4px solid rgba(255,255,255,.12);top:18px;left:18px;
    }
    .display-panel::after{
      width:190px;height:190px;border:4px solid rgba(231,195,90,.14);bottom:18px;right:18px;animation-delay:1.3s;
    }
    @keyframes kickFloat{
      0%,100%{transform:translateY(0) rotate(0deg);}
      50%{transform:translateY(-10px) rotate(8deg);}
    }
    .display-content{position:relative;z-index:2;max-width:100%;}
    .display-title{font-size:1.9rem;font-weight:bold;margin-bottom:10px;}
    .display-sub{color:var(--soft);font-size:1rem;line-height:1.6;}
    .display-score{
      font-size:4rem;font-weight:bold;color:var(--gold);margin-top:12px;text-shadow:0 0 18px rgba(231,195,90,.18);
    }
    .line-card{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:16px;}
    .line-card canvas{width:100%;height:320px;display:block;background:rgba(255,255,255,.03);border-radius:14px;border:1px solid rgba(255,255,255,.06);}
    .link-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
    .link-btn{
      display:block;text-decoration:none;text-align:center;padding:16px;border-radius:16px;font-weight:bold;color:#fff;
      background:rgba(255,255,255,.06);border:1px solid var(--border);
    }
    .ref-status{
      background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:16px;
    }
    @media (max-width:1100px){
      .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .form-grid,.button-row,.card-grid,.score-layout,.deduction-grid,.score-summary,.link-grid{grid-template-columns:1fr;}
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
      .display-score{font-size:3rem;}
      .display-title{font-size:1.5rem;}
    }
  </style>
</head>
<body>
  <div class="bg-orb orb1"></div>
  <div class="bg-orb orb2"></div>

  <div class="mobile-top">
    <button id="menuToggle">☰ Open Referee Menu</button>
  </div>

  <div class="app">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-inner">
        <div class="brand">
          <h2>Hi Ref,</h2>
          <p>Let’s give the best judgment in a real modern way. Manage schedules, score matches, and track your referee progress.</p>
        </div>

        <div class="nav">
          <a class="<?= $activeSection === 'dashboardSection' ? 'active' : '' ?>" href="referee.php?section=dashboardSection">📊 Dashboard</a>
          <a class="<?= $activeSection === 'scheduleSection' ? 'active' : '' ?>" href="referee.php?section=scheduleSection">🗓 Schedule</a>
          <a class="<?= $activeSection === 'scoringSection' ? 'active' : '' ?>" href="referee.php?section=scoringSection">🎯 Scoring</a>
          <a class="<?= $activeSection === 'rulesSection' ? 'active' : '' ?>" href="referee.php?section=rulesSection">📚 Rules & Updates</a>
          <a class="<?= $activeSection === 'accountSection' ? 'active' : '' ?>" href="referee.php?section=accountSection">🔐 Change Password</a>
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
          <h1>Welcome, <?= e($currentReferee['first_name']) ?></h1>
          <p>Referee code: <?= e($currentReferee['referee_code']) ?> · Current referee level: <?= e($currentReferee['level']) ?></p>
        </div>
        <div class="badge"><?= e($rankData['rank']) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Referee Rank</h3>
          <div class="big"><?= e($rankData['rank']) ?></div>
          <p><?= e($rankData['next']) ?></p>
        </div>
        <div class="stat-card">
          <h3>Referee Points</h3>
          <div class="big"><?= e((string)$currentPoints) ?></div>
          <p>Points increase automatically from saved match results.</p>
        </div>
        <div class="stat-card">
          <h3>Total Games</h3>
          <div class="big"><?= e((string)$totalGames) ?></div>
          <p>Total games scored by this referee.</p>
        </div>
        <div class="stat-card">
          <h3>Refresher Countdown</h3>
          <div class="big"><?= e((string)$refresherDays) ?></div>
          <p><?= $refresherDays <= 0 ? 'Refresher course required now.' : 'Days left until refresher course is required.' ?></p>
        </div>
      </div>

      <section class="section <?= $activeSection === 'dashboardSection' ? 'active' : '' ?>">
        <h2>Referee Overview</h2>
        <p class="section-desc">Track referee rank, experience, performance history, and international course eligibility.</p>

        <div class="card-grid">
          <div class="ref-status">
            <h3 style="margin-bottom:10px;">Career Status</h3>
            <p style="color:var(--soft);line-height:1.8;">
              Current Rank: <strong><?= e($rankData['rank']) ?></strong><br>
              Total Games Scored: <strong><?= e((string)$totalGames) ?></strong><br>
              Tournament Experience: <strong><?= e((string)$totalTournaments) ?></strong><br>
              International Track: <strong><?= e($rankData['international']) ?></strong>
            </p>
          </div>

          <div class="ref-status">
            <h3 style="margin-bottom:10px;">Promotion Rules</h3>
            <p style="color:var(--soft);line-height:1.8;">
              Fresher: 0–14 games<br>
              District Referee: 15–24 games<br>
              Province Referee: 25–34 games<br>
              National Referee: 35+ games<br>
              International Course Eligibility: 60 tournaments
            </p>
          </div>
        </div>

        <div class="line-card" style="margin-top:18px;">
          <h3 style="margin-bottom:12px;">Complete Referee Performance Graph</h3>
          <canvas id="scoreHistoryChart" width="900" height="320"></canvas>
        </div>
      </section>

      <section class="section <?= $activeSection === 'scheduleSection' ? 'active' : '' ?>">
        <h2>Schedule</h2>
        <p class="section-desc">When admin assigns you to a tournament arena, it appears here. Open a tournament to start scoring.</p>

        <?php if (!$scheduleRows): ?>
          <div class="result-box">No schedule assigned yet. Once admin assigns you to an arena, your tournament schedule will appear here.</div>
        <?php else: ?>
          <?php foreach ($scheduleRows as $row): ?>
            <div class="schedule-card">
              <h4><?= e($row['tournament_name']) ?></h4>
              <p>Arena: <?= e($row['arena_name']) ?></p>
              <a class="btn btn-primary" style="display:inline-block;text-decoration:none;" href="referee.php?section=scoringSection&tournament=<?= (int)$row['id'] ?>">
                Enter Scoring Panel
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <section class="section <?= $activeSection === 'scoringSection' ? 'active' : '' ?>">
        <h2>Scoring Panel</h2>
        <p class="section-desc">Choose your schedule, select player, score presentation and accuracy, then save final result.</p>

        <form method="get" class="form-grid">
          <input type="hidden" name="section" value="scoringSection">

          <div class="form-group">
            <label>Select Tournament</label>
            <select name="tournament" onchange="this.form.submit()">
              <option value="">Select tournament</option>
              <?php foreach ($scheduleRows as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= $selectedTournamentId === (int)$row['id'] ? 'selected' : '' ?>>
                  <?= e($row['tournament_name']) ?> - <?= e($row['arena_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Select Player / Entry</label>
            <select name="applicant" onchange="this.form.submit()">
              <option value="">Select player / entry</option>
              <?php foreach ($applicants as $app): ?>
                <option value="<?= (int)$app['id'] ?>" <?= $selectedApplicantId === (int)$app['id'] ? 'selected' : '' ?>>
                  <?= e($app['applicant_name']) ?> - <?= e($app['event_type']) ?><?= $app['final_score'] !== null ? ' (Scored: ' . e((string)$app['final_score']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <div class="score-layout">
          <div class="score-block">
            <div class="score-header">
              <div>
                <h3><?= $currentTournamentName !== '' ? e($currentTournamentName) : 'Select a tournament' ?></h3>
                <p style="color:var(--soft);line-height:1.6;">
                  Arena: <?= $currentArena !== '' ? e($currentArena) : '-' ?><br>
                  Player: <span id="livePlayerName"><?= $selectedApplicant ? e($selectedApplicant['applicant_name']) : 'No player selected' ?></span>
                </p>
              </div>
              <div class="score-number" id="liveTotalScore">10.00</div>
            </div>

            <form method="post" id="scoreForm">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="save_score">
              <input type="hidden" name="tournament_id" value="<?= (int)$selectedTournamentId ?>">
              <input type="hidden" name="applicant_id" value="<?= (int)$selectedApplicantId ?>">
              <input type="hidden" name="arena_name" value="<?= e($currentArena) ?>">

              <input type="hidden" name="presentation_minor" id="presentationMinorInput" value="0">
              <input type="hidden" name="presentation_major" id="presentationMajorInput" value="0">
              <input type="hidden" name="accuracy_minor" id="accuracyMinorInput" value="0">
              <input type="hidden" name="accuracy_major" id="accuracyMajorInput" value="0">

              <div class="deduction-grid">
                <div class="deduction-card">
                  <h4>Presentation (Base 6.00)</h4>
                  <p style="color:var(--soft);line-height:1.6;">Use major and minor deductions for presentation.</p>

                  <div class="deduction-row">
                    <button type="button" class="minor-btn" onclick="changeDeduction('presentationMinor', 0.1)">Minor -0.10</button>
                    <button type="button" class="major-btn" onclick="changeDeduction('presentationMajor', 0.3)">Major -0.30</button>
                  </div>

                  <div class="deduction-row">
                    <button type="button" class="tiny-btn" onclick="changeDeduction('presentationMinor', -0.1)">Undo Minor</button>
                    <button type="button" class="tiny-btn" onclick="changeDeduction('presentationMajor', -0.3)">Undo Major</button>
                  </div>
                </div>

                <div class="deduction-card">
                  <h4>Accuracy (Base 4.00)</h4>
                  <p style="color:var(--soft);line-height:1.6;">Use major and minor deductions for accuracy.</p>

                  <div class="deduction-row">
                    <button type="button" class="minor-btn" onclick="changeDeduction('accuracyMinor', 0.1)">Minor -0.10</button>
                    <button type="button" class="major-btn" onclick="changeDeduction('accuracyMajor', 0.3)">Major -0.30</button>
                  </div>

                  <div class="deduction-row">
                    <button type="button" class="tiny-btn" onclick="changeDeduction('accuracyMinor', -0.1)">Undo Minor</button>
                    <button type="button" class="tiny-btn" onclick="changeDeduction('accuracyMajor', -0.3)">Undo Major</button>
                  </div>
                </div>
              </div>

              <div class="score-summary">
                <div class="sum-box">
                  <h5>Presentation</h5>
                  <div class="value" id="presentationScore">6.00</div>
                </div>
                <div class="sum-box">
                  <h5>Accuracy</h5>
                  <div class="value" id="accuracyScore">4.00</div>
                </div>
                <div class="sum-box">
                  <h5>Final Score</h5>
                  <div class="value" id="finalScore">10.00</div>
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="button" onclick="resetScores()">Reset</button>
                <button class="btn btn-success" type="submit" <?= $selectedApplicant ? '' : 'disabled' ?>>Final Result</button>
              </div>
            </form>
          </div>

          <div class="score-block">
            <h3 style="margin-bottom:12px;">External Display</h3>
            <div class="display-panel" id="externalDisplay">
              <div class="display-content">
                <?php if ($selectedApplicant): ?>
                  <div class="display-title"><?= e($selectedApplicant['applicant_name']) ?></div>
                  <div class="display-sub">Ready for performance · <?= e((string)$selectedApplicant['event_type']) ?></div>
                  <div class="display-score" id="externalScore">10.00</div>
                <?php else: ?>
                  <div class="display-title">Taekwondo Performance Arena</div>
                  <div class="display-sub">
                    Dynamic live display mode is active.<br>
                    Select a player to show live performance information and score.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($selectedApplicant): ?>
              <div class="result-box" style="margin-top:14px;">
Selected Entry: <?= e($selectedApplicant['applicant_name']) ?>

Event Type: <?= e((string)$selectedApplicant['event_type']) ?>
Age Category: <?= e((string)$selectedApplicant['age_category']) ?>
Weight Category: <?= e((string)$selectedApplicant['weight_category']) ?>
Club: <?= e((string)$selectedApplicant['club_name']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="section <?= $activeSection === 'rulesSection' ? 'active' : '' ?>">
        <h2>Rules & Updates</h2>
        <p class="section-desc">Quick access to official rules and updates relevant to referees.</p>

        <div class="link-grid">
          <?php foreach ($rulesLinks as $link): ?>
            <a class="link-btn" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= e($link['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="result-box" style="margin-top:18px;">
Refresher Countdown: <?= e((string)$refresherDays) ?> day(s)

<?= $refresherDays <= 0
    ? 'Your refresher course is due now. Please complete it as soon as possible.'
    : 'Your refresher course will be required when the countdown reaches 0.' ?>

<?= e($rankData['international']) ?>
        </div>
      </section>

      <section class="section <?= $activeSection === 'accountSection' ? 'active' : '' ?>">
        <h2>Change Password</h2>
        <p class="section-desc">All referee IDs created by admin use the default password <strong>Referee@123</strong>. Change your password here for better security.</p>

        <div class="mini-card">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-grid">
              <div class="form-group full">
                <label>Email</label>
                <input type="email" value="<?= e($currentReferee['email']) ?>" readonly>
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

          <div class="result-box">
Default referee password issued by admin:
<?= e(DEFAULT_REFEREE_PASSWORD) ?>

After changing it here, use your new password on the login page.
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

    const chartLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const chartValues = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;

    function drawLineChart() {
      const canvas = document.getElementById("scoreHistoryChart");
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

      if (!chartValues.length) {
        ctx.fillStyle = "#cfcfcf";
        ctx.font = "18px Arial";
        ctx.fillText("No score history yet.", padding, h / 2);
        return;
      }

      const minVal = 0;
      const maxVal = 10;
      const stepX = chartValues.length > 1 ? (w - padding * 2) / (chartValues.length - 1) : 0;

      ctx.beginPath();
      ctx.lineWidth = 3;
      ctx.strokeStyle = "#ffffff";

      chartValues.forEach((val, idx) => {
        const x = padding + stepX * idx;
        const y = h - padding - ((val - minVal) / (maxVal - minVal)) * (h - padding * 2);

        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();

      chartValues.forEach((val, idx) => {
        const x = padding + stepX * idx;
        const y = h - padding - ((val - minVal) / (maxVal - minVal)) * (h - padding * 2);

        ctx.beginPath();
        ctx.arc(x, y, 5, 0, Math.PI * 2);
        ctx.fillStyle = "#e7c35a";
        ctx.fill();

        ctx.fillStyle = "#ffffff";
        ctx.font = "12px Arial";
        ctx.fillText(String(val), x - 10, y - 10);

        if (chartLabels[idx]) {
          ctx.fillStyle = "#cfcfcf";
          ctx.fillText(chartLabels[idx], x - 20, h - 20);
        }
      });
    }

    let deductions = {
      presentationMinor: 0,
      presentationMajor: 0,
      accuracyMinor: 0,
      accuracyMajor: 0
    };

    function syncInputs() {
      const pMinor = document.getElementById("presentationMinorInput");
      const pMajor = document.getElementById("presentationMajorInput");
      const aMinor = document.getElementById("accuracyMinorInput");
      const aMajor = document.getElementById("accuracyMajorInput");

      if (pMinor) pMinor.value = deductions.presentationMinor.toFixed(2);
      if (pMajor) pMajor.value = deductions.presentationMajor.toFixed(2);
      if (aMinor) aMinor.value = deductions.accuracyMinor.toFixed(2);
      if (aMajor) aMajor.value = deductions.accuracyMajor.toFixed(2);
    }

    function updateScoreBoard() {
      const presentationBase = 6;
      const accuracyBase = 4;

      const presentation = Math.max(0, presentationBase - deductions.presentationMinor - deductions.presentationMajor);
      const accuracy = Math.max(0, accuracyBase - deductions.accuracyMinor - deductions.accuracyMajor);
      const finalScore = (presentation + accuracy).toFixed(2);

      const presentationEl = document.getElementById("presentationScore");
      const accuracyEl = document.getElementById("accuracyScore");
      const finalEl = document.getElementById("finalScore");
      const liveTotal = document.getElementById("liveTotalScore");
      const externalScore = document.getElementById("externalScore");

      if (presentationEl) presentationEl.textContent = presentation.toFixed(2);
      if (accuracyEl) accuracyEl.textContent = accuracy.toFixed(2);
      if (finalEl) finalEl.textContent = finalScore;
      if (liveTotal) liveTotal.textContent = finalScore;
      if (externalScore) externalScore.textContent = finalScore;

      syncInputs();
    }

    function changeDeduction(type, amount) {
      if (!(type in deductions)) return;
      deductions[type] = Math.max(0, +(deductions[type] + amount).toFixed(2));
      updateScoreBoard();
    }

    function resetScores() {
      deductions = {
        presentationMinor: 0,
        presentationMajor: 0,
        accuracyMinor: 0,
        accuracyMajor: 0
      };
      updateScoreBoard();
    }

    drawLineChart();
    updateScoreBoard();
  </script>
</body>
</html>