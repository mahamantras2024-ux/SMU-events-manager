<?php
// chats.php — unified list of chats for users and admins (uses event_person.role = 'creator')
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

if (!isset($_SESSION['username'])) {
  echo "<script>alert('Please login'); location.href='Login.php';</script>";
  exit;
}

$cm = new ConnectionManager();
$db = method_exists($cm,'connect') ? $cm->connect() : $cm->getConnection();

// who am i?
$u = $db->prepare("SELECT id, username, role FROM users WHERE username=?");
$u->execute([$_SESSION['username']]);
$me = $u->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo "User not found"; exit; }

$userId   = (int)$me['id'];
$username = (string)$me['username'];
$isAdmin  = strtolower((string)$me['role']) === 'admin';

$APP_ID = SENDBIRD_APP_ID;
$TOKEN  = SENDBIRD_API_TOKEN;
$BASE   = "https://api-$APP_ID.sendbird.com/v3";

$rows = [];

if ($isAdmin) {
  // ADMIN: events where this admin is the creator according to event_person.role
  $sql = "
    SELECT e.id AS event_id, e.title, e.picture, ecc.channel_url
    FROM event_person ep
    JOIN events e              ON e.id = ep.event_id
    JOIN event_chat_channel ecc ON ecc.event_id = e.id
    WHERE ep.person_id = ?
      AND LOWER(ep.role) = 'creator'
    ORDER BY e.date DESC, e.start_time DESC
  ";
  $st = $db->prepare($sql);
  $st->execute([$userId]);

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
      'event_id'    => (int)$r['event_id'],
      'title'       => $r['title'],
      'picture'     => $r['picture'] ?: null,
      'channel_url' => $r['channel_url'],
      'members'     => null,
      'unread'      => null,
      'last'        => null,
    ];
  }

} else {
  // USER: list channels this user belongs to (Sendbird)
  $sbUserId = "user_".$userId;

  $url = "$BASE/users/".rawurlencode($sbUserId)."/my_group_channels?limit=50&custom_types=event_chat&order=latest_last_message";
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Api-Token: $TOKEN", "Accept: application/json"],
    CURLOPT_SSL_VERIFYPEER => false, // fine for localhost
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 12,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code < 300 && $res) {
    $payload = json_decode($res, true);
    foreach (($payload['channels'] ?? []) as $c) {
      if (($c['custom_type'] ?? '') !== 'event_chat') continue;

      $eventIdFromData = null;
      if (!empty($c['data'])) {
        $d = json_decode($c['data'], true);
        if (isset($d['event_id'])) $eventIdFromData = (int)$d['event_id'];
      }

      // pull avatar from events if we have event_id
      $pic = null;
      if ($eventIdFromData) {
        $img = $db->prepare("SELECT picture FROM events WHERE id=? LIMIT 1");
        $img->execute([$eventIdFromData]);
        $pic = $img->fetchColumn() ?: null;
      }

      $rows[] = [
        'event_id'    => $eventIdFromData,
        'title'       => $c['name'] ?? 'Event chat',
        'picture'     => $pic,
        'channel_url' => $c['channel_url'],
        'members'     => $c['member_count'] ?? null,
        'unread'      => $c['unread_message_count'] ?? 0,
        'last'        => $c['last_message']['message'] ?? null,
      ];
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $isAdmin ? 'My Event Chats (Admin)' : 'My Chats' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { background:#f6f7fb }
  .wrap { max-width: 900px; margin: 24px auto; }
  .chat-item { border:1px solid #e5e7eb; border-radius:12px; padding:12px 14px; background:#fff }
  .chat-item + .chat-item { margin-top:10px }
  .title { font-weight:600 }
  .meta { color:#6b7280; font-size: 0.9rem }
  .pill { font-size:.75rem; padding:.15rem .45rem; border:1px solid #e5e7eb; border-radius:999px; }
  .unread { background:#fee2e2; color:#991b1b; border-color:#fecaca }
  .last { color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 50ch;}
  .avatar { width:44px; height:44px; border-radius:8px; object-fit:cover; background:#eef1f5; }
</style>
</head>
<body>
<div class="wrap">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0"><?= $isAdmin ? 'Chats for My Events' : 'My Group Chats' ?></h3>
    <a class="btn btn-outline-secondary btn-sm" href="events.php">Back to Events</a>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-secondary">No chats yet.</div>
  <?php endif; ?>

  <?php foreach ($rows as $r):
    $eventId = $r['event_id']; // may be null for user if channel missing data
    $link    = $eventId ? "chat.php?event_id={$eventId}" : "#";
    $pic     = $r['picture'] ?: 'https://via.placeholder.com/64x64?text=E';
  ?>
    <div class="chat-item d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <img class="avatar" src="<?= htmlspecialchars($pic) ?>" alt="">
        <div>
          <div class="title">
            <a href="<?= htmlspecialchars($link) ?>" <?= $eventId ? '' : 'tabindex="-1" aria-disabled="true" class="disabled link-secondary text-decoration-none"' ?>>
              <?= htmlspecialchars($r['title'] ?? 'Event chat') ?>
            </a>
          </div>
          <div class="meta">
            <?php if ($r['members'] !== null): ?>
              <span class="pill"><?= (int)$r['members'] ?> members</span>
            <?php endif; ?>
            <?php if ($r['unread']): ?>
              <span class="pill unread ms-2"><?= (int)$r['unread'] ?> new</span>
            <?php endif; ?>
            <?php if ($r['last']): ?>
              <span class="last ms-2">“<?= htmlspecialchars($r['last']) ?>”</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <?php if ($eventId): ?>
          <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($link) ?>">Open</a>
        <?php else: ?>
          <button class="btn btn-secondary btn-sm" disabled>Open</button>
        <?php endif; ?>

        <?php if (!$isAdmin && $eventId): ?>
          <button class="btn btn-outline-danger btn-sm" onclick="leaveGroup(<?= (int)$eventId ?>)">Leave</button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
async function leaveGroup(eventId){
  if(!confirm('Leave this group?')) return;
  const res = await fetch('chat_leave.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
    body: new URLSearchParams({ event_id: String(eventId) })
  });
  const txt = await res.text();
  let data; try{ data = JSON.parse(txt); }catch(e){ alert('Leave failed'); return; }
  if(!res.ok || !data.ok){ alert(data.error || 'Leave failed'); return; }
  location.reload();
}
</script>
</body>
</html>