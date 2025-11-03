<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) { echo json_encode(['error'=>'Login required']); exit; }

$eventId   = (int)($_POST['event_id'] ?? 0);
$messageId = (string)($_POST['message_id'] ?? '');
if ($eventId <= 0 || $messageId === '') { echo json_encode(['error'=>'Missing params']); exit; }

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
  $channelUrl = $row['channel_url'];
  $chanEnc = rawurlencode($channelUrl);

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  // fetch message text to store preview
  $get = curl_init();
  curl_setopt_array($get, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/messages/$messageId",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Api-Token: ' . $SB_TOKEN],
    CURLOPT_TIMEOUT => 20,
  ]);
  $g = curl_exec($get);
  $gerr = curl_error($get);
  $gh = (int)curl_getinfo($get, CURLINFO_HTTP_CODE);
  curl_close($get);
  if ($gh >= 400 || !$g) throw new RuntimeException('Message not found: '.$gerr);
  $msg = json_decode($g, true);

  $preview = '';
  if (($msg['message_type'] ?? '') === 'FILE') {
    $preview = $msg['name'] ?? 'file';
  } else {
    $preview = trim((string)($msg['message'] ?? ''));
    if ($preview === '') $preview = '(message)';
  }

  // ✅ Correct body shape for metadata upsert
  $payload = [
    'metadata' => [
      'pinned'         => $messageId,
      'pinned_preview' => $preview
    ],
    'upsert' => true
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/metadata",
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Api-Token: ' . $SB_TOKEN],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20,
  ]);
  $resp = curl_exec($ch);
  $cerr = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($http >= 400) throw new RuntimeException("Pin failed (HTTP $http) $cerr $resp");

  echo json_encode(['ok'=>true, 'pinned'=>$messageId, 'pinned_text'=>$preview]);
} catch (Throwable $e) {
  error_log('chat_pin: '.$e->getMessage());
  echo json_encode(['error'=>$e->getMessage()]);
}
