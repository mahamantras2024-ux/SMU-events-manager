<?php
// manage_events_admin.php — only show/manage events created by this admin; picture via file upload
declare(strict_types=1);
session_start();
require_once 'db_connect.php';
require_once 'config.php';

if (!isset($_SESSION['username'])) {
  echo "<script>alert('Login required'); location.href='Login.php';</script>";
  exit;
}

$cm = new ConnectionManager();
$db = $cm->connect();

// Resolve current user
$u = $db->prepare("SELECT id, role FROM users WHERE username = ?");
$u->execute([$_SESSION['username']]);
$me = $u->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo "<script>alert('User not found'); location.href='Login.php';</script>"; exit; }
$myId = (int)$me['id'];

// Fetch ONLY events this user created, via event_person
// Supports either event_person.role='creator' OR event_person.is_creator=1
$sql = "
  SELECT e.id, e.title, e.picture, e.category, e.date, e.start_time, e.end_time, e.location, e.details
  FROM events e
  JOIN event_person ep ON ep.event_id = e.id
  WHERE ep.person_id = :uid
    AND (COALESCE(ep.role,'') = 'creator')
  ORDER BY e.date DESC, e.start_time ASC
";
$st = $db->prepare($sql);
$st->execute([':uid' => $myId]);
$events = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Manage My Events</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <style>
    .event-card { position: relative; }
    .cat-chip{ padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:600; }
    .cat-tech{ background:#e8f3ff; color:#0b63b6; }
    .cat-arts{ background:#fde7ff; color:#9b2aa8; }
    .cat-sports{ background:#e6ffef; color:#0d7a3a; }
    .cat-career{ background:#fff6e0; color:#7a5d00; }
    .icon-btn{ width:2.35rem; height:2.35rem; display:inline-flex; align-items:center; justify-content:center; border-radius:.5rem; }
    .thumb { max-height: 180px; object-fit: cover; }
    .form-text.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <h3 class="me-auto">Manage My Events</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
      <i class="bi bi-plus-circle"></i> New Event
    </button>
  </div>

  <div class="row g-3">
    <?php foreach ($events as $ev): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 event-card shadow-sm">
          <?php if (!empty($ev['picture'])): ?>
            <img class="card-img-top thumb" src="<?= htmlspecialchars($ev['picture']) ?>" alt="">
          <?php else: ?>
            <img class="card-img-top thumb" src="https://via.placeholder.com/640x360?text=Event" alt="">
          <?php endif; ?>

          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <h5 class="card-title mb-0"><?= htmlspecialchars($ev['title']) ?></h5>
              <?php
                $cat = strtolower((string)($ev['category'] ?? ''));
                $catClass = [
                  'tech' => 'cat-tech',
                  'arts' => 'cat-arts',
                  'sports' => 'cat-sports',
                  'career' => 'cat-career'
                ][$cat] ?? 'cat-tech';
                $catLabel = ucfirst($cat ?: 'Tech');
              ?>
              <span class="cat-chip <?= $catClass ?>"><?= htmlspecialchars($catLabel) ?></span>
            </div>

            <div class="event-meta text-muted small d-grid gap-2 mb-3">
              <div class="item"><i class="bi bi-calendar-event"></i>
                <span><?= htmlspecialchars(date('D, M d, Y', strtotime($ev['date']))) ?></span>
              </div>
              <div class="item"><i class="bi bi-clock"></i>
                <span><?= htmlspecialchars($ev['start_time']) ?> – <?= htmlspecialchars($ev['end_time']) ?></span>
              </div>
              <?php if (!empty($ev['location'])): ?>
                <div class="item"><i class="bi bi-geo-alt"></i>
                  <span><?= htmlspecialchars($ev['location']) ?></span>
                </div>
              <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <!-- Messages icon to open group chat -->
              <a class="btn btn-light border icon-btn"
                 href="chat.php?= (int)$ev['id'] ?>"
                 title="Open chat for this event">
                <i class="bi bi-chat-dots"></i>
              </a>

              <button class="btn btn-light border icon-btn"
                      title="Edit"
                      onclick='openEdit(<?= (int)$ev["id"] ?>, <?= json_encode($ev, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil-square"></i>
              </button>

              <button class="btn btn-light border icon-btn" title="Delete"
                      onclick="doDelete(<?= (int)$ev['id'] ?>)">
                <i class="bi bi-trash3"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Create Modal (multipart for file upload) -->
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="admin_events_api.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Create Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select class="form-select" name="category" required>
              <option value="tech">Tech</option>
              <option value="arts">Arts</option>
              <option value="sports">Sports</option>
              <option value="career">Career</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Time</label>
            <input type="time" class="form-control" name="start_time" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">End Time</label>
            <input type="time" class="form-control" name="end_time" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Location</label>
            <input class="form-control" name="location">
          </div>

          <!-- Picture upload (file from desktop) -->
          <div class="col-md-6">
            <label class="form-label">Event Image</label>
            <input type="file" class="form-control" name="picture_file" accept="image/*">
            <div class="form-text mono">Accepted: image/* — Max size depends on php.ini (upload_max_filesize)</div>
          </div>

          <!-- Details -->
          <div class="col-12">
            <label class="form-label">Details</label>
            <textarea class="form-control" name="details" rows="4"
                      placeholder="Describe the event (agenda, speakers, notes)…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal (multipart for replacing image) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="admin_events_api.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Edit Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="evIdEdit">
        <input type="hidden" name="existing_picture" id="evPictureExisting">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" id="evTitleEdit" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select class="form-select" name="category" id="evCategoryEdit" required>
              <option value="tech">Tech</option>
              <option value="arts">Arts</option>
              <option value="sports">Sports</option>
              <option value="career">Career</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date" id="evDateEdit" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Time</label>
            <input type="time" class="form-control" name="start_time" id="evStartEdit" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">End Time</label>
            <input type="time" class="form-control" name="end_time" id="evEndEdit" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Location</label>
            <input class="form-control" name="location" id="evLocationEdit">
          </div>

          <!-- Current image preview + replace -->
          <div class="col-md-6">
            <label class="form-label">Event Image</label>
            <input type="file" class="form-control" name="picture_file" accept="image/*">
            <div class="form-text">Leave empty to keep the current image.</div>
            <img id="evPicturePreview" class="mt-2 rounded w-100" style="max-height:180px; object-fit:cover;" alt=""/>
          </div>

          <!-- Details -->
          <div class="col-12">
            <label class="form-label">Details</label>
            <textarea class="form-control" name="details" id="evDetailsEdit" rows="4"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(id, ev){
  document.getElementById('evIdEdit').value       = id;
  document.getElementById('evTitleEdit').value    = ev.title || '';
  document.getElementById('evCategoryEdit').value = (ev.category || 'tech').toLowerCase();
  document.getElementById('evDateEdit').value     = ev.date || '';
  document.getElementById('evStartEdit').value    = ev.start_time || '';
  document.getElementById('evEndEdit').value      = ev.end_time || '';
  document.getElementById('evLocationEdit').value = ev.location || '';
  document.getElementById('evDetailsEdit').value  = ev.details || '';
  document.getElementById('evPictureExisting').value = ev.picture || '';
  const prev = document.getElementById('evPicturePreview');
  prev.src = ev.picture ? ev.picture : 'https://via.placeholder.com/640x360?text=Event';

  const modal = new bootstrap.Modal(document.getElementById('editModal'));
  modal.show();
}

async function doDelete(id){
  if(!confirm('Delete this event?')) return;
  const fd = new FormData();
  fd.set('action','delete'); fd.set('id', String(id));
  const r = await fetch('admin_events_api.php', { method:'POST', body:fd });
  location.reload();
}
</script>
</body>
</html>
