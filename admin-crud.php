<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Reservation.php';
Auth::requireRole('Admin');

function escape(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $message, string $type = 'success'): never
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    header('Location: admin-crud.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$error = null;
$editing = null;
$reservations = [];
$users = [];
$courts = [];
$keyword = trim((string) ($_GET['search'] ?? ''));
$paymentRoute = defined('PAYMENT_ROUTE') ? PAYMENT_ROUTE : 'payment.php';

try {
    $connection = Database::connect();
    $reservationService = new Reservation($connection);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $id = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'delete') {
            if (!$reservationService->deleteReservation($id)) {
                throw new RuntimeException('Reservation not found.');
            }
            redirectWithMessage('Reservation deleted successfully.');
        }

        $formData = [
            'reservation_date' => trim((string) ($_POST['reservation_date'] ?? '')),
            'start_time' => trim((string) ($_POST['start_time'] ?? '')),
            'end_time' => trim((string) ($_POST['end_time'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? '')),
            'user_id' => filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'court_id' => filter_var($_POST['court_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
        ];

        if ($action === 'add') {
            $formData['status'] = 'Pending';
            $newId = $reservationService->addReservation($formData);
            header('Location: ' . $paymentRoute . '?reservation_id=' . $newId);
            exit;
        }

        if ($action === 'update') {
            $reservationService->updateReservation($id, $formData);
            redirectWithMessage("Reservation #{$id} updated successfully.");
        }

        throw new InvalidArgumentException('Unknown action.');
    }

    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    if ($editId > 0) {
        $editing = $reservationService->getReservation($editId);
        if ($editing === null) {
            $error = 'The selected reservation no longer exists.';
        }
    }

    $userStatement = $connection->prepare(
        "SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name FROM users ORDER BY first_name, last_name"
    );
    $userStatement->execute();
    $users = $userStatement->fetchAll();

    $courtStatement = $connection->prepare(
        "SELECT court_id, court_name, location FROM courts WHERE status = :status ORDER BY court_name"
    );
    $courtStatement->execute(['status' => 'Available']);
    $courts = $courtStatement->fetchAll();

    $reservations = $keyword === ''
        ? $reservationService->getReservations()
        : $reservationService->searchReservations($keyword);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$defaults = [
    'reservation_id' => '',
    'reservation_date' => date('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '10:00',
    'status' => 'Pending',
    'user_id' => '',
    'court_id' => '',
];
$form = array_merge($defaults, $editing ?? []);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laguerta Picklebook | Court Reservations</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="admin-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>COURT RESERVATIONS</small></span></a>
    <nav class="top-actions" aria-label="Primary navigation">
        <a class="admin-back-button" href="admin-dashboard.php">← Back to Booking Results</a>
        <a href="#reservations">Reservations</a>
        <a class="book-button" href="#booking-form">Book a court</a>
    </nav>
</header>

<main class="shell">
    <section class="hero">
        <div>
            <p class="eyebrow">Play more. Wait less.</p>
            <h1>Your next pickleball game starts here.</h1>
            <p>Find an open court, reserve your schedule, and manage every booking from one simple dashboard.</p>
            <a class="hero-button" href="#booking-form">Reserve now <span aria-hidden="true">→</span></a>
        </div>
        <div class="metric">
            <span class="ball-mark" aria-hidden="true">●</span>
            <div><strong><?= count($reservations) ?></strong>
            <span><?= $keyword === '' ? 'active bookings' : 'matching bookings' ?></span></div>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="notice <?= escape($flash['type']) ?>" role="status"><?= escape($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice error" role="alert"><?= escape($error) ?></div>
    <?php endif; ?>

    <section class="workspace">
        <article class="panel form-panel" id="booking-form">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $editing ? 'Edit mode' : 'New booking' ?></p>
                    <h2><?= $editing ? 'Update reservation #' . escape($form['reservation_id']) : 'Add a reservation' ?></h2>
                </div>
                <?php if ($editing): ?><a class="text-link" href="admin-crud.php">Cancel edit</a><?php endif; ?>
            </div>

            <form method="post" class="reservation-form">
                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
                <input type="hidden" name="reservation_id" value="<?= escape($form['reservation_id']) ?>">

                <label>Player
                    <select name="user_id" required>
                        <option value="">Select a player</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= escape($user['user_id']) ?>" <?= (int) $form['user_id'] === (int) $user['user_id'] ? 'selected' : '' ?>>
                                <?= escape($user['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Court
                    <select name="court_id" required>
                        <option value="">Select a court</option>
                        <?php foreach ($courts as $court): ?>
                            <option value="<?= escape($court['court_id']) ?>" <?= (int) $form['court_id'] === (int) $court['court_id'] ? 'selected' : '' ?>>
                                <?= escape($court['court_name'] . ' - ' . $court['location']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Date
                    <input type="date" name="reservation_date" value="<?= escape($form['reservation_date']) ?>" required>
                </label>

                <div class="two-columns">
                    <label>Start time
                        <input type="time" name="start_time" value="<?= escape(substr((string) $form['start_time'], 0, 5)) ?>" required>
                    </label>
                    <label>End time
                        <input type="time" name="end_time" value="<?= escape(substr((string) $form['end_time'], 0, 5)) ?>" required>
                    </label>
                </div>

                <?php if ($editing): ?>
                    <label>Status
                        <select name="status" required>
                            <?php foreach (['Pending', 'Completed', 'Cancelled'] as $status): ?>
                                <option value="<?= $status ?>" <?= $form['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                            <?php endforeach; ?>
                            <?php if ($form['status'] === 'Confirmed'): ?><option value="Confirmed" selected>Confirmed (paid)</option><?php endif; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="status" value="Pending">
                    <p class="payment-note"><strong>Next step: GCash payment</strong>Your booking stays pending until payment is submitted.</p>
                <?php endif; ?>

                <button class="primary-button" type="submit"><?= $editing ? 'Save changes' : 'Create reservation' ?></button>
            </form>
        </article>

        <article class="panel records-panel" id="reservations">
            <div class="panel-heading records-heading">
                <div>
                    <p class="eyebrow">Reservation list</p>
                    <h2>Current bookings</h2>
                </div>
                <form method="get" class="search-form">
                    <input type="search" name="search" value="<?= escape($keyword) ?>" placeholder="Search any field" aria-label="Search reservations">
                    <button type="submit">Search</button>
                    <?php if ($keyword !== ''): ?><a href="admin-crud.php">Clear</a><?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>ID</th><th>Player</th><th>Court</th><th>Schedule</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$reservations): ?>
                        <tr><td class="empty" colspan="6">No matching reservations found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reservations as $item): ?>
                        <tr>
                            <td>#<?= escape($item['reservation_id']) ?></td>
                            <td><strong><?= escape($item['player_name']) ?></strong><small>@<?= escape($item['username']) ?></small></td>
                            <td><strong><?= escape($item['court_name']) ?></strong><small><?= escape($item['location']) ?></small></td>
                            <td><strong><?= escape(date('M j, Y', strtotime($item['reservation_date']))) ?></strong><small><?= escape(date('g:i A', strtotime($item['start_time']))) ?> - <?= escape(date('g:i A', strtotime($item['end_time']))) ?></small></td>
                            <td><span class="status status-<?= escape(strtolower($item['status'])) ?>"><?= escape($item['status']) ?></span></td>
                            <td class="actions">
                                <?php if ($item['status'] === 'Pending'): ?>
                                    <a class="pay-button" href="<?= escape($paymentRoute) ?>?reservation_id=<?= escape($item['reservation_id']) ?>">Pay</a>
                                <?php endif; ?>
                                <a class="edit-button" href="?edit=<?= escape($item['reservation_id']) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this reservation permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reservation_id" value="<?= escape($item['reservation_id']) ?>">
                                    <button class="delete-button" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</main>
</body>
</html>
