<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $van = $_POST["van_datum"];
    $tot = $_POST["tot_datum"];
    $reden = $_POST["reden"];
    aanvraag_verlof($_SESSION["user"], $van, $tot, $reden);
    $succes = "Verzoek ingediend!";
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Verlof aanvragen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="verlof-body">
    <main class="verlof-outer">
        <section class="verlof-card">
            <header class="verlof-header">
                <i class="fa-solid fa-plane-departure verlof-icon"></i>
                <h1>Verlof aanvragen</h1>
                <p class="verlof-subtitle">Vraag hier snel en eenvoudig je verlof aan</p>
            </header>
            <?php if (isset($succes)): ?>
                <div class="verlof-success"><?= htmlspecialchars($succes) ?></div>
            <?php endif; ?>
            <form method="POST" class="verlof-form">
                <div class="verlof-field">
                    <label for="van_datum">Van</label>
                    <input type="date" name="van_datum" id="van_datum" required>
                </div>
                <div class="verlof-field">
                    <label for="tot_datum">Tot</label>
                    <input type="date" name="tot_datum" id="tot_datum" required>
                </div>
                <div class="verlof-field">
                    <label for="reden">Reden</label>
                    <textarea name="reden" id="reden" required rows="3" placeholder="Bijvoorbeeld: vakantie, studie, privé..."></textarea>
                </div>
                <button type="submit" class="verlof-btn">
                    Aanvragen <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
            <div class="verlof-links">
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Terug naar dashboard
                </a>
            </div>
        </section>
    </main>
</body>
</html>