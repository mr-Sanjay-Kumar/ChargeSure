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
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";
$message = "";
$messageType = "success";

// Handle user deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_user"])) {

    $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

    if (!$userId || $userId <= 0) {
        $message = "Invalid user selected.";
        $messageType = "danger";
    } elseif ($userId === (int) $_SESSION["user_id"]) {
        $message = "You cannot delete your own administrator account.";
        $messageType = "danger";
    } else {
        // Check whether the selected user exists and is not an administrator
        $check = $conn->prepare(
            "SELECT role FROM users WHERE id = ?"
        );
        $check->bind_param("i", $userId);
        $check->execute();

        $result = $check->get_result();
        $selectedUser = $result->fetch_assoc();
        $check->close();

        if (!$selectedUser) {
            $message = "User not found.";
            $messageType = "danger";
        } elseif ($selectedUser["role"] === "admin") {
            $message = "Administrator accounts cannot be deleted here.";
            $messageType = "danger";
        } else {
            // Keep reports safe: do not delete users who have submitted reports
            $reportCheck = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM reports
                 WHERE user_id = ?"
            );
            $reportCheck->bind_param("i", $userId);
            $reportCheck->execute();

            $reportResult = $reportCheck->get_result();
            $reportData = $reportResult->fetch_assoc();
            $reportCheck->close();

            if ((int) $reportData["total"] > 0) {
                $message = "This user has submitted reports. The account cannot be deleted because those reports must be preserved.";
                $messageType = "warning";
            } else {
                $delete = $conn->prepare(
                    "DELETE FROM users WHERE id = ? AND role <> 'admin'"
                );
                $delete->bind_param("i", $userId);

                if ($delete->execute() && $delete->affected_rows === 1) {
                    $message = "User deleted successfully.";
                    $messageType = "success";
                } else {
                    $message = "Unable to delete this user.";
                    $messageType = "danger";
                }

                $delete->close();
            }
        }
    }
}

// Search users by name or email
$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $searchTerm = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT id, name, email, role, created_at
         FROM users
         WHERE name LIKE ? OR email LIKE ?
         ORDER BY created_at DESC, id DESC"
    );

    $stmt->bind_param("ss", $searchTerm, $searchTerm);
} else {
    $stmt = $conn->prepare(
        "SELECT id, name, email, role, created_at
         FROM users
         ORDER BY created_at DESC, id DESC"
    );
}

$stmt->execute();
$users = $stmt->get_result();

$totalUsers = $users->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Users | ChargeSure</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: #f5f7fb;
        }

        .page-header {
            background: linear-gradient(135deg, #ff8c00, #ff5e00);
            color: white;
            padding: 35px 0;
            border-radius: 0 0 22px 22px;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.06);
        }

        .table th {
            background: #fff3e6;
            white-space: nowrap;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .admin-role {
            background: #e8ddff;
            color: #5a269b;
        }

        .user-role {
            background: #d9f7e8;
            color: #146c43;
        }

        .search-button {
            background: #ff7900;
            color: white;
            border: none;
        }

        .search-button:hover {
            background: #e56800;
            color: white;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            ChargeSure ⚡
        </a>

        <div class="ms-auto d-flex gap-2">
            <a href="admin.php" class="btn btn-outline-light btn-sm">
                Admin Dashboard
            </a>

            <a href="logout.php" class="btn btn-warning btn-sm">
                Logout
            </a>
        </div>
    </div>
</nav>

<header class="page-header">
    <div class="container">
        <h1 class="fw-bold">Manage Registered Users</h1>

        <p class="mb-0">
            Welcome, <?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?>!
            View and manage ChargeSure accounts.
        </p>
    </div>
</header>

<main class="container my-4">

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    <?php endif; ?>

    <section class="content-card">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h3 class="mb-1">User Accounts</h3>
                <p class="text-muted mb-0">
                    Matching accounts: <?= $totalUsers ?>
                </p>
            </div>

            <a href="admin.php" class="btn btn-outline-secondary">
                ← Back to Dashboard
            </a>
        </div>

        <form method="GET" action="manage-users.php" class="row g-2 mb-4">

            <div class="col-md-9">
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Search by name or email..."
                    value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
                >
            </div>

            <div class="col-md-3 d-grid">
                <button type="submit" class="btn search-button">
                    Search Users
                </button>
            </div>

            <?php if ($search !== ""): ?>
                <div class="col-12">
                    <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">
                        Clear Search
                    </a>
                </div>
            <?php endif; ?>

        </form>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered On</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($totalUsers > 0): ?>

                    <?php while ($user = $users->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int) $user["id"] ?></td>

                            <td>
                                <?= htmlspecialchars($user["name"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?php if ($user["role"] === "admin"): ?>

                                    <span class="role-badge admin-role">
                                        Administrator
                                    </span>

                                <?php else: ?>

                                    <span class="role-badge user-role">
                                        Normal User
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $user["created_at"] ?? "Not available",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?php if ($user["role"] === "admin"): ?>

                                    <span class="text-muted">Protected</span>

                                <?php else: ?>

                                    <form
                                        method="POST"
                                        action="manage-users.php<?= $search !== "" ? "?search=" . urlencode($search) : "" ?>"
                                        onsubmit="return confirm('Are you sure you want to delete this user?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_user"
                                            class="btn btn-sm btn-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="text-center py-4">
                            No users found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        

    </section>

</main>

<footer class="text-center py-4 text-muted">
    <p class="mb-0">
        &copy; <?= date("Y") ?> ChargeSure. Check Before You Charge.
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>