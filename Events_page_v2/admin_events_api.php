<?php
// admin_events_api.php — updated for 'details' field, file upload, creator ownership
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $cm = new ConnectionManager();
  $db = $cm->connect();

  if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["error" => "Login required"]);
    exit;
  }

  // resolve current user
  $stmt = $db->prepare("SELECT id, role FROM users WHERE username=?");
  $stmt->execute([$_SESSION['username']]);
  $me = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$me || strtolower($me['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Admins only"]);
    exit;
  }
  $myId = (int)$me['id'];

  $action = $_POST['action'] ?? ($_GET['action'] ?? '');
  if (!in_array($action, ['create', 'update', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(["error" => "Bad action"]);
    exit;
  }

  // helpers
  $norm = fn($s) => trim((string)$s);
  $toISO = fn(string $date, string $time)
    => date('Y-m-d H:i:s', strtotime("$date $time"));

  // reusable file saver
  function save_event_image(?array $file): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    @mkdir(__DIR__ . '/uploads/events', 0775, true);
    $fname = uniqid('ev_', true) . '.' . $ext;
    $rel = 'uploads/events/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $rel)) return null;
    return $rel;
  }

  // ---------------- CREATE ----------------
  if ($action === 'create') {
    $title    = $norm($_POST['title'] ?? '');
    $category = $norm($_POST['category'] ?? '');
    $date     = $norm($_POST['date'] ?? '');
    $start    = $norm($_POST['start_time'] ?? '');
    $end      = $norm($_POST['end_time'] ?? '');
    $location = $norm($_POST['location'] ?? '');
    $details  = $norm($_POST['details'] ?? '');
    $picture  = save_event_image($_FILES['picture_file'] ?? null);

    if ($title === '' || $category === '' || $date === '' || $start === '' || $end === '') {
      http_response_code(400);
      echo json_encode(["error" => "Missing required fields"]);
      exit;
    }

    $startISO = $toISO($date, $start);
    $endISO   = $toISO($date, $end);

    $ins = $db->prepare("
      INSERT INTO events(title,category,date,start_time,end_time,location,picture,startISO,endISO,details)
      VALUES(?,?,?,?,?,?,?,?,?,?)
    ");
    $ins->execute([$title,$category,$date,$start,$end,$location,$picture,$startISO,$endISO,$details]);
    $eventId = (int)$db->lastInsertId();

    // mark creator ownership
    $db->prepare("INSERT INTO event_person(person_id, event_id, role)
                  VALUES (?, ?, 'creator')")
       ->execute([$myId, $eventId]);

    // --- auto-create Sendbird channel for this event ---
    if (defined('SENDBIRD_APP_ID') && defined('SENDBIRD_API_TOKEN')) {
      $APP_ID    = SENDBIRD_APP_ID;
      $API_TOKEN = SENDBIRD_API_TOKEN;
      $API_BASE  = "https://api-{$APP_ID}.sendbird.com/v3";

      $adminIds = $db->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
      $members = array_map(fn($id) => "user_" . (int)$id, $adminIds);

      $eventTitle = $title ?: "Event #$eventId";

      $ch = curl_init("$API_BASE/group_channels");
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Api-Token: $API_TOKEN", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode([
          "name"        => "Event #$eventId Q&A",
          "user_ids"    => $members,
          "custom_type" => "event_chat",
          "data"        => json_encode(["event_id" => $eventId], JSON_UNESCAPED_SLASHES),
        ], JSON_UNESCAPED_SLASHES),
      ]);
      $res  = curl_exec($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($code < 300 && $res) {
        $payload = json_decode($res, true);
        $channelUrl = $payload['channel_url'] ?? null;
        if ($channelUrl) {
          $db->exec("CREATE TABLE IF NOT EXISTS event_chat_channel(
                      event_id INT PRIMARY KEY,
                      channel_url VARCHAR(255) NOT NULL
                    )");
          $map = $db->prepare("INSERT IGNORE INTO event_chat_channel(event_id, channel_url) VALUES(?,?)");
          $map->execute([$eventId, $channelUrl]);
        }
      }
    }

  header("Location: manage_events_admin.php");
  exit;
  }

  // ---------------- UPDATE ----------------
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(["error"=>"Bad id"]); exit; }

    // verify ownership
    $chk = $db->prepare("SELECT COUNT(*) FROM event_person WHERE event_id=? AND person_id=? AND (role='creator' OR is_creator=1)");
    $chk->execute([$id, $myId]);
    if (!$chk->fetchColumn()) {
      http_response_code(403);
      echo json_encode(["error"=>"Not your event"]);
      exit;
    }

    $title    = $norm($_POST['title'] ?? '');
    $category = $norm($_POST['category'] ?? '');
    $date     = $norm($_POST['date'] ?? '');
    $start    = $norm($_POST['start_time'] ?? '');
    $end      = $norm($_POST['end_time'] ?? '');
    $location = $norm($_POST['location'] ?? '');
    $details  = $norm($_POST['details'] ?? '');
    $newPic   = save_event_image($_FILES['picture_file'] ?? null);

    if ($title==='' || $category==='' || $date==='' || $start==='' || $end==='') {
      http_response_code(400); echo json_encode(["error"=>"Missing required fields"]); exit;
    }

    $startISO = $toISO($date,$start);
    $endISO   = $toISO($date,$end);

    $sql = "UPDATE events SET
              title=?, category=?, date=?, start_time=?, end_time=?, location=?, startISO=?, endISO=?, details=?";
    $params = [$title,$category,$date,$start,$end,$location,$startISO,$endISO,$details];

    if ($newPic !== null) {
      $sql .= ", picture=?";
      $params[] = $newPic;
      // remove old pic
      $old = $db->prepare("SELECT picture FROM events WHERE id=?");
      $old->execute([$id]);
      $oldPath = $old->fetchColumn();
      if ($oldPath && is_file(__DIR__.'/'.$oldPath)) @unlink(__DIR__.'/'.$oldPath);
    }

    $sql .= " WHERE id=?";
    $params[] = $id;
    $upd = $db->prepare($sql);
    $upd->execute($params);

    header("Location: manage_events_admin.php");
    exit;
  }

  // ---------------- DELETE ----------------
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(["error"=>"Bad id"]); exit; }

    // verify ownership
    $chk = $db->prepare("SELECT COUNT(*) FROM event_person WHERE event_id=? AND person_id=? AND (role='creator' OR is_creator=1)");
    $chk->execute([$id, $myId]);
    if (!$chk->fetchColumn()) {
      http_response_code(403);
      echo json_encode(["error"=>"Not your event"]);
      exit;
    }

    // remove uploaded image
    $old = $db->prepare("SELECT picture FROM events WHERE id=?");
    $old->execute([$id]);
    $oldPath = $old->fetchColumn();
    if ($oldPath && is_file(__DIR__.'/'.$oldPath)) @unlink(__DIR__.'/'.$oldPath);

    $db->prepare("DELETE FROM event_chat_channel WHERE event_id=?")->execute([$id]);
    $db->prepare("DELETE FROM event_person WHERE event_id=?")->execute([$id]);
    $db->prepare("DELETE FROM events WHERE id=?")->execute([$id]);

    header("Location: manage_events_admin.php");
    exit;

  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "detail" => $e->getMessage()]);
}
