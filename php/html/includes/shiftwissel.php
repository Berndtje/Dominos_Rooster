<?php
require_once "db.php";


function get_shift_aanvragen() {
    global $db;
    $res = $db->query("SELECT * FROM shift_aanvragen ORDER BY id DESC");
    $result = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $result[] = $row;
    }
    return $result;
}

function get_shift_aanvraag_by_id($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM shift_aanvragen WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $res = $stmt->execute();
    return $res->fetchArray(SQLITE3_ASSOC);
}

function update_shift_aanvraag_status($id, $status) {
    global $db;
    $stmt = $db->prepare("UPDATE shift_aanvragen SET status = :s WHERE id = :id");
    $stmt->bindValue(":s", $status);
    $stmt->bindValue(":id", $id);
    $stmt->execute();
}

function wissel_shift($aanvraag) {
    global $db;

    // Verwijder oorspronkelijke shift van 'gebruiker_van'
    $stmtDelVan = $db->prepare("DELETE FROM rooster WHERE gebruiker = :g AND dag = :d AND week = :w AND tijd = :t AND locatie = :l");
    $stmtDelVan->bindValue(":g", $aanvraag['gebruiker_van']);
    $stmtDelVan->bindValue(":d", $aanvraag['dag']);
    $stmtDelVan->bindValue(":w", $aanvraag['week']);
    $stmtDelVan->bindValue(":t", $aanvraag['tijd']);
    $stmtDelVan->bindValue(":l", $aanvraag['locatie']);
    $stmtDelVan->execute();

    // Verwijder bestaande shift van 'gebruiker_naar' op dezelfde dag/week/locatie
    $stmtDelNaar = $db->prepare("DELETE FROM rooster WHERE gebruiker = :g AND dag = :d AND week = :w AND locatie = :l");
    $stmtDelNaar->bindValue(":g", $aanvraag['gebruiker_naar']);
    $stmtDelNaar->bindValue(":d", $aanvraag['dag']);
    $stmtDelNaar->bindValue(":w", $aanvraag['week']);
    $stmtDelNaar->bindValue(":l", $aanvraag['locatie']);
    $stmtDelNaar->execute();

    // Voeg shift van gebruiker_naar toe aan gebruiker_van (tijd moet dan ook omgewisseld worden)
    $stmtGetTijd = $db->prepare("SELECT tijd FROM rooster WHERE gebruiker = :g AND dag = :d AND week = :w AND locatie = :l LIMIT 1");
    $stmtGetTijd->bindValue(":g", $aanvraag['gebruiker_naar']);
    $stmtGetTijd->bindValue(":d", $aanvraag['dag']);
    $stmtGetTijd->bindValue(":w", $aanvraag['week']);
    $stmtGetTijd->bindValue(":l", $aanvraag['locatie']);
    $tijdResult = $stmtGetTijd->execute();
    $tijdData = $tijdResult->fetchArray(SQLITE3_ASSOC);
    $tijdVanNaar = $tijdData ? $tijdData['tijd'] : null;

    if ($tijdVanNaar) {
        $stmtAddToVan = $db->prepare("INSERT INTO rooster (gebruiker, dag, week, tijd, locatie) VALUES (:g, :d, :w, :t, :l)");
        $stmtAddToVan->bindValue(":g", $aanvraag['gebruiker_van']);
        $stmtAddToVan->bindValue(":d", $aanvraag['dag']);
        $stmtAddToVan->bindValue(":w", $aanvraag['week']);
        $stmtAddToVan->bindValue(":t", $tijdVanNaar);
        $stmtAddToVan->bindValue(":l", $aanvraag['locatie']);
        $stmtAddToVan->execute();
    }

    // Voeg originele shift toe aan 'gebruiker_naar'
    $stmtAdd = $db->prepare("INSERT INTO rooster (gebruiker, dag, week, tijd, locatie) VALUES (:g, :d, :w, :t, :l)");
    $stmtAdd->bindValue(":g", $aanvraag['gebruiker_naar']);
    $stmtAdd->bindValue(":d", $aanvraag['dag']);
    $stmtAdd->bindValue(":w", $aanvraag['week']);
    $stmtAdd->bindValue(":t", $aanvraag['tijd']);
    $stmtAdd->bindValue(":l", $aanvraag['locatie']);
    $stmtAdd->execute();
}
?>