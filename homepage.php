<?php
session_start();
require_once 'connection.php';

// Benutzerprüfung
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// Fahrten laden
$stmt = $con->prepare("
    SELECT s.*, CONCAT(s.date, ' ', s.time) AS datetime
    FROM schedule s
    JOIN schedule_assignment sa ON sa.schedule_id = s.id
    WHERE sa.user_id = ?
    ORDER BY s.date, s.time
");
$stmt->execute([$userId]);
$userRides = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();
$foundRide = false;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Startseite – MeineApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/homepage.css"/>
</head>
<body>
    <header>
        <div class="logo">MeineApp</div>
        <nav>
            <ul>
                <li><a href="#">Fahrten</a></li>
                <li><a href="userlist.php">Admin</a></li>
                <li><a href="#">Einstellungen</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

<main class="content">
  <h1>Willkommen, <?= htmlspecialchars($username) ?>!</h1>
  
  <section class="ride-section" aria-label="Deine Fahrten">
    <h2>🚌 Deine Fahrten</h2>

    <div class="ride-list">
      <?php
      $foundRide = false;
      foreach ($userRides as $ride):
        $rideDateTime = new DateTime($ride['datetime']);
        $startWindow = (clone $rideDateTime)->modify('-1 hour');

        if ($now >= $startWindow):
          $foundRide = true;
          $status = $now >= $rideDateTime ? 'Gestartet 🚀' : 'In Kürze ⏳';
          $statusClass = $now >= $rideDateTime ? 'gestartet' : 'in-kuerze';
      ?>
        <div class="ride-entry" tabindex="0" aria-label="Fahrt <?= htmlspecialchars($ride['title']) ?> am <?= $rideDateTime->format('d.m.Y H:i') ?>">
          <strong><?= htmlspecialchars($ride['title']) ?></strong>
          <div>
            <small><?= $rideDateTime->format('d.m.Y') ?> – <?= htmlspecialchars(substr($ride['time'], 0, 5)) ?> Uhr</small>
          </div>
          <em class="<?= $statusClass ?>">Status: <?= $status ?></em>
        </div>
      <?php
        endif;
      endforeach;

      if (!$foundRide):
      ?>
        <div class="no-rides" role="alert" style="color: black; font-weight: bold;">
          Zurzeit sind dir keine aktiven oder bald startenden Fahrten zugewiesen.
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

    <footer>
        &copy; <?= date("Y") ?> MeineApp – Alle Rechte vorbehalten
    </footer>
</body>
</html>