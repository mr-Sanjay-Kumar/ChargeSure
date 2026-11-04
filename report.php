<?php
session_start();

require_once "config/database.php";

// Check whether the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$message = "";
$messageType = "success";

// Get the selected station ID from the homepage
$selectedStationId = filter_input(
    INPUT_GET,
    "station_id",
    FILTER_VALIDATE_INT
);

if (!$selectedStationId || $selectedStationId <= 0) {
    $selectedStationId = 0;
}

// Handle report submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stationId = filter_input(
        INPUT_POST,
        "station_id",
        FILTER_VALIDATE_INT
    );

    $status = trim($_POST["status"] ?? "");
    $waitingInput = trim($_POST["waiting_minutes"] ?? "");
    $comments = trim($_POST["comments"] ?? "");

    $allowedStatuses = [
        "Available",
        "Occupied",
        "Out of Service"
    ];

    $waitingMinutes = null;

    // Validate waiting time
    if ($waitingInput !== "") {

        $validatedWaiting = filter_var(
            $waitingInput,
            FILTER_VALIDATE_INT
        );

        if (
            $validatedWaiting === false ||
            $validatedWaiting < 0 ||
            $validatedWaiting > 1440
        ) {
            $message = "Waiting time must be between 0 and 1440 minutes.";
            $messageType = "danger";
        } else {
            $waitingMinutes = $validatedWaiting;
        }
    }

    // Validate the remaining fields
    if ($message === "") {

        if (!$stationId || $stationId <= 0) {

            $message = "Please select a valid charging station.";
            $messageType = "danger";

        } elseif (!in_array($status, $allowedStatuses, true)) {

            $message = "Please select a valid charging status.";
            $messageType = "danger";

        } elseif (strlen($comments) > 2000) {

            $message = "Comments cannot exceed 2000 characters.";
            $messageType = "danger";

        } else {

            // Verify that the station exists
            $check = $conn->prepare(
                "SELECT id FROM stations WHERE id = ?"
            );

            $check->bind_param("i", $stationId);
            $check->execute();

            $stationResult = $check->get_result();
            $stationExists = $stationResult->num_rows > 0;

            $check->close();

            if (!$stationExists) {

                $message = "The selected charging station does not exist.";
                $messageType = "danger";

            } else {

                // Insert the report into the database
                $stmt = $conn->prepare(
                    "INSERT INTO reports
                    (
                        user_id,
                        station_id,
                        status,
                        waiting_minutes,
                        comments
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                if ($stmt) {

                    $stmt->bind_param(
                        "iisis",
                        $userId,
                        $stationId,
                        $status,
                        $waitingMinutes,
                        $comments
                    );

                    if ($stmt->execute()) {

                        $stmt->close();

                        // Redirect to avoid duplicate submissions
                        header("Location: report.php?success=1");
                        exit;

                    } else {

                        $message = "Unable to submit the report. Please try again.";
                        $messageType = "danger";

                        $stmt->close();

                    }

                } else {

                    $message = "Unable to prepare the report. Please try again.";
                    $messageType = "danger";

                }
            }
        }
    }

    $selectedStationId = (
        $stationId !== false &&
        $stationId !== null &&
        $stationId > 0
    ) ? $stationId : 0;
}

// Show the success message after redirect
if (
    isset($_GET["success"]) &&
    $_GET["success"] === "1"
) {
    $message = "Your charging station report was submitted successfully!";
    $messageType = "success";
}

// Fetch all charging stations
$stationQuery = $conn->prepare(
    "SELECT id, station_name, location
     FROM stations
     ORDER BY station_name ASC"
);

$stationQuery->execute();

$stations = $stationQuery->get_result();

// Validate a station ID supplied through the URL
if ($selectedStationId > 0) {

    $stationCheck = $conn->prepare(
        "SELECT id FROM stations WHERE id = ?"
    );

    $stationCheck->bind_param("i", $selectedStationId);
    $stationCheck->execute();

    $selectedStationExists =
        $stationCheck->get_result()->num_rows > 0;

    $stationCheck->close();

    if (!$selectedStationExists) {
        $selectedStationId = 0;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Charger Status | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: #f5f7fb;
            font-family: Arial, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #ff8c00, #ff5e00);
            color: white;
            padding: 45px 0;
            border-radius: 0 0 24px 24px;
        }

        .report-card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.06);
        }

        .orange-button {
            background: #ff7900;
            color: white;
            border: none;
        }

        .orange-button:hover {
            background: #e56800;
            color: white;
        }

        .form-label {
            font-weight: 600;
        }

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ff7900;
            padding: 15px;
            border-radius: 8px;
        }

        footer {
            margin-top: 50px;
            background: #212529;
            color: white;
            padding: 25px 0;
        }
    </style>

