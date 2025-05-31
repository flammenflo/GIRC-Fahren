<?php
session_start();
require("connection.php");

// Prüfe ob Nutzer eingeloggt ist
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Versuche Rang aus Session zu nehmen
$user_rank = $_SESSION['user_rank'] ?? null;

// Rang prüfen (case-insensitive)
if (strtolower($user_rank) !== 'admin') {
    header("Location: homepage.php?error=keine-rechte");
    exit;
}

// Handle Rangänderung nur wenn Admin
$message = '';
if (isset($_POST['change_rank'])) {
    $username = $_POST['username'] ?? '';
    $newRank = $_POST['new_rank'] ?? '';

    $allowedRanks = ['admin', 'Benutzer'];

    if ($username && in_array($newRank, $allowedRanks, true)) {
        // Rang aktualisieren
        $stmt = $con->prepare("UPDATE users SET rank = :rank WHERE username = :username");
        if ($stmt->execute([':rank' => $newRank, ':username' => $username])) {
            $message = "Rang erfolgreich geändert.";
        } else {
            $message = "Fehler beim Ändern des Rangs.";
        }
    } else {
        $message = "Ungültige Eingabe.";
    }
}

// Alle Benutzer holen
$stmt = $con->query("SELECT username, rank FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <title>Benutzerliste</title>
  <link rel="stylesheet" href="css/playerrank.css" />
</head>
<body>
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

  <div class="background-overlay">
  <div class="content">
    <h2>Benutzer &amp; Ränge</h2>
    <?php if ($message): ?>
      <div style="color: #007700; margin-bottom: 10px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <table>
      <thead>
        <tr>
          <th>Benutzername</th>
          <th>Rang</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td>
              <form method="post" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                <input type="hidden" name="username" value="<?= htmlspecialchars($user['username']) ?>" />
                <select name="new_rank" aria-label="Rang ändern von <?= htmlspecialchars($user['username']) ?>">
                  <?php 
                    $ranks = ['admin', 'Benutzer'];
                    foreach ($ranks as $rank): 
                      $selected = (strtolower($rank) === strtolower($user['rank'])) ? "selected" : "";
                  ?>
                    <option value="<?= htmlspecialchars($rank) ?>" <?= $selected ?>><?= ucfirst(htmlspecialchars($rank)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="change_rank" title="Rang speichern">💾</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>