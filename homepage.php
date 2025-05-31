<?php
session_start();
require_once 'connection.php';

// 🔐 Zugriffsschutz
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// 🚍 Fahrten des Benutzers abrufen
$stmt = $con->prepare("
    SELECT s.*, CONCAT(s.date, ' ', s.time) AS datetime
    FROM schedule s
    JOIN schedule_assignment sa ON sa.schedule_id = s.id
    WHERE sa.user_id = ?
    ORDER BY s.date, s.time
");
$stmt->execute([$userId]);
$userRides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 📍 Stationen abrufen
function getStations($con, $schedule_id) {
    $stmt = $con->prepare("SELECT id, schedule_id, station_name, platform, time, date, km FROM schedule_station WHERE schedule_id = ? ORDER BY time ASC, id ASC");
    $stmt->execute([$schedule_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$now = new DateTime();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Startseite – MeineApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/homepage.css"/>
    <style>
      /* 🚀 Interaktives Design */
      .ride-entry {
        transition: all 0.3s ease;
        cursor: pointer;
        max-height: 90px;
        overflow: hidden;
        padding: 18px 24px;
        border: 1.5px solid #0078d7;
        border-radius: 10px;
        margin-bottom: 18px;
        background: #f0f8ff;
        box-shadow: 0 4px 12px rgba(0,120,215,0.15);
        position: relative;
      }

      .ride-entry.expanded {
        max-height: 400px;
        box-shadow: 0 10px 30px rgba(0,120,215,0.3);
        background: #dbe9ff;
        padding-bottom: 40px;
      }

      .ride-entry .details {
        margin-top: 12px;
        font-size: 14px;
        color: #004a99;
        display: none;
      }

      .ride-entry.expanded .details {
        display: block;
      }

      .sort-buttons {
        text-align: center;
        margin-bottom: 20px;
      }

      .sort-buttons button {
        background-color: #0078d7;
        color: white;
        border: none;
        padding: 8px 14px;
        margin: 0 6px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: background-color 0.3s ease;
      }

      .sort-buttons button:hover {
        background-color: #005ea2;
      }

      .no-rides {
        color: black;
        font-weight: bold;
        text-align: center;
        margin-top: 40px;
      }
    </style>
</head>
<body>
<header>
  <div class="logo">GIRC Manager</div>
    <nav>
        <ul>
            <li><a href="#">Fahrten</a></li>
            <li><a href="userlist.php">Admin</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<main class="content">
  <h1>Willkommen, <?= htmlspecialchars($username) ?>!</h1>
  
  <section class="ride-section" aria-label="Deine Fahrten">
    <h2>🚌 Deine Fahrten</h2>

    <div class="sort-buttons">
      <button id="sortAsc">Sortiere nach Datum ↑</button>
      <button id="sortDesc">Sortiere nach Datum ↓</button>
    </div>

    <div class="ride-list" role="list">
  <?php
  $foundRide = false;
  foreach ($userRides as $ride):
    $rideDateTime = new DateTime($ride['datetime']);
    $startWindow = (clone $rideDateTime)->modify('-1 hour');

    if ($now >= $startWindow):
      $foundRide = true;
      $status = $now >= $rideDateTime ? 'Gestartet 🚀' : 'In Kürze ⏳';
      $statusClass = $now >= $rideDateTime ? 'gestartet' : 'in-kuerze';
      $stations = getStations($con, $ride['id']);
  ?>
    <div 
      class="ride-entry" 
      tabindex="0" 
      aria-label="Fahrt <?= htmlspecialchars($ride['title']) ?> am <?= $rideDateTime->format('d.m.Y H:i') ?>" 
      data-datetime="<?= $ride['datetime'] ?>" 
      role="listitem"
    >
      <strong><?= htmlspecialchars($ride['title']) ?></strong>
      <div><small><?= $rideDateTime->format('d.m.Y') ?> – <?= substr($ride['time'], 0, 5) ?> Uhr</small></div>
      <em class="<?= $statusClass ?>">Status: <?= $status ?></em>

      <div class="details">
        <p><strong>Startzeit:</strong> <?= $rideDateTime->format('H:i') ?> Uhr</p>
        <p><strong>Beschreibung:</strong> <?= htmlspecialchars($ride['description'] ?? 'Keine weiteren Details') ?></p>
        <p><strong>Stationen:</strong></p>

        <?php if ($stations): ?>
  <div class="station-list-block">
    <?php foreach ($stations as $st): ?>
      <div class="station-line">
        <?= htmlspecialchars($st['time']) ?> – 
        <?= htmlspecialchars($st['station_name']) ?> 
        (Gleis <?= htmlspecialchars($st['platform']) ?>) 
        [<?= htmlspecialchars($st['km']) ?> km]
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <em>Keine Stationen vorhanden.</em>
<?php endif; ?>
      </div>
    </div>
  <?php
    endif;
  endforeach;

  if (!$foundRide):
  ?>
    <div class="no-rides" role="alert">
      Zurzeit sind dir keine aktiven oder bald startenden Fahrten zugewiesen.
    </div>
  <?php endif; ?>
</div>

  </section>
</main>

<footer>
    &copy; <?= date("Y") ?> MeineApp – Alle Rechte vorbehalten
</footer>

<!-- 📜 Interaktive JS-Funktionen -->
<script>
  document.querySelectorAll('.ride-entry').forEach(entry => {
    entry.addEventListener('click', () => {
      entry.classList.toggle('expanded');
    });
  });

  const container = document.querySelector('.ride-list');
  const sortAscBtn = document.getElementById('sortAsc');
  const sortDescBtn = document.getElementById('sortDesc');

  function sortRides(asc = true) {
    const rides = Array.from(container.children);
    rides.sort((a, b) => {
      const dateA = new Date(a.dataset.datetime);
      const dateB = new Date(b.dataset.datetime);
      return asc ? dateA - dateB : dateB - dateA;
    });
    rides.forEach(ride => container.appendChild(ride));
  }

  sortAscBtn.addEventListener('click', () => sortRides(true));
  sortDescBtn.addEventListener('click', () => sortRides(false));
</script>
</body>
</html>