</head>

<body>

<!-- Navigation Bar -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">

    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            ChargeSure ⚡
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#reportNavbar"
            aria-controls="reportNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="reportNavbar">

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="btn btn-outline-light btn-sm"
                        href="profile.php"
                    >
                        My Profile
                    </a>
                </li>

                <?php if (($_SESSION["user_role"] ?? "") === "admin"): ?>

                    <li class="nav-item">
                        <a
                            class="btn btn-outline-warning btn-sm"
                            href="admin.php"
                        >
                            Admin Dashboard
                        </a>
                    </li>

                <?php endif; ?>

                <li class="nav-item">
                    <span class="nav-link">
                        Welcome,
                        <?= htmlspecialchars(
                            $_SESSION["user_name"] ?? "User",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </span>
                </li>

                <li class="nav-item">
                    <a
                        class="btn btn-warning btn-sm"
                        href="logout.php"
                    >
                        Logout
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>


<!-- Page Header -->

<header class="page-header">

    <div class="container">

        <h1 class="fw-bold">
            Report Charging Status ⚡
        </h1>

        <p class="mb-0">
            Help other EV drivers by sharing the latest charging station information.
        </p>

    </div>

</header>


<!-- Report Form -->

<main class="container my-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="report-card">

                <h3 class="fw-bold mb-2">
                    Submit a Report
                </h3>

                <p class="text-muted mb-4">
                    Select a charging station and report its current observed status.
                </p>


                <?php if ($message !== ""): ?>

                    <div
                        class="alert alert-<?= $messageType ?> alert-dismissible fade show"
                        role="alert"
                    >

                        <?= htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>

                    </div>

                <?php endif; ?>


                <form method="POST" action="report.php">

                    <!-- Charging Station -->

                    <div class="mb-4">

                        <label for="station_id" class="form-label">
                            Charging Station
                        </label>

                        <select
                            id="station_id"
                            name="station_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select a charging station
                            </option>

                            <?php while ($station = $stations->fetch_assoc()): ?>

                                <option
                                    value="<?= (int) $station["id"] ?>"
                                    <?= $selectedStationId === (int) $station["id"] ? "selected" : "" ?>
                                >

                                    <?= htmlspecialchars(
                                        $station["station_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    —

                                    <?= htmlspecialchars(
                                        $station["location"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <!-- Charging Status -->

                    <div class="mb-4">

                        <label for="status" class="form-label">
                            Current Charging Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select status
                            </option>

                            <option value="Available">
                                Available
                            </option>

                            <option value="Occupied">
                                Occupied
                            </option>

                            <option value="Out of Service">
                                Out of Service
                            </option>

                        </select>

                    </div>


                    <!-- Waiting Time -->

                    <div class="mb-4">

                        <label for="waiting_minutes" class="form-label">
                            Estimated Waiting Time (Minutes)
                        </label>

                        <input
                            type="number"
                            id="waiting_minutes"
                            name="waiting_minutes"
                            class="form-control"
                            min="0"
                            max="1440"
                            placeholder="Example: 15"
                        >

                        <small class="text-muted">
                            Leave blank if the waiting time is unknown.
                        </small>

                    </div>


                    <!-- Comments -->

                    <div class="mb-4">

                        <label for="comments" class="form-label">
                            Additional Comments
                        </label>

                        <textarea
                            id="comments"
                            name="comments"
                            class="form-control"
                            rows="4"
                            maxlength="2000"
                            placeholder="Share useful information about the station..."
                        ></textarea>

                    </div>


                    <!-- Information -->

                    <div class="info-box mb-4">

                        <strong>Important:</strong>

                        Please submit accurate information based on what you observe.
                        Your report helps other drivers make better decisions.

                    </div>


                    <!-- Submit Button -->

                    <button
                        type="submit"
                        class="btn orange-button btn-lg w-100"
                    >
                        Submit Report
                    </button>

                    <a
                        href="index.php"
                        class="btn btn-outline-secondary w-100 mt-3"
                    >
                        Back to Home
                    </a>

                </form>

            </div>

        </div>

    </div>

</main>


<!-- Footer -->

<footer>

    <div class="container text-center">

        <h5 class="fw-bold">
            ChargeSure ⚡
        </h5>

        <p>
            Check Before You Charge.
        </p>

        <p class="mb-0">
            &copy; <?= date("Y") ?> ChargeSure. All rights reserved.
        </p>

    </div>

</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="js/script.js"></script>

</body>

</html>

<?php
$stationQuery->close();
$conn->close();
?>