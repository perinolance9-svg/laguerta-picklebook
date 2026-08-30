<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Reservation.php';
Auth::requireRole('Player');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    $userId = Auth::id();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $service = new Reservation(Database::connect());
    $service->confirmPaidReservationsByUser($userId);
    $rows = array_values(array_filter($service->getReservationsByUser($userId), static function(array $reservation) use ($now): bool {
        $end = new DateTimeImmutable($reservation['reservation_date'] . ' ' . $reservation['end_time'], new DateTimeZone('Asia/Manila'));
        return $reservation['status'] !== 'Cancelled' && $end > $now;
    }));
    $bookings = array_map(static function(array $row) use ($userId): array {
        $isOwn = (int)$row['user_id'] === $userId;
        $paymentStatus = (string)($row['payment_status'] ?? 'Not paid');
        return [
            'reservation_id' => (int)$row['reservation_id'],
            'day' => date('d', strtotime($row['reservation_date'])),
            'month' => date('M', strtotime($row['reservation_date'])),
            'court_name' => (string)$row['court_name'],
            'player_name' => (string)$row['player_name'],
            'location' => (string)$row['location'],
            'time_label' => date('g:i A', strtotime($row['start_time'])) . '–' . date('g:i A', strtotime($row['end_time'])),
            'status' => (string)$row['status'],
            'is_own' => $isOwn,
            'payment_status' => $paymentStatus,
            'can_pay' => $isOwn && $row['status'] === 'Pending' && $paymentStatus !== 'For Verification',
        ];
    }, $rows);
    echo json_encode(['bookings'=>$bookings], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error'=>'Booking status is temporarily unavailable.']);
}
