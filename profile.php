<?php
session_start();

require_once "config/database.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$success_message = "";
$error_message = "";

// Fetch user details
$stmt = $conn->prepare(
    "SELECT id, name, email, role, password, created_at
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Change password
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['change_password'])) {

    $current_password = $_POST['current_password'] ?? "";
    $new_password = $_POST['new_password'] ?? "";
    $confirm_password = $_POST['confirm_password'] ?? "";

    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Current password is incorrect.";

    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must contain at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";

    } else {
        $hashed_password = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        $update_password = $conn->prepare(
            "UPDATE users SET password = ? WHERE id = ?"
        );

        $update_password->bind_param(
            "si",
            $hashed_password,
            $user_id
        );

        if ($update_password->execute()) {
            $success_message = "Password changed successfully.";
            $user['password'] = $hashed_password;
        } else {
            $error_message = "Unable to change password. Please try again.";
        }

        $update_password->close();
    }
}

// Fetch only the logged-in user's reports
$reports_stmt = $conn->prepare(
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
     WHERE r.user_id = ?
     ORDER BY r.reported_at DESC, r.id DESC"
);

$reports_stmt->bind_param("i", $user_id);
$reports_stmt->execute();

$reports = $reports_stmt->get_result();

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

    <title>My Profile | ChargeSure</title>

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

        .profile-header {
            background: linear-gradient(
                135deg,
                #0756b5,
                #3288e8
            );
            color: white;
            padding: 35px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .table th {
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-available {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-occupied {
            background: #fff3cd;
            color: #664d03;
        }

        .status-out {
            background: #f8d7da;
            color: #842029;
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

        <button
            class="navbar-toggler bg-light"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMenu"
            aria-controls="navbarMenu"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">

            <div class="navbar-nav ms-auto">

                <a class="nav-link" href="index.php">
                    Home
                </a>

                <?php if ($user['role'] === 'admin'): ?>
                    <a class="nav-link" href="admin.php">
                        Admin Dashboard
                    </a>
                <?php endif; ?>

                <a class="nav-link active" href="profile.php">
                    My Profile
                </a>

                <a class="nav-link" href="logout.php">
                    Logout
                </a>

            </div>

        </div>
    </div>
</nav>


<div class="container py-4">

    <!-- Profile Header -->
    <div class="profile-header">

        <h2>
            Welcome, <?= escapeHTML($user['name']); ?>!
        </h2>

        <p class="mb-0">
            Manage your account and submitted charging station reports.
        </p>

    </div>


    <!-- Messages -->
    <?php if ($success_message !== ""): ?>
        <div class="alert alert-success">
            <?= escapeHTML($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message !== ""): ?>
        <div class="alert alert-danger">
            <?= escapeHTML($error_message); ?>
        </div>
    <?php endif; ?>


    <!-- Account Information -->
    <div class="content-card">

        <h4 class="section-title">
            Account Information
        </h4>

        <div class="row">

            <div class="col-md-6 mb-3">
                <strong>Name:</strong><br>
                <?= escapeHTML($user['name']); ?>
            </div>

            <div class="col-md-6 mb-3">
                <strong>Email:</strong><br>
                <?= escapeHTML($user['email']); ?>
            </div>

            <div class="col-md-6 mb-3">
                <strong>Role:</strong><br>
                <?= escapeHTML(ucfirst($user['role'])); ?>
            </div>

            <div class="col-md-6 mb-3">
                <strong>Joined On:</strong><br>
                <?= escapeHTML($user['created_at']); ?>
            </div>

        </div>

    </div>


    <!-- Change Password -->
    <div class="content-card">

        <h4 class="section-title">
            Change Password
        </h4>

        <form method="POST" action="profile.php">

            <div class="mb-3">

                <label for="current_password" class="form-label">
                    Current Password
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="current_password"
                    name="current_password"
                    required
                >

            </div>

            <div class="mb-3">

                <label for="new_password" class="form-label">
                    New Password
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    required
                >

            </div>

            <div class="mb-3">

                <label for="confirm_password" class="form-label">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required
                >

            </div>

            <button
                type="submit"
                name="change_password"
                value="1"
                class="btn btn-primary"
            >
                Change Password
            </button>

        </form>

    </div>


    <!-- Submitted Reports -->
    <div class="content-card">

        <h4 class="section-title">
            My Submitted Reports
        </h4>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light">

                    <tr>
                        <th>Station</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Waiting Time</th>
                        <th>Comments</th>
                        <th>Reported At</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($reports->num_rows > 0): ?>

                    <?php while ($report = $reports->fetch_assoc()): ?>

                        <?php
                        $status_class = 'status-available';

                        if ($report['status'] === 'Occupied') {
                            $status_class = 'status-occupied';
                        } elseif ($report['status'] === 'Out of Service') {
                            $status_class = 'status-out';
                        }
                        ?>

                        <tr>

                            <td>
                                <?= escapeHTML($report['station_name']); ?>
                            </td>

                            <td>
                                <?= escapeHTML($report['location']); ?>
                            </td>

                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= escapeHTML($report['status']); ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeHTML($report['waiting_minutes']); ?>
                                minutes
                            </td>

                            <td>
                                <?= escapeHTML($report['comments']); ?>
                            </td>

                            <td>
                                <?= escapeHTML($report['reported_at']); ?>
                            </td>

                            <td>
                                <a
                                    href="edit-report.php?id=<?= (int)$report['id']; ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    Edit Report
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            You have not submitted any reports yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <a href="index.php" class="btn btn-success">
            View Charging Stations
        </a>

    </div>

</div>


<footer>
    &copy; <?= date('Y'); ?> ChargeSure.
    Check Before You Charge.
</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>

<?php
$reports_stmt->close();
?>