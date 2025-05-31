<?php

require_once 'connection.php';

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_schedule') {
        // Fahrplan anlegen
        $stmt = $con->prepare("INSERT INTO schedule (title, description, date, time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['date'], $_POST['time']]);
        $schedule_id = $con->lastInsertId();

        // Stationen anlegen
        if (!empty($_POST['stations']) && is_array($_POST['stations'])) {
            foreach ($_POST['stations'] as $st) {
                if (!empty($st['station_name']) && !empty($st['time']) && !empty($st['date']) && isset($st['km'])) {
                    $stmt = $con->prepare("INSERT INTO schedule_station (schedule_id, station_name, platform, time, date, km) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $schedule_id,
                        $st['station_name'],
                        $st['platform'],
                        $st['time'],
                        $st['date'],
                        $st['km']
                    ]);
                }
            }
        }

        // Nutzer zuweisen
        if (!empty($_POST['assigned_users']) && is_array($_POST['assigned_users'])) {
            foreach ($_POST['assigned_users'] as $user_id) {
                if ($user_id) {
                    $stmt = $con->prepare("INSERT INTO schedule_assignment (user_id, schedule_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $schedule_id]);
                }
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'add_station') {
        $stmt = $con->prepare("INSERT INTO schedule_station (schedule_id, station_name, platform, time, date, km) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['schedule_id'], $_POST['station_name'], $_POST['platform'], $_POST['time'], $_POST['date'], $_POST['km']]);
    }
    if (isset($_POST['action']) && $_POST['action'] === 'assign_user') {
        $stmt = $con->prepare("INSERT INTO schedule_assignment (user_id, schedule_id) VALUES (?, ?)");
        $stmt->execute([$_POST['user_id'], $_POST['schedule_id']]);
    }
    if (isset($_POST['delete_assignment_id'])) {
        $stmt = $con->prepare("DELETE FROM schedule_assignment WHERE id = ?");
        $stmt->execute([$_POST['delete_assignment_id']]);
    }
    // Fahrplan löschen
    if (isset($_POST['delete_schedule_id'])) {
        $schedule_id = $_POST['delete_schedule_id'];
        // Lösche zugehörige Stationen und Zuweisungen
        $con->prepare("DELETE FROM schedule_station WHERE schedule_id = ?")->execute([$schedule_id]);
        $con->prepare("DELETE FROM schedule_assignment WHERE schedule_id = ?")->execute([$schedule_id]);
        $con->prepare("DELETE FROM schedule WHERE id = ?")->execute([$schedule_id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Fahrplan bearbeiten
    if (isset($_POST['action']) && $_POST['action'] === 'edit_schedule' && isset($_POST['schedule_id'])) {
        $stmt = $con->prepare("UPDATE schedule SET title = ?, description = ?, date = ?, time = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['date'],
            $_POST['time'],
            $_POST['schedule_id']
        ]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Station löschen
    if (isset($_POST['delete_station_id'])) {
        $stmt = $con->prepare("DELETE FROM schedule_station WHERE id = ?");
        $stmt->execute([$_POST['delete_station_id']]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Station bearbeiten
    if (isset($_POST['action']) && $_POST['action'] === 'edit_station' && isset($_POST['station_id'])) {
        $stmt = $con->prepare("UPDATE schedule_station SET station_name = ?, platform = ?, time = ?, date = ?, km = ? WHERE id = ?");
        $stmt->execute([
            $_POST['station_name'],
            $_POST['platform'],
            $_POST['time'],
            $_POST['date'],
            $_POST['km'],
            $_POST['station_id']
        ]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- Abgelaufene Fahrpläne nach 5 Minuten löschen ---
function deleteExpiredSchedules($con) {
    $sql = "SELECT s.id, MAX(ss.time) as last_time, MAX(ss.date) as last_date
            FROM schedule s
            JOIN schedule_station ss ON s.id = ss.schedule_id
            GROUP BY s.id";
    $stmt = $con->query($sql);
    $now = new DateTime();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $row['last_date'].' '.$row['last_time']);
        if (!$lastDateTime) {
            $lastDateTime = DateTime::createFromFormat('Y-m-d H:i', $row['last_date'].' '.$row['last_time']);
        }
        if ($lastDateTime && $now->getTimestamp() > ($lastDateTime->getTimestamp() + 5*60)) {
            $con->prepare("DELETE FROM schedule_station WHERE schedule_id = ?")->execute([$row['id']]);
            $con->prepare("DELETE FROM schedule_assignment WHERE schedule_id = ?")->execute([$row['id']]);
            $con->prepare("DELETE FROM schedule WHERE id = ?")->execute([$row['id']]);
        }
    }
}
deleteExpiredSchedules($con);

// Load Data
$schedules = $con->query("SELECT * FROM schedule ORDER BY date DESC, time DESC")->fetchAll(PDO::FETCH_ASSOC);
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

function getLastStationDateTime($con, $schedule_id) {
    $stmt = $con->prepare("SELECT date, time FROM schedule_station WHERE schedule_id = ? ORDER BY date DESC, time DESC LIMIT 1");
    $stmt->execute([$schedule_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['date'].' '.$row['time']);
        if (!$dt) {
            $dt = DateTime::createFromFormat('Y-m-d H:i', $row['date'].' '.$row['time']);
        }
        return $dt;
    }
    return null;
}

// Bearbeitungsmodus prüfen
$edit_id = isset($_POST['edit_schedule_id']) ? intval($_POST['edit_schedule_id']) : null;
$edit_schedule = null;
if ($edit_id) {
    foreach ($schedules as $s) {
        if ($s['id'] == $edit_id) {
            $edit_schedule = $s;
            break;
        }
    }
}

// Stationen-Bearbeitungsmodus prüfen
$edit_station_id = isset($_POST['edit_station_id']) ? intval($_POST['edit_station_id']) : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Fahrplan Verwaltung</title>
  <link rel="stylesheet" href="css/ride.css">
  <script>
let stationIndex = 0;
function addStationRow() {
  const tpl = document.getElementById('station-template').content.cloneNode(true);

  // Alle Inputs im Template aktualisieren
  tpl.querySelectorAll('input').forEach(input => {
    const name = input.getAttribute('name');
    if (name) {
      // Korrigiere: Ersetze ALLE Vorkommen von [] durch [index]
      input.setAttribute('name', name.replace(/\[\]/g, `[${stationIndex}]`));
    }
  });

  document.getElementById('stations-list').appendChild(tpl);
  stationIndex++;
}

function removeStationRow(btn) {
  btn.closest('.station-row').remove();
}

// --- Nutzer hinzufügen/entfernen ---
function addUserRow() {
  const tpl = document.getElementById('user-template').content.cloneNode(true);
  document.getElementById('users-list').appendChild(tpl);
}
function removeUserRow(btn) {
  btn.closest('.user-row').remove();
}
  </script>
  <style>
    .expired-schedule {
      background: #ffeaea !important;
      border: 2px solid #ff4d4f !important;
      opacity: 0.7;
    }
    .ended-schedule {
      background: #fffbe6 !important;
      border: 2px solid #ffd700 !important;
    }
  </style>
</head>
<body>
<div class="background-overlay"></div>
<header>
  <div class="logo">GIRC Manager</div>
  <nav>
    <ul>
        <li><a href="homepage.php">Startseite</a></li>
        <li><a href="Userlist.php">UserListe</a></li>
        <li><a href="Fahrpläne.php">Fahrpläne</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>
<div class="content">
  <h2>🧾 Fahrplan Manager</h2>
  <div class="userlist">

    <form method="post" autocomplete="off">
      <h3>➕ Neuen Fahrplan inkl. Stationen & Nutzer erstellen</h3>
      <input type="hidden" name="action" value="create_schedule">
      <div class="form-group">
        <label>Titel</label>
        <input type="text" name="title" placeholder="Titel" required>
      </div>
      <div class="form-group">
        <label>Beschreibung</label>
        <textarea name="description" placeholder="Beschreibung"></textarea>
      </div>
      <div class="form-group">
        <label>Datum & Uhrzeit</label>
        <input type="date" name="date" required>
        <input type="time" name="time" required>
      </div>
      <div class="form-group">
        <label>Stationen</label>
        <div id="stations-list"></div>
        <button type="button" onclick="addStationRow()">➕ Station hinzufügen</button>
      </div>
      <div class="form-group">
        <label>Nutzer zuweisen</label>
        <div id="users-list"></div>
        <button type="button" onclick="addUserRow()">➕ Nutzer hinzufügen</button>
      </div>
      <button type="submit">Fahrplan inkl. Stationen & Nutzer erstellen</button>
    </form>

    <!-- Templates für dynamische Felder -->
    <template id="station-template">
      <div class="station-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
        <input type="text" name="stations[][station_name]" placeholder="Stationsname" required style="flex:2;">
        <input type="text" name="stations[][platform]" placeholder="Gleis" style="flex:1;">
        <input type="time" name="stations[][time]" required style="flex:1;">
        <input type="date" name="stations[][date]" required style="flex:1;">
        <input type="number" step="0.1" name="stations[][km]" placeholder="km" required style="flex:1;">
        <button type="button" onclick="removeStationRow(this)" style="background:#ff4d4f;">✖</button>
      </div>
    </template>
    <template id="user-template">
      <div class="user-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
        <select name="assigned_users[]">
          <option value="">Benutzer auswählen</option>
          <?php foreach ($users as $user): ?>
            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" onclick="removeUserRow(this)" style="background:#ff4d4f;">✖</button>
      </div>
    </template>

    <h3>📋 Bestehende Fahrpläne</h3>
    <div class="schedule-grid">
      <?php foreach ($schedules as $s): ?>
        <?php
          $lastStationDT = getLastStationDateTime($con, $s['id']);
          $now = new DateTime();
          $isEnded = false;
          $isExpired = false;
          if ($lastStationDT) {
              if ($now >= $lastStationDT) {
                  $isEnded = true;
              }
              if ($now->getTimestamp() > ($lastStationDT->getTimestamp() + 5*60)) {
                  $isExpired = true;
              }
          }
        ?>
        <?php if ($isExpired) continue; // Nicht mehr anzeigen, wird gelöscht ?>
        <div class="schedule-card<?= $isEnded ? ' ended-schedule' : '' ?>">
          <?php if ($edit_schedule && $edit_schedule['id'] == $s['id']): ?>
            <!-- Bearbeitungsformular -->
            <form method="post">
              <input type="hidden" name="action" value="edit_schedule">
              <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
              <label>Titel</label>
              <input type="text" name="title" value="<?= htmlspecialchars($s['title']) ?>" required>
              <label>Beschreibung</label>
              <textarea name="description"><?= htmlspecialchars($s['description']) ?></textarea>
              <label>Datum</label>
              <input type="date" name="date" value="<?= htmlspecialchars($s['date']) ?>" required>
              <label>Uhrzeit</label>
              <input type="time" name="time" value="<?= htmlspecialchars($s['time']) ?>" required>
              <button type="submit">Speichern</button>
              <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="margin-left:10px;">Abbrechen</a>
            </form>
          <?php else: ?>
            <h4><?= htmlspecialchars($s['title']) ?></h4>
            <div class="meta"><?= $s['date'] ?> <?= $s['time'] ?></div>
            <div class="desc"><?= nl2br(htmlspecialchars($s['description'])) ?></div>
            <div class="stations">
              <strong>Stationen:</strong>
              <ul>
                <?php foreach (getStations($con, $s['id']) as $st): ?>
                  <li>
                    <?php if ($edit_station_id && $edit_station_id == $st['id']): ?>
                      <!-- Bearbeitungsformular für Station -->
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="edit_station">
                        <input type="hidden" name="station_id" value="<?= $st['id'] ?>">
                        <input type="text" name="station_name" value="<?= htmlspecialchars($st['station_name']) ?>" required style="width:120px;">
                        <input type="text" name="platform" value="<?= htmlspecialchars($st['platform']) ?>" style="width:60px;">
                        <input type="time" name="time" value="<?= htmlspecialchars($st['time']) ?>" required>
                        <input type="date" name="date" value="<?= htmlspecialchars($st['date']) ?>" required>
                        <input type="number" step="0.1" name="km" value="<?= htmlspecialchars($st['km']) ?>" required style="width:70px;">
                        <button type="submit">Speichern</button>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="margin-left:10px;">Abbrechen</a>
                      </form>
                    <?php else: ?>
                      <?= htmlspecialchars($st['station_name']) ?><?= $st['platform'] ? " (Gleis: ".htmlspecialchars($st['platform']).")" : "" ?> | <?= $st['time'] ?> | <?= $st['km'] ?> km
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="edit_station_id" value="<?= $st['id'] ?>">
                        <button type="submit">✏️</button>
                      </form>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Station wirklich löschen?');">
                        <input type="hidden" name="delete_station_id" value="<?= $st['id'] ?>">
                        <button type="submit" style="background:#ff4d4f;">🗑️</button>
                      </form>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="users">
              <strong>Zugewiesene Nutzer:</strong>
              <ul>
                <?php foreach (getAssignments($con, $s['id']) as $a): ?>
                  <li>
                    <?= htmlspecialchars($a['username']) ?>
                    <form method="post" class="inline-btn" style="display:inline;">
                      <input type="hidden" name="delete_assignment_id" value="<?= $a['id'] ?>">
                      <button type="submit" style="background:#ff4d4f;">🗑️</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="card-actions" style="margin-top:10px;">
              <form method="post" style="display:inline;">
                <input type="hidden" name="edit_schedule_id" value="<?= $s['id'] ?>">
                <button type="submit">✏️ Bearbeiten</button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Fahrplan wirklich löschen?');">
                <input type="hidden" name="delete_schedule_id" value="<?= $s['id'] ?>">
                <button type="submit" style="background:#ff4d4f;">🗑️ Löschen</button>
              </form>
            </div>
            <!-- ...existing details/actions... -->
            <div class="card-actions">
              <details>
                <summary>Station hinzufügen</summary>
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
              </details>
              <details>
                <summary>Nutzer zuweisen</summary>
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
              </details>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
