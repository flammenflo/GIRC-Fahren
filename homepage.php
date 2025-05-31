<?php

  // Beispiel für Begrüßung nach Login (falls Nutzername in Session)
  $username = $_SESSION['username'] ?? 'Gast';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Startseite</title>
  <link rel="stylesheet" href="css/home.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <p>Schön, dass du da bist. Hier kannst du dein Dashboard gestalten oder weitere Inhalte anzeigen.</p>
    <!-- Platz für weitere Funktionen -->
  </main>

  <footer>
    &copy; <?= date("Y") ?> MeineApp – Alle Rechte vorbehalten
  </footer>
</body>
</html>