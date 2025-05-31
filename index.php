<?php
session_start();
require("connection.php");

$registerError = "";
$loginError = "";

// Registrierung
if (isset($_POST["register"])) {
    $username = $_POST["reg_username"];
    $password = password_hash($_POST["reg_password"], PASSWORD_DEFAULT);

    $stmt = $con->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        $stmt = $con->prepare("INSERT INTO users(username, password) VALUES(:username, :password)");
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $password);
        $stmt->execute();
        header("Location: homepage.php");
        exit;
    } else {
        $registerError = "Benutzername ist bereits vergeben.";
    }
}

// Login
if (isset($_POST["login"])) {
    $username = $_POST["log_username"];
    $password = $_POST["log_password"];

    $stmt = $con->prepare("SELECT password FROM users WHERE username = :username");
    $stmt->bindParam(":username", $username);
    $stmt->execute();
    $hash = $stmt->fetchColumn();

    if ($hash && password_verify($password, $hash)) {
        $_SESSION['username'] = $username;  
        header("Location: homepage.php");
        exit;
    } else {
        $loginError = "Benutzername oder Passwort ist falsch.";
    }
}

?>
<!DOCTYPE html>
<head>
<html lang="de">
  <link rel="icon" type="png" href="img/logo.png">
  <meta charset="UTF-8">
  <title>Login & Registrierung</title>
  <link rel="stylesheet" href="css/register.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="container">
    <div class="form-box">
      <h2>Registrieren</h2>
      <form method="POST">
        <input type="text" name="reg_username" placeholder="Benutzername" required>
        <input type="password" name="reg_password" placeholder="Passwort" required>
        <button type="submit" name="register">Registrieren</button>
        <?php if (!empty($registerError)): ?>
          <p class="error-message"><?= htmlspecialchars($registerError) ?></p>
        <?php endif; ?>
      </form>
    </div>

    <div class="divider"></div>

    <div class="form-box">
      <h2>Login</h2>
      <form method="POST">
        <input type="text" name="log_username" placeholder="Benutzername" required>
        <input type="password" name="log_password" placeholder="Passwort" required>
        <button type="submit" name="login">Login</button>
        <?php if (!empty($loginError)): ?>
          <p class="error-message"><?= htmlspecialchars($loginError) ?></p>
        <?php endif; ?>
      </form>
    </div>
  </div>
</body>
</html>
