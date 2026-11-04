<?php
session_start();

require_once "config/database.php";

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get report ID
$report_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$report_id || $report_id <= 0) {
    header("Location: profile.php");
    exit();
}

$error_message = "";
$success_message = "";

// Fetch report only if it belongs to the logged-in user
$stmt = $conn->prepare(
    "SELECT
        r.id,
        r.station_id,
        r.status,
        r.waiting_minutes,
        r.comments,
        r.reported_at,
        s.station_name,
        s.location
     FROM reports r
     INNER JOIN stations s ON r.station_id = s.id
     WHERE r.id = ? AND r.user_id = ?"
);

$stmt->bind_param("ii", $report_id, $user_id);
$stmt->execute();

$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prevent editing another user's report
if (!$report) {
    http_response_code(404);
    exit("Report not found or you are not authorized to edit it.");
}


// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $status = trim($_POST['status'] ?? "");
    $waiting_minutes_input = trim(
        $_POST['waiting_minutes'] ?? ""
    );
    $comments = trim($_POST['comments'] ?? "");

    $allowed_statuses = [
        "Available",
        "Occupied",
        "Out of Service"
    ];

    // Validate status
    if (!in_array($status, $allowed_statuses, true)) {

        $error_message = "Please select a valid charger status.";

    } elseif (
        $waiting_minutes_input === ""
        || !ctype_digit($waiting_minutes_input)
    ) {

        $error_message = "Waiting time must be a non-negative whole number.";

    } elseif ((float)$waiting_minutes_input > 1000000) {

        $error_message = "Please enter a reasonable waiting time.";

    } elseif (strlen($comments) > 1000) {

        $error_message = "Comments cannot exceed 1000 characters.";

    } else {

        $waiting_minutes = (int)$waiting_minutes_input;

        /*
         * Update only the logged-in user's report.
         * reported_at is updated so this report becomes
         * the latest community report on the homepage.
         */
        $update_stmt = $conn->prepare(
            "UPDATE reports
             SET status = ?,
                 waiting_minutes = ?,
                 comments = ?,
                 reported_at = NOW()
             WHERE id = ? AND user_id = ?"
        );

        $update_stmt->bind_param(
            "sisii",
            $status,
            $waiting_minutes,
            $comments,
            $report_id,
            $user_id
        );

        if ($update_stmt->execute()) {

            if ($update_stmt->affected_rows >= 0) {

                $update_stmt->close();

                header("Location: profile.php?updated=1");
                exit();

            } else {

                $error_message = "Unable to update the report.";
                $update_stmt->close();
            }

        } else {

            $error_message = "Something went wrong. Please try again.";
            $update_stmt->close();
        }
    }

    // Preserve submitted values when validation fails
    $report['status'] = $status;
    $report['waiting_minutes'] = $waiting_minutes_input;
    $report['comments'] = $comments;
}


// HTML escaping function
function escapeHTML($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Report | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f7fb;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #087f5b;
        }

        .navbar-brand,
        .navbar .nav-link {
            color: white !important;
        }

        .edit-card {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            background: linear-gradient(
                135deg,
                #0756b5,
                #3288e8
            );
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>

</head>

<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg">

    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            ChargeSure
        </a>

        <div class="navbar-nav ms-auto">

            <a class="nav-link" href="index.php">
                Home
            </a>

            <a class="nav-link" href="profile.php">
                My Profile
            </a>

            <a class="nav-link" href="logout.php">
                Logout
            </a>

        </div>

    </div>

</nav>


<div class="container">

    <div class="edit-card">

        <!-- Header -->
        <div class="page-header">

            <h2>Edit Report</h2>

            <p class="mb-0">
                Update the latest information about this charging station.
            </p>

        </div>


        <!-- Station Details -->
        <div class="alert alert-info">

            <strong>Station:</strong>
            <?= escapeHTML($report['station_name']); ?>

            <br>

            <strong>Location:</strong>
            <?= escapeHTML($report['location']); ?>

        </div>


        <!-- Error Message -->
        <?php if ($error_message !== ""): ?>

            <div class="alert alert-danger">
                <?= escapeHTML($error_message); ?>
            </div>

        <?php endif; ?>


        <!-- Edit Form -->
        <form method="POST" action="edit-report.php?id=<?= (int)$report_id; ?>">

            <!-- Charger Status -->
            <div class="mb-3">

                <label for="status" class="form-label">
                    Charger Status
                </label>

                <select
                    name="status"
                    id="status"
                    class="form-select"
                    required
                >

                    <option
                        value="Available"
                        <?= $report['status'] === 'Available' ? 'selected' : ''; ?>
                    >
                        Available
                    </option>

                    <option
                        value="Occupied"
                        <?= $report['status'] === 'Occupied' ? 'selected' : ''; ?>
                    >
                        Occupied
                    </option>

                    <option
                        value="Out of Service"
                        <?= $report['status'] === 'Out of Service' ? 'selected' : ''; ?>
                    >
                        Out of Service
                    </option>

                </select>

            </div>


            <!-- Waiting Time -->
            <div class="mb-3">

                <label for="waiting_minutes" class="form-label">
                    Waiting Time (Minutes)
                </label>

                <input
                    type="number"
                    name="waiting_minutes"
                    id="waiting_minutes"
                    class="form-control"
                    min="0"
                    max="1000000"
                    step="1"
                    value="<?= escapeHTML($report['waiting_minutes']); ?>"
                    required
                >

            </div>


            <!-- Comments -->
            <div class="mb-4">

                <label for="comments" class="form-label">
                    Comments
                </label>

                <textarea
                    name="comments"
                    id="comments"
                    class="form-control"
                    rows="4"
                    maxlength="1000"
                    placeholder="Enter updated information about the charger..."
                ><?= escapeHTML($report['comments']); ?></textarea>

                <small class="text-muted">
                    Maximum 1000 characters.
                </small>

            </div>


            <!-- Buttons -->
            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Changes
            </button>

            <a
                href="profile.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>


<footer>
    &copy; <?= date('Y'); ?> ChargeSure.
    Check Before You Charge.
</footer>

</body>
</html>