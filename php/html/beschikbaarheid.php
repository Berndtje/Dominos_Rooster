<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION["user"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["opslaan"])) {
    foreach ($_POST["beschikbaar"] as $dag => $waarde) {
        zet_beschikbaarheid($user, $dag, intval($waarde));
    }
    $bericht = "Beschikbaarheid opgeslagen!";
}

$beschikbaarheden = haal_beschikbaarheid($user);
$dagen = ["Maandag", "Dinsdag", "Woensdag", "Donderdag", "Vrijdag", "Zaterdag", "Zondag"];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <title>Beschikbaarheid</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="dashboard-body">
<main class="dashboard-main">
    <section class="dashboard-card">
        <header class="dashboard-header">
            <h1>Algemene Beschikbaarheid</h1>
        </header>
        <?php if(isset($bericht)): ?>
            <div class="verlof-success"><?= htmlspecialchars($bericht) ?></div>
        <?php endif; ?>
        <form method="POST" class="dashboard-form">
            <table class="dashboard-table">
                <?php foreach ($dagen as $dag): ?>
                <tr>
                    <td><?= htmlspecialchars($dag) ?></td>
                    <td>
                        <select name="beschikbaar[<?= $dag ?>]" class="form-control">
                            <option value="1" <?= isset($beschikbaarheden[$dag]) && $beschikbaarheden[$dag] ? "selected" : "" ?>>Beschikbaar</option>
                            <option value="0" <?= isset($beschikbaarheden[$dag]) && !$beschikbaarheden[$dag] ? "selected" : "" ?>>Niet Beschikbaar</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div class="button-group" style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="opslaan" class="btn-primary">Opslaan</button>
                <a href="dashboard.php" class="btn-secondary">Terug</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>