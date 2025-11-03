<?php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) { echo json_encode(['error'=>'Login required']); exit; }

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId <= 0) { echo json_encode(['metadata'=>[]]); exit; }

try {
  $cm = new ConnectionManager(); $db = $cm->connect();
  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['channel_url'])) { echo json_encode(['metadata'=>[]]); exit; }

  $chanEnc = rawurlencode($row['channel_url']); // ✅ encode it!

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/metadata",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Api-Token: ' . $SB_TOKEN],
    CURLOPT_TIMEOUT => 20,
  ]);
  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($http >= 400 || !$resp) {
    echo json_encode(['metadata'=>[], 'error'=>'Sendbird request failed']); exit;
  }

  $meta = json_decode($resp, true);
  echo json_encode([
    'metadata'      => $meta,
    'metadata_text' => $meta['pinned_preview'] ?? ''
  ]);
} catch (Throwable $e) {
  echo json_encode(['metadata'=>[], 'error'=>$e->getMessage()]);
}
