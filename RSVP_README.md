# RSVP Extension for Baïkal

## Overview

This extension adds web-based RSVP functionality to Baïkal's CalDAV scheduling capabilities. It provides:

- **Web-based RSVP pages**: Recipients can respond to calendar invitations via a web browser
- **Email invitations with RSVP links**: Beautiful, customizable email templates (Google Calendar-style by default)
- **Yes/No/Maybe responses**: Standard CalDAV PARTSTAT responses (ACCEPTED, DECLINED, TENTATIVE)
- **Token-based security**: Secure, expiring links for RSVP responses
- **Organizer notifications**: Automatic email notifications when attendees respond
- **Template customization**: Easy-to-customize Twig templates for emails and web pages

## Features

### Email Invitations

When an organizer sends a calendar invitation, attendees receive:
- A multipart email (HTML + plain text + iCalendar attachment)
- Beautiful Google Calendar-style HTML formatting
- Direct action buttons (Yes, No, Maybe)
- Event details (date, time, location, description)
- Compatible with all email clients

### RSVP Web Interface

Recipients can respond to invitations by:
- Clicking direct action links in the email (instant one-click response)
- Visiting the full RSVP page to review event details before responding
- Viewing event information even after responding

### Calendar Integration

- Full CalDAV scheduling support via SabreDAV
- Compatible with:
  - macOS Calendar
  - iOS Calendar
  - Android (via DAVx5)
  - Google Calendar (via CalDAV sync)
  - Thunderbird
  - Evolution
  - Any CalDAV-compatible client

## Installation

### 1. Prerequisites

- Baïkal 0.7.0 or later
- PHP 8.2 or later
- Database: MySQL, PostgreSQL, or SQLite
- Composer dependencies already installed

### 2. Create Database Tables

Run the installation script from the Baïkal root directory:

```bash
php install-rsvp-tables.php
```

This creates two tables:
- `rsvp_tokens`: Stores invitation tokens and metadata
- `rsvp_responses`: Tracks attendee responses

### 3. Configure Baïkal

Edit `config/baikal.yaml`:

```yaml
system:
  rsvp_enabled: true
  invite_from: 'noreply@yourdomain.com'  # Email address for sending invitations
```

### 4. Server Configuration

Ensure your web server can handle the RSVP endpoint:

**Apache (.htaccess is already configured)**
No additional configuration needed.

**Nginx**
Add to your Baïkal location block:

```nginx
location /rsvp.php {
    try_files $uri =404;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## Usage

### For Organizers

1. Create an event in your CalDAV calendar application
2. Add attendees by email address
3. Save/send the event
4. Baïkal automatically sends RSVP-enabled invitations

### For Attendees

1. Receive email invitation
2. Click Yes/No/Maybe button for instant response, or
3. Click "View full invitation details" to see event details first
4. Your response is automatically sent to the organizer

## Template Customization

Templates are located in `Core/Resources/templates/rsvp/`:

### Email Templates

- `email_invitation.html.twig`: HTML email template
- `email_invitation.txt.twig`: Plain text email template

### Web Interface

- `rsvp_page.html.twig`: RSVP response page

### Customization Example

Edit `email_invitation.html.twig` to customize colors:

```html
<style>
    .header {
        background-color: #your-color;  /* Change header color */
    }
    .rsvp-yes {
        background-color: #your-button-color;  /* Customize buttons */
    }
</style>
```

You can also:
- Add your logo/branding
- Change fonts and colors
- Modify layout and structure
- Add custom fields

## Configuration Options

### baikal.yaml Settings

```yaml
system:
  # Enable/disable RSVP functionality
  rsvp_enabled: true
  
  # Sender email address for invitations
  invite_from: 'calendar@yourdomain.com'
  
  # Base URI (usually auto-detected)
  base_uri: 'https://yourdomain.com/baikal'
```

### Token Expiration

Default: 90 days

To change, edit `RSVPIMipPlugin.php`:

```php
$expiresAt = $createdAt + (90 * 24 * 60 * 60); // Change 90 to desired days
```

## Technical Details

### Architecture

```
┌─────────────┐
│   Client    │ Creates event with attendees
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│  SabreDAV       │ Processes scheduling request
│  Schedule       │
│  Plugin         │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│  RSVPIMipPlugin │ Generates token, renders email
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│   Email sent    │ HTML + Plain text + iCalendar
│   with RSVP     │ Contains RSVP links
└─────────────────┘
       │
       ▼
┌─────────────────┐
│  Attendee       │ Clicks RSVP link
│  clicks link    │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│   rsvp.php      │ Validates token, shows page
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│  Response       │ Updates calendar, notifies organizer
│  processed      │
└─────────────────┘
```

### Database Schema

**rsvp_tokens**
- `id`: Primary key
- `token`: Unique 64-character token
- `event_uid`: Event UID from calendar
- `recipient_email`: Attendee email address
- `created_at`: Token creation timestamp
- `expires_at`: Token expiration timestamp

**rsvp_responses**
- `id`: Primary key
- `token`: Reference to rsvp_tokens
- `response`: ACCEPTED, DECLINED, or TENTATIVE
- `responded_at`: Response timestamp

### Security

- Tokens are cryptographically random (32 bytes, hex-encoded)
- Tokens expire after configurable period (default: 90 days)
- One token per event/recipient combination
- No authentication required for RSVP (link acts as credential)
- Responses tracked to prevent duplicate notifications

## Compatibility

### Tested CalDAV Clients

- ✓ macOS Calendar
- ✓ iOS Calendar
- ✓ Android (DAVx5)
- ✓ Thunderbird + Lightning
- ✓ Evolution
- ✓ CalDAV-Sync

### Email Clients

The HTML emails are tested with:
- Gmail (web, mobile)
- Outlook (web, desktop)
- Apple Mail
- Thunderbird
- Mobile clients (iOS Mail, Android Gmail)

## Troubleshooting

### Emails not sending

1. Check `invite_from` is set in `config/baikal.yaml`
2. Verify PHP `mail()` function works on your server
3. Check error logs: `tail -f /var/log/php-error.log`

### RSVP links not working

1. Verify database tables exist: `SELECT * FROM rsvp_tokens LIMIT 1`
2. Check `base_uri` in configuration
3. Ensure `rsvp.php` is accessible via web server

### Responses not updating calendar

1. Check database permissions
2. Verify event still exists in calendar
3. Review error logs for exceptions

### Templates not loading

1. Verify template directory exists: `Core/Resources/templates/rsvp/`
2. Check file permissions (should be readable by web server)
3. Ensure Twig is installed: `composer show twig/twig`

## Performance Considerations

### Database Cleanup

Old tokens should be cleaned up periodically. Add to cron:

```bash
# Clean up expired RSVP tokens daily at 2 AM
0 2 * * * mysql -u baikal -p baikal -e "DELETE FROM rsvp_tokens WHERE expires_at < UNIX_TIMESTAMP()"
```

For SQLite:
```bash
0 2 * * * sqlite3 /path/to/db.sqlite "DELETE FROM rsvp_tokens WHERE expires_at < strftime('%s','now')"
```

### Scaling

For high-volume installations:
- Use MySQL or PostgreSQL instead of SQLite
- Add database indexes (already included in schema)
- Consider separate email queue service for large batches

## Development

### Adding Custom Fields

To add custom fields to invitations:

1. Edit `RSVPIMipPlugin.php` → `extractEventData()`
2. Update templates to display new fields
3. Test with sample event

### Custom Email Service

To use a service like SendGrid instead of PHP `mail()`:

Override `sendMultipartEmail()` in `RSVPIMipPlugin.php`:

```php
protected function sendMultipartEmail($to, $subject, $htmlBody, $textBody, $icalBody, $fromName, $replyTo)
{
    // Your SendGrid/SMTP implementation
}
```

## License

This extension is part of Baïkal and follows the same GPL-3.0 license.

## Credits

- Built on [SabreDAV](https://sabre.io/dav/)
- Uses [Twig](https://twig.symfony.com/) templating
- Inspired by Google Calendar's invitation system

## Support

- GitHub Issues: https://github.com/sabre-io/Baikal/issues
- Documentation: https://sabre.io/baikal/
- Community: https://github.com/sabre-io/Baikal/discussions

## Changelog

### Version 1.0.0 (2026)
- Initial release
- Web-based RSVP functionality
- Customizable email templates
- Google Calendar-style default theme
- Support for MySQL, PostgreSQL, SQLite
