<?php
session_start();
require_once 'connection.php';

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create_schedule') {
        $stmt = $con->prepare("INSERT INTO schedule (title, description, date, time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['date'], $_POST['time']]);
    }
    if ($_POST['action'] === 'add_station') {
        $stmt = $con->prepare("INSERT INTO schedule_station (schedule_id, station_name, platform, time, date, km) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['schedule_id'], $_POST['station_name'], $_POST['platform'], $_POST['time'], $_POST['date'], $_POST['km']]);
    }
    if ($_POST['action'] === 'assign_user') {
        $stmt = $con->prepare("INSERT INTO schedule_assignment (user_id, schedule_id) VALUES (?, ?)");
        $stmt->execute([$_POST['user_id'], $_POST['schedule_id']]);
    }
    if (isset($_POST['delete_assignment_id'])) {
        $stmt = $con->prepare("DELETE FROM schedule_assignment WHERE id = ?");
        $stmt->execute([$_POST['delete_assignment_id']]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Load Data
$schedules = $con->query("SELECT * FROM schedule ORDER BY date, time")->fetchAll(PDO::FETCH_ASSOC);
$users = $con->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);

function getStations($con, $schedule_id) {
    $stmt = $con->prepare("SELECT * FROM schedule_station WHERE schedule_id = ? ORDER BY time");
    $stmt->execute([$schedule_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAssignments($con, $schedule_id) {
    $stmt = $con->prepare("
        SELECT sa.id, u.username 
        FROM schedule_assignment sa 
        JOIN users u ON sa.user_id = u.id 
        WHERE sa.schedule_id = ?");
    $stmt->execute([$schedule_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Fahrplan Verwaltung</title>
  <link rel="stylesheet" href="css/userlist.css"> <!-- Dein ausgelagerter CSS-Dateipfad -->
  <style>
   
  </style>
</head>
<body>

<div class="background-overlay"></div>

<header>
  <div class="logo">MeineApp</div>
  <nav>
    <ul>
      <li><a href="Fahrpläne.php">Fahrpläne</a></li>
      <li><a href="Userlist.php">UserListe</a></li>
      <li><a href="#">Fahrpläne</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<div class="content">
  <h2>🧾 Fahrplan Verwaltung</h2>
  <div class="userlist">

    <form method="post">
      <h3>➕ Neuen Fahrplan erstellen</h3>
      <input type="hidden" name="action" value="create_schedule">
      <div class="form-group">
        <input type="text" name="title" placeholder="Titel" required>
      </div>
      <div class="form-group">
        <textarea name="description" placeholder="Beschreibung"></textarea>
      </div>
      <div class="form-group">
        <input type="date" name="date" required>
        <input type="time" name="time" required>
      </div>
      <button type="submit">Fahrplan erstellen</button>
    </form>

    <h3>📋 Bestehende Fahrpläne</h3>
    <?php foreach ($schedules as $s): ?>
      <details>
        <summary><?= htmlspecialchars($s['title']) ?> – <?= $s['date'] ?> <?= $s['time'] ?></summary>
        <p><?= nl2br(htmlspecialchars($s['description'])) ?></p>

        <h4>📍 Station hinzufügen</h4>
        <form method="post">
          <input type="hidden" name="action" value="add_station">
          <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
          <input type="text" name="station_name" placeholder="Stationsname" required>
          <input type="text" name="platform" placeholder="Gleis">
          <input type="time" name="time" required>
          <input type="date" name="date" required>
          <input type="number" step="0.1" name="km" placeholder="Kilometerstand" required>
          <button type="submit">➕ Station speichern</button>
        </form>

        <h4>🗺️ Stationen</h4>
        <ul>
          <?php foreach (getStations($con, $s['id']) as $st): ?>
            <li><?= htmlspecialchars($st['station_name']) ?> – <?= $st['platform'] ?> | <?= $st['time'] ?> | <?= $st['km'] ?> km</li>
          <?php endforeach; ?>
        </ul>

        <h4>👤 Benutzer zuweisen</h4>
        <form method="post">
          <input type="hidden" name="action" value="assign_user">
          <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
          <select name="user_id" required>
            <option value="">Benutzer auswählen</option>
            <?php foreach ($users as $user): ?>
              <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">✅ Zuweisen</button>
        </form>

        <h4>👥 Zugewiesene Benutzer</h4>
        <ul>
          <?php foreach (getAssignments($con, $s['id']) as $a): ?>
            <li>
              <?= htmlspecialchars($a['username']) ?>
              <form method="post" class="inline-btn">
                <input type="hidden" name="delete_assignment_id" value="<?= $a['id'] ?>">
                <button type="submit">🗑️ Entfernen</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>

  </div>
</div>

</body>
</html>
