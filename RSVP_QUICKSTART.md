# RSVP Extension - Quick Start Guide

## Schnellstart (Deutsch)

Diese Erweiterung fügt Baïkal RSVP-Funktionalität (Zu-/Absagen) für Kalendereinladungen hinzu.

### Installation in 3 Schritten:

**1. Datenbanktabellen erstellen**
```bash
php install-rsvp-tables.php
```

**2. Konfiguration aktivieren**

Bearbeite `config/baikal.yaml`:
```yaml
system:
  rsvp_enabled: true
  invite_from: 'kalender@deine-domain.de'
```

**3. Webserver neu starten**
```bash
sudo systemctl reload apache2
# oder
sudo systemctl reload nginx
```

### Nutzung

**Für Organisatoren:**
1. Erstelle ein Event in deinem Kalender (z.B. macOS Kalender, Thunderbird)
2. Füge Teilnehmer per E-Mail hinzu
3. Speichere/sende das Event
4. Teilnehmer erhalten automatisch eine E-Mail mit RSVP-Links

**Für Teilnehmer:**
1. E-Mail öffnen
2. Auf "Ja", "Nein" oder "Vielleicht" klicken
3. Fertig! Die Antwort wird automatisch an den Organisator gesendet

### Templates anpassen

Templates befinden sich in `Core/Resources/templates/rsvp/`:

- `email_invitation.html.twig` - HTML E-Mail
- `email_invitation.txt.twig` - Text E-Mail  
- `rsvp_page.html.twig` - RSVP Webseite

### Beispiel: Farben ändern

Bearbeite `email_invitation.html.twig`:

```html
<style>
    .header {
        background-color: #4285f4;  /* Google Blau - ändern Sie dies */
    }
    .rsvp-yes {
        background-color: #1a73e8;  /* Ja-Button Farbe */
    }
    .rsvp-no {
        background-color: #ea4335;  /* Nein-Button Farbe */
    }
</style>
```

### Kompatibilität

✓ macOS Kalender
✓ iOS Kalender
✓ Android (DAVx5)
✓ Google Calendar (via CalDAV)
✓ Thunderbird
✓ Evolution

### Shared Hosting (All-Inkl)

Die Erweiterung funktioniert auf Shared Hosting:

1. Upload via FTP nach `/baikal/`
2. SSH Zugang aktivieren (in All-Inkl Control Panel)
3. Via SSH einloggen und Installer ausführen:
   ```bash
   cd /www/htdocs/username/baikal
   php install-rsvp-tables.php
   ```
4. Konfiguration anpassen (siehe oben)

### Aufwand

Die Installation ist einfach und nimmt ca. 5-10 Minuten in Anspruch:
- ✓ Keine Code-Änderungen nötig
- ✓ Einfacher Installer
- ✓ Funktioniert out-of-the-box
- ✓ Template-Anpassungen optional

---

## Quick Start (English)

This extension adds RSVP functionality for calendar invitations to Baïkal.

### Installation in 3 Steps:

**1. Create database tables**
```bash
php install-rsvp-tables.php
```

**2. Enable in configuration**

Edit `config/baikal.yaml`:
```yaml
system:
  rsvp_enabled: true
  invite_from: 'calendar@yourdomain.com'
```

**3. Restart web server**
```bash
sudo systemctl reload apache2
# or
sudo systemctl reload nginx
```

### Usage

**For Organizers:**
1. Create an event in your calendar app
2. Add attendees by email
3. Save/send the event
4. Attendees receive email with RSVP links automatically

**For Attendees:**
1. Open the email
2. Click "Yes", "No", or "Maybe"
3. Done! Response is sent to organizer automatically

### Template Customization

Templates are located in `Core/Resources/templates/rsvp/`:

- `email_invitation.html.twig` - HTML email
- `email_invitation.txt.twig` - Text email
- `rsvp_page.html.twig` - RSVP web page

### Example: Change Colors

Edit `email_invitation.html.twig`:

```html
<style>
    .header {
        background-color: #4285f4;  /* Google Blue - change this */
    }
    .rsvp-yes {
        background-color: #1a73e8;  /* Yes button color */
    }
    .rsvp-no {
        background-color: #ea4335;  /* No button color */
    }
</style>
```

### Compatibility

✓ macOS Calendar
✓ iOS Calendar
✓ Android (DAVx5)
✓ Google Calendar (via CalDAV)
✓ Thunderbird
✓ Evolution

### Installation Effort

Installation is simple and takes about 5-10 minutes:
- ✓ No code changes needed
- ✓ Simple installer script
- ✓ Works out-of-the-box
- ✓ Template customization is optional

---

## Full Documentation

See [RSVP_README.md](RSVP_README.md) for complete documentation including:
- Detailed architecture
- Template customization guide
- Troubleshooting
- Advanced configuration
- API documentation
