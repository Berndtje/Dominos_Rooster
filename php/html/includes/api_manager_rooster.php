<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["week"])) {
    $week = intval($_POST["week"]);
    $roosterRes = $db->query("SELECT * FROM rooster WHERE week = '$week' ORDER BY dag, locatie, tijd");
    $rooster = [];
    while ($row = $roosterRes->fetchArray(SQLITE3_ASSOC)) {
        $rooster[$row["dag"]][$row["locatie"]][] = $row;
    }

    $verlofRes = $db->query("SELECT gebruiker, van_datum, tot_datum FROM verlofaanvragen WHERE status = 'goedgekeurd'");
    $verlof = [];
    while ($row = $verlofRes->fetchArray(SQLITE3_ASSOC)) {
        $verlof[] = [
            "gebruiker" => $row["gebruiker"],
            "van_datum" => $row["van_datum"],
            "tot_datum" => $row["tot_datum"]
        ];
    }

    $beschikbaarheid = haal_alle_beschikbaarheid();

    header("Content-Type: application/json");
    echo json_encode([
        "rooster" => $rooster,
        "verlof" => $verlof,
        "beschikbaarheid" => $beschikbaarheid
    ]);
    exit;
}
?>