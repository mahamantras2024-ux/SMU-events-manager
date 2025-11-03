<?php
// chat_kick.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) { echo json_encode(['error'=>'Login required']); exit; }

$eventId = (int)($_POST['event_id'] ?? 0);
$targetLocalId = (int)($_POST['target_user_id'] ?? 0); // numeric (e.g., 42)
if ($eventId <= 0 || $targetLocalId <= 0) { echo json_encode(['error'=>'Missing params']); exit; }

try {
  $cm = new ConnectionManager(); $db = $cm->connect();

  // who am i
  $u = $db->prepare("SELECT id, role FROM users WHERE username=?");
  $u->execute([$_SESSION['username']]);
  $me = $u->fetch(PDO::FETCH_ASSOC);
  if (!$me) throw new RuntimeException('User not found');
  $myLocalId = (int)$me['id'];
  $isAdmin = strtolower((string)$me['role']) === 'admin';
  if (!$isAdmin) throw new RuntimeException('Admins only');

  // cannot kick self
  if ($targetLocalId === $myLocalId) throw new RuntimeException("You can't kick yourself");

  // channel
  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['channel_url'])) throw new RuntimeException('No channel');

  $channelUrl = $row['channel_url'];
  $chanEnc = rawurlencode($channelUrl);
  $targetSbId = 'user_' . $targetLocalId;
  $targetEnc  = rawurlencode($targetSbId);

  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  // Remove member (kick)
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/members/$targetEnc",
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Api-Token: '.$SB_TOKEN],
    CURLOPT_TIMEOUT => 20,
  ]);
  $resp = curl_exec($ch);
  $cerr = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http >= 400) {
    // Optional fallback: hard-kick by banning once (removes immediately)
    if ($http === 404) {
      // if user is already out, treat as ok
      echo json_encode(['ok'=>true, 'note'=>'User not in channel']); exit;
    }
    // try a one-shot ban to force removal then unban
    $ban = curl_init();
    curl_setopt_array($ban, [
      CURLOPT_URL => $SB_HOST . "/v3/group_channels/$chanEnc/ban",
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json','Api-Token: '.$SB_TOKEN],
      CURLOPT_POSTFIELDS => json_encode(['user_id'=>$targetSbId, 'seconds'=>1]),
      CURLOPT_TIMEOUT => 20,
    ]);
    $bresp = curl_exec($ban); $bhttp = (int)curl_getinfo($ban, CURLINFO_HTTP_CODE); curl_close($ban);
    if ($bhttp >= 400) throw new RuntimeException("Kick failed ($http/$bhttp) $cerr $resp");
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  error_log('chat_kick: '.$e->getMessage());
  echo json_encode(['error'=>$e->getMessage()]);
}
