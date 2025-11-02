<?php
  session_start();
  spl_autoload_register(
    function ($class) {
      require_once "model/$class.php";
    }
  );

  if (!($_SESSION['role']=='admin')){
    echo "
    <script>
      alert('Unauthorised access!');
      window.location.href = 'Login.php';
    </script>";

    exit();
  }

  $dao = new EventCollectionDAO();

  $userID = $dao->getUserId($_SESSION["username"]);

  // get the admin's events
  $user_events_obj = $dao->getUsersEvents($userID);
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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Manage Events</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="events_style.css">
  <style>
    .sect {
      display: flex;
      gap: 1.5rem;
      padding: 1.5rem;
      overflow-x: auto;
    }
    
    .sect .column {
      flex: 1;
      min-width: 280px;
      background: #f8f9fa;
      border-radius: 8px;
      padding: 1rem;
    }
    
    .sect .column h3 {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #333;
    }
    
    .sect .card {
      background: white;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s;
    }
    
    .sect .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .sect .card h4 {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    .sect .tags {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    
    .sect .tag {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 500;
    }
    
    .sect .tag.purple {
      background: #e9d5ff;
      color: #7c3aed;
    }
    
    .new-task-btn {
      margin: 1rem 1.5rem;
      padding: 0.75rem 1.5rem;
      background: #0d6efd;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s;
    }
    
    .new-task-btn:hover {
      background: #0b5ed7;
    }
    
    .event-meta {
      font-size: 0.85rem;
      color: #666;
      margin: 0.5rem 0;
    }
    
    .event-meta i {
      margin-right: 0.25rem;
    }
  </style>
</head>
<body>

<div class="container-fluid h-100">
  <div class="row h-100">

    <!-- sidebar -->
    <aside class="col-auto sidebar d-flex flex-column p-4" id="navbarid">
      <ul class="navbar-nav ps-0">
        <div class="navbar-nav" id="navitems">
        <a class="nav-item nav-link ula nvit" href="#">Manage Events </a>
        <a class="nav-item nav-link ula nvit" href="#">Statistics</a>
        <a class="nav-item nav-link ula nvit" href="#">Chat</a>
        <a class="nav-item nav-link ula nvit" id="logout" href="logout.php">Logout</a>
      </ul>
    </aside>

  <main class="col d-flex flex-column p-0">
    <header class="top-nav d-flex justify-content-between align-items-center px-4 py-3">
      <div class="wbname">
        <h1>Omni</h1>
      </div>
      <div class="d-flex align-items-end gap-3">
        <button class="btn btn-outline-primary">
          <a class="nav-item nav-link ula nvit" id="logout" href="logout.php">Logout</a>
        </button>
      </div>
    </header>

    <button class="new-task-btn">+ Add new event</button>

    <section class="sect" id="sectBoard">
      <div class="column" id="previousEvents">
        <h3>Previous</h3>
      </div>

      <div class="column" id="ongoingEvents">
        <h3>Ongoing</h3>
      </div>

      <div class="column" id="futureEvents">
        <h3>Future</h3>
      </div>
    </section>

  </main>
  </div> 
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
/* =========================
   DATA (with ISO datetimes)
   ========================= */
// const events = [
//   {title:"HackSMU: 24-Hour Hackathon",dateText:"Fri, 5 Dec 2025",timeText:"7:00 PM - Sat, 7:00 PM",locText:"SIS Building",img:"pictures/hackathon.png",categories:["tech"],startISO:"2025-12-05T19:00:00+08:00",endISO:"2025-12-06T19:00:00+08:00"},
//   {title:"AI & Robotics Demo Day",dateText:"Sat, 6 Dec 2025",timeText:"10:00 AM - 1:00 PM",locText:"SMU Labs",img:"robotics.webp",categories:["tech"],startISO:"2025-12-06T10:00:00+08:00",endISO:"2025-12-06T13:00:00+08:00"}
// ];
let events = <?= $user_events_json ?>;
console.log(events);

/* =========================
   Helper functions
   ========================= */
function keyOf(e) {
  return `${e.title}|${e.startISO}|${e.endISO}`;
}

// use for manage_events_admin
// function saveMyEvents(list) {
//   localStorage.setItem('myEvents', JSON.stringify(list));
// }

function googleCalUrl({title, startISO, endISO, location}) {
  const start = startISO.replace(/[-:]/g,'').split('.')[0];
  const end = endISO.replace(/[-:]/g,'').split('.')[0];
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: title,
    dates: `${start}/${end}`,
    location: location || '',
    details: ''
  });
  return `https://calendar.google.com/calendar/render?${params}`;
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

function clashesWithOthers(eventObj, savedList){
  const s = new Date(eventObj.startISO).getTime();
  const e = new Date(eventObj.endISO).getTime();
  return savedList.some(m => {
    if (keyOf(m) === keyOf(eventObj)) return false;
    const S = new Date(m.startISO).getTime(), E = new Date(m.endISO).getTime();
    return Math.max(s, S) < Math.min(e, E);
  });
}

/* =========================
   Determine event status
   ========================= */
function getEventStatus(event) {
  const now = new Date().getTime();
  const start = new Date(event.startISO).getTime();
  const end = new Date(event.endISO).getTime();
  
  if (end < now) return 'previous';
  if (start <= now && now <= end) return 'ongoing';
  return 'future';
}

/* =========================
   Card template
   ========================= */
function cardTemplate(e, isSaved, hasClashAgainstOthers){
  const showClash = !isSaved && hasClashAgainstOthers;
  
  const saveBtnClasses = `btn ${isSaved ? 'btn-success' : (showClash ? 'btn-outline-secondary' : 'btn-outline-primary')} btn-sm`;
  const saveDisabled = (isSaved || showClash) ? 'disabled aria-disabled="true"' : '';

  return `
<div class="card">
  <div class="avatars"></div>
  <h4>${e.title}</h4>
  <div class="event-meta">
    <div><i class="bi bi-calendar2-event"></i> ${formatDate(e.startISO)}</div>
    <div><i class="bi bi-clock"></i> ${e.start_time} - ${e.end_time}</div>
    <div><i class="bi bi-geo-alt"></i> ${e.location}</div>
  </div>
  <div class="tags">
    <span class="tag purple">${e.category}</span>
    ${showClash ? `<span class="badge text-bg-danger">Clashes</span>` : ''}
  </div>
  <div class="event-actions mt-2 d-flex gap-2 flex-wrap">
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
       ${isSaved ? 'Saved' : 'Save'}
    </button>
    <a class="btn btn-outline-secondary btn-sm" href="#">Details</a>
  </div>
</div>`;
}

function renderSect() {
  let saved = events;
  const previousCol = document.getElementById('previousEvents');
  const ongoingCol = document.getElementById('ongoingEvents');
  const futureCol = document.getElementById('futureEvents');
  
  // Clear existing cards (keep headers)
  [previousCol, ongoingCol, futureCol].forEach(col => {
    const cards = col.querySelectorAll('.card');
    cards.forEach(card => card.remove());
  });
  
  // Categorize and render events
  events.forEach(event => {
    const status = getEventStatus(event);
    const isSaved = saved.some(m => keyOf(m) === keyOf(event));
    const hasClashAgainstOthers = clashesWithOthers(event, saved);
    
    const cardHtml = cardTemplate(event, isSaved, hasClashAgainstOthers);
    
    if (status === 'previous') {
      previousCol.innerHTML += cardHtml;
    } else if (status === 'ongoing') {
      ongoingCol.innerHTML += cardHtml;
    } else {
      futureCol.innerHTML += cardHtml;
    }
  });
}

/* =========================
   Click handlers
   ========================= */
document.addEventListener('click', (e) => {
  if (e.target.closest('.disabled,[disabled],[aria-disabled="true"]')) {
    e.preventDefault();
    return;
  }

  const sbtn = e.target.closest('[data-save-local]');
  if (sbtn) {
    const item = {
      id: sbtn.dataset.eid,
      title: sbtn.dataset.title,
      startISO: sbtn.dataset.start,
      endISO: sbtn.dataset.end,
      location: sbtn.dataset.location,
      img: sbtn.dataset.img || '',
      categories: JSON.parse(sbtn.dataset.categories || '[]')
    };
    const mine = events;
    if (clashesWithOthers(item, mine)) return;
    if (!mine.some(m => keyOf(m) === keyOf(item))) {
      mine.push(item);
      saveMyEvents(mine);
    }
    renderSect();
  }
});

renderSect();

}); 
</script>
</body>
</html>