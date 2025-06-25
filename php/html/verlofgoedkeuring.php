<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["role"] !== "manager") {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"], $_POST["status"])) {
    update_verlof_status($_POST["id"], $_POST["status"]);
}

$aanvragen = get_verlofaanvragen();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Verlof goedkeuren</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="verlofkeuring-body">
    <main class="verlofkeuring-outer">
        <section class="verlofkeuring-card">
            <header class="verlofkeuring-header">
                <i class="fa-solid fa-calendar-check verlofkeuring-icon"></i>
                <h1>Verlof goedkeuren</h1>
                <p class="verlofkeuring-subtitle">Bekijk en verwerk openstaande verlofaanvragen</p>
            </header>
            <div class="verlofkeuring-tablewrap">
                <table class="verlofkeuring-table">
                    <thead>
                        <tr>
                            <th>Gebruiker</th>
                            <th>Van</th>
                            <th>Tot</th>
                            <th>Reden</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($aanvragen as $aanvraag): ?>
                        <tr>
                            <td><?= htmlspecialchars($aanvraag['gebruiker']) ?></td>
                            <td><?= htmlspecialchars($aanvraag['van_datum'] ?? $aanvraag['datum'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($aanvraag['tot_datum'] ?? $aanvraag['datum'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($aanvraag['reden']) ?></td>
                            <td>
                                <span class="verlofkeuring-status verlof-status-<?= htmlspecialchars(strtolower($aanvraag['status'])) ?>">
                                    <?= htmlspecialchars($aanvraag['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $aanvraag['id'] ?>">
                                    <button type="submit" name="status" value="goedgekeurd" class="verlofkeuring-btn goedkeuren" title="Goedkeuren">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $aanvraag['id'] ?>">
                                    <button type="submit" name="status" value="afgewezen" class="verlofkeuring-btn afwijzen" title="Afwijzen">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="verlofkeuring-links">
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Terug naar dashboard
                </a>
            </div>
        </section>
    </main>
</body>
</html>