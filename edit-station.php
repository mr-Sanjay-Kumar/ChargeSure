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

$error = "";

// Get station ID from the URL
$station_id = filter_var(
    $_GET["id"] ?? $_POST["station_id"] ?? null,
    FILTER_VALIDATE_INT
);

if ($station_id === false || $station_id === null || $station_id < 1) {
    header("Location: manage-stations.php");
    exit();
}

// Fetch existing station details
$stmt = $conn->prepare(
    "SELECT id, station_name, location, charger_type, total_chargers
     FROM stations
     WHERE id = ?"
);

$stmt->bind_param("i", $station_id);
$stmt->execute();

$result = $stmt->get_result();
$station = $result->fetch_assoc();

$stmt->close();

if (!$station) {
    header("Location: manage-stations.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_station"])) {

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
        $error = "Please enter valid station details.";
    } else {

        $update = $conn->prepare(
            "UPDATE stations
             SET station_name = ?,
                 location = ?,
                 charger_type = ?,
                 total_chargers = ?
             WHERE id = ?"
        );

        $update->bind_param(
            "sssii",
            $station_name,
            $location,
            $charger_type,
            $total_chargers,
            $station_id
        );

        if ($update->execute()) {
            $update->close();

            header("Location: manage-stations.php?message=updated");
            exit();
        } else {
            $error = "Unable to update the station. Please try again.";
            $update->close();
        }
    }

    // Keep the entered values visible if validation fails
    $station["station_name"] = $station_name;
    $station["location"] = $location;
    $station["charger_type"] = $charger_type;
    $station["total_chargers"] = $_POST["total_chargers"] ?? "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Charging Station | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">

        <a class="navbar-brand fw-bold" href="admin.php">
            ChargeSure Admin
        </a>

        <div>
            <a href="manage-stations.php" class="btn btn-outline-light btn-sm">
                Manage Stations
            </a>

            <a href="logout.php" class="btn btn-danger btn-sm">
                Logout
            </a>
        </div>

    </div>
</nav>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Charging Station</h4>
                </div>

                <div class="card-body p-4">

                    <p class="text-muted">
                        Update the station details below.
                    </p>

                    <?php if ($error !== ""): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit-station.php?id=<?php echo (int) $station_id; ?>">

                        <input
                            type="hidden"
                            name="station_id"
                            value="<?php echo (int) $station_id; ?>"
                        >

                        <div class="mb-3">
                            <label for="station_name" class="form-label">
                                Station Name
                            </label>

                            <input
                                type="text"
                                id="station_name"
                                name="station_name"
                                class="form-control"
                                maxlength="150"
                                value="<?php echo htmlspecialchars((string) $station["station_name"]); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">
                                Location
                            </label>

                            <input
                                type="text"
                                id="location"
                                name="location"
                                class="form-control"
                                maxlength="200"
                                value="<?php echo htmlspecialchars((string) $station["location"]); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="charger_type" class="form-label">
                                Charger Type
                            </label>

                            <select
                                id="charger_type"
                                name="charger_type"
                                class="form-select"
                                required
                            >
                                <option
                                    value="AC Charger"
                                    <?php echo $station["charger_type"] === "AC Charger" ? "selected" : ""; ?>
                                >
                                    AC Charger
                                </option>

                                <option
                                    value="DC Fast Charger"
                                    <?php echo $station["charger_type"] === "DC Fast Charger" ? "selected" : ""; ?>
                                >
                                    DC Fast Charger
                                </option>
                            </select>
                        </div>

                        <div class="mb-4">
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
                                value="<?php echo htmlspecialchars((string) $station["total_chargers"]); ?>"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            name="update_station"
                            class="btn btn-primary"
                        >
                            Save Changes
                        </button>

                        <a
                            href="manage-stations.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                    </form>

                </div>
            </div>

        </div>
    </div>

</div>

<script src="js/script.js"></script>

</body>
</html>