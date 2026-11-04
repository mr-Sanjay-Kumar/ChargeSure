<?php
session_start();

require_once "config/database.php";

// Allow only administrators
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Fetch all user reports with station and user details
$sql = "SELECT
            r.id,
            r.status,
            r.waiting_minutes,
            r.comments,
            r.reported_at,
            s.station_name,
            s.location,
            u.name AS user_name,
            u.email AS user_email
        FROM reports r
        INNER JOIN stations s ON r.station_id = s.id
        INNER JOIN users u ON r.user_id = u.id
        ORDER BY r.reported_at DESC, r.id DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load reports. Please check the database.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Reports | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">

<!-- Navigation -->
<nav class="navbar navbar-dark bg-dark">
    <div class="container">

        <a class="navbar-brand fw-bold" href="admin.php">
            ChargeSure Admin
        </a>

        <div class="d-flex gap-2">

            <a href="admin.php" class="btn btn-outline-light btn-sm">
                Dashboard
            </a>

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

    <h2 class="fw-bold mb-2">Manage User Reports</h2>

    <p class="text-muted mb-4">
        View charger availability reports submitted by registered users.
    </p>

    <!-- Report Count -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-0">
                Total Reports:
                <span class="badge bg-primary">
                    <?php echo $result->num_rows; ?>
                </span>
            </h5>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">All Charging Station Reports</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Report ID</th>
                            <th>Station</th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th>Waiting Time</th>
                            <th>Comments</th>
                            <th>Reported At</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($report = $result->fetch_assoc()): ?>

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
                                    <?php echo (int) $report["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["station_name"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["location"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["user_name"]); ?>

                                    <br>

                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($report["user_email"]); ?>
                                    </small>
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
                                    <?php
                                    echo $report["comments"] !== null &&
                                         trim($report["comments"]) !== ""
                                        ? nl2br(htmlspecialchars($report["comments"]))
                                        : "No comments";
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($report["reported_at"] ?? ""); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="text-center py-4">
                                No user reports have been submitted yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <p class="text-muted small mb-0">
                These are community-submitted reports. They do not represent
                independently verified, real-time charger availability.
            </p>

        </div>
    </div>

    <div class="mt-4">
        <a href="admin.php" class="btn btn-secondary">
            Back to Admin Dashboard
        </a>
    </div>

</div>

<script src="js/script.js"></script>

</body>
</html>