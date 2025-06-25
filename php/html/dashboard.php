<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['week'])) {
    $_SESSION['selectedWeek'] = intval($_POST['week']);
}
require_once "includes/db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION["user"];
if (isset($_SESSION['selectedWeek'])) {
    $selectedWeek = $_SESSION['selectedWeek'];
} else {
    $selectedWeek = intval(date('W'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['week'])) {
    $selectedWeek = intval($_POST['week']);
}
$rooster = get_rooster($user, $selectedWeek);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Rooster</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <main class="dashboard-main">
        <section class="dashboard-card">
            <form id="weekForm" class="week-select" style="margin-bottom:18px; text-align:center;">
                <label for="week">Week:</label>
                <select name="week" id="week" style="font-size:1rem;">
                    <?php for ($w = 1; $w <= 52; $w++): ?>
                        <option value="<?= $w ?>" <?= $w == $selectedWeek ? 'selected' : '' ?>><?= $w ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <header class="dashboard-header">
                <i class="fa-solid fa-user dashboard-icon"></i>
                <h1>Welkom, <?= htmlspecialchars($user) ?></h1>
                <p class="dashboard-subtitle">Dit is jouw persoonlijke rooster</p>
            </header>
            <h2 class="dashboard-section-title"><i class="fa-solid fa-calendar-days"></i> Jouw rooster</h2>
            <?php
            // 4x2 grid rooster
            $dagnamen = ["Ma", "Di", "Wo", "Do", "Vr", "Za", "Zo"];
            $dagrooster = [];
            for ($x = 0; $x < 7; $x++) {
                $dagrooster[$x] = null; // leeg voor elke dag
            }
            $totaalUren = 0.0;
            foreach ($rooster as $dag) {
                $i = array_search(substr($dag["dag"], 0, 2), $dagnamen);
                if ($i !== false) {
                    $dagrooster[$i] = $dag;
                    // Uren optellen alleen als deze dag in de huidige week zit
                    if (preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $dag["tijd"], $m)) {
                        $start = (int)$m[1]*60 + (int)$m[2];
                        $end = (int)$m[3]*60 + (int)$m[4];
                        $uren = max(0, ($end-$start)/60);
                        $totaalUren += $uren;
                    }
                }
            }
            ?>
            <div class="dashboard-weekgrid" id="roosterGrid">
              <?php for ($rij = 0; $rij < 2; $rij++): ?>
                <?php for ($kol = 0; $kol < 4; $kol++): ?>
                  <?php $i = $kol + $rij*4; ?>
                  <?php if ($i < 7): ?>
                    <div class="dashboard-daycell">
                      <div class="grid-daylabel"><?= $dagnamen[$i] ?></div>
                      <?php if (isset($dagrooster[$i])): ?>
                        <div class="grid-time"><?= htmlspecialchars($dagrooster[$i]["tijd"]) ?></div>
                        <div class="grid-loc"><?= htmlspecialchars($dagrooster[$i]["locatie"]) ?></div>
                      <?php else: ?>
                        <div class="grid-empty">-</div>
                      <?php endif; ?>
                    </div>
                  <?php elseif ($rij === 1 && $kol === 3): ?>
                    <div class="dashboard-totalcell">
                      <div class="grid-daylabel">Totaal uren</div>
                      <div class="grid-time" style="font-size:1.3em;font-weight:600;"><?= number_format($totaalUren, 2, ',', '.') ?> u</div>
                    </div>
                  <?php endif; ?>
                <?php endfor; ?>
              <?php endfor; ?>
            </div>
            <div class="dashboard-links">
                <a class="btn-secondary" href="beschikbaarheid.php"><i class="fa-solid fa-calendar-days"></i> Beschikbaarheid aangeven</a>
                <a class="btn-primary" href="verlof.php"><i class="fa-solid fa-plane-departure"></i> Verlof aanvragen</a>
                <a class="btn-primary" href="shift_ruilen.php"><i class="fa-solid fa-right-left"></i> Shiftwissels</a>
                <?php if ($_SESSION["role"] === "manager"): ?>
                    <a class="btn-primary" href="manager.php"><i class="fa-solid fa-user-tie"></i> Managerpagina</a>
                    <a class="btn-primary" href="verlofgoedkeuring.php"><i class="fa-solid fa-calendar-check"></i> Verlof goedkeuren</a>
                <?php endif; ?>
                <a class="btn-secondary" href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Uitloggen</a>
            </div>
        </section>
    </main>
</body>
<script>
const dagnamen = ["Ma", "Di", "Wo", "Do", "Vr", "Za", "Zo"];

document.getElementById('week').addEventListener('change', function() {
    const week = this.value;
    fetch('includes/api_rooster.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'week=' + encodeURIComponent(week)
    })
    .then(r => r.json())
    .then(data => {
        updateRoosterGrid(data, week);
        window.history.pushState({week: week}, '', window.location.pathname);
    });
});

window.onpopstate = function(event) {
    if (event.state && event.state.week) {
        document.getElementById('week').value = event.state.week;
        document.getElementById('week').dispatchEvent(new Event('change'));
    }
};

if (window.history.state && window.history.state.week) {
    document.getElementById('week').value = window.history.state.week;
}

function updateRoosterGrid(rooster, week) {
    let dagrooster = [];
    for (let x = 0; x < 7; x++) dagrooster[x] = null;
    let totaalUren = 0.0;
    rooster.forEach(dag => {
        let i = dagnamen.indexOf(dag["dag"].substring(0,2));
        if (i !== -1) {
            dagrooster[i] = dag;
            let match = dag["tijd"].match(/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/);
            if (match) {
                let start = parseInt(match[1])*60 + parseInt(match[2]);
                let end = parseInt(match[3])*60 + parseInt(match[4]);
                let uren = Math.max(0, (end-start)/60);
                totaalUren += uren;
            }
        }
    });
    let html = '';
    for (let rij = 0; rij < 2; rij++) {
        for (let kol = 0; kol < 4; kol++) {
            let i = kol + rij*4;
            if (i < 7) {
                html += '<div class="dashboard-daycell">';
                html += `<div class="grid-daylabel">${dagnamen[i]}</div>`;
                if (dagrooster[i]) {
                    html += `<div class="grid-time">${escapeHtml(dagrooster[i]["tijd"]||'')}</div>`;
                    html += `<div class="grid-loc">${escapeHtml(dagrooster[i]["locatie"]||'')}</div>`;
                } else {
                    html += '<div class="grid-empty">-</div>';
                }
                html += '</div>';
            } else if (rij === 1 && kol === 3) {
                html += `<div class="dashboard-totalcell">
                    <div class="grid-daylabel">Totaal uren</div>
                    <div class="grid-time" style="font-size:1.3em;font-weight:600;">${totaalUren.toLocaleString('nl-NL', {minimumFractionDigits:2, maximumFractionDigits:2})} u</div>
                </div>`;
            }
        }
    }
    document.getElementById('roosterGrid').innerHTML = html;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
        return ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            '`': '&#x60;', '=': '&#x3D;', '/': '&#x2F;'
        })[s];
    });
}
</script>
</html>