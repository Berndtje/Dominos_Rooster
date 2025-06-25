<?php
session_start();
require_once "includes/db.php";
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
$eigenRooster = get_rooster($user);
$alleGebruikers = get_all_users_except($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    list($dag, $week, $tijd, $locatie) = explode('|', $_POST['shift']);
    voeg_shift_aanvraag_toe($user, $_POST['gebruiker_naar'], $dag, $week, $tijd, $locatie);
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <title>Shift Ruilen</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="dashboard-body">
  <main class="dashboard-main">
    <section class="dashboard-card">
      <h1><i class="fa-solid fa-people-arrows"></i> Shift ruilen</h1>
      <?php if (!empty($success)): ?>
        <p style="color: green;">Je aanvraag is ingediend!</p>
      <?php endif; ?>
      <form method="post">
        <label>Jouw shift:</label>
        <select name="shift" required>
          <?php foreach ($eigenRooster as $r): ?>
            <?php
              $shiftValue = implode('|', [$r['dag'], $r['week'], $r['tijd'], $r['locatie']]);
              $shiftLabel = "{$r['dag']} ({$r['tijd']}, {$r['locatie']}, week {$r['week']})";
            ?>
            <option value="<?= $shiftValue ?>"><?= $shiftLabel ?></option>
          <?php endforeach; ?>
        </select>
        <label>Ruilen met:</label>
        <select name="gebruiker_naar" required>
          <?php foreach ($alleGebruikers as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary">Aanvraag indienen</button>
      </form>
      <a href="dashboard.php" class="btn-secondary" style="margin-top: 10px; display: inline-block;">← Terug naar dashboard</a>
    </section>
  </main>
</body>
</html>