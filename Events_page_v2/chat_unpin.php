<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) { echo json_encode(['error'=>'Login required']); exit; }

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) { echo json_encode(['error'=>'Missing params']); exit; }

try {
  $cm = new ConnectionManager(); $db = $cm->connect();

  $u = $db->prepare("SELECT id, role FROM users WHERE username=?");
  $u->execute([$_SESSION['username']]);
  $me = $u->fetch(PDO::FETCH_ASSOC);
  if (!$me) throw new RuntimeException('User not found');
  if (strtolower((string)$me['role']) !== 'admin') throw new RuntimeException('Admins only');

  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['channel_url'])) throw new RuntimeException('No channel');
  $chanEnc = rawurlencode($row['channel_url']);

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  foreach (['pinned','pinned_preview'] as $key) {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/metadata/$key",
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Api-Token: ' . $SB_TOKEN],
      CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $cerr = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http >= 400) throw new RuntimeException("Unpin failed ($key) HTTP $http $cerr $resp");
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  error_log('chat_unpin: '.$e->getMessage());
  echo json_encode(['error'=>$e->getMessage()]);
}
