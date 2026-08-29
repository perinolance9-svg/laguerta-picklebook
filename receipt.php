<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
Auth::requireRole('Admin');

$paymentId = filter_var($_GET['payment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$statement = Database::connect()->prepare('SELECT receipt_image FROM payments WHERE payment_id = :id LIMIT 1');
$statement->execute(['id' => $paymentId]);
$relative = (string) ($statement->fetchColumn() ?: '');
$base = realpath(__DIR__ . '/uploads/payment-receipts');
$file = $relative !== '' ? realpath(__DIR__ . '/' . $relative) : false;
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404); exit('Receipt not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(415); exit('Unsupported receipt.'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store');
readfile($file);
exit;
