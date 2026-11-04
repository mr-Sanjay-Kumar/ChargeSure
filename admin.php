<?php
session_start();

require_once "config/database.php";

// Allow only logged-in administrators
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Get administrator name
$admin_name = $_SESSION["user_name"] ?? "Administrator";

// Get total charging stations
$station_result = $conn->query(
    "SELECT COUNT(*) AS total FROM stations"
);

$total_stations = $station_result
    ? (int) $station_result->fetch_assoc()["total"]
    : 0;

// Get total registered users
$user_result = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
);

$total_users = $user_result
    ? (int) $user_result->fetch_assoc()["total"]
    : 0;

// Get total user reports
$report_result = $conn->query(
    "SELECT COUNT(*) AS total FROM reports"
);

$total_reports = $report_result
    ? (int) $report_result->fetch_assoc()["total"]
    : 0;

// Get latest five user reports
$latest_reports = $conn->query(
    "SELECT
        r.id,
        r.status,
        r.waiting_minutes,
        r.reported_at,
        s.station_name,
        s.location,
        u.name AS user_name
     FROM reports r
     INNER JOIN stations s ON r.station_id = s.id
     INNER JOIN users u ON r.user_id = u.id
     ORDER BY r.reported_at DESC, r.id DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background-color: #f5f7fb;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #172554, #2563eb);
            color: white;
            padding: 35px;
            border-radius: 15px;
        }

        .stat-card {
            border: none;
            border-radius: 14px;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .action-card {
            border: none;
            border-radius: 14px;
            height: 100%;
        }

        .action-card .card-body {
            padding: 25px;
        }

        .table th {
            white-space: nowrap;
        }
    </style>
</head>

<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-dark bg-dark">
    <div class="container">

        <a class="navbar-brand fw-bold" href="admin.php">
            ⚡ ChargeSure Admin
        </a>

        <div class="d-flex gap-2">

            <a href="index.php" class="btn btn-outline-light btn-sm">
                Homepage
            </a>

            <a href="logout.php" class="btn btn-danger btn-sm">
                Logout
            </a>

        </div>

    </div>
</nav>

<div class="container py-5">

    <!-- Dashboard Welcome -->
    <div class="dashboard-header mb-5">

        <h1 class="fw-bold">Admin Dashboard</h1>

        <p class="mb-1">
            Welcome, <?php echo htmlspecialchars($admin_name); ?>!
        </p>

        <p class="mb-0">
            Manage charging stations and monitor community-submitted reports.
        </p>

    </div>

    <!-- Statistics -->
    <h3 class="fw-bold mb-3">System Overview</h3>

    <div class="row g-4 mb-5">

        <!-- Stations -->
        <div class="col-md-4">

            <div class="card stat-card shadow-sm">
                <div class="card-body">

                    <h6 class="text-muted">
                        Total Charging Stations
                    </h6>

                    <div class="stat-number text-primary">
                        <?php echo $total_stations; ?>
                    </div>

                    <p class="mb-0 text-muted">
                        Registered stations
                    </p>

                </div>
            </div>

        </div>

        <!-- Users -->
        <div class="col-md-4">

            <div class="card stat-card shadow-sm">
                <div class="card-body">

                    <h6 class="text-muted">
                        Registered Users
                    </h6>

                    <div class="stat-number text-success">
                        <?php echo $total_users; ?>
                    </div>

                    <p class="mb-0 text-muted">
                        Total registered accounts
                    </p>

                </div>
            </div>

        </div>

        <!-- Reports -->
        <div class="col-md-4">

            <div class="card stat-card shadow-sm">
                <div class="card-body">

                    <h6 class="text-muted">
                        Total User Reports
                    </h6>

                    <div class="stat-number text-warning">
                        <?php echo $total_reports; ?>
                    </div>

                    <p class="mb-0 text-muted">
                        Community-submitted reports
                    </p>

                </div>
            </div>

        </div>

    </div>

    <!-- Management Options -->
    <h3 class="fw-bold mb-3">Management Options</h3>

    <div class="row g-4 mb-5">

        <!-- Manage Stations -->
        <div class="col-md-6">

            <div class="card action-card shadow-sm">

                <div class="card-body">

                    <h4 class="fw-bold">
                        ⚡ Manage Charging Stations
                    </h4>

                    <p class="text-muted">
                        Add new stations, view station details, edit
                        information, and delete eligible stations.
                    </p>

                    <a
                        href="manage-stations.php"
                        class="btn btn-primary"
                    >
                        Manage Stations
                    </a>

                </div>

            </div>

        </div>

        <!-- Manage Reports -->
        <div class="col-md-6">

            <div class="card action-card shadow-sm">

                <div class="card-body">

                    <h4 class="fw-bold">
                        📋 Manage User Reports
                    </h4>

                    <p class="text-muted">
                        Review user-submitted availability statuses,
                        waiting times, comments, and report timestamps.
                    </p>

                    <a
                        href="manage-reports.php"
                        class="btn btn-success"
                    >
                        View User Reports
                    </a>

                </div>

            </div>

        </div>

    </div>

    <!-- Latest Reports -->
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">

            <h4 class="fw-bold mb-0">
                Latest User Reports
            </h4>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>Station</th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th>Waiting Time</th>
                            <th>Reported At</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($latest_reports && $latest_reports->num_rows > 0): ?>

                        <?php while ($report = $latest_reports->fetch_assoc()): ?>

                            <?php
                            $badge_class = "secondary";

                            if ($report["status"] === "Available") {
                                $badge_class = "success";
                            } elseif ($report["status"] === "Occupied") {
                                $badge_class = "warning text-dark";
                            } elseif ($report["status"] === "Out of Service") {
                                $badge_class = "danger";
                            }
                            ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($report["station_name"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["location"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["user_name"]); ?>
                                </td>

                                <td>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($report["status"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    if ($report["waiting_minutes"] === null) {
                                        echo "Not specified";
                                    } else {
                                        echo (int) $report["waiting_minutes"] . " minutes";
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["reported_at"] ?? ""); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="text-center py-4">
                                No user reports available yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <p class="text-muted small mb-0">
                Availability information is community-reported and is not
                independently verified in real time.
            </p>

            <div class="mt-3">

                <a
                    href="manage-reports.php"
                    class="btn btn-outline-primary"
                >
                    View All Reports
                </a>

            </div>

        </div>

    </div>

</div>

<footer class="text-center text-muted py-4">
    <p class="mb-0">
        &copy; <?php echo date("Y"); ?> ChargeSure |
        Check Before You Charge
    </p>
</footer>

<script src="js/script.js"></script>

</body>
</html>