<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) { echo json_encode(['members'=>[], 'error'=>'Login required']); exit; }

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId <= 0) { echo json_encode(['members'=>[]]); exit; }

try {
  $cm = new ConnectionManager(); $db = $cm->connect();

  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['channel_url'])) { echo json_encode(['members'=>[]]); exit; }

  $chanEnc = rawurlencode($row['channel_url']); // ✅
  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  // ✅ Use members endpoint
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/members?limit=100",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Api-Token: ' . $SB_TOKEN],
    CURLOPT_TIMEOUT => 20,
  ]);
  $resp = curl_exec($ch);
  $cerr = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($http >= 400 || !$resp) {
    echo json_encode(['members'=>[], 'error'=>"Sendbird request failed ($http) $cerr"]); exit;
  }

  $json = json_decode($resp, true);
  $members = [];
  foreach (($json['members'] ?? []) as $m) {
    $members[] = [
      'user_id'  => $m['user_id'] ?? '',
      'nickname' => $m['nickname'] ?? ($m['user_id'] ?? ''),
    ];
  }

  echo json_encode(['members' => $members]);
} catch (Throwable $e) {
  error_log('chat_members: '.$e->getMessage());
  echo json_encode(['members'=>[], 'error'=>$e->getMessage()]);
}
