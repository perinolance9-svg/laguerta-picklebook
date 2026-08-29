<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Reservation.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $courts = (new Reservation(Database::connect()))->getCourtAvailability();
    echo json_encode(['generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DATE_ATOM), 'courts' => $courts], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Court availability could not be loaded.']);
}
