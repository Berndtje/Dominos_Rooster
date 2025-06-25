<?php
$db = new SQLite3("/var/www/html/db/database.sqlite");

function get_rooster($username, $week = null) {
    global $db;
    if ($week === null) {
        // Oude functionaliteit: alles voor gebruiker
        $stmt = $db->prepare("SELECT * FROM rooster WHERE gebruiker = :gebruiker");
        $stmt->bindValue(":gebruiker", $username);
    } else {
        // Nieuwe functionaliteit: alleen voor geselecteerde week
        $stmt = $db->prepare("SELECT * FROM rooster WHERE gebruiker = :gebruiker AND week = :week");
        $stmt->bindValue(":gebruiker", $username);
        $stmt->bindValue(":week", $week);
    }
    $res = $stmt->execute();
    $result = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $result[] = $row;
    }
    return $result;
}

function aanvraag_verlof($gebruiker, $van_datum, $tot_datum, $reden) {
    global $db;
    $stmt = $db->prepare("INSERT INTO verlofaanvragen (gebruiker, van_datum, tot_datum, reden, status) VALUES (:g, :v, :t, :r, 'Ingediend')");
    $stmt->bindValue(":g", $gebruiker);
    $stmt->bindValue(":v", $van_datum);
    $stmt->bindValue(":t", $tot_datum);
    $stmt->bindValue(":r", $reden);
    $stmt->execute();
}

function get_verlofaanvragen() {
    global $db;
    $res = $db->query("SELECT * FROM verlofaanvragen");
    $result = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $result[] = $row;
    }
    return $result;
}

function update_verlof_status($id, $status) {
    global $db;
    $stmt = $db->prepare("UPDATE verlofaanvragen SET status = :status WHERE id = :id");
    $stmt->bindValue(":status", $status);
    $stmt->bindValue(":id", $id);
    $stmt->execute();
}
// Opslaan van algemene beschikbaarheid
function zet_beschikbaarheid($gebruiker, $dag, $beschikbaar) {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO beschikbaarheid (gebruiker, dag, beschikbaar) VALUES (:g, :d, :b)");
    $stmt->bindValue(":g", $gebruiker);
    $stmt->bindValue(":d", $dag);
    $stmt->bindValue(":b", $beschikbaar);
    $stmt->execute();
}

// Ophalen van algemene beschikbaarheid
function haal_beschikbaarheid($gebruiker) {
    global $db;
    $stmt = $db->prepare("SELECT dag, beschikbaar FROM beschikbaarheid WHERE gebruiker = :g");
    $stmt->bindValue(":g", $gebruiker);
    $res = $stmt->execute();
    $resultaat = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $resultaat[$row["dag"]] = $row["beschikbaar"];
    }
    return $resultaat;
}

// Alle gebruikers en hun algemene beschikbaarheid voor managers
function haal_alle_beschikbaarheid() {
    global $db;
    $stmt = $db->prepare("SELECT gebruiker, dag, beschikbaar FROM beschikbaarheid");
    $res = $stmt->execute();
    $resultaat = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $resultaat[$row["gebruiker"]][$row["dag"]] = $row["beschikbaar"];
    }
    return $resultaat;
}
function voeg_shift_aanvraag_toe($gebruiker_van, $gebruiker_naar, $dag, $week, $tijd, $locatie) {
    global $db;
    $stmt = $db->prepare("INSERT INTO shift_aanvragen (gebruiker_van, gebruiker_naar, dag, week, tijd, locatie, status) VALUES (:van, :naar, :dag, :week, :tijd, :locatie, 'Ingediend')");
    $stmt->bindValue(":van", $gebruiker_van);
    $stmt->bindValue(":naar", $gebruiker_naar);
    $stmt->bindValue(":dag", $dag);
    $stmt->bindValue(":week", $week);
    $stmt->bindValue(":tijd", $tijd);
    $stmt->bindValue(":locatie", $locatie);
    $stmt->execute();
}

function get_all_users_except($username) {
    global $db;
    $stmt = $db->prepare("SELECT DISTINCT gebruiker FROM rooster WHERE gebruiker != :username");
    $stmt->bindValue(":username", $username);
    $res = $stmt->execute();
    $gebruikers = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $gebruikers[] = $row["gebruiker"];
    }
    return $gebruikers;
}
?>
