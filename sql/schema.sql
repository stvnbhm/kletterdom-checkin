-- Kletterdom Check-in: vollständiges Schema für eine frische Installation.
-- PII liegt im Klartext vor (Hallendienst braucht lesbare Namen). Members
-- werden datensparsam gehalten: nur Lookup-Felder + HMAC aus Nachname +
-- Geburtsdatum für den Abgleich mit Registrierungen.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_number VARCHAR(64) NOT NULL,
    membership_status VARCHAR(32) NOT NULL DEFAULT 'active',
    payment_status VARCHAR(32) NOT NULL DEFAULT 'paid',
    name_birth_hash CHAR(64) NULL,
    last_imported_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY members_member_number_unique (member_number),
    KEY members_name_birth_hash_idx (name_birth_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    birth_date DATE NOT NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    member_type ENUM('member','guest') NOT NULL DEFAULT 'guest',
    member_number VARCHAR(64) NULL,
    waiver_accepted TINYINT(1) NOT NULL DEFAULT 0,
    waiver_version VARCHAR(16) NOT NULL DEFAULT 'v1',
    payment_status VARCHAR(32) NOT NULL DEFAULT 'paid',
    access_status ENUM('green','blue','orange','red') NOT NULL DEFAULT 'red',
    access_reason VARCHAR(500) NULL,
    manual_exception_reason VARCHAR(500) NULL,
    manual_exception_until DATETIME NULL,
    checked_in_at DATETIME NULL,
    qr_token CHAR(36) NOT NULL,
    trial_visits_count INT UNSIGNED NOT NULL DEFAULT 0,
    needs_supervision TINYINT(1) NOT NULL DEFAULT 0,
    needs_parent_consent TINYINT(1) NOT NULL DEFAULT 0,
    parent_consent_received TINYINT(1) NOT NULL DEFAULT 0,
    parent_consent_received_at DATETIME NULL,
    supervision_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    name_birth_hash CHAR(64) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY registrations_qr_token_unique (qr_token),
    KEY registrations_member_number_idx (member_number),
    KEY registrations_name_birth_hash_idx (name_birth_hash),
    KEY registrations_access_status_idx (access_status),
    KEY registrations_member_type_idx (member_type),
    KEY registrations_created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id BIGINT UNSIGNED NOT NULL,
    checked_in_at DATETIME NOT NULL,
    checked_out_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY checkins_registration_id_idx (registration_id),
    KEY checkins_checked_in_at_idx (checked_in_at),
    KEY checkins_open_idx (checked_out_at),
    CONSTRAINT checkins_registration_id_fk
        FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
