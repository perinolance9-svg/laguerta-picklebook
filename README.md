# Pickle Book - Pickleball Court Reservation System

This project implements Lance Perino's prelim proposal as the PHP Data Objects midterm application. Its main entity is **Reservation**, matching the submitted ERD. The web interface supports add, view, update, delete, and multi-field search operations.

## Requirements

- PHP 8.1 or newer with the `pdo_mysql` extension
- MySQL 8.0 or MariaDB 10.5+
- A local server package such as XAMPP, WAMP, Laragon, or PHP's built-in server

## Setup

1. Create the database by importing `database/pickle_book.sql` in phpMyAdmin, MySQL Workbench, or the MySQL command line.
2. Open `config/database.php` and adjust the host, port, database name, username, and password if your MySQL settings differ. The defaults are for a typical local XAMPP installation.
3. Put this folder inside your web server's document root, such as `C:\xampp\htdocs\pickle-book`.
4. Start Apache and MySQL, then visit `http://localhost/pickle-book/`.

Alternatively, from the project folder, run `php -S localhost:8000`, then open `http://localhost:8000`.

## Project structure

```text
pickle-book/
|-- assets/style.css            Responsive interface styling
|-- classes/Reservation.php     Main entity and all CRUD/search methods
|-- classes/Payment.php         GCash payment recording and confirmation transaction
|-- config/database.php         PDO connection and error configuration
|-- database/pickle_book.sql    Seven-table ERD schema and sample records
|-- index.php                   Forms, controller flow, and record list
|-- payment.php                 GCash payment step after booking
`-- README.md                   Setup and method explanations
```

## Database design

The SQL file implements the seven entities in Perino's ERD:

1. `users` - player and administrator accounts
2. `courts` - court name, location, and availability status
3. `reservations` - the main midterm entity
4. `payments` - one payment record associated with a reservation
5. `notifications` - messages associated with users
6. `open_play` - open-play schedules and capacity
7. `openplay_players` - junction table joining users to open-play sessions

Foreign keys preserve the relationships shown in the ERD. Unique constraints protect usernames, emails, court names, and duplicate open-play registrations. Check constraints reject invalid time ranges, player capacities, and negative payment amounts.

## Required `Reservation` methods

### `addReservation(array $data)`

The Add Reservation form submits a POST request to `index.php`. The controller verifies the CSRF token, collects the six entity fields, and passes them to `Reservation::addReservation()`. The class validates required values, status, and start/end times. It also runs a prepared overlap check so the same court cannot be booked for conflicting times. A prepared `INSERT` statement writes the valid record and returns its new ID.

### `getReservations()`

When no search keyword is supplied, the controller calls `getReservations()`. A prepared `SELECT` joins reservations with users and courts so the list can display the player's name, court, location, date, times, and status. The method returns all rows as an associative array, and the PHP view safely escapes each value before showing it in the table.

### `updateReservation(int $id, array $data)`

Clicking Edit loads one record through `getReservation($id)` and fills the form. Submitting the form sends the edited values and reservation ID by POST. The class repeats validation and conflict checking, excluding the record currently being changed. A prepared `UPDATE` statement modifies only the row whose `reservation_id` matches the bound ID.

### `deleteReservation(int $id)`

Clicking Delete first shows a browser confirmation. The POST request includes the record ID and CSRF token. `deleteReservation($id)` executes a prepared `DELETE` statement. Related payment data is automatically removed by the schema's `ON DELETE CASCADE` rule. The method checks the affected-row count so a missing record is not reported as a successful deletion.

### `searchReservations(string $keyword)`

The search bar sends the keyword through a GET request. `searchReservations()` binds one wildcard parameter to a prepared query that checks the reservation ID, date, start time, end time, status, player name, username, court name, and location. Matching joined records are returned and displayed in the same list. A blank keyword falls back to `getReservations()`.

## PDO, OOP, and error handling

- `Database` owns connection creation and enables exception mode, associative results, UTF-8, and native prepared statements.
- `Reservation` owns every database operation concerning the main entity.
- All values supplied by users are passed to PDO as bound parameters; they are never concatenated into SQL.
- Database details are written only to the server error log. Visitors receive short, safe messages.
- Input validation rejects missing fields, invalid statuses, and impossible time ranges before a write occurs.
- CSRF tokens protect add, update, and delete requests, and output escaping protects the page from stored HTML/script injection.
- The original double-booking problem is addressed by an overlap query, not merely by exact-time duplicate detection.

## Required coding snippets

These examples correspond to the midterm's five coding tasks. The working controller in `index.php` uses the same calls.

```php
$pdo = Database::connect();
$reservations = new Reservation($pdo);

// Add
$newId = $reservations->addReservation([
    'reservation_date' => '2026-09-01',
    'start_time' => '09:00',
    'end_time' => '10:00',
    'status' => 'Confirmed',
    'user_id' => 1,
    'court_id' => 1,
]);

// Fetch all
$allRows = $reservations->getReservations();

// Update
$reservations->updateReservation($newId, [
    'reservation_date' => '2026-09-01',
    'start_time' => '10:00',
    'end_time' => '11:00',
    'status' => 'Confirmed',
    'user_id' => 1,
    'court_id' => 1,
]);

// Search
$matches = $reservations->searchReservations('Court A');

// Delete
$reservations->deleteReservation($newId);
```

## Midterm checklist

- [x] Original system based on Lance Perino's persona and proposal
- [x] Main entity and attributes match the submitted ERD
- [x] PHP class named `Reservation`
- [x] Add, fetch, update, delete, and search methods
- [x] PDO prepared statements for every operation
- [x] Error handling and server-side validation
- [x] Working add/edit/delete forms and search bar
- [x] Seven related tables with keys and constraints
- [x] Step-by-step explanation for all required operations

## Automatic PayMongo GCash confirmation

New reservations are created with `Pending` status and redirect to `payment.php`. The server creates a PayMongo Hosted Checkout Session restricted to GCash and redirects the customer to PayMongo. Returning through `payment-success.php` does not confirm the booking. Only a signature-verified `checkout_session.payment.paid` request to `paymongo-webhook.php` changes the payment to `Paid` and the reservation to `Confirmed` in one database transaction.

For a fresh installation, import the updated `database/pickle_book.sql`. If the earlier GCash migration is already installed, run `database/add_paymongo_checkout.sql` once.

Set the four server environment variables shown in `paymongo-config.example.txt`. `APP_BASE_URL` must be the project's public HTTPS URL; localhost cannot receive PayMongo webhooks. In PayMongo Dashboard, create a test-mode webhook pointing to `https://YOUR-DOMAIN/lantoy2/paymongo-webhook.php`, subscribe only to `checkout_session.payment.paid`, and store its displayed `whsk_...` secret in `PAYMONGO_WEBHOOK_SECRET`.
