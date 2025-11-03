<?php
session_start();
spl_autoload_register(
  function ($class) {
    require_once "model/$class.php";
  }
);

if (!isset($_SESSION['username'])){
  echo "
  <script>
    alert('Please login to access this page');
    window.location.href = 'Login.php';
  </script>";

  exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SMU Events – Trending</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="events_style.css">

  <script src='https://unpkg.com/axios/dist/axios.min.js'></script>
</head>
<body>

<div class="container py-4">
  <div class="wbname">
    <h1>Omni</h1>
  </div>
  <br>

  <nav class="navbar navbar-expand-lg navbar-light" id="navbarid">
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
    <div class="navbar-nav" id="navitems">
      <a class="nav-item nav-link ula nvit" href="#">Events </a>
      <!-- <a class="nav-item nav-link ula nvit" href="#">Daily Challenge</a> -->
      <a class="nav-item nav-link ula nvit" href="#">Account</a>
      <a class="nav-item nav-link ula nvit" href="my_events_user.php">My Events</a>
      <a class="nav-item nav-link ula nvit" href="dashboard.php">Dashboard</a>
    </div>
      <div class="navbar-nav ms-auto"><a class="nav-item nav-link ula nvit me-3" id="logout" href="logout.php">Logout</a></div>
  </div>
  </nav>

  <div class="filters" id="filters">
    <span class="chip accent-grey" data-filter="all">All</span>
    <span class="chip accent-sky" data-filter="tech">Tech</span>
    <span class="chip accent-pink" data-filter="arts">Arts</span>
    <span class="chip accent-mint" data-filter="sports">Sports</span>
    <span class="chip accent-lav" data-filter="career">Career</span>
  </div>

  <div id="eventsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6500">
    <div class="carousel-inner" id="carouselInner"></div>
    <div class="carousel-indicators" id="carouselDots"></div>

    <button class="carousel-control-prev" type="button" data-bs-target="#eventsCarousel" data-bs-slide="prev">
      <i class="bi bi-chevron-left"></i>
      <span class="visually-hidden">Prev</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#eventsCarousel" data-bs-slide="next">
      <i class="bi bi-chevron-right"></i>
      <span class="visually-hidden">Next</span>
    </button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
  $dao = new EventCollectionDAO();

  // all events
  $events_obj = $dao->getEvents();
  $events_arr = array_map(function ($event) {
    return [
      'id' => $event->getId(),
      'title' => $event->getTitle(),
      'category' => $event->getCategory(),
      'date' => $event->getDate(),
      'start_time' => $event->getStartTime(),
      'end_time' => $event->getEndTime(),
      'location' => $event->getLocation(),
      'picture' => $event->getPicture(),
      'startISO' => $event->getStartISO(),
      'endISO' => $event->getEndISO(),
    ];
  }, $events_obj);

  $events_json = json_encode($events_arr);

  // user's saved events
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
document.addEventListener('DOMContentLoaded', () => {
/* =========================
   DATA (with ISO datetimes)
   ========================= */
// const events = [
//   {title:"HackSMU: 24-Hour Hackathon",dateText:"Fri, 5 Dec 2025",timeText:"7:00 PM → Sat, 7:00 PM",locText:"SIS Building",img:"hackathon.png",categories:["tech"],startISO:"2025-12-05T19:00:00+08:00",endISO:"2025-12-06T19:00:00+08:00"},
//   {title:"Open Mic & Poetry Slam",dateText:"Fri, 12 Dec 2025",timeText:"8:00–10:30 PM",locText:"Concourse Hall",img:"mic.jpeg",categories:["arts"],startISO:"2025-12-12T20:00:00+08:00",endISO:"2025-12-12T22:30:00+08:00"},
//   {title:"Finance Forum: Markets 2025",dateText:"Wed, 10 Dec 2025",timeText:"5:30–7:30 PM",locText:"Shaw Alumni House",img:"finance.png",categories:["career"],startISO:"2025-12-10T17:30:00+08:00",endISO:"2025-12-10T19:30:00+08:00"},
//   {title:"Skatathon 2025",dateText:"Fri, 19 Dec 2025",timeText:"7:00–9:00 PM",locText:"Fort Canning Park",img:"skate.jpeg",categories:["sports"],startISO:"2025-12-19T19:00:00+08:00",endISO:"2025-12-19T21:00:00+08:00"},
//   {title:"Art Jamming & Chill",dateText:"Sat, 22 Nov 2025",timeText:"2:00–5:00 PM",locText:"Tanjong Hall",img:"art.jpg",categories:["arts"],startISO:"2025-11-22T14:00:00+08:00",endISO:"2025-11-22T17:00:00+08:00"},
//   {title:"AI & Robotics Demo Day",dateText:"Sat, 6 Dec 2025",timeText:"10:00 AM–1:00 PM",locText:"SMU Labs",img:"robotics.webp",categories:["tech"],startISO:"2025-12-06T10:00:00+08:00",endISO:"2025-12-06T13:00:00+08:00"},
//   {title:"Eco-Smart Upcycling Workshop",dateText:"Sat, 13 Dec 2025",timeText:"3:00–6:00 PM",locText:"T3 Lobby",img:"upcycling.jpg",categories:["arts"],startISO:"2025-12-13T15:00:00+08:00",endISO:"2025-12-13T18:00:00+08:00"},
//   {title:"Career Coffee Chats",dateText:"Thu, 4 Dec 2025",timeText:"4:00–6:00 PM",locText:"LKCSB Atrium",img:"chat.webp",categories:["career"],startISO:"2025-12-04T16:00:00+08:00",endISO:"2025-12-04T18:00:00+08:00"}
// ];
let events = <?= $events_json ?>;
console.log(events);

let user_events = <?= $user_events_json ?>;
console.log(user_events);

/* =========================
   Local “My Events” store
   ========================= */
const MY_EVENTS_KEY = 'smu_my_events_v1';
const loadMyEvents = user_events;

const keyOf = (ev) => `${ev.title}__${ev.startISO}`;

console.log(loadMyEvents);

/* Clash with ANY saved event (excluding itself if saved) */
function clashesWithOthers(eventObj, savedList){
  const s = new Date(eventObj.startISO).getTime();
  const e = new Date(eventObj.endISO).getTime();
  return savedList.some(m => {
    if (keyOf(m) === keyOf(eventObj)) return false; // exclude self
    const S = new Date(m.startISO).getTime(), E = new Date(m.endISO).getTime();
    return Math.max(s, S) < Math.min(e, E);
  });
}

/* =========================
   Google Calendar link (no auth)
   ========================= */
function toUTCBasic(iso){
  const d = new Date(iso), pad = n => String(n).padStart(2,'0');
  return `${d.getUTCFullYear()}${pad(d.getUTCMonth()+1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}${pad(d.getUTCSeconds())}Z`;
}
function googleCalUrl({title, startISO, endISO, location, details=""}){
  const u = new URL('https://calendar.google.com/calendar/render');
  u.searchParams.set('action','TEMPLATE');
  u.searchParams.set('text', title);
  u.searchParams.set('dates', `${toUTCBasic(startISO)}/${toUTCBasic(endISO)}`);
  if (location) u.searchParams.set('location', location);
  if (details)  u.searchParams.set('details', details);
  return u.toString();
}

function formatDate(startISO) {
  const optsDate = {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  };
  const optsTime = { hour:'2-digit', minute:'2-digit' };

  let s = new Date(startISO);
  let dateText = s.toLocaleDateString(undefined, optsDate);
  return dateText;
}
/* =========================
   UI templates & rendering
   ========================= */
function cardTemplate(e, isSaved, hasClashAgainstOthers){
  const showClash = !isSaved && hasClashAgainstOthers;

  // Disable interactions on clash; Saved state always disabled
  const addDisabled  = showClash ? 'disabled aria-disabled="true" tabindex="-1"' : '';
  const saveDisabled = (isSaved || showClash) ? 'disabled aria-disabled="true"' : '';

  const addBtnClasses  = `btn ${showClash ? 'btn-danger' : 'btn-primary'} btn-sm ${showClash ? 'disabled' : ''}`;
  const saveBtnClasses = `btn ${isSaved ? 'btn-success' : (showClash ? 'btn-outline-secondary' : 'btn-outline-primary')} btn-sm`;

  return `
<div class="event-card ${e.category}">
  <img class="event-thumb" src="${e.picture}" alt="${e.title}">
  <div class="event-body">
    <div class="d-flex justify-content-between align-items-start">
      <h5 class="event-title mb-1">${e.title}</h5>
      ${showClash ? `<span class="badge text-bg-danger">Clashes with My Events</span>` : ``}
    </div>
    <ul class="meta-list">
      <li><i class="bi bi-calendar2-event"></i>${formatDate(e.startISO)}</li>
      <li><i class="bi bi-clock"></i>${e.start_time} - ${e.end_time}</li>
      <li><i class="bi bi-geo-alt"></i>${e.location}</li>
    </ul>
    <div class="event-actions d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary btn-sm" href="#">Details</a>
      <button class="${saveBtnClasses}"
         type="button"
         ${saveDisabled}
         data-save-local
         data-eid="${e.id}"
         data-title="${e.title}"
         data-location="${e.location}"
         data-start="${e.startISO}"
         data-end="${e.endISO}"
         data-img="${e.picture}"
         data-categories='${JSON.stringify(e.category)}'>
         ${isSaved ? 'Saved' : 'Save to My Events'}
      </button>
    </div>
  </div>
</div>`;
}

function renderCarousel(list){
  const inner = document.getElementById('carouselInner');
  const dots  = document.getElementById('carouselDots');
  if (!inner || !dots) return;

  inner.innerHTML = ''; dots.innerHTML = '';
  // finds the events that are saved and loads them
  const saved = loadMyEvents;

  for (let i = 0; i < list.length; i += 4) {
    const chunk = list.slice(i, i + 4);

    const cols = chunk.map(ev => {
      const isSaved = saved.some(m => keyOf(m) === keyOf(ev));
      const hasClashAgainstOthers = clashesWithOthers(ev, saved);
      // Use (e, isSaved, hasClashAgainstOthers) — no accentClass
      return `<div class="col-12 col-sm-6 col-lg-3">${cardTemplate(ev, isSaved, hasClashAgainstOthers)}</div>`;
    }).join('');

    inner.innerHTML += `
      <div class="carousel-item${i === 0 ? ' active' : ''}">
        <div class="row g-3">${cols}</div>
      </div>`;
    dots.innerHTML += `
      <button type="button" data-bs-target="#eventsCarousel"
              data-bs-slide-to="${i/4}"
              class="${i===0?'active':''}"
              aria-label="Slide ${i/4+1}"></button>`;
  }
}

/* =========================
   Filtering + Carousel init
   ========================= */
let current = 'all';
function applyFilter(){
  const filtered = current === 'all' ? events : events.filter(e => e.category.includes(current));
  renderCarousel(filtered);
  const el = document.getElementById('eventsCarousel');
  if (el) bootstrap.Carousel.getOrCreateInstance(el).to(0);
}

document.getElementById('filters')?.addEventListener('click', (e) => {
  const chip = e.target.closest('.chip'); if (!chip) return;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  current = chip.dataset.filter;
  applyFilter();
});

/* =========================
   Click handlers
   ========================= */
document.addEventListener('click', (e) => {
  // ignore clicks on disabled controls
  if (e.target.closest('.disabled,[disabled],[aria-disabled="true"]')) {
    e.preventDefault();
    return;
  }

  // Add to Google
  const gbtn = e.target.closest('[data-add-to-gcal]');
  if (gbtn) {
    e.preventDefault();
    const payload = {
      title: gbtn.dataset.title,
      startISO: gbtn.dataset.start,
      endISO: gbtn.dataset.end,
      location: gbtn.dataset.location
    };
    if (clashesWithOthers(payload, loadMyEvents)) return; // safety net
    window.open(googleCalUrl(payload), '_blank', 'noopener');
    return;
  }

  // Save to My Events
  const sbtn = e.target.closest('[data-save-local]');
  if (sbtn) {
    console.log(sbtn);
    const item = {
      id: sbtn.dataset.eid,
      title: sbtn.dataset.title,
      startISO: sbtn.dataset.start,
      endISO: sbtn.dataset.end,
      location: sbtn.dataset.location,
      img: sbtn.dataset.img || '',
      categories: JSON.parse(sbtn.dataset.categories || '')
    };
    const mine = loadMyEvents;
    if (clashesWithOthers(item, mine)) return; // block on clash
    if (!mine.some(m => keyOf(m) === keyOf(item))) {
      mine.push(item);
      // saveMyEvents(mine);  // add event to sql event_person table
      storeEvents(item.id);
    }
    applyFilter(); // refresh to update Saved/disabled states
  }
});

function storeEvents(eid) {
  let userID = <?= $currentUser ?>;
  let url = "axios/sql_updating.php";

  axios.get(url, { params:
    {
    "personID": userID,
    "eventID": eid,
    "option": "add"
    }
  })
    .then(response => {
        console.log(response);
        
    })
    .catch(error => {
        console.log(error.message);
    });

}

/* Initial render */
applyFilter();

}); // DOMContentLoaded
</script>
</body>
</html>