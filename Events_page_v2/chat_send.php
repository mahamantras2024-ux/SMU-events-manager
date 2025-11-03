<?php
// chat_send.php (diagnostic logging version)
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

function log_err(string $msg) {
  error_log('[chat_send] ' . $msg . "\n", 3, __DIR__ . 'chat_send.log');
}

if (!isset($_SESSION['username'])) { http_response_code(401); echo json_encode(['error'=>'Login required']); exit; }

$eventId = (int)($_POST['event_id'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));
if ($eventId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing event_id']); exit; }

try {
  $cm = new ConnectionManager(); $db = $cm->connect();

  $u = $db->prepare("SELECT id, username FROM users WHERE username=?");
  $u->execute([$_SESSION['username']]);
  $me = $u->fetch(PDO::FETCH_ASSOC);
  if (!$me) throw new RuntimeException('User not found');

  $userId   = (int)$me['id'];
  $sbUserId = "user_" . $userId;

  $q = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['channel_url'])) throw new RuntimeException('Channel not ready for this event');

  $chanEnc = rawurlencode($row['channel_url']);
  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  $hasFile = isset($_FILES['file']) && is_array($_FILES['file'])
           && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
           && is_uploaded_file($_FILES['file']['tmp_name']);

  if ($hasFile) {
    $origName = $_FILES['file']['name'] ?: 'file';
    $mime     = $_FILES['file']['type'] ?: 'application/octet-stream';
    $cfile    = new CURLFile($_FILES['file']['tmp_name'], $mime, $origName);

    $fields = [
      'message_type'  => 'FILE',
      'user_id'       => $sbUserId,
      'file'          => $cfile,         // <- the actual file
      'file_name'     => $origName,      // <- SB-friendly field
    ];
    if ($message !== '') $fields['message'] = $message;
    if (strpos($mime, 'image/') === 0) $fields['thumbnail_sizes'] = json_encode([[320,320],[640,640]]);

    $url = $SB_HOST . "/v3/group_channels/$chanEnc/messages";
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Api-Token: '.$SB_TOKEN], // let cURL set multipart
      CURLOPT_POSTFIELDS => $fields,
      CURLOPT_TIMEOUT => 90,
    ]);
    $resp = curl_exec($ch);
    $cerr = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http >= 400 || $resp === false) {
      log_err("HTTP $http FILE send failed. cURL='$cerr' url='$url'. Fields=".print_r([
        'message_type'=>'FILE','user_id'=>$sbUserId,'file_name'=>$origName,'mime'=>$mime
      ], true) . " Body=" . substr((string)$resp,0,500));
      throw new RuntimeException("Sendbird FILE failed (HTTP $http)");
    }
    echo $resp; exit;
  }

  // TEXT branch
  $payload = ['message_type'=>'MESG','user_id'=>$sbUserId,'message'=>$message];
  $url = $SB_HOST . "/v3/group_channels/$chanEnc/messages";
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json','Api-Token: '.$SB_TOKEN],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
  ]);
  $resp = curl_exec($ch);
  $cerr = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http >= 400 || $resp === false) {
    log_err("HTTP $http TEXT send failed. cURL='$cerr' url='$url'. Payload=" . json_encode($payload) . " Body=" . substr((string)$resp,0,500));
    throw new RuntimeException("Sendbird TEXT failed (HTTP $http)");
  }

  echo $resp ?: json_encode(['ok'=>true]);

} catch (Throwable $e) {
  log_err('Exception: ' . $e->getMessage());
  // Return 200 but with error field so UI can alert
  http_response_code(200);
  echo json_encode(['error'=>$e->getMessage()]);
}
