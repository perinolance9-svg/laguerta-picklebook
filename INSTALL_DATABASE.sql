SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Player') NOT NULL DEFAULT 'Player',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    google_sub VARCHAR(255) NULL UNIQUE,
    avatar_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courts (
    court_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    court_name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(100) NOT NULL,
    status ENUM('Available', 'Maintenance', 'Closed') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS open_play (
    openplay_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(30) NOT NULL,
    play_date DATE NOT NULL,
    play_time TIME NOT NULL,
    max_players INT UNSIGNED NOT NULL,
    CONSTRAINT chk_open_play_capacity CHECK (max_players > 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    user_id INT UNSIGNED NOT NULL,
    court_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_reservation_time CHECK (end_time > start_time),
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_reservation_court FOREIGN KEY (court_id) REFERENCES courts(court_id),
    INDEX idx_reservation_schedule (court_id, reservation_date, start_time, end_time),
    INDEX idx_reservation_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'GCash',
    gcash_number VARCHAR(11) NULL,
    gcash_account_name VARCHAR(100) NULL,
    reference_number VARCHAR(50) NULL UNIQUE,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    paid_at TIMESTAMP NULL,
    checkout_session_id VARCHAR(100) NULL UNIQUE,
    gateway_payment_id VARCHAR(100) NULL UNIQUE,
    webhook_event_id VARCHAR(100) NULL UNIQUE,
    receipt_image VARCHAR(255) NULL,
    reservation_id INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT chk_payment_amount CHECK (amount >= 0),
    CONSTRAINT fk_payment_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS openplay_players (
    join_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    openplay_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_openplay_player_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_openplay_player_session FOREIGN KEY (openplay_id) REFERENCES open_play(openplay_id) ON DELETE CASCADE,
    CONSTRAINT uq_openplay_player UNIQUE (user_id, openplay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO courts (court_name, location, status) VALUES
('Court A', 'Campus Sports Center', 'Available'),
('Court B', 'Campus Sports Center', 'Available'),
('Court C', 'Community Gym', 'Maintenance')
ON DUPLICATE KEY UPDATE location = VALUES(location);
