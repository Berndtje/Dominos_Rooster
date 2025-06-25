<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["role"] !== "manager") {
    header("Location: login.php");
    exit;
}

// Actie verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aanvraag_id'], $_POST['actie'])) {
    $id = intval($_POST['aanvraag_id']);
    $actie = $_POST['actie'];
    $aanvraag = get_shift_aanvraag_by_id($id);

    if ($aanvraag) {
        if ($actie === 'goedkeuren') {
            wissel_shift($aanvraag); // Rooster bijwerken
            update_shift_aanvraag_status($id, 'Goedgekeurd');
        } elseif ($actie === 'afwijzen') {
            update_shift_aanvraag_status($id, 'Afgewezen');
        }
    }
}

$aanvragen = get_shift_aanvragen();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Shift-aanvragen beheren</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="dashboard-body">
    <main class="dashboard-main">
        <section class="dashboard-card">
            <h1><i class="fa-solid fa-list-check"></i> Shift-aanvragen beheren</h1>
            <?php if (empty($aanvragen)): ?>
                <p>Er zijn momenteel geen shift-aanvragen.</p>
            <?php else: ?>
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Van</th>
                            <th>Naar</th>
                            <th>Dag</th>
                            <th>Week</th>
                            <th>Tijd</th>
                            <th>Locatie</th>
                            <th>Status</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aanvragen as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['gebruiker_van']) ?></td>
                                <td><?= htmlspecialchars($a['gebruiker_naar']) ?></td>
                                <td><?= htmlspecialchars($a['dag']) ?></td>
                                <td><?= htmlspecialchars($a['week']) ?></td>
                                <td><?= htmlspecialchars($a['tijd']) ?></td>
                                <td><?= htmlspecialchars($a['locatie']) ?></td>
                                <td><?= htmlspecialchars($a['status']) ?></td>
                                <td>
                                    <?php if ($a['status'] === 'Ingediend'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="aanvraag_id" value="<?= $a['id'] ?>">
                                            <button name="actie" value="goedkeuren" class="btn-primary">Goedkeuren</button>
                                            <button name="actie" value="afwijzen" class="btn-secondary">Afwijzen</button>
                                        </form>
                                    <?php else: ?>
                                        <em>-</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>