<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','1');
session_start();

require_once 'db_connect.php';
require_once 'config.php'; // defines SENDBIRD_APP_ID / SENDBIRD_API_TOKEN

// ── 1) Gate: must be logged in + admin ────────────────────────────────────────
$cm = new ConnectionManager();
$db = $cm->connect();

if (!isset($_SESSION['username'])) {
  http_response_code(401); exit('Login required');
}
$roleStmt = $db->prepare("SELECT role users WHERE username = ?");
$roleStmt->execute([(int)$_SESSION['username']]);
$userRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRow || $userRow['role'] !== 'admin') {
  http_response_code(403); exit('Admins only');
}

// ── 2) Sendbird helpers ──────────────────────────────────────────────────────
$APP_ID    = SENDBIRD_APP_ID;
$API_TOKEN = SENDBIRD_API_TOKEN;
$API_BASE  = "https://api-{$APP_ID}.sendbird.com/v3";

$sb_post = function(string $url, array $payload) use ($API_TOKEN) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Api-Token: $API_TOKEN", "Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  if ($res === false || $code >= 300) {
    throw new RuntimeException("Sendbird error $code: " . ($res ?: $err ?: 'no response'));
  }
  return json_decode($res, true);
};

// ── 3) Find events with NO chat yet ──────────────────────────────────────────
// If your table is named `event` (singular), change the FROM table below.
$missing = $db->query("
  SELECT e.id
  FROM events e
  LEFT JOIN event_chat_channel c ON c.event_id = e.id
  WHERE c.event_id IS NULL
")->fetchAll(PDO::FETCH_COLUMN);

// Build initial members = all admins
$adminIds = $db->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$adminMembers = array_values(array_unique(array_map(fn($id)=>"user_".(int)$id, $adminIds)));

$created = [];
foreach ($missing as $eid) {
  // Create channel in Sendbird
  $resp = $sb_post("$API_BASE/group_channels", [
    "name"        => "Event #$eid Q&A",
    "user_ids"    => $adminMembers,                 // only admins initially
    "custom_type" => "event_chat",
    "data"        => json_encode(["event_id" => (int)$eid], JSON_UNESCAPED_SLASHES),
  ]);

  $channelUrl = $resp['channel_url'];

  // Save mapping locally
  $ins = $db->prepare("INSERT INTO event_chat_channel(event_id, channel_url) VALUES(?, ?)");
  $ins->execute([(int)$eid, $channelUrl]);

  $created[] = ["event_id" => (int)$eid, "channel_url" => $channelUrl];
}

// ── 4) Done ──────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
echo json_encode(["created" => $created], JSON_UNESCAPED_SLASHES);
