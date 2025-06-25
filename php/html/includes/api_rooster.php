<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user"])) {
    http_response_code(403);
    echo json_encode(["error" => "Niet ingelogd"]);
    exit;
}

$user = $_SESSION["user"];
$week = isset($_POST["week"]) ? intval($_POST["week"]) : intval(date('W'));
$rooster = get_rooster($user, $week);

header('Content-Type: application/json');
echo json_encode($rooster);