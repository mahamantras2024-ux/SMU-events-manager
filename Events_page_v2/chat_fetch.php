<?php
// chat_fetch.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['error'=>'Login required']); exit;
}

$eventId = (int)($_GET['event_id'] ?? 0);
$limit   = (int)($_GET['limit'] ?? 50);
if ($eventId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing event_id']); exit; }
$limit = max(1, min(100, $limit));

try {
  $cm = new ConnectionManager();
  $db = $cm->connect();

  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { throw new RuntimeException('No channel for this event'); }
  $channelUrl = $row['channel_url'];

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  $nowMs = (int)(microtime(true) * 1000);
  $url = $SB_HOST . "/v3/group_channels/$channelUrl/messages?message_ts=$nowMs&prev_limit=$limit";

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Api-Token: '.$SB_TOKEN],
    CURLOPT_TIMEOUT => 15,
  ]);
  $resp = curl_exec($ch);
  $errno= curl_errno($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno !== 0 || $http >= 400) {
    throw new RuntimeException("Sendbird fetch failed (HTTP $http)");
  }

  echo $resp ?: json_encode(['messages'=>[]]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('chat_fetch error: '.$e->getMessage());
  echo json_encode(['error'=>$e->getMessage()]);
}
?>