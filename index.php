<?php
session_start();

require_once "config/database.php";

// Fetch each station and its latest community report
$sql = "
    SELECT
        s.id,
        s.station_name,
        s.location,
        s.charger_type,
        s.total_chargers,
        r.status,
        r.waiting_minutes,
        r.comments,
        r.reported_at
    FROM stations s
    LEFT JOIN reports r
        ON r.id = (
            SELECT r2.id
            FROM reports r2
            WHERE r2.station_id = s.id
            ORDER BY r2.reported_at DESC, r2.id DESC
            LIMIT 1
        )
    ORDER BY s.id ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load charging stations. Please check the database query.");
}

// Format report age for display
function reportAge($reportedAt)
{
    if (empty($reportedAt)) {
        return "No reports yet";
    }

    $timestamp = strtotime($reportedAt);

    if ($timestamp === false) {
        return "Report time unavailable";
    }

    $seconds = time() - $timestamp;

    if ($seconds < 60) {
        return "Just now";
    }

    if ($seconds < 3600) {
        return floor($seconds / 60) . " minutes ago";
    }

    if ($seconds < 86400) {
        return floor($seconds / 3600) . " hours ago";
    }

    return floor($seconds / 86400) . " days ago";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ChargeSure - Check Before You Charge</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* ==================================
           HERO SECTION - GREEN GRADIENT
           ================================== */

        .hero-section {
            background: linear-gradient(
                135deg,
                #087f5b,
                #20a879
            ) !important;

            padding: 70px 20px;
            color: #ffffff !important;
        }

        .hero-section h1,
        .hero-section p {
            color: #ffffff !important;
        }

        .hero-section .btn-light {
            background-color: #ffffff;
            color: #087f5b;
            border: 2px solid #ffffff;
            font-weight: 600;
        }

        .hero-section .btn-light:hover {
            background-color: #e8f5ee;
            color: #065f46;
        }

        .hero-section .btn-dark {
            font-weight: 600;
        }

        /* ==================================
           STATION CARDS
           ================================== */

        .station-card {
            border: none;
            border-radius: 14px;
            transition: transform 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .station-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.12);
        }

        /* ==================================
           STATUS BADGES
           ================================== */

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-available {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-occupied {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-out {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-unknown {
            background-color: #e2e3e5;
            color: #41464b;
        }

        /* ==================================
           REPORT DETAILS
           ================================== */

        .report-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
        }

        /* ==================================
           RESPONSIVE DESIGN
           ================================== */

        @media (max-width: 768px) {
            .hero-section {
                padding: 45px 20px;
            }

            .hero-section h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">

        <div class="container">

            <a class="navbar-brand fw-bold" href="index.php">
                ⚡ ChargeSure
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarContent"
                aria-controls="navbarContent"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">

                <ul class="navbar-nav me-auto ms-lg-4">

                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            Home
                        </a>
                    </li>

                </ul>

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <?php if (isset($_SESSION["user_id"])): ?>

                        <span class="text-white">
                            Welcome,
                            <?= htmlspecialchars($_SESSION["user_name"]) ?>!
                        </span>

                        <a href="report.php" class="btn btn-success">
                            Report Status
                        </a>

                        <a href="profile.php" class="btn btn-outline-light">
                            My Profile
                        </a>

                        <?php if (($_SESSION["user_role"] ?? "") === "admin"): ?>
                            <a href="admin.php" class="btn btn-outline-light">
                                Admin Dashboard
                            </a>
                        <?php endif; ?>

                        <a href="logout.php" class="btn btn-outline-light">
                            Logout
                        </a>

                    <?php else: ?>

                        <a href="login.php" class="btn btn-outline-light">
                            Login
                        </a>

                        <a href="register.php" class="btn btn-success">
                            Register
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </nav>


    <!-- Hero Section - GREEN -->
    <section class="hero-section text-center text-white">

        <div class="container">

            <h1 class="display-4 fw-bold">
                Find Your EV Charging Station
            </h1>

            <p class="lead mt-3">
                Check Before You Charge.
            </p>

            <p class="mb-4">
                Check the latest community-reported charging status
                and estimated waiting time before travelling.
            </p>

            <a href="#stations" class="btn btn-light btn-lg">
                Explore Stations
            </a>

            <?php if (isset($_SESSION["user_id"])): ?>

                <a href="report.php" class="btn btn-dark btn-lg ms-2">
                    Submit a Report
                </a>

            <?php endif; ?>

        </div>

    </section>


    <!-- Information Section -->
    <section class="container my-5">

        <div class="row g-4 text-center">

            <div class="col-md-4">

                <div class="card h-100 shadow-sm border-0">

                    <div class="card-body">

                        <h4>📍 Find Stations</h4>

                        <p class="text-muted mb-0">
                            Browse EV charging station information
                            in one place.
                        </p>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card h-100 shadow-sm border-0">

                    <div class="card-body">

                        <h4>🔋 Check Reports</h4>

                        <p class="text-muted mb-0">
                            View the latest status reported by users.
                        </p>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card h-100 shadow-sm border-0">

                    <div class="card-body">

                        <h4>🤝 Help Other Drivers</h4>

                        <p class="text-muted mb-0">
                            Submit station updates to help other
                            EV drivers plan their journeys.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- Charging Stations Section -->
    <section class="container my-5" id="stations">

        <div class="text-center mb-4">

            <h2 class="fw-bold">
                EV Charging Stations
            </h2>

            <p class="text-muted">
                View station information and the latest submitted reports.
            </p>

        </div>

        <div class="row g-4">

            <?php if ($result->num_rows > 0): ?>

                <?php while ($station = $result->fetch_assoc()): ?>

                    <?php
                    $status = $station["status"] ?? "";

                    $statusClass = "status-unknown";

                    if ($status === "Available") {
                        $statusClass = "status-available";
                    } elseif ($status === "Occupied") {
                        $statusClass = "status-occupied";
                    } elseif ($status === "Out of Service") {
                        $statusClass = "status-out";
                    }
                    ?>

                    <div class="col-md-6 col-lg-4">

                        <div class="card station-card shadow-sm h-100">

                            <div class="card-body p-4">

                                <h5 class="card-title fw-bold">
                                    <?= htmlspecialchars($station["station_name"]) ?>
                                </h5>

                                <p class="text-muted mb-3">
                                    📍
                                    <?= htmlspecialchars($station["location"]) ?>
                                </p>

                                <p>
                                    <strong>Charger Type:</strong>
                                    <?= htmlspecialchars($station["charger_type"] ?? "Not specified") ?>
                                </p>

                                <p>
                                    <strong>Total Chargers:</strong>
                                    <?= htmlspecialchars((string) $station["total_chargers"]) ?>
                                </p>

                                <hr>

                                <h6 class="fw-bold">
                                    Latest Reported Status
                                </h6>

                                <?php if ($station["status"] !== null): ?>

                                    <p class="mb-3">

                                        <span class="status-badge <?= $statusClass ?>">

                                            <?php if ($status === "Available"): ?>
                                                ✓ Available
                                            <?php elseif ($status === "Occupied"): ?>
                                                ● Occupied
                                            <?php elseif ($status === "Out of Service"): ?>
                                                ⚠ Out of Service
                                            <?php else: ?>
                                                <?= htmlspecialchars($status) ?>
                                            <?php endif; ?>

                                        </span>

                                    </p>

                                    <div class="report-details">

                                        <p class="mb-2">
                                            <strong>Estimated Waiting:</strong>
                                            <?= (int) $station["waiting_minutes"] ?>
                                            minutes
                                        </p>

                                        <p class="mb-2">
                                            <strong>Reported:</strong>
                                            <?= htmlspecialchars(
                                                reportAge($station["reported_at"])
                                            ) ?>
                                        </p>

                                        <p class="mb-0">
                                            <strong>Report Time:</strong>
                                            <?= htmlspecialchars($station["reported_at"]) ?>
                                        </p>

                                        <?php if (!empty($station["comments"])): ?>

                                            <hr>

                                            <p class="mb-0">
                                                <strong>Comment:</strong><br>

                                                <?= nl2br(
                                                    htmlspecialchars($station["comments"])
                                                ) ?>
                                            </p>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="status-badge status-unknown">
                                        No Reports Yet
                                    </span>

                                    <p class="text-muted small mt-3 mb-0">
                                        No community report has been submitted
                                        for this station.
                                    </p>

                                <?php endif; ?>


                                <?php if (isset($_SESSION["user_id"])): ?>

                                    <a
                                        href="report.php?station_id=<?= (int) $station["id"] ?>"
                                        class="btn btn-outline-success w-100 mt-3"
                                    >
                                        Report This Station
                                    </a>

                                <?php else: ?>

                                    <a
                                        href="login.php"
                                        class="btn btn-outline-success w-100 mt-3"
                                    >
                                        Login to Report
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="col-12">

                    <div class="alert alert-info text-center">
                        No charging stations are available in the database.
                    </div>

                </div>

            <?php endif; ?>

        </div>

        

    </section>


    <!-- Call to Action -->
    <section class="container my-5">

        <div class="p-4 p-md-5 text-center bg-light rounded-3">

            <h3 class="fw-bold">
                Help Make EV Charging Easier
            </h3>

            <p class="text-muted">
                Share station updates and help fellow EV drivers
                make better charging decisions.
            </p>

            <?php if (isset($_SESSION["user_id"])): ?>

                <a href="report.php" class="btn btn-success btn-lg">
                    Submit a Station Report
                </a>

            <?php else: ?>

                <a href="register.php" class="btn btn-success btn-lg">
                    Join ChargeSure
                </a>

            <?php endif; ?>

        </div>

    </section>


    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3 mt-5">

        <div class="container">

            <p class="mb-1">
                &copy; <?= date("Y") ?> ChargeSure
            </p>

            <small>
                Check Before You Charge.
            </small>

        </div>

    </footer>


    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="js/script.js"></script>

</body>

</html>