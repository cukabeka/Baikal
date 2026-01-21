-- RSVP Extension Tables for Baïkal
-- These tables support web-based RSVP functionality for CalDAV scheduling

-- Table for storing RSVP tokens
CREATE TABLE rsvp_tokens (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL,
    event_uid VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    created_at INT(11) UNSIGNED NOT NULL,
    expires_at INT(11) UNSIGNED NOT NULL,
    UNIQUE KEY unique_token (token),
    INDEX idx_event_uid (event_uid),
    INDEX idx_recipient (recipient_email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing RSVP responses
CREATE TABLE rsvp_responses (
    id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL,
    response VARCHAR(20) NOT NULL,
    responded_at INT(11) UNSIGNED NOT NULL,
    UNIQUE KEY unique_token_response (token),
    INDEX idx_responded_at (responded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
