<?php
session_start();

if (!isset($_SESSION['username'])){
  echo "
  <script>
    alert('Please login to access this page');
    window.location.href = 'Login.php';
  </script>";

  exit();
}

spl_autoload_register(
  function ($class) {
    require_once "model/$class.php";
  }
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>My Events – SMU</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="events_style.css?v=3">

  <script src='https://unpkg.com/axios/dist/axios.min.js'></script>
</head>
<body>
<div class="container py-4 px-4 my-events">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h4 mb-0">My Events</h2>
    <div class="d-flex gap-2">
      <a href="events.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left me-1"></i>Back to Events</a>
      <button id="clearAll" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Clear All</button>
    </div>
  </div>

  <div id="myEventsContainer" class="row row-cols-1 row-cols-md-3 g-4 justify-content-center">
</div>
  <p id="emptyState" class="text-muted mt-4" style="display:none;">No saved events yet. Go to <a href="events.html">Events</a> to add some.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php 
  $dao = new EventCollectionDAO();

  $currentUser = $dao->getUserId($_SESSION["username"]);
  $user_events_obj = $dao->getUsersEvents($currentUser);

  $user_events_arr = array_map(function ($events) {
    return [
      'id' => $events->getId(),
      'title' => $events->getTitle(),
      'category' => $events->getCategory(),
      'date' => $events->getDate(),
      'start_time' => $events->getStartTime(),
      'end_time' => $events->getEndTime(),
      'location' => $events->getLocation(),
      'picture' => $events->getPicture(),
      'startISO' => $events->getStartISO(),
      'endISO' => $events->getEndISO(),
    ];
  }, $user_events_obj);

  $user_events_json = json_encode($user_events_arr);
?>

<script>
// Shared with events page
const MY_EVENTS_KEY = 'smu_my_events_v1';
let loadMyEvents = <?= $user_events_json ?>;
const saveMyEvents = (list) => localStorage.setItem(MY_EVENTS_KEY, JSON.stringify(list));

// same category→accent map used on events page
const ACCENT = { tech:'accent-sky', arts:'accent-pink', sports:'accent-mint', career:'accent-lav', community:'accent-mint' };

// Google link builder
function toUTCBasic(iso){ const d=new Date(iso),pad=n=>String(n).padStart(2,'0'); return `${d.getUTCFullYear()}${pad(d.getUTCMonth()+1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}${pad(d.getUTCSeconds())}Z`; }
function googleCalUrl({title, startISO, endISO, location, details=""}){
  const u = new URL('https://calendar.google.com/calendar/render');
  u.searchParams.set('action','TEMPLATE');
  u.searchParams.set('text', title);
  u.searchParams.set('dates', `${toUTCBasic(startISO)}/${toUTCBasic(endISO)}`);
  if(location) u.searchParams.set('location', location);
  if(details)  u.searchParams.set('details', details);
  return u.toString();
}

function formatRange(startISO, endISO){
  const optsDate = { weekday:'short', day:'2-digit', month:'short', year:'numeric' };
  const optsTime = { hour:'2-digit', minute:'2-digit' };
  const s = new Date(startISO), e = new Date(endISO);
  const dateText = s.toLocaleDateString(undefined, optsDate);
  const timeText = `${s.toLocaleTimeString(undefined, optsTime)} – ${e.toLocaleTimeString(undefined, optsTime)}`;
  return {dateText, timeText};
}

function card(e){
  console.log(e);
  const {dateText, timeText} = formatRange(e.startISO, e.endISO);
  const picture = e.picture || 'placeholder.jpg';
  return `
  <div class="col">
    <div class="event-card">
      <img class="event-thumb" src="${picture}" alt="${e.title}">
      <div class="event-body">
        <h5 class="event-title">${e.title}</h5>
        <ul class="meta-list">
          <li><i class="bi bi-calendar2-event"></i>${dateText}</li>
          <li><i class="bi bi-clock"></i>${timeText}</li>
          <li><i class="bi bi-geo-alt"></i>${e.location || ''}</li>
        </ul>
        <div class="event-actions d-flex gap-2 flex-wrap">
          <a class="btn btn-primary btn-sm" href="${googleCalUrl(e)}" target="_blank" rel="noopener">
            <i class="bi bi-calendar-plus me-1"></i>Add to Google
          </a>
          <button class="btn btn-outline-danger btn-sm" data-eid="${e.id}" data-remove="${e.title}|${e.startISO}">
            <i class="bi bi-x-circle me-1"></i>Remove
          </button>
        </div>
      </div>
    </div>
  </div>`;
}



function render(list){
  // const list = loadMyEvents;
  const cont = document.getElementById('myEventsContainer');
  const empty = document.getElementById('emptyState');
  if (!list.length) {
    cont.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';
  cont.innerHTML = list.map(card).join('');
}

document.getElementById('clearAll').addEventListener('click', () => {
  if (confirm('Clear all saved events?')) {
    loadMyEvents = [];
    // axios for removing everything in sql database
    removeAllEvents();
    render(loadMyEvents);
  }
});

document.addEventListener('click', (e) => {
  let btn = e.target.closest('[data-remove]');
  if (!btn) return;
  let [title, startISO] = btn.dataset.remove.split('|');
  loadMyEvents = loadMyEvents.filter(ev => !(ev.title === title && ev.startISO === startISO));
  console.log(btn.dataset.eid);
  removeEvents(btn.dataset.eid);

  console.log(loadMyEvents);
  render(loadMyEvents);
});


function removeEvents(eventID) {
  let userID = <?= $currentUser ?>;
  let url = "axios/sql_updating.php";

  axios.get(url, { params:
    {
    "personID": userID,
    "eventID": eventID,
    "option": "remove"
    }
  })
    .then(response => {
        console.log(response.data);
        
    })
    .catch(error => {
        console.log(error.message);
    });
}

function removeAllEvents() {
  let userID = <?= $currentUser ?>;
  let url = "axios/sql_updating.php";

  axios.get(url, { params:
    {
    "personID": userID,
    "option": "removeAll"
    }
  })
    .then(response => {
        console.log(response.data);
        
    })
    .catch(error => {
        console.log(error.message);
    });
}



render(loadMyEvents);
</script>
</body>
</html>