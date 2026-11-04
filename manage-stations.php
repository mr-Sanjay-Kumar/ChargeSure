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

$message = "";
$message_type = "success";

// Handle adding a station
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_station"])) {

    $station_name = trim($_POST["station_name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $charger_type = trim($_POST["charger_type"] ?? "");

    $total_chargers = filter_var(
        $_POST["total_chargers"] ?? null,
        FILTER_VALIDATE_INT
    );

    $allowed_types = ["AC Charger", "DC Fast Charger"];

    if (
        $station_name === "" ||
        strlen($station_name) > 150 ||
        $location === "" ||
        strlen($location) > 200 ||
        !in_array($charger_type, $allowed_types, true) ||
        $total_chargers === false ||
        $total_chargers === null ||
        $total_chargers < 1 ||
        $total_chargers > 10000
    ) {
        $message = "Please enter valid station details.";
        $message_type = "danger";
    } else {

        $stmt = $conn->prepare(
            "INSERT INTO stations
            (station_name, location, charger_type, total_chargers)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssi",
            $station_name,
            $location,
            $charger_type,
            $total_chargers
        );

        if ($stmt->execute()) {
            $stmt->close();

            header("Location: manage-stations.php?message=added");
            exit();
        } else {
            $message = "Unable to add the station.";
            $message_type = "danger";
        }

        $stmt->close();
    }
}

// Handle deleting a station
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_station"])) {

    $station_id = filter_var(
        $_POST["station_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($station_id === false || $station_id === null || $station_id < 1) {

        $message = "Invalid station selected.";
        $message_type = "danger";

    } else {

        // Check whether reports exist for this station
        $check = $conn->prepare(
            "SELECT id FROM reports WHERE station_id = ? LIMIT 1"
        );

        $check->bind_param("i", $station_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "This station has user reports and cannot be deleted.";
            $message_type = "warning";

        } else {

            $delete = $conn->prepare(
                "DELETE FROM stations WHERE id = ?"
            );

            $delete->bind_param("i", $station_id);

            if ($delete->execute() && $delete->affected_rows > 0) {

                $delete->close();
                $check->close();

                header("Location: manage-stations.php?message=deleted");
                exit();

            } else {

                $message = "Station not found or could not be deleted.";
                $message_type = "danger";
            }

            $delete->close();
        }

        $check->close();
    }
}

// Display success messages
if (isset($_GET["message"])) {

    if ($_GET["message"] === "added") {
        $message = "Charging station added successfully!";
    } elseif ($_GET["message"] === "deleted") {
        $message = "Charging station deleted successfully!";
    } elseif ($_GET["message"] === "updated") {
        $message = "Charging station updated successfully!";
    }

    if ($message !== "") {
        $message_type = "success";
    }
}

// Fetch all charging stations
$result = $conn->query(
    "SELECT id, station_name, location, charger_type,
            total_chargers, created_at
     FROM stations
     ORDER BY id DESC"
);

if (!$result) {
    die("Unable to load charging stations.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Stations | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">

<!-- Navigation Bar -->
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

    <h2 class="fw-bold mb-2">Manage Charging Stations</h2>

    <p class="text-muted mb-4">
        Add, view, edit and delete charging station details.
    </p>

    <!-- Status Message -->
    <?php if ($message !== ""): ?>

        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <!-- Add Station Form -->
    <div class="card shadow-sm mb-5">

        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New Charging Station</h5>
        </div>

        <div class="card-body">

            <form method="POST" action="manage-stations.php">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label for="station_name" class="form-label">
                            Station Name
                        </label>

                        <input
                            type="text"
                            id="station_name"
                            name="station_name"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="location" class="form-label">
                            Location
                        </label>

                        <input
                            type="text"
                            id="location"
                            name="location"
                            class="form-control"
                            maxlength="200"
                            required
                        >

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="charger_type" class="form-label">
                            Charger Type
                        </label>

                        <select
                            id="charger_type"
                            name="charger_type"
                            class="form-select"
                            required
                        >

                            <option value="">Select Charger Type</option>

                            <option value="AC Charger">
                                AC Charger
                            </option>

                            <option value="DC Fast Charger">
                                DC Fast Charger
                            </option>

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label for="total_chargers" class="form-label">
                            Total Chargers
                        </label>

                        <input
                            type="number"
                            id="total_chargers"
                            name="total_chargers"
                            class="form-control"
                            min="1"
                            max="10000"
                            required
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    name="add_station"
                    class="btn btn-primary"
                >
                    + Add Station
                </button>

            </form>

        </div>
    </div>

    <!-- Stations Table -->
    <div class="card shadow-sm">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Registered Charging Stations</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>
                            <th>ID</th>
                            <th>Station Name</th>
                            <th>Location</th>
                            <th>Charger Type</th>
                            <th>Total Chargers</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php while ($station = $result->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <?php echo (int) $station["id"]; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($station["station_name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($station["location"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($station["charger_type"]); ?>
                                    </td>

                                    <td>
                                        <?php echo (int) $station["total_chargers"]; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($station["created_at"] ?? ""); ?>
                                    </td>

                                    <td>

                                        <div class="d-flex gap-2">

                                            <!-- Edit Button -->
                                            <a
                                                href="edit-station.php?id=<?php echo (int) $station["id"]; ?>"
                                                class="btn btn-warning btn-sm"
                                            >
                                                Edit
                                            </a>

                                            <!-- Delete Button -->
                                            <form
                                                method="POST"
                                                action="manage-stations.php"
                                                onsubmit="return confirm('Are you sure you want to delete this station?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="station_id"
                                                    value="<?php echo (int) $station["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_station"
                                                    class="btn btn-danger btn-sm"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="text-center">
                                    No charging stations found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-4">

        <a href="admin.php" class="btn btn-secondary">
            Back to Admin Dashboard
        </a>

    </div>

</div>

<script src="js/script.js"></script>

</body>
</html>