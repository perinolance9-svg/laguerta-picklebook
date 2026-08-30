<?php
declare(strict_types=1);

final class Payment
{
    public function __construct(private PDO $connection) {}

    public function getPayableReservation(int $reservationId): ?array
    {
        $sql = "SELECT r.reservation_id, r.user_id, r.reservation_date, r.start_time, r.end_time, r.status,
                       CONCAT(u.first_name, ' ', u.last_name) AS player_name,
                       c.court_name, c.location, p.payment_status, p.checkout_session_id
                FROM reservations r
                INNER JOIN users u ON u.user_id = r.user_id
                INNER JOIN courts c ON c.court_id = r.court_id
                LEFT JOIN payments p ON p.reservation_id = r.reservation_id
                WHERE r.reservation_id = :reservation_id AND r.status = 'Pending'
                  AND (p.payment_id IS NULL OR p.payment_status = 'Pending')";
        $statement = $this->connection->prepare($sql);
        $statement->execute(['reservation_id' => $reservationId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function savePendingCheckout(int $reservationId, int $amountCentavos, array $checkout): void
    {
        $sql = "INSERT INTO payments
                    (amount, payment_method, payment_status, reservation_id, checkout_session_id, reference_number)
                VALUES (:amount, 'GCash', 'Pending', :reservation_id, :checkout_session_id, :reference_number)
                ON DUPLICATE KEY UPDATE amount = VALUES(amount), checkout_session_id = VALUES(checkout_session_id),
                    reference_number = VALUES(reference_number), payment_status = 'Pending'";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'amount' => number_format($amountCentavos / 100, 2, '.', ''),
            'reservation_id' => $reservationId,
            'checkout_session_id' => $checkout['id'],
            'reference_number' => $checkout['reference_number'],
        ]);
    }

    public function getReservationPaymentStatus(int $reservationId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT r.user_id, r.status AS reservation_status, p.payment_status FROM reservations r
             LEFT JOIN payments p ON p.reservation_id = r.reservation_id
             WHERE r.reservation_id = :reservation_id'
        );
        $statement->execute(['reservation_id' => $reservationId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function markCheckoutPaid(string $sessionId, string $gatewayPaymentId, int $paidCentavos, string $eventId): void
    {
        $this->connection->beginTransaction();
        try {
            $lock = $this->connection->prepare(
                'SELECT payment_id, reservation_id, amount, payment_status FROM payments
                 WHERE checkout_session_id = :session_id FOR UPDATE'
            );
            $lock->execute(['session_id' => $sessionId]);
            $payment = $lock->fetch();
            if (!$payment) throw new RuntimeException('Unknown checkout session.');
            if ($payment['payment_status'] === 'Paid') { $this->connection->commit(); return; }
            if ((int) round((float) $payment['amount'] * 100) !== $paidCentavos) {
                throw new RuntimeException('Paid amount does not match the reservation fee.');
            }
            $update = $this->connection->prepare(
                "UPDATE payments SET payment_status = 'Paid', paid_at = CURRENT_TIMESTAMP,
                 gateway_payment_id = :gateway_payment_id, webhook_event_id = :event_id
                 WHERE payment_id = :payment_id"
            );
            $update->execute(['gateway_payment_id' => $gatewayPaymentId, 'event_id' => $eventId, 'payment_id' => $payment['payment_id']]);
            $confirm = $this->connection->prepare(
                "UPDATE reservations SET status = 'Confirmed' WHERE reservation_id = :reservation_id AND status = 'Pending'"
            );
            $confirm->execute(['reservation_id' => $payment['reservation_id']]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function submitPersonalGcashReference(int $reservationId, string $payerName, string $payerNumber, string $reference, int $amountCentavos, string $receiptImage): void
    {
        $payerName = trim($payerName);
        $payerNumber = preg_replace('/\D/', '', $payerNumber) ?? '';
        $reference = strtoupper(trim($reference));
        if ($payerName === '' || mb_strlen($payerName) > 100) {
            throw new InvalidArgumentException('Enter the GCash account name shown on the receipt.');
        }
        if (!preg_match('/^09\d{9}$/', $payerNumber)) {
            throw new InvalidArgumentException('Enter the payer GCash number in 09XXXXXXXXX format.');
        }
        if (!preg_match('/^[A-Z0-9-]{6,50}$/', $reference)) {
            throw new InvalidArgumentException('Enter a valid GCash reference number.');
        }
        if ($receiptImage === '') throw new InvalidArgumentException('Upload the GCash payment screenshot.');

        $sql = "INSERT INTO payments
                    (amount, payment_method, gcash_number, gcash_account_name, reference_number, payment_status, reservation_id, receipt_image)
                VALUES (:amount, 'Personal GCash', :gcash_number, :gcash_account_name, :reference_number, 'For Verification', :reservation_id, :receipt_image)
                ON DUPLICATE KEY UPDATE gcash_number = VALUES(gcash_number), gcash_account_name = VALUES(gcash_account_name),
                    reference_number = VALUES(reference_number), payment_method = 'Personal GCash',
                    payment_status = 'For Verification', receipt_image = VALUES(receipt_image)";
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute([
                'amount' => number_format($amountCentavos / 100, 2, '.', ''),
                'gcash_number' => $payerNumber,
                'gcash_account_name' => $payerName,
                'reference_number' => $reference,
                'reservation_id' => $reservationId,
                'receipt_image' => $receiptImage,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') throw new RuntimeException('That GCash reference number was already submitted.');
            throw new RuntimeException('The payment reference could not be submitted.');
        }
    }

    public function getPersonalGcashSubmissions(): array
    {
        $sql = "SELECT p.payment_id, p.reservation_id, p.amount, p.gcash_number, p.gcash_account_name, p.reference_number, p.receipt_image,
                       p.payment_status, r.reservation_date, r.start_time, r.end_time,
                       CONCAT(u.first_name, ' ', u.last_name) AS player_name, c.court_name
                FROM payments p INNER JOIN reservations r ON r.reservation_id = p.reservation_id
                INNER JOIN users u ON u.user_id = r.user_id INNER JOIN courts c ON c.court_id = r.court_id
                WHERE p.payment_method = 'Personal GCash'
                ORDER BY r.reservation_date DESC, r.start_time DESC, p.payment_id DESC";
        $statement = $this->connection->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function confirmPersonalGcashPayment(int $paymentId): void
    {
        $this->connection->beginTransaction();
        try {
            $lock = $this->connection->prepare(
                "SELECT reservation_id, payment_status FROM payments
                 WHERE payment_id = :payment_id AND payment_method = 'Personal GCash' FOR UPDATE"
            );
            $lock->execute(['payment_id' => $paymentId]);
            $payment = $lock->fetch();
            if (!$payment) throw new RuntimeException('Payment submission not found.');
            if ($payment['payment_status'] !== 'Paid') {
                $update = $this->connection->prepare(
                    "UPDATE payments SET payment_status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE payment_id = :payment_id"
                );
                $update->execute(['payment_id' => $paymentId]);
            }
            $confirm = $this->connection->prepare(
                "UPDATE reservations SET status = 'Confirmed' WHERE reservation_id = :reservation_id AND status = 'Pending'"
            );
            $confirm->execute(['reservation_id' => $payment['reservation_id']]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }
}
