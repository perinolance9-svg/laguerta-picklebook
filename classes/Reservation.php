<?php
declare(strict_types=1);

final class Reservation
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function addReservation(array $data): int
    {
        $data = $this->validate($data);
        $startedTransaction = !$this->connection->inTransaction();
        try {
            if ($startedTransaction) $this->connection->beginTransaction();
            $this->lockCourt((int)$data['court_id']);
            $this->assertScheduleAvailable($data);
            $this->assertPlayerBookingLimit($data['user_id']);
            $sql = 'INSERT INTO reservations
                        (reservation_date, start_time, end_time, status, user_id, court_id)
                    VALUES
                        (:reservation_date, :start_time, :end_time, :status, :user_id, :court_id)';
            $statement = $this->connection->prepare($sql);
            $statement->execute($data);
            $id = (int)$this->connection->lastInsertId();
            if ($startedTransaction) $this->connection->commit();
            return $id;
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            error_log($exception->getMessage());
            throw new RuntimeException('The reservation could not be added.');
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function getReservations(): array
    {
        $sql = $this->baseSelect() . ' ORDER BY r.reservation_date DESC, r.start_time DESC';

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('Reservations could not be retrieved.');
        }
    }

    public function getReservationsByUser(int $userId): array
    {
        if ($userId < 1) return [];
        try {
            $statement = $this->connection->prepare(
                $this->baseSelect() . ' WHERE r.user_id = :user_id ORDER BY r.reservation_date DESC, r.start_time DESC'
            );
            $statement->execute(['user_id' => $userId]);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('Player reservations could not be retrieved.');
        }
    }

    public function confirmPaidReservationsByUser(int $userId): void
    {
        if ($userId < 1) return;
        $statement = $this->connection->prepare(
            "UPDATE reservations r
             INNER JOIN payments p ON p.reservation_id = r.reservation_id
             SET r.status = 'Confirmed'
             WHERE r.user_id = :user_id
               AND r.status = 'Pending'
               AND p.payment_status = 'Paid'"
        );
        $statement->execute(['user_id'=>$userId]);
    }

    public function getCourtAvailability(?DateTimeImmutable $moment = null): array
    {
        $moment ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $date = $moment->format('Y-m-d');
        $time = $moment->format('H:i:s');

        $courts = $this->connection->query(
            'SELECT court_id, court_name, location, status FROM courts ORDER BY court_name'
        )->fetchAll();
        $statement = $this->connection->prepare(
            "SELECT reservation_id, reservation_date, start_time, end_time, status
             FROM reservations
             WHERE court_id = :court_id
               AND status IN ('Pending', 'Confirmed')
               AND (reservation_date > :future_date
                    OR (reservation_date = :today AND end_time > :current_time))
             ORDER BY reservation_date, start_time"
        );

        foreach ($courts as &$court) {
            if ($court['status'] !== 'Available') {
                $court['availability'] = strtolower($court['status']);
                $court['message'] = $court['status'];
                $court['remaining_seconds'] = null;
                continue;
            }

            $statement->execute(['court_id' => $court['court_id'], 'future_date' => $date, 'today' => $date, 'current_time' => $time]);
            $bookings = $statement->fetchAll();
            $current = null; $next = null;
            foreach ($bookings as $booking) {
                if ($booking['reservation_date'] === $date && $booking['start_time'] <= $time && $booking['end_time'] > $time) { $current = $booking; break; }
                if ($next === null) $next = $booking;
            }

            if ($current) {
                $end = new DateTimeImmutable($date . ' ' . $current['end_time'], new DateTimeZone('Asia/Manila'));
                $court['availability'] = 'occupied';
                $court['remaining_seconds'] = max(0, $end->getTimestamp() - $moment->getTimestamp());
                $court['message'] = 'Until ' . $end->format('g:i A');
            } elseif ($next) {
                $nextStart = new DateTimeImmutable($next['reservation_date'] . ' ' . $next['start_time'], new DateTimeZone('Asia/Manila'));
                $court['availability'] = 'reserved';
                $court['remaining_seconds'] = max(0, $nextStart->getTimestamp() - $moment->getTimestamp());
                $court['available_seconds'] = null;
                $court['message'] = 'Booking starts ' . ($next['reservation_date'] === $date ? 'today at ' . $nextStart->format('g:i A') : $nextStart->format('M j, g:i A'));
            } else {
                $court['availability'] = 'vacant';
                $court['remaining_seconds'] = null;
                $court['available_seconds'] = null;
                $court['message'] = 'Open for the rest of today';
            }
        }
        unset($court);
        return $courts;
    }

    public function getReservation(int $id): ?array
    {
        try {
            $statement = $this->connection->prepare($this->baseSelect() . ' WHERE r.reservation_id = :id');
            $statement->execute(['id' => $id]);
            $reservation = $statement->fetch();
            return $reservation ?: null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('The reservation could not be retrieved.');
        }
    }

    public function updateReservation(int $id, array $data): bool
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Invalid reservation ID.');
        }

        $data = $this->validate($data);
        $startedTransaction = !$this->connection->inTransaction();
        try {
            if ($startedTransaction) $this->connection->beginTransaction();
            $this->lockCourt((int)$data['court_id']);
            $this->assertScheduleAvailable($data, $id);
            $data['reservation_id'] = $id;
            $sql = 'UPDATE reservations
                    SET reservation_date = :reservation_date,
                        start_time = :start_time,
                        end_time = :end_time,
                        status = :status,
                        user_id = :user_id,
                        court_id = :court_id
                    WHERE reservation_id = :reservation_id';
            $statement = $this->connection->prepare($sql);
            $updated = $statement->execute($data);
            if ($startedTransaction) $this->connection->commit();
            return $updated;
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            error_log($exception->getMessage());
            throw new RuntimeException('The reservation could not be updated.');
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function deleteReservation(int $id): bool
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Invalid reservation ID.');
        }

        try {
            $statement = $this->connection->prepare(
                'DELETE FROM reservations WHERE reservation_id = :reservation_id'
            );
            $statement->execute(['reservation_id' => $id]);
            return $statement->rowCount() === 1;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('The reservation could not be deleted.');
        }
    }

    public function searchReservations(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $this->getReservations();
        }

        $sql = $this->baseSelect() . "
                WHERE CONCAT_WS(' ',
                    CAST(r.reservation_id AS CHAR),
                    DATE_FORMAT(r.reservation_date, '%Y-%m-%d'),
                    DATE_FORMAT(r.reservation_date, '%b %e, %Y'),
                    DATE_FORMAT(r.reservation_date, '%M %e, %Y'),
                    TIME_FORMAT(r.start_time, '%H:%i'),
                    TIME_FORMAT(r.end_time, '%H:%i'),
                    r.status,
                    u.first_name,
                    u.last_name,
                    u.username,
                    c.court_name,
                    c.location
                ) LIKE :keyword
                ORDER BY r.reservation_date DESC, r.start_time DESC";

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute(['keyword' => '%' . $keyword . '%']);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('The reservation search failed.');
        }
    }

    private function validate(array $data): array
    {
        $required = ['reservation_date', 'start_time', 'end_time', 'status', 'user_id', 'court_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new InvalidArgumentException('All reservation fields are required.');
            }
        }

        $allowedStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
        if (!in_array($data['status'], $allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid reservation status.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['reservation_date']);
        if (!$date || $date->format('Y-m-d') !== $data['reservation_date']) {
            throw new InvalidArgumentException('Enter a valid reservation date.');
        }

        foreach (['start_time', 'end_time'] as $timeField) {
            $time = DateTimeImmutable::createFromFormat('!H:i', (string) $data[$timeField]);
            if (!$time || $time->format('H:i') !== $data[$timeField]) {
                throw new InvalidArgumentException('Enter valid start and end times.');
            }
        }

        if ((int) $data['user_id'] < 1 || (int) $data['court_id'] < 1) {
            throw new InvalidArgumentException('Select a valid player and court.');
        }

        if ($data['start_time'] >= $data['end_time']) {
            throw new InvalidArgumentException('End time must be later than start time.');
        }

        [$startHour, $startMinute] = array_map('intval', explode(':', $data['start_time']));
        [$endHour, $endMinute] = array_map('intval', explode(':', $data['end_time']));
        $startTotal = ($startHour * 60) + $startMinute;
        $endTotal = ($endHour * 60) + $endMinute;
        $earlyMorningSchedule = $startTotal < 120 && $endTotal <= 120;
        $daySchedule = $startTotal >= 240 && $endTotal > 240;
        if (!$earlyMorningSchedule && !$daySchedule) {
            throw new InvalidArgumentException('Booking hours are 4:00 AM to 2:00 AM. A booking cannot cross the 2:00 AM–4:00 AM closure or midnight.');
        }

        return [
            'reservation_date' => $data['reservation_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $data['status'],
            'user_id' => (int) $data['user_id'],
            'court_id' => (int) $data['court_id'],
        ];
    }

    private function assertScheduleAvailable(array $data, ?int $ignoreId = null): void
    {
        if ($data['status'] === 'Cancelled') {
            return;
        }

        $sql = "SELECT COUNT(*) FROM reservations
                WHERE court_id = :court_id
                  AND reservation_date = :reservation_date
                  AND status <> 'Cancelled'
                  AND start_time < :end_time
                  AND end_time > :start_time";
        $parameters = [
            'court_id' => $data['court_id'],
            'reservation_date' => $data['reservation_date'],
            'end_time' => $data['end_time'],
            'start_time' => $data['start_time'],
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND reservation_id <> :ignore_id';
            $parameters['ignore_id'] = $ignoreId;
        }

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
            if ((int) $statement->fetchColumn() > 0) {
                throw new DomainException('That court already has an overlapping reservation.');
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('Court availability could not be checked.');
        }
    }

    private function lockCourt(int $courtId): void
    {
        $statement = $this->connection->prepare('SELECT court_id FROM courts WHERE court_id = :court_id FOR UPDATE');
        $statement->execute(['court_id'=>$courtId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('Select a valid court.');
    }

    private function assertPlayerBookingLimit(int $userId): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE user_id = :user_id
               AND status IN ('Pending', 'Confirmed')
               AND (reservation_date > :future_date
                    OR (reservation_date = :today AND end_time > :current_time))"
        );
        $statement->execute([
            'user_id' => $userId,
            'future_date' => $now->format('Y-m-d'),
            'today' => $now->format('Y-m-d'),
            'current_time' => $now->format('H:i:s'),
        ]);
        if ((int) $statement->fetchColumn() >= 5) {
            throw new DomainException('This player already has the maximum of 5 active bookings.');
        }
    }

    private function baseSelect(): string
    {
        return "SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time,
                       r.status, r.user_id, r.court_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS player_name,
                       u.username, c.court_name, c.location, p.payment_status
                FROM reservations r
                INNER JOIN users u ON u.user_id = r.user_id
                INNER JOIN courts c ON c.court_id = r.court_id
                LEFT JOIN payments p ON p.reservation_id = r.reservation_id";
    }
}
