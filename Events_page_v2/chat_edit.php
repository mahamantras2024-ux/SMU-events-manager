<?php
// chat_edit.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['username'])){ http_response_code(401); echo json_encode(['error'=>'Login required']); exit; }

$eventId   = (int)($_POST['event_id'] ?? 0);
$messageId = (string)($_POST['message_id'] ?? '');
$newText   = trim((string)($_POST['message'] ?? ''));
if($eventId<=0 || $messageId==='' || $newText===''){ http_response_code(400); echo json_encode(['error'=>'Missing params']); exit; }

try{
  $cm=new ConnectionManager(); $db=$cm->connect();
  $u=$db->prepare("SELECT id FROM users WHERE username=?"); $u->execute([$_SESSION['username']]);
  $me=$u->fetch(PDO::FETCH_ASSOC); if(!$me) throw new RuntimeException('User not found');
  $sbUserId="user_".(int)$me['id'];

  $q=$db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]); $row=$q->fetch(PDO::FETCH_ASSOC); if(!$row) throw new RuntimeException('No channel');
  $channelUrl=$row['channel_url'];

  $SB_HOST=rtrim(SENDBIRD_API_HOST,'/'); $SB_TOKEN=SENDBIRD_API_TOKEN;

  // fetch message -> author + age
  $get=curl_init();
  curl_setopt_array($get,[
    CURLOPT_URL=>$SB_HOST."/v3/group_channels/$channelUrl/messages/$messageId",
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Api-Token: '.$SB_TOKEN],
    CURLOPT_TIMEOUT=>20,
  ]);
  $g=curl_exec($get); $gh=(int)curl_getinfo($get,CURLINFO_HTTP_CODE); curl_close($get);
  if($gh>=400 || !$g) throw new RuntimeException('Message not found');
  $msg=json_decode($g,true);
  $owner=$msg['user']['user_id'] ?? '';
  $createdMs=(int)($msg['created_at'] ?? 0);

  if($owner !== $sbUserId) throw new RuntimeException('You can only edit your messages');
  if($createdMs>0 && (time()*1000 - $createdMs) > 15*60*1000) throw new RuntimeException('Edit window expired');

  // update
  $payload=['message_type'=>'MESG','user_id'=>$sbUserId,'message'=>$newText];
  $ch=curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL=>$SB_HOST."/v3/group_channels/$channelUrl/messages/$messageId",
    CURLOPT_CUSTOMREQUEST=>'PUT',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Api-Token: '.$SB_TOKEN],
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_TIMEOUT=>20,
  ]);
  $resp=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($http>=400) throw new RuntimeException('Edit failed');
  echo $resp ?: json_encode(['ok'=>true]);

}catch(Throwable $e){
  http_response_code(200); echo json_encode(['error'=>$e->getMessage()]);
}