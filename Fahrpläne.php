<?php
session_start();
require_once 'connection.php'; // stellt sicher, dass $con verfügbar ist

// Fahrplan speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['date'], $_POST['time']) && !isset($_POST['user_id'])) {
    $stmt = $con->prepare("INSERT INTO schedule (title, description, date, time) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['date'],
        $_POST['time']
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Zuweisung speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['schedule_id']) && !isset($_POST['assignment_id'])) {
    $stmt = $con->prepare("INSERT INTO schedule_assignment (user_id, schedule_id) VALUES (?, ?)");
    $stmt->execute([
        $_POST['user_id'],
        $_POST['schedule_id']
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Zuweisung löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $stmt = $con->prepare("DELETE FROM schedule_assignment WHERE id = ?");
    $stmt->execute([$_POST['assignment_id']]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Daten abrufen
$users = $con->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $con->query("SELECT * FROM schedule ORDER BY date, time")->fetchAll(PDO::FETCH_ASSOC);
$assignments = $con->query("
    SELECT sa.id, u.username, s.title, s.date, s.time 
    FROM schedule_assignment sa 
    JOIN users u ON sa.user_id = u.id 
    JOIN schedule s ON sa.schedule_id = s.id 
    ORDER BY s.date, s.time
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fahrplan Verwaltung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 20px;
        }
        .content {
            max-width: 900px;
            background: white;
            padding: 30px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            margin-top: 0;
        }
        form {
            margin-bottom: 30px;
        }
        input, textarea, select, button {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background-color: #0078d7;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background-color: #005ea2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #0078d7;
            color: white;
        }
        td form {
            display: inline;
        }
    </style>
</head>
<body>
<div class="content">
    <h2>Fahrplan erstellen</h2>
    <form method="post">
        <input type="text" name="title" placeholder="Titel" required>
        <textarea name="description" placeholder="Beschreibung"></textarea>
        <input type="date" name="date" required>
        <input type="time" name="time" required>
        <button type="submit">➕ Fahrplan hinzufügen</button>
    </form>

    <h2>Benutzer zu Fahrplan zuweisen</h2>
    <form method="post">
        <select name="user_id" required>
            <option value="">Benutzer wählen</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="schedule_id" required>
            <option value="">Fahrplan wählen</option>
            <?php foreach ($schedules as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['date']) ?> <?= htmlspecialchars($s['time']) ?> - <?= htmlspecialchars($s['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">✅ Zuweisen</button>
    </form>

    <h2>Aktuelle Zuweisungen</h2>
    <table>
        <thead>
        <tr>
            <th>Benutzer</th>
            <th>Fahrplan</th>
            <th>Aktion</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['date']) ?> <?= htmlspecialchars($a['time']) ?> - <?= htmlspecialchars($a['title']) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                        <button type="submit">🗑️</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
