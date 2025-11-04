<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$list_id = (int)($_GET['list_id'] ?? 0);

if ($list_id === 0) {
    exit;
}

// Verify list ownership
$stmt = $mysqli->prepare("SELECT list_name FROM contact_lists WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $list_id, $team_id);
$stmt->execute();
$list = $stmt->get_result()->fetch_assoc();

if (!$list) {
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . slugify($list['list_name']) . '_contacts.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['email', 'first_name', 'last_name', 'created_at']);

$contacts_result = $mysqli->prepare("SELECT c.email, c.first_name, c.last_name, c.created_at FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.team_id = ?");
$contacts_result->bind_param('ii', $list_id, $team_id);
$contacts_result->execute();
$contacts = $contacts_result->get_result();

while ($row = $contacts->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}
