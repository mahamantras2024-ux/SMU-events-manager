<?php
// chat_bootstrap.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
  http_response_code(200); echo json_encode(['ok'=>false,'error'=>'Login required']); exit;
}

$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
if ($eventId <= 0) {
  http_response_code(200); echo json_encode(['ok'=>false,'error'=>'Missing event_id']); exit;
}

try {
  $cm = new ConnectionManager(); $db = $cm->connect();

  // who am i
  $u = $db->prepare("SELECT id, username, role FROM users WHERE username=?");
  $u->execute([$_SESSION['username']]);
  $me = $u->fetch(PDO::FETCH_ASSOC);
  if (!$me) throw new RuntimeException('User not found');

  $userId   = (int)$me['id'];
  $username = (string)$me['username'];
  $isAdmin  = strtolower((string)$me['role']) === 'admin';
  $sbUserId = "user_" . $userId;

  // event title for channel name
  $et = $db->prepare("SELECT title FROM events WHERE id=?");
  $et->execute([$eventId]);
  $event = $et->fetch(PDO::FETCH_ASSOC);
  $eventTitle = $event ? trim((string)$event['title']) : '';
  $channelName = $eventTitle !== '' ? $eventTitle : "Event #$eventId";

  // sendbird helpers
  $SB_HOST  = rtrim(SENDBIRD_API_HOST, '/');
  $SB_TOKEN = SENDBIRD_API_TOKEN;

  $sb = function(string $method, string $path, ?array $body=null) use($SB_HOST,$SB_TOKEN){
    $ch=curl_init(); $url=$SB_HOST.$path;
    $headers=['Api-Token: '.$SB_TOKEN];
    if($method!=='GET') $headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[
      CURLOPT_URL=>$url, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>25,
    ]);
    if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
    $resp=curl_exec($ch); $errno=curl_errno($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($errno!==0) throw new RuntimeException("Sendbird cURL error $errno $method $path");
    $json=$resp?json_decode($resp,true):[];
    if($http>=400) throw new RuntimeException("Sendbird $http: ".(($json['message']??"HTTP $http")));
    return $json;
  };

  // ensure user
  try { $sb('GET', "/v3/users/$sbUserId"); }
  catch(Throwable $e){ $sb('POST','/v3/users',['user_id'=>$sbUserId,'nickname'=>$username]); }

  // ensure channel row
  $sel = $db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $sel->execute([$eventId]); $row = $sel->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $chResp = $sb('POST','/v3/group_channels',[
      'name'        => $channelName,
      'is_distinct' => false,
      'inviter_id'  => $sbUserId,
      'user_ids'    => [$sbUserId],
      'custom_type' => 'event_chat',
      'data'        => json_encode(['event_id'=>$eventId])
    ]);
    $channelUrl = $chResp['channel_url'] ?? '';
    if (!$channelUrl) throw new RuntimeException('Channel creation failed');
    $ins = $db->prepare("INSERT INTO event_chat_channel(event_id, channel_url) VALUES(?,?)");
    $ins->execute([$eventId,$channelUrl]);
  } else {
    $channelUrl = $row['channel_url'];
    // sync channel name if title changed
    $sb('PUT',"/v3/group_channels/$channelUrl",['name'=>$channelName]);
  }

  // ensure membership
  $chan = $sb('GET', "/v3/group_channels/$channelUrl");
  $isMember=false; foreach(($chan['members']??[]) as $m){ if(($m['user_id']??'')===$sbUserId){ $isMember=true; break; } }
  if(!$isMember){ $sb('POST',"/v3/group_channels/$channelUrl/invite",['user_ids'=>[$sbUserId]]); $sb('PUT',"/v3/group_channels/$channelUrl/join"); }

  echo json_encode(['ok'=>true,'channel_url'=>$channelUrl,'user_id'=>$sbUserId,'is_admin'=>$isAdmin,'channel_name'=>$channelName]);

} catch(Throwable $e){
  http_response_code(200);
  error_log('chat_bootstrap: '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}