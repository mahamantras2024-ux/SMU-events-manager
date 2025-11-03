<?php
// chat.php — updated
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
$u = $db->prepare("SELECT id, username, role FROM users WHERE username=?");
$u->execute([$_SESSION['username']]);
$me = $u->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo "User not found"; exit; }

$userId   = (int)$me['id'];
$username = (string)$me['username'];
$isAdmin  = strtolower((string)$me['role']) === 'admin';

// Chats list: event title + picture (+ channel url if exists)
$chatsStmt = $db->prepare("
  SELECT 
    e.id AS event_id,
    e.title AS event_title,
    e.picture AS event_picture,
    ecc.channel_url
  FROM event_person ep
  JOIN events e ON e.id = ep.event_id
  LEFT JOIN event_chat_channel ecc ON ecc.event_id = e.id
  WHERE ep.person_id = ?
  GROUP BY e.id, e.title, e.picture, ecc.channel_url
  ORDER BY e.id DESC
");
$chatsStmt->execute([$userId]);
$chats = $chatsStmt->fetchAll(PDO::FETCH_ASSOC);

$initialEventId = !empty($chats) ? (int)$chats[0]['event_id'] : 0;
$initialTitle   = !empty($chats) ? (string)$chats[0]['event_title'] : '-';
$initialPic     = !empty($chats) ? (string)$chats[0]['event_picture'] : '';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Chat — Events</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <style>
    body{background:#f6f7fb;}
    .app{display:grid; grid-template-columns: 320px 1fr; height:100vh;}
    .sidebar{border-right:1px solid #e8e8f2; background:#fff; overflow:auto;}
    .chat-list-item{display:flex; align-items:center; gap:.75rem; padding:.65rem .9rem; border-bottom:1px solid #f1f1f7; cursor:pointer;}
    .chat-list-item.active{background:#f7f9ff;}
    .avatar{width:40px; height:40px; border-radius:50%; object-fit:cover; background:#eee;}
    .chat-title{font-weight:700; font-size:.98rem; margin:0;}
    .chat-sub{color:#6b7280; font-size:.85rem;}
    .main{display:flex; flex-direction:column;}
    .chat-header{display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; border-bottom:1px solid #e8e8f2; background:#fff;}
    #pinnedBar{display:none; background:#fff6d9; border-bottom:1px dashed #f1d48a; color:#6b5500; padding:.5rem 1rem;}
    #pinnedBar .pin-dismiss{border:none;background:transparent;color:#6b5500;}
    .messages{flex:1; overflow:auto; padding:1rem;}
    .msg{max-width:70%; margin-bottom:.75rem; display:flex; flex-direction:column;}
    .msg.me{margin-left:auto; align-items:flex-end;}
    .bubble{background:#fff; border:1px solid #ececf4; padding:.6rem .75rem; border-radius:10px; word-break:break-word;}
    .msg.me .bubble{background:#e8f3ff; border-color:#d5e8ff;}
    .meta{color:#6b7280; font-size:.78rem; margin-top:.15rem;}
    .actions{display:flex; gap:.5rem; margin-top:.25rem;}
    .actions .icon{border:none; background:transparent; padding:0; font-size:1rem; color:#6b7280; cursor:pointer;}
    .actions .icon:hover{color:#111827;}
    .composer{border-top:1px solid #e8e8f2; padding:.5rem; background:#fff;}
    .composer .bar{display:flex; gap:.5rem; align-items:center;}
    .composer textarea{resize:none; height:46px;}
    .icon-btn{border:none; background:transparent; padding:.375rem .5rem; font-size:1.25rem; cursor:pointer; color:#374151;}
    .icon-btn:hover{color:#111827;}
    .hidden{display:none;}
    .badge[data-unread]{min-width:18px;}
  </style>
</head>
<body>
<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="p-3 border-bottom">
      <div class="fw-bold">Hi, <?php echo htmlspecialchars($username); ?></div>
      <div class="text-muted small"><?php echo $isAdmin ? 'Admin' : 'User'; ?></div>
    </div>
    <div id="chatList">
      <?php foreach ($chats as $c): 
        $eid = (int)$c['event_id'];
        $active = $eid === $initialEventId ? 'active' : '';
        $pic = trim((string)($c['event_picture'] ?? '')) ?: 'https://cdn.example.com/avatars/group-default.png';
        $title = trim((string)$c['event_title']) ?: ('Event #'.$eid);
        $channelUrl = (string)($c['channel_url'] ?? '');
      ?>
      <div class="chat-list-item <?php echo $active; ?>" 
           data-event-id="<?php echo $eid; ?>"
           data-channel-url="<?php echo htmlspecialchars($channelUrl); ?>">
        <img class="avatar" src="<?php echo htmlspecialchars($pic); ?>" alt="grp"/>
        <div class="me-auto">
          <p class="chat-title mb-0"><?php echo htmlspecialchars($title); ?></p>
          <div class="chat-sub">Tap to open</div>
        </div>
        <div class="ms-2">
          <span class="badge text-bg-primary" data-unread style="display:none">0</span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($chats)): ?>
        <div class="p-3 text-muted">No chats yet.</div>
      <?php endif; ?>
    </div>
  </aside>

  <!-- Main -->
  <section class="main">
    <div class="chat-header">
      <img id="headerAvatar" class="avatar" src="<?php echo $initialPic ?: 'https://cdn.example.com/avatars/group-default.png'; ?>" alt="grp"/>
      <div class="me-auto">
        <div class="fw-bold" id="headerTitle"><?php echo htmlspecialchars($initialTitle); ?></div>
        <div class="text-muted small" id="headerSub">Group chat</div>
      </div>

      <button class="btn btn-outline-primary btn-sm me-2" id="btnMembers" data-bs-toggle="modal" data-bs-target="#membersModal">
        <i class="bi bi-people"></i> Members
      </button>

      <button class="btn btn-outline-secondary btn-sm" id="btnLeave" <?php echo $isAdmin ? 'disabled title="Admins cannot leave this group"' : ''; ?>>
        <i class="bi bi-box-arrow-right"></i> Leave
      </button>
    </div>

    <!-- Pinned bar -->
    <div id="pinnedBar">
      <strong><i class="bi bi-pin-angle-fill"></i> Pinned:</strong>
      <span id="pinnedText" class="ms-1"></span>
      <?php if ($isAdmin): ?>
      <button class="pin-dismiss float-end" id="btnUnpin" title="Unpin"><i class="bi bi-x-lg"></i></button>
      <?php endif; ?>
    </div>

    <div class="messages" id="log"></div>

    <div class="composer">
      <form id="sendForm" class="bar" enctype="multipart/form-data">
        <input type="hidden" name="event_id" id="event_id" value="<?php echo $initialEventId; ?>"/>
        <input type="file" name="file" id="fileInput" class="hidden" />
        <button class="icon-btn" type="button" id="attachBtn" title="Attach file"><i class="bi bi-paperclip"></i></button>
        <textarea class="form-control" name="message" id="message" placeholder="Write a message..."></textarea>
        <button class="icon-btn" type="submit" title="Send"><i class="bi bi-send-fill"></i></button>
      </form>
      <div class="small text-muted ms-2" id="fileHint" style="display:none;"></div>
    </div>
  </section>
</div>

<!-- Members modal -->
<div class="modal fade" id="membersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Group members</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="membersList" class="list-group small">
          <div class="text-muted">Loading members…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  const MY_SB_USER_ID = "user_<?php echo (int)$userId; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const chatListEl   = document.getElementById('chatList');
  const logEl        = document.getElementById('log');
  const headerTitle  = document.getElementById('headerTitle');
  const headerAvatar = document.getElementById('headerAvatar');
  const eventIdInput = document.getElementById('event_id');
  const sendForm     = document.getElementById('sendForm');
  const fileInput    = document.getElementById('fileInput');
  const attachBtn    = document.getElementById('attachBtn');
  const btnLeave     = document.getElementById('btnLeave');
  const fileHint     = document.getElementById('fileHint');
  const pinnedBar    = document.getElementById('pinnedBar');
  const pinnedTextEl = document.getElementById('pinnedText');
  const btnUnpin     = document.getElementById('btnUnpin');
  const membersModalEl = document.getElementById('membersModal');
  const membersList  = document.getElementById('membersList');
  let membersModal;

  let currentEventId = Number(eventIdInput.value || 0);
  let pollTimer = null;

  function el(tag, cls, txt){ const d=document.createElement(tag); if(cls)d.className=cls; if(txt!=null)d.textContent=txt; return d; }
  function setActiveChatItem(eid){
    [...chatListEl.querySelectorAll('.chat-list-item')].forEach(n => n.classList.toggle('active', Number(n.dataset.eventId) === eid));
  }

  function addActions(row, m){
    const isMine = (m.user && m.user.user_id) === MY_SB_USER_ID;
    const actions = el('div','actions');
    const fifteen = 15*60*1000;
    const ageOk = (Date.now() - Number(m.created_at||0)) <= fifteen;

    // Pin (admin)
    if (IS_ADMIN) {
      const pinBtn = el('button','icon','');
      pinBtn.innerHTML = '<i class="bi bi-pin-angle"></i>';
      pinBtn.title = 'Pin';
      pinBtn.onclick = async () => {
        try {
          const fd = new FormData();
          fd.set('event_id', String(currentEventId));
          fd.set('message_id', String(m.message_id));
          const r = await fetch('chat_pin.php',{method:'POST',body:fd});
          const js = await r.json().catch(()=>null);
          if (js && js.ok) {
            pinnedBar.style.display='block';
            pinnedTextEl.textContent = js.pinned_text || '(message)';
            pinBtn.innerHTML = '<i class="bi bi-pin-angle-fill"></i>';
          } else {
            alert(js?.error || 'Pin failed');
          }
        } catch(e){ console.error(e); alert('Pin failed'); }
      };
      actions.appendChild(pinBtn);
    }

    // Edit (mine, text, <=15m)
    if (isMine && m.message_type !== 'FILE' && ageOk) {
      const eBtn = el('button','icon','');
      eBtn.innerHTML = '<i class="bi bi-pencil-square"></i>';
      eBtn.title = 'Edit';
      eBtn.onclick = async () => {
        const t = prompt('Edit message:', m.message || '');
        if (t!=null && t.trim()!=='') {
          const fd = new FormData();
          fd.set('event_id', String(currentEventId));
          fd.set('message_id', String(m.message_id));
          fd.set('message', t.trim());
          await fetch('chat_edit.php',{method:'POST',body:fd});
          await fetchMessages();
        }
      };
      actions.appendChild(eBtn);
    }

    // Delete (mine)
    if (isMine) {
      const dBtn = el('button','icon','');
      dBtn.innerHTML = '<i class="bi bi-trash"></i>';
      dBtn.title = 'Delete';
      dBtn.onclick = async () => {
        if(!confirm('Delete this message?')) return;
        const fd = new FormData();
        fd.set('event_id', String(currentEventId));
        fd.set('message_id', String(m.message_id));
        await fetch('chat_delete.php',{method:'POST',body:fd});
        await fetchMessages();
      };
      actions.appendChild(dBtn);
    }

    row.appendChild(actions);
  }

  function render(messages){
    logEl.innerHTML = '';
    messages.forEach(m => {
      const isMine = (m.user && m.user.user_id) === MY_SB_USER_ID;
      const row = el('div', 'msg' + (isMine ? ' me' : ''));
      const bubble = el('div', 'bubble');

      if (m.message_type === 'FILE') {
        const isImg = (m.type && m.type.indexOf('image/') === 0) || (m.url && /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/i.test(m.url));
        if (isImg && m.url) {
          const img = document.createElement('img');
          img.src = m.url; img.alt = m.name || 'image';
          img.style.maxWidth = '320px'; img.style.borderRadius = '8px';
          img.style.display = 'block';
          bubble.appendChild(img);
          if (m.message) bubble.appendChild(el('div','',m.message));
        } else {
          const a = document.createElement('a');
          a.href = m.url; a.target = '_blank'; a.rel='noopener';
          a.textContent = (m.message ? (m.message + ' • ') : '') + (m.name || 'file');
          bubble.appendChild(a);
        }
      } else {
        bubble.textContent = m.message || (m.data && m.data.text) || '';
      }
      row.appendChild(bubble);

      // small “edited” line UNDER the bubble
      const ua = Number(m.updated_at || 0), ca = Number(m.created_at || 0);
      if (ua > 0 && ua !== ca) {
        const ed = el('div','text-muted small','edited');
        row.appendChild(ed);
      }

      const meta = el('div', 'meta', (m.user?.nickname || m.user?.user_id || 'Unknown') + ' • ' + new Date(m.created_at).toLocaleString());
      row.appendChild(meta);

      addActions(row, m);
      logEl.appendChild(row);
    });
    logEl.scrollTop = logEl.scrollHeight;
  }

  async function fetchMessages(){
    if (!currentEventId) return;
    try {
      const res = await fetch(`chat_fetch.php?event_id=${currentEventId}`);
      const js  = await res.json();
      if (js && Array.isArray(js.messages)) render(js.messages);
      else render([]);
    } catch(e){ console.error(e); }
  }

  // ----- Pinned helpers
  async function loadPinned(){
  if (!currentEventId) return;
  try{
    const res = await fetch(`chat_meta.php?event_id=${currentEventId}`);
    const js  = await res.json().catch(()=>null);
    if (js?.metadata?.pinned) {
      pinnedBar.style.display='block';
      pinnedTextEl.textContent = js.metadata_text || '(message)';
    } else {
      pinnedBar.style.display='none';
    }
  }catch(e){ pinnedBar.style.display='none'; }
}

  if (btnUnpin) {
    btnUnpin.addEventListener('click', async ()=>{
      try{
        const fd = new FormData();
        fd.set('event_id', String(currentEventId));
        const r = await fetch('chat_unpin.php',{method:'POST',body:fd});
        const js = await r.json().catch(()=>null);
        if (js && js.ok) { pinnedBar.style.display='none'; }
        else { alert(js?.error || 'Unpin failed'); }
      }catch(e){ alert('Unpin failed'); }
    });
  }

  async function bootstrapOnce(){
    if (!currentEventId) return;
    try { await fetch(`chat_bootstrap.php?event_id=${currentEventId}`); } catch(_){}
  }

  function startPolling(){
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async ()=>{
      await fetchMessages();
      await refreshUnread();
      await loadPinned();
    }, 3000);
  }

  // ----- Send text/file
  attachBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', () => {
    if (!fileInput.files || !fileInput.files.length) return;
    fileHint.style.display='block';
    fileHint.textContent = `Selected: ${fileInput.files[0].name}`;
    // Let user press send manually
  });


  sendForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!currentEventId) return;
    const fd = new FormData(sendForm);
    fd.set('event_id', String(currentEventId));
    try {
      const res = await fetch('chat_send.php', { method:'POST', body: fd });
      const js  = await res.json().catch(()=>null);
      if (res.ok) {
        sendForm.message.value = '';
        fileInput.value = '';
        fileHint.style.display='none';
        await fetchMessages();
        await markCurrentAsRead();
      } else {
        alert((js && js.error) ? js.error : 'Send failed');
      }
    } catch (err) { console.error(err); alert('Send failed'); }
  });

  // ----- Switch chats
  chatListEl.addEventListener('click', (e) => {
    const tile = e.target.closest('.chat-list-item'); if (!tile) return;
    const eid = Number(tile.dataset.eventId); if (!eid || eid === currentEventId) return;

    currentEventId = eid;
    eventIdInput.value = String(eid);
    const img = tile.querySelector('img.avatar');
    headerAvatar.src = img ? img.src : 'https://cdn.example.com/avatars/group-default.png';
    headerTitle.textContent = tile.querySelector('.chat-title')?.textContent || `Event #${eid}`;
    setActiveChatItem(eid);

    (async () => { 
      await bootstrapOnce(); 
      await fetchMessages(); 
      await markCurrentAsRead(); 
      await refreshUnread();
      await loadPinned();
    })();
  });

  // ----- Leave (disabled for admin)
  if (btnLeave) {
    btnLeave.addEventListener('click', async () => {
      if (!currentEventId || IS_ADMIN) return;
      if (!confirm('Leave this group?')) return;

      const fd = new FormData();
      fd.set('event_id', String(currentEventId));
      try {
        const res = await fetch('chat_leave.php', { method:'POST', body: fd });
        const js  = await res.json().catch(()=>null);
        if (res.ok) {
          const tile = chatListEl.querySelector(`.chat-list-item[data-event-id="${currentEventId}"]`);
          if (tile) tile.remove();
          const next = chatListEl.querySelector('.chat-list-item');
          if (next) next.click();
          else { currentEventId = 0; eventIdInput.value = '0'; headerTitle.textContent='No chat selected'; logEl.innerHTML=''; pinnedBar.style.display='none'; }
          await refreshUnread();
        } else {
          alert((js && js.error) ? js.error : 'Unable to leave');
        }
      } catch (err) { console.error(err); alert('Unable to leave'); }
    });
  }

  // ----- Members modal: ensure bootstrap + encode-safe server calls
  function ensureMembersModal(){ if (!membersModal) membersModal = new bootstrap.Modal(membersModalEl); }
  async function loadMembers(){
    membersList.innerHTML = '<div class="text-muted">Loading…</div>';
    try{
      const res = await fetch(`chat_members.php?event_id=${currentEventId}`);
      const js  = await res.json();
      membersList.innerHTML = '';
      (js.members||[]).forEach(m=>{
        const uid = m.user_id, name = m.nickname || uid;
        const isMe = uid === MY_SB_USER_ID;
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex align-items-center justify-content-between';
        const left = document.createElement('div');
        left.innerHTML = `<div class="fw-bold">${name}${m.is_creator ? ' <span class="badge text-bg-secondary ms-1">creator</span>' : ''}</div>`;
        item.appendChild(left);
        const right = document.createElement('div');
        if (IS_ADMIN && !isMe) {
          const k = document.createElement('button');
          k.className='btn btn-outline-danger btn-sm';
          k.innerHTML='<i class="bi bi-person-dash"></i>';
          k.onclick = async ()=>{
            if(!confirm(`Kick ${name}?`)) return;
            const targetId = Number(uid.replace(/^user_/,''));
            const fd = new FormData();
            fd.set('event_id', String(currentEventId));
            fd.set('target_user_id', String(targetId));
            const r = await fetch('chat_kick.php',{method:'POST', body:fd});
            const js2 = await r.json().catch(()=>null);
            if (!r.ok || js2?.error) { alert(js2?.error || 'Kick failed'); return; }
            await loadMembers(); await fetchMessages(); await refreshUnread();
          };
          right.appendChild(k);
        }
        item.appendChild(right);
        membersList.appendChild(item);
      });
      if(!membersList.children.length){ membersList.innerHTML = '<div class="text-muted">No members</div>'; }
    }catch(e){ membersList.innerHTML = '<div class="text-danger">Failed to load members</div>'; }
  }
  async function openMembers(){
    ensureMembersModal();
    try { await fetch(`chat_bootstrap.php?event_id=${currentEventId}`); } catch(_){}
    await loadMembers();
    membersModal.show();
  }
  document.getElementById('btnMembers')?.addEventListener('click', () => { if (currentEventId) openMembers(); });
  membersModalEl.addEventListener('shown.bs.modal', () => { if (currentEventId) loadMembers(); });

  // ----- Unread badges
  async function refreshUnread(){
    try{
      const res = await fetch('chat_unread.php');
      const js  = await res.json();
      const map = js.channels || {};
      [...chatListEl.querySelectorAll('.chat-list-item')].forEach(tile=>{
        const badge = tile.querySelector('[data-unread]');
        const chUrl = tile.dataset.channelUrl || '';
        const count = map[chUrl] || 0;
        if (Number(tile.dataset.eventId) === currentEventId || count <= 0) {
          badge.style.display='none';
        } else {
          badge.style.display='inline-block';
          badge.textContent = String(count);
        }
      });
    }catch(e){}
  }
  async function markCurrentAsRead(){
    try{ await fetch(`chat_unread.php?action=mark&event_id=${currentEventId}`); }catch(e){}
  }

  // First load
  (async () => {
    if (!currentEventId) return;
    await bootstrapOnce();
    await fetchMessages();
    await markCurrentAsRead();
    await refreshUnread();
    await loadPinned();
    startPolling();
  })();
})();
</script>
</body>
</html>
