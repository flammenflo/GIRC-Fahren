<?php


$dsn = 'mysql:dbname=gircmanager;host=localhost';
$username = 'root';
$password = '';
$con = new PDO($dsn, $username, $password);

if (isset($_POST['login'])) {
    $user = $_POST['log_username'];
    $pass = $_POST['log_password'];

    $stmt = $con->prepare("SELECT username, password, rank FROM users WHERE username = :username");
    $stmt->bindParam(':username', $user);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['username'] = $row['username'];
        $_SESSION['user_rank'] = $row['rank'];  // rank aus DB in Session speichern
        header('Location: homepage.php');
        exit;
    } else {
        $loginError = "Benutzername oder Passwort ist falsch.";
    }
}
?>
