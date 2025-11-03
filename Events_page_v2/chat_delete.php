<?php
// chat_delete.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['username'])){ http_response_code(401); echo json_encode(['error'=>'Login required']); exit; }

$eventId   = (int)($_POST['event_id'] ?? 0);
$messageId = (string)($_POST['message_id'] ?? '');
if($eventId<=0 || $messageId===''){ http_response_code(400); echo json_encode(['error'=>'Missing params']); exit; }

try{
  $cm=new ConnectionManager(); $db=$cm->connect();
  $u=$db->prepare("SELECT id FROM users WHERE username=?"); $u->execute([$_SESSION['username']]);
  $me=$u->fetch(PDO::FETCH_ASSOC); if(!$me) throw new RuntimeException('User not found');
  $sbUserId="user_".(int)$me['id'];

  $q=$db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?");
  $q->execute([$eventId]); $row=$q->fetch(PDO::FETCH_ASSOC); if(!$row) throw new RuntimeException('No channel');
  $channelUrl=$row['channel_url'];

  $SB_HOST=rtrim(SENDBIRD_API_HOST,'/'); $SB_TOKEN=SENDBIRD_API_TOKEN;

  // check author
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
  if(($msg['user']['user_id'] ?? '') !== $sbUserId) throw new RuntimeException('You can only delete your messages');

  // delete
  $ch=curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL=>$SB_HOST."/v3/group_channels/$channelUrl/messages/$messageId",
    CURLOPT_CUSTOMREQUEST=>'DELETE',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Api-Token: '.$SB_TOKEN],
    CURLOPT_TIMEOUT=>20,
  ]);
  $resp=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($http>=400) throw new RuntimeException('Delete failed');
  echo json_encode(['ok'=>true]);

}catch(Throwable $e){
  http_response_code(200); echo json_encode(['error'=>$e->getMessage()]);
}