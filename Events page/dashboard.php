<?php
session_start();
spl_autoload_register(
    function ($class) {
        require_once "model/$class.php";
    }
);

if (!isset($_SESSION['username'])) {
    echo "
  <script>
    alert('Please login to access this page');
    window.location.href = 'Login.php';
  </script>";

    exit();
}

$username = $_SESSION['username'];

$dao = new EventCollectionDAO;

$userID = $dao->getUserId($username);
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

$all_users_obj = $dao->getUsers();
$all_users_arr = array_map(function ($user) {
    return [
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'school' => $user->getSchool(),
        'points' => $user->getPoints()
    ];
}, $all_users_obj);


$all_users_json = json_encode($all_users_arr);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Omni Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="events_style.css">
    <style>
        /* styling for dashboard */
        .dashboard-header {
            text-align: center;
            color: #041373;
            font-weight: bolder;
            margin: 20px 0;
        }

        .card-custom {
            border-radius: 14px;
            background: #fff;
            border: 1px solid #ececf4;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
            padding: 20px;
            transition: .18s ease;
        }

        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .1);
        }

        .badge-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            margin-right: 10px;
        }

        .badge-bronze {
            background: #cd7f32;
        }

        .badge-silver {
            background: #c0c0c0;
        }

        .badge-gold {
            background: #ffd700;
        }

        .leaderboard-table th {
            background-color: #041373;
            color: white;
        }

        .leaderboard-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .search-bar {
            margin-bottom: 15px;
            max-width: 300px;
        }
    </style>
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
                    <a class="nav-item nav-link ula nvit" href="events.php">Events </a>
                    <!-- <a class="nav-item nav-link ula nvit" href="#">Daily Challenge</a> -->
                    <a class="nav-item nav-link ula nvit" href="#">Account</a>
                    <a class="nav-item nav-link ula nvit" href="my_events_user.php">My Events</a>
                    <a class="nav-item nav-link ula nvit" href="#">Dashboard</a>
                </div>
                <div class="navbar-nav ms-auto"><a class="nav-item nav-link ula nvit me-3" id="logout" href="logout.php">Logout</a></div>
            </div>
        </nav>


        <div class="container">
            <div class="row">
                <!-- My Stats -->
                <div class="col-lg-4 mb-4">
                    <div class="card-custom">
                        <h4><?= $username ?>'s Stats</h4>
                        <p><strong>Total Points:</strong> <span id="userPoints"></span></p>
                        <p><strong>Rank:</strong> <span id="userRank"></span></p>
                        <div class="d-flex align-items-center">
                            <div id="userBadge"></div>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="col-lg-8 mb-4">
                    <div class="card-custom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4>Leaderboard</h4>
                            <input type="text" id="searchInput" class="form-control form-control-sm search-bar" placeholder="Search user...">
                        </div>
                        <table class="table leaderboard-table mt-3">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Username</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody id="leaderboardBody"></tbody>
                        </table>
                        <div class="text-center">
                            <button class="btn btn-outline-primary btn-sm">View More</button>
                            <p class="mt-2"><strong>Your current rank:</strong> <span id="yourCurrentRank"></span></p>
                        </div>
                    </div>
                </div>

            </div>
            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-12 mb-5">
                    <div class="card-custom">
                        <h4>Recent Activity</h4>
                        <div class="row" id="recentEvents"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Helper fns
        function formatDate(startISO) {
            const optsDate = {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            };
            const optsTime = {
                hour: '2-digit',
                minute: '2-digit'
            };

            let s = new Date(startISO);
            let dateText = s.toLocaleDateString(undefined, optsDate);
            return dateText;
        }

        let leaderboard = <?= $all_users_json ?>

        let username = "<?php echo $username; ?>";
        let user = leaderboard.find(u => u.username === username) || leaderboard[0];

        // Populate leaderboard
        let leaderboardBody = document.getElementById("leaderboardBody");
        leaderboard.slice(0, 10).forEach((u, i) => {
            leaderboardBody.innerHTML += `
        <tr>
          <td>${i + 1}</td>
          <td>${u.username}</td>
          <td>${u.points}</td>
        </tr>`;
        });

        // Personal card
        document.getElementById("userPoints").innerText = user.points;
        document.getElementById("userRank").innerText = leaderboard.indexOf(user) + 1;
        document.getElementById("yourCurrentRank").innerText = leaderboard.indexOf(user) + 1;

        // Badge logic
        let badgeContainer = document.getElementById("userBadge");
        let badgeClass = "badge-bronze",
            badgeText = "Bronze";
        if (user.points >= 30 && user.points < 80) {
            badgeClass = "badge-silver";
            badgeText = "Silver";
        } else if (user.points >= 80) {
            badgeClass = "badge-gold";
            badgeText = "Gold";
        }
        badgeContainer.innerHTML = `<div class="badge-circle ${badgeClass}">${badgeText[0]}</div><strong>${badgeText} Badge</strong>`;

        // Recent activity
        let recentEvents = <?= $user_events_json ?>;
        let recentContainer = document.getElementById("recentEvents");
        recentEvents.forEach(ev => {
            recentContainer.innerHTML += `
        <div class="col-md-4 mb-3">
          <div class="event-card ${ev.category}">
            <img class="event-thumb" src="${ev.picture}" alt="${ev.title}">
            <div class="event-body">
              <div class="d-flex justify-content-between align-items-start">
                <h5 class="event-title mb-1">${ev.title}</h5>
              </div>
              <ul class="meta-list">
                <li><i class="bi bi-calendar2-event"></i>${formatDate(ev.startISO)}</li>
                <li><i class="bi bi-clock"></i>${ev.start_time} - ${ev.end_time}</li>
                <li><i class="bi bi-geo-alt"></i>${ev.location}</li>
              </ul>
            </div>
          </div>    
        </div>`;
        });

        // Search filter
        document.getElementById("searchInput").addEventListener("keyup", function() {
            let filter = this.value.toLowerCase();
            let rows = leaderboardBody.getElementsByTagName("tr");
            for (let r of rows) {
                let usernameCell = r.cells[1].textContent.toLowerCase();
                r.style.display = usernameCell.includes(filter) ? "" : "none";
            }
        });
    </script>
</body>

</html>