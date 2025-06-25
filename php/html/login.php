<?php
session_start();
require_once "includes/ldap.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    $auth = ldap_authenticate($username, $password);

    if ($auth["status"]) {
        $_SESSION["user"] = $username;
        $_SESSION["role"] = $auth["role"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Ongeldige login.";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Login - Rooster</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
    <main class="login-outer">
        <section class="login-card">
            <header class="login-header">
                <i class="fa-solid fa-pizza-slice login-icon"></i>
                <h1>Inloggen</h1>
                <p class="login-subtitle">Voer je gegevens in om verder te gaan</p>
            </header>
            <form method="POST" class="login-form">
                <div class="login-field">
                    <input type="text" name="username" required placeholder="Gebruikersnaam" autocomplete="username" autofocus>
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="login-field">
                    <input type="password" name="password" required placeholder="Wachtwoord" autocomplete="current-password">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <?php if (isset($error)): ?>
                    <div class="login-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <button type="submit" class="login-btn">Inloggen</button>
            </form>
        </section>
    </main>
</body>
</html>