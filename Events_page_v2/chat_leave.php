<?php
// chat_leave.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['error'=>'Login required']); exit;
}

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing event_id']); exit; }

try {
  $cm = new ConnectionManager();
  $db = $cm->connect();

  // who am i
  $u = $db->prepare("SELECT id, role FROM users WHERE username=?");
  $u->execute([$_SESSION['username']]);
  $me = $u->fetch(PDO::FETCH_ASSOC);
  if (!$me) { throw new RuntimeException('User not found'); }
  $userId = (int)$me['id'];
  $isAdmin= strtolower((string)$me['role']) === 'admin';

  if ($isAdmin) {
    http_response_code(403);
    echo json_encode(['error'=>'Admins cannot leave the group']);
    return;
  }

  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { throw new RuntimeException('No channel for this event'); }
  $channelUrl = $row['channel_url'];

  $sbUserId = "user_" . $userId;

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  // leave = /leave (Member leaves the channel)
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$channelUrl/leave",
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Api-Token: '.$SB_TOKEN
    ],
    CURLOPT_POSTFIELDS => json_encode(['user_id' => $sbUserId]),
    CURLOPT_TIMEOUT => 15,
  ]);
  $resp = curl_exec($ch);
  $errno= curl_errno($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno !== 0 || $http >= 400) {
    throw new RuntimeException("Leave failed (HTTP $http)");
  }

  echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('chat_leave error: '.$e->getMessage());
  echo json_encode(['error'=>$e->getMessage()]);
}
?>