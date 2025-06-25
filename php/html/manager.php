<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "includes/db.php";
require_once "includes/shiftwissel.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["importeer_vorig_rooster"], $_POST["importweek"])) {
    $huidigeWeek = intval($_POST["importweek"]);
    $_SESSION['selectedWeek'] = $huidigeWeek;
    $vorigeWeek = $huidigeWeek - 1;
    if ($vorigeWeek >= 1) {
        // Haal alle shifts uit de vorige week
        $stmt = $db->prepare("SELECT gebruiker, dag, tijd, locatie FROM rooster WHERE week = :vorigeWeek");
        $stmt->bindValue(":vorigeWeek", $vorigeWeek);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Check of er al een shift bestaat in deze week/dag/locatie/gebruiker
            $check = $db->prepare("SELECT COUNT(*) as aantal FROM rooster WHERE week = :week AND dag = :dag AND locatie = :locatie AND gebruiker = :gebruiker");
            $check->bindValue(":week", $huidigeWeek);
            $check->bindValue(":dag", $row['dag']);
            $check->bindValue(":locatie", $row['locatie']);
            $check->bindValue(":gebruiker", $row['gebruiker']);
            $cresult = $check->execute()->fetchArray(SQLITE3_ASSOC);
            if ($cresult['aantal'] == 0) {
                // Voeg alleen toe als nog niet aanwezig
                $ins = $db->prepare("INSERT INTO rooster (gebruiker, dag, tijd, locatie, week) VALUES (:gebruiker, :dag, :tijd, :locatie, :week)");
                $ins->bindValue(":gebruiker", $row['gebruiker']);
                $ins->bindValue(":dag", $row['dag']);
                $ins->bindValue(":tijd", $row['tijd']);
                $ins->bindValue(":locatie", $row['locatie']);
                $ins->bindValue(":week", $huidigeWeek);
                $ins->execute();
            }
        }
    }
    header("Location: manager.php");
    exit;
}
require_once "includes/ldap.php";
$gebruikers = [];
$foutmelding = null;
$ldap_host = getenv("LDAP_HOST");
$base_dn = getenv("LDAP_BASE_DN");
$ldap_admin_dn = "cn=admin,$base_dn";
$ldap_admin_pass = "nS65tn92sZTGtQB"; // Zet hier het admin-wachtwoord uit je docker-compose.yml

$ds = ldap_connect($ldap_host);
ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

if (!@ldap_bind($ds, $ldap_admin_dn, $ldap_admin_pass)) {
    die("LDAP bind als admin mislukt");
}

$ous = ['Drivers', 'Insiders', 'Managers'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dag"], $_POST["locatie"], $_POST["gebruiker"], $_POST["tijd"], $_POST["week"])) {
    $_SESSION['selectedWeek'] = intval($_POST["week"]);
    // Controle op overlappende shift!
    $gebruiker = $_POST["gebruiker"];
    $dag = $_POST["dag"];
    $week = intval($_POST["week"]);
    $nieuwe_tijd = $_POST["tijd"];
    // Alle bestaande shifttijden van deze werknemer op deze dag+week
    $check = $db->prepare("SELECT tijd FROM rooster WHERE gebruiker = :g AND dag = :d AND week = :w");
    $check->bindValue(":g", $gebruiker);
    $check->bindValue(":d", $dag);
    $check->bindValue(":w", $week);
    $res = $check->execute();
    $conflict = false;
    function tijden_overlappen($a, $b) {
        if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $a, $ma)) return false;
        if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $b, $mb)) return false;
        $start_a = intval($ma[1]) * 60 + intval($ma[2]);
        $end_a   = intval($ma[3]) * 60 + intval($ma[4]);
        $start_b = intval($mb[1]) * 60 + intval($mb[2]);
        $end_b   = intval($mb[3]) * 60 + intval($mb[4]);
        return $start_a < $end_b && $start_b < $end_a;
    }
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if (tijden_overlappen($row["tijd"], $nieuwe_tijd)) {
            $conflict = true;
            break;
        }
    }
    if ($conflict) {
        $foutmelding = "Deze werknemer is al ingepland op dit tijdstip.";
    } else {
        $stmt = $db->prepare("INSERT INTO rooster (gebruiker, dag, tijd, locatie, week) VALUES (:g, :d, :t, :l, :w)");
        $stmt->bindValue(":g", $gebruiker);
        $stmt->bindValue(":d", $dag);
        $stmt->bindValue(":t", $nieuwe_tijd);
        $stmt->bindValue(":l", $_POST["locatie"]);
        $stmt->bindValue(":w", $week);
        $stmt->execute();
        header("Location: manager.php");
        exit;
    }
}
// Manager keurt shiftverzoeken goed of af
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["wissel_actie"], $_POST["aanvraag_id"])) {
    $id = intval($_POST["aanvraag_id"]);
    if ($_POST["wissel_actie"] === "goedkeuren") {
        $aanvraag = get_shift_aanvraag_by_id($id);
        if ($aanvraag) {
            wissel_shift($aanvraag);
            update_shift_aanvraag_status($id, "Goedgekeurd");
        }
    } elseif ($_POST["wissel_actie"] === "afwijzen") {
        update_shift_aanvraag_status($id, "Afgewezen");
    }
    header("Location: manager.php");
    exit;
}

// Tijden toevoegen
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["nieuwe_tijd"], $_POST["week_tijd"])) {
    $stmt = $db->prepare("INSERT INTO tijden (tijd, week) VALUES (:tijd, :week)");
    $stmt->bindValue(":tijd", $_POST["nieuwe_tijd"]);
    $stmt->bindValue(":week", $_POST["week_tijd"]);
    $stmt->execute();
    header("Location: manager.php");
    exit;
}
// Tijd verwijderen
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["verwijder_tijd_id"], $_POST["week"])) {
    $stmt = $db->prepare("DELETE FROM tijden WHERE id = :id AND week = :week");
    $stmt->bindValue(":id", $_POST["verwijder_tijd_id"]);
    $stmt->bindValue(":week", $_POST["week"]);
    $stmt->execute();
    header("Location: manager.php");
    exit;
}
// Shift verwijderen
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {
    if (isset($_POST["week"]) && is_numeric($_POST["week"])) {
        $_SESSION['selectedWeek'] = intval($_POST["week"]);
    }
    $stmt = $db->prepare("DELETE FROM rooster WHERE id = :id");
    $stmt->bindValue(":id", $_POST["delete_id"]);
    $stmt->execute();
    header("Location: manager.php");
    exit;
}
// Shift wijzigen
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_id"], $_POST["edit_gebruiker"], $_POST["edit_tijd"])) {
    if (isset($_POST["week"]) && is_numeric($_POST["week"])) {
        $_SESSION['selectedWeek'] = intval($_POST["week"]);
    }
    $stmt = $db->prepare("UPDATE rooster SET gebruiker = :g, tijd = :t WHERE id = :id");
    $stmt->bindValue(":g", $_POST["edit_gebruiker"]);
    $stmt->bindValue(":t", $_POST["edit_tijd"]);
    $stmt->bindValue(":id", $_POST["edit_id"]);
    $stmt->execute();
    header("Location: manager.php");
    exit;
}


// Gebruikers ophalen
$gebruikers_binnen = [];
$gebruikers_buiten = [];
foreach ($ous as $ou) {
    $ou_dn = "ou=$ou,$base_dn";
    $search = @ldap_search($ds, $ou_dn, "(objectClass=inetOrgPerson)");
    if ($search) {
        $entries = ldap_get_entries($ds, $search);
        for ($i = 0; $i < $entries["count"]; $i++) {
            if (isset($entries[$i]["uid"][0])) {
                $uid = $entries[$i]["uid"][0];
                if ($ou == "Insiders") $gebruikers_binnen[] = $uid;
                if ($ou == "Drivers") $gebruikers_buiten[] = $uid;
                if ($ou == "Managers") {
                    $gebruikers_binnen[] = $uid;
                    $gebruikers_buiten[] = $uid;
                }
            }
        }
    }
}
sort($gebruikers_binnen);
sort($gebruikers_buiten);

// Haal goedgekeurde verlofaanvragen op
$verlof = [];
$verlofRes = $db->query("SELECT gebruiker, van_datum, tot_datum FROM verlofaanvragen WHERE status = 'goedgekeurd'");
while ($row = $verlofRes->fetchArray(SQLITE3_ASSOC)) {
    $verlof[] = $row;
}

// Suggestie functionaliteit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["genereer_suggestie"], $_POST["suggestieweek"])) {
    require_once "includes/roostersuggestie.php";
    $suggestie = genereer_roostersuggestie(
        intval($_POST["suggestieweek"]),
        $gebruikers_binnen,
        $gebruikers_buiten,
        $verlof
    );
    $_SESSION['rooster_suggestie'] = $suggestie;
    $_SESSION['suggestieweek'] = intval($_POST["suggestieweek"]);
    header("Location: manager.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["wis_suggestie"])) {
    unset($_SESSION['rooster_suggestie']);
    unset($_SESSION['suggestieweek']);
    header("Location: manager.php");
    exit;
}
// Helper: check of gebruiker verlof heeft op een dag in deze week
function heeftVerlofOpDag($gebruiker, $dag, $week, $verlof) {
    $dateNow = new DateTime("now", new DateTimeZone('Europe/Amsterdam'));
    $year = $dateNow->format('Y');
    $dagenlijst = ["Maandag", "Dinsdag", "Woensdag", "Donderdag", "Vrijdag", "Zaterdag", "Zondag"];
    $datum = new DateTime();
    $datum->setISODate($year, $week, array_search($dag, $dagenlijst) + 1);
    foreach ($verlof as $v) {
        if ($v['gebruiker'] !== $gebruiker) continue;
        if (empty($v['van_datum']) || empty($v['tot_datum'])) continue;
        if ($datum >= new DateTime($v['van_datum']) && $datum <= new DateTime($v['tot_datum'])) {
            return true;
        }
    }
    return false;
}

if (!isset($_SESSION["user"]) || $_SESSION["role"] !== "manager") {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['week'])) {
    $_SESSION['selectedWeek'] = intval($_POST['week']);
}
if (isset($_SESSION['selectedWeek'])) {
    $selectedWeek = $_SESSION['selectedWeek'];
} else {
    $date = new DateTime("now", new DateTimeZone('Europe/Amsterdam'));
    $selectedWeek = intval($date->format('W'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['week'])) {
    $selectedWeek = intval($_POST['week']);
}

// Beschikbaarheid ophalen voor alle medewerkers
$alleBeschikbaarheid = haal_alle_beschikbaarheid();

// Shiftwissel-aanvragen ophalen
$shiftAanvragen = get_shift_aanvragen();

$dagen = ["Maandag", "Dinsdag", "Woensdag", "Donderdag", "Vrijdag", "Zaterdag", "Zondag"];
$tijden = [];
$res = $db->query("SELECT id, tijd FROM tijden WHERE week = '$selectedWeek' ORDER BY tijd");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $tijden[] = $row;
}

$rooster = $db->query("SELECT * FROM rooster WHERE week = '$selectedWeek' ORDER BY dag, locatie, tijd");
$huidigRooster = [];
while ($row = $rooster->fetchArray(SQLITE3_ASSOC)) {
    $huidigRooster[$row["dag"]][$row["locatie"]][] = $row;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Manager Rooster</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="manager-body">
    <main class="manager-outer">
        <section class="manager-card">
        <?php if (isset($foutmelding) && $foutmelding): ?>
    <div class="manager-foutmelding" id="managerFoutmelding"><?= htmlspecialchars($foutmelding) ?></div>
<?php endif; ?>
            <header class="manager-header">
                <i class="fa-solid fa-calendar-week manager-icon"></i>
                <h1>Manager rooster</h1>
                <p class="manager-subtitle">Plan en wijzig het volledige teamrooster</p>
            </header>
            <form id="weekForm" class="week-select" style="margin-bottom:18px; text-align:center;">
                <label for="week">Week:</label>
                <select name="week" id="week" style="font-size:1rem;">
                    <?php for ($w = 1; $w <= 52; $w++): ?>
                        <option value="<?= $w ?>" <?= $w == $selectedWeek ? 'selected' : '' ?>><?= $w ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <!-- Roostersuggestie-knop en weergave -->
            <form method="post" style="display:inline;">
                <input type="hidden" name="importweek" value="<?= $selectedWeek ?>">
                <button class="btn-primary" type="submit" name="importeer_vorig_rooster">
                         Importeer rooster van vorige week
                </button>
                </form>
            <?php
            if (isset($_SESSION['rooster_suggestie']) && isset($_SESSION['suggestieweek']) && $_SESSION['suggestieweek'] == $selectedWeek) {
                echo "<section class='manager-suggestie'>";
                echo "<h2>Roostersuggestie voor week " . htmlspecialchars($selectedWeek) . "</h2>";
                echo "<div class='suggestie-tabel'>";
                foreach ($dagen as $dag) {
                    echo "<div class='suggestie-row'><div class='suggestie-dag'><strong>$dag</strong></div>";
                    foreach (["binnen", "buiten"] as $locatie) {
                        echo "<div class='suggestie-locatie'>";
                        echo "<span class='loc-label'>" . ucfirst($locatie) . ":</span> ";
                        if (!empty($_SESSION['rooster_suggestie'][$dag][$locatie])) {
                            echo "<span class='loc-namen'>" . implode(", ", array_map('htmlspecialchars', $_SESSION['rooster_suggestie'][$dag][$locatie])) . "</span>";
                        } else {
                            echo "<span class='loc-namen leeg'>–</span>";
                        }
                        echo "</div>";
                    }
                    echo "</div>";
                }
                echo "</div>";
                echo "<form method='post' style='margin-top:14px'><button class='btn-primary' type='submit' name='wis_suggestie'>Sluiten</button></form>";
                echo "</section>";
            }
            ?>

            <div class="manager-gridwrap" id="managerGrid"></div>
            <div class="manager-contract-block">
                <h3>Contractmedewerkers</h3>
                <p>(Later toe te voegen functionaliteit)</p>
            </div>
            <div class="manager-links">
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Terug naar dashboard
                </a>
            </div>

            <section class="manager-shiftwissel">
                <h2><i class="fa-solid fa-right-left"></i> Shiftwissel-verzoeken</h2>
                <?php if (empty($shiftAanvragen)): ?>
                    <p>Er zijn geen openstaande verzoeken.</p>
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
                            <?php foreach ($shiftAanvragen as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['gebruiker_van']) ?></td>
                                    <td><?= htmlspecialchars($v['gebruiker_naar']) ?></td>
                                    <td><?= htmlspecialchars($v['dag']) ?></td>
                                    <td><?= htmlspecialchars($v['week']) ?></td>
                                    <td><?= htmlspecialchars($v['tijd']) ?></td>
                                    <td><?= htmlspecialchars($v['locatie']) ?></td>
                                    <td><?= htmlspecialchars($v['status']) ?></td>
                                    <td>
                                        <?php if ($v['status'] === 'Ingediend'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="aanvraag_id" value="<?= $v['id'] ?>">
                                                <button class="btn-primary" name="wissel_actie" value="goedkeuren">Goedkeuren</button>
                                                <button class="btn-secondary" name="wissel_actie" value="afwijzen">Afwijzen</button>
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
        </section>
    </main>
    <div id="popupBevestiging" class="popup-overlay" style="display:none;">
  <div class="popup-content">
    <p>Weet je zeker dat je deze shift wilt verwijderen?</p>
    <form method="post">
      <input type="hidden" name="delete_id" id="popupDeleteId">
      <input type="hidden" name="week" value="<?= $selectedWeek ?>">
      <button type="submit" class="btn-primary">Verwijderen</button>
      <button type="button" class="btn-secondary" onclick="sluitPopup()">Annuleren</button>
    </form>
  </div>
</div>
</body>
<script>
function toonVerwijderPopup(id) {
  document.getElementById('popupDeleteId').value = id;
  document.getElementById('popupBevestiging').style.display = 'flex';
}
function sluitPopup() {
  document.getElementById('popupBevestiging').style.display = 'none';
}
</script>
<script>
const dagen = ["Maandag", "Dinsdag", "Woensdag", "Donderdag", "Vrijdag", "Zaterdag", "Zondag"];
const locaties = ["binnen", "buiten"];
const gebruikers_binnen = <?= json_encode($gebruikers_binnen) ?>;
const gebruikers_buiten = <?= json_encode($gebruikers_buiten) ?>;

document.getElementById('week').addEventListener('change', function() {
    const week = this.value;
    fetch('includes/api_manager_rooster.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'week=' + encodeURIComponent(week)
    })
    .then(r => r.json())
    .then(data => {
        updateManagerGrid({
            rooster: data.rooster,
            verlof: data.verlof,
            beschikbaarheid: data.beschikbaarheid
        }, week);
        window.history.pushState({week: week}, '', window.location.pathname);
    });
});

window.onpopstate = function(event) {
    if (event.state && event.state.week) {
        document.getElementById('week').value = event.state.week;
        document.getElementById('week').dispatchEvent(new Event('change'));
    }
};

function updateManagerGrid(huidigRooster, selectedWeek) {
    let html = `
        <div class="manager-grid-header"></div>
        <div class="manager-grid-header">Binnen</div>
        <div class="manager-grid-header">Buiten</div>
    `;
    dagen.forEach(function(dag) {
        html += `<div class="manager-grid-rowlabel">${dag}</div>`;
        locaties.forEach(function(locatie) {
            html += `<div class="manager-shift-card">`;
            // Shift toevoegen form
            html += `<form method="post" class="shift-form" onsubmit="return voegShiftToe(event, this);">
                <input type="hidden" name="dag" value="${dag}">
                <input type="hidden" name="locatie" value="${locatie}">
                <input type="hidden" name="week" value="${selectedWeek}">
                <select name="gebruiker" required>
                    <option disabled selected>Werknemer</option>`;
            const lijst = locatie === "binnen" ? gebruikers_binnen : gebruikers_buiten;
            lijst.forEach(function(g) {
                // Verlof check
                let heeftVerlof = false;
                if (huidigRooster.verlof) {
                    heeftVerlof = huidigRooster.verlof.some(v => {
                        if (v.gebruiker !== g) return false;
                        const dagIndex = dagen.indexOf(dag) + 1;
                        const jaar = (new Date()).getFullYear();
                        const datum = new Date(jaar, 0, 1 + (selectedWeek - 1) * 7 + (dagIndex - 1));
                        return new Date(v.van_datum) <= datum && datum <= new Date(v.tot_datum);
                    });
                }
                // Beschikbaarheid check
                const beschikbaar = (!huidigRooster.beschikbaarheid || !huidigRooster.beschikbaarheid[g] || typeof huidigRooster.beschikbaarheid[g][dag] === "undefined" || huidigRooster.beschikbaarheid[g][dag] == 1) && !heeftVerlof;
                const label = `${g}${beschikbaar ? "" : (heeftVerlof ? " (Verlof)" : " (Niet beschikbaar)")}`;
                html += `<option value="${escapeHtml(g)}" ${beschikbaar ? "" : "disabled"}>${escapeHtml(label)}</option>`;
            });
            html += `</select>
                <input type="text" name="tijd" placeholder="Bijv. 15:45-21:03" required>
                <button type="submit"><i class="fa-solid fa-plus"></i></button>
            </form>`;
            // Shiftlijst tonen
            if (huidigRooster.rooster && huidigRooster.rooster[dag] && huidigRooster.rooster[dag][locatie]) {
                html += `<ul class="manager-shift-list">`;
                huidigRooster.rooster[dag][locatie].forEach(function(entry) {
                    html += `<li>
                        ${escapeHtml(entry.gebruiker)} <span>${escapeHtml(entry.tijd)}</span>
                        <form method="post" style="display:inline;" onsubmit="return verwijderShift(event, this);">
                            <input type="hidden" name="delete_id" value="${entry.id}">
                            <button type="button" title="Verwijder shift" onclick="toonVerwijderPopup(${entry.id})"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <details style="display:inline;">
                            <summary style="cursor:pointer;display:inline;"><i class="fa-solid fa-pen"></i></summary>
                            <form method="post" style="display:inline;" onsubmit="return wijzigShift(event, this);">
                                <input type="hidden" name="edit_id" value="${entry.id}">
                                <select name="edit_gebruiker" required>`;
                    lijst.forEach(function(g) {
                        html += `<option value="${escapeHtml(g)}"${entry.gebruiker===g?' selected':''}>${escapeHtml(g)}</option>`;
                    });
                    html += `</select>
                                <input type="text" name="edit_tijd" value="${escapeHtml(entry.tijd)}" required>
                                <button type="submit" title="Wijzig shift"><i class="fa-solid fa-check"></i></button>
                            </form>
                        </details>
                    </li>`;
                });
                html += `</ul>`;
            } else {
                html += `<p class="manager-empty">Geen shifts</p>`;
            }
            html += `</div>`;
        });
    });
    document.getElementById('managerGrid').innerHTML = html;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
        return ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            '`': '&#x60;', '=': '&#x3D;', '/': '&#x2F;'
        })[s];
    });
}
// --- AJAX shift toevoegen/verwijderen/wijzigen functies ---
function voegShiftToe(event, form) {
    event.preventDefault();
    const formData = new FormData(form);
    fetch('manager.php', {
        method: 'POST',
        body: formData
    })
    .then(() => {
        document.getElementById('week').dispatchEvent(new Event('change'));
    });
    return false;
}
function verwijderShift(event, form) {
    event.preventDefault();
    const formData = new FormData(form);
    fetch('manager.php', {
        method: 'POST',
        body: formData
    })
    .then(() => {
        document.getElementById('week').dispatchEvent(new Event('change'));
    });
    return false;
}
function wijzigShift(event, form) {
    event.preventDefault();
    const formData = new FormData(form);
    fetch('manager.php', {
        method: 'POST',
        body: formData
    })
    .then(() => {
        document.getElementById('week').dispatchEvent(new Event('change'));
    });
    return false;
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var foutDiv = document.getElementById('managerFoutmelding');
    if (foutDiv) {
        setTimeout(function() {
            foutDiv.style.transition = 'opacity 0.6s';
            foutDiv.style.opacity = '0';
            setTimeout(function() {
                foutDiv.remove();
            }, 600);
        }, 2000);
    }
});
</script>
</html>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const week = document.getElementById('week').value;
    fetch('includes/api_manager_rooster.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'week=' + encodeURIComponent(week)
    })
    .then(r => r.json())
    .then(data => {
        updateManagerGrid({
            rooster: data.rooster,
            verlof: data.verlof,
            beschikbaarheid: data.beschikbaarheid
        }, week);
    });
});
</script>