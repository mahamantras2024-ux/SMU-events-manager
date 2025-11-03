<?php
// chat_unread.php
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['username'])){ echo json_encode(['channels'=>[]]); exit; }

$action = $_GET['action'] ?? 'list';
$eventId = (int)($_GET['event_id'] ?? 0);

try{
  $cm=new ConnectionManager(); $db=$cm->connect();
  $u=$db->prepare("SELECT id FROM users WHERE username=?"); $u->execute([$_SESSION['username']]);
  $me=$u->fetch(PDO::FETCH_ASSOC); if(!$me) throw new RuntimeException('User not found');
  $sbUserId="user_".(int)$me['id'];

  $SB_HOST=rtrim(SENDBIRD_API_HOST,'/'); $SB_TOKEN=SENDBIRD_API_TOKEN;

  if($action==='mark' && $eventId>0){
    $q=$db->prepare("SELECT channel_url FROM event_chat_channel WHERE event_id=?"); $q->execute([$eventId]);
    $row=$q->fetch(PDO::FETCH_ASSOC); if(!$row) throw new RuntimeException('No channel');
    $channelUrl=$row['channel_url'];

    // mark as read
    $ch=curl_init();
    curl_setopt_array($ch,[
      CURLOPT_URL=>$SB_HOST."/v3/group_channels/$channelUrl/messages/mark_as_read",
      CURLOPT_CUSTOMREQUEST=>'PUT',
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>['Content-Type: application/json','Api-Token: '.$SB_TOKEN],
      CURLOPT_POSTFIELDS=>json_encode(['user_id'=>$sbUserId]),
      CURLOPT_TIMEOUT=>15,
    ]);
    $resp=curl_exec($ch); curl_close($ch);
    echo json_encode(['ok'=>true]); exit;
  }

  // list with unread
  $url=$SB_HOST."/v3/users/$sbUserId/my_group_channels?show_unread_count=true&limit=100";
  $ch=curl_init();
  curl_setopt_array($ch,[ CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Api-Token: '.$SB_TOKEN], CURLOPT_TIMEOUT=>20 ]);
  $resp=curl_exec($ch); curl_close($ch);
  $json=$resp?json_decode($resp,true):[];
  $out=[];
  foreach(($json['channels']??[]) as $c){
    $out[$c['channel_url']] = (int)($c['unread_message_count']??0);
  }
  echo json_encode(['channels'=>$out]);

}catch(Throwable $e){ echo json_encode(['channels'=>[], 'error'=>$e->getMessage()]); }
