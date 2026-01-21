-- RSVP Extension Tables for Baïkal (SQLite)
-- These tables support web-based RSVP functionality for CalDAV scheduling

-- Table for storing RSVP tokens
CREATE TABLE rsvp_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL,
    event_uid TEXT NOT NULL,
    recipient_email TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);

CREATE UNIQUE INDEX unique_token ON rsvp_tokens (token);
CREATE INDEX idx_event_uid ON rsvp_tokens (event_uid);
CREATE INDEX idx_recipient ON rsvp_tokens (recipient_email);
CREATE INDEX idx_expires ON rsvp_tokens (expires_at);

-- Table for storing RSVP responses
CREATE TABLE rsvp_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL,
    response TEXT NOT NULL,
    responded_at INTEGER NOT NULL
);

CREATE UNIQUE INDEX unique_token_response ON rsvp_responses (token);
CREATE INDEX idx_responded_at ON rsvp_responses (responded_at);
