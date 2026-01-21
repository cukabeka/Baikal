<?php

/***************************************************************
*  Copyright notice
*
*  (c) 2026 Baïkal Contributors
*  All rights reserved
*
*  http://sabre.io/baikal
*
*  This script is part of the Baïkal Server project. The Baïkal
*  Server project is free software; you can redistribute it
*  and/or modify it under the terms of the GNU General Public
*  License as published by the Free Software Foundation; either
*  version 2 of the License, or (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

use Symfony\Component\Yaml\Yaml;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

ini_set("session.cookie_httponly", 1);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

define("BAIKAL_CONTEXT", true);
define("PROJECT_CONTEXT_BASEURI", "/");

if (file_exists(getcwd() . "/Core")) {
    # Flat FTP mode
    define("PROJECT_PATH_ROOT", getcwd() . "/");
} else {
    # Dedicated server mode
    define("PROJECT_PATH_ROOT", dirname(getcwd()) . "/");
}

if (!file_exists(PROJECT_PATH_ROOT . 'vendor/')) {
    exit('<h1>Incomplete installation</h1><p>Ba&iuml;kal dependencies have not been installed.</p>');
}
require PROJECT_PATH_ROOT . 'vendor/autoload.php';

# Bootstrapping Flake
\Flake\Framework::bootstrap();

# Bootstrapping Baïkal
\Baikal\Framework::bootstrap();

try {
    $config = Yaml::parseFile(PROJECT_PATH_CONFIG . "baikal.yaml");
} catch (\Exception $e) {
    exit('<h1>Configuration Error</h1><p>Unable to read configuration file.</p>');
}

// Get token from request
$token = isset($_GET['token']) ? $_GET['token'] : '';
$response = isset($_GET['response']) ? $_GET['response'] : '';

if (empty($token)) {
    exit('<h1>Invalid Request</h1><p>No token provided.</p>');
}

// Validate token and get event details
$pdo = $GLOBALS['DB']->getPDO();

// Check if token exists and is valid
$stmt = $pdo->prepare(
    'SELECT * FROM rsvp_tokens WHERE token = ? AND expires_at > ?'
);
$stmt->execute([$token, time()]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    exit('<h1>Invalid or Expired Link</h1><p>This RSVP link is no longer valid.</p>');
}

// Initialize Twig
$templateDir = PROJECT_PATH_ROOT . 'Core/Resources/templates/rsvp';
$loader = new FilesystemLoader($templateDir);
$twig = new Environment($loader, [
    'cache' => false,
    'autoescape' => 'html',
]);

// Get event details from calendar
$eventUid = $tokenData['event_uid'];
$recipientEmail = $tokenData['recipient_email'];

// Find the event in the database
$eventData = findEventByUid($pdo, $eventUid);

if (!$eventData) {
    exit('<h1>Event Not Found</h1><p>The event associated with this invitation could not be found.</p>');
}

$successMessage = '';
$errorMessage = '';
$responded = false;

// Process RSVP response if provided
if (!empty($response) && in_array($response, ['ACCEPTED', 'DECLINED', 'TENTATIVE'])) {
    $result = processRSVPResponse($pdo, $tokenData, $eventData, $response, $config);
    if ($result['success']) {
        $successMessage = $result['message'];
        $responded = true;
    } else {
        $errorMessage = $result['message'];
    }
}

// Prepare template data
$templateData = array_merge($eventData, [
    'token' => $token,
    'success_message' => $successMessage,
    'error_message' => $errorMessage,
    'responded' => $responded,
]);

// Render the RSVP page
echo $twig->render('rsvp_page.html.twig', $templateData);

/**
 * Find event details by UID in the calendar database.
 */
function findEventByUid($pdo, $uid)
{
    // Query calendar objects for the event
    $stmt = $pdo->prepare(
        'SELECT calendardata FROM calendarobjects WHERE uid = ?'
    );
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return null;
    }
    
    // Parse iCalendar data
    try {
        $vcalendar = Sabre\VObject\Reader::read($row['calendardata']);
        $vevent = $vcalendar->VEVENT;
        
        $data = [];
        $data['summary'] = isset($vevent->SUMMARY) ? (string)$vevent->SUMMARY : 'Event';
        $data['description'] = isset($vevent->DESCRIPTION) ? (string)$vevent->DESCRIPTION : '';
        $data['location'] = isset($vevent->LOCATION) ? (string)$vevent->LOCATION : '';
        
        // Parse dates
        if (isset($vevent->DTSTART)) {
            $dtstart = $vevent->DTSTART->getDateTime();
            $data['start_date'] = $dtstart->format('l, F j, Y');
            $data['start_time'] = $dtstart->format('g:i A');
            $data['start_datetime'] = $dtstart;
        }
        
        if (isset($vevent->DTEND)) {
            $dtend = $vevent->DTEND->getDateTime();
            $data['end_date'] = $dtend->format('l, F j, Y');
            $data['end_time'] = $dtend->format('g:i A');
            $data['end_datetime'] = $dtend;
        }
        
        // Check if all-day event
        $data['all_day'] = isset($vevent->DTSTART) && !$vevent->DTSTART->hasTime();
        
        // Organizer info
        if (isset($vevent->ORGANIZER)) {
            $organizer = (string)$vevent->ORGANIZER;
            if (strpos($organizer, 'mailto:') === 0) {
                $data['organizer_email'] = substr($organizer, 7);
            }
            if (isset($vevent->ORGANIZER['CN'])) {
                $data['organizer_name'] = (string)$vevent->ORGANIZER['CN'];
            }
        }
        
        return $data;
    } catch (\Exception $e) {
        error_log('Error parsing event: ' . $e->getMessage());
        return null;
    }
}

/**
 * Process RSVP response and update calendar.
 */
function processRSVPResponse($pdo, $tokenData, $eventData, $response, $config)
{
    $eventUid = $tokenData['event_uid'];
    $recipientEmail = $tokenData['recipient_email'];
    
    // Get the calendar object
    $stmt = $pdo->prepare(
        'SELECT id, calendarid, calendardata, uri FROM calendarobjects WHERE uid = ?'
    );
    $stmt->execute([$eventUid]);
    $calendarObject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$calendarObject) {
        return ['success' => false, 'message' => 'Event not found in calendar.'];
    }
    
    try {
        // Parse the calendar object
        $vcalendar = Sabre\VObject\Reader::read($calendarObject['calendardata']);
        $vevent = $vcalendar->VEVENT;
        
        // Update or add attendee status
        $attendeeFound = false;
        if (isset($vevent->ATTENDEE)) {
            foreach ($vevent->ATTENDEE as $attendee) {
                $attendeeEmail = (string)$attendee;
                if (strpos($attendeeEmail, 'mailto:' . $recipientEmail) !== false) {
                    $attendee['PARTSTAT'] = $response;
                    $attendeeFound = true;
                    break;
                }
            }
        }
        
        // If attendee not found, add them
        if (!$attendeeFound) {
            $vevent->add('ATTENDEE', 'mailto:' . $recipientEmail, [
                'PARTSTAT' => $response,
                'RSVP' => 'TRUE',
            ]);
        }
        
        // Update the calendar object in database
        $updatedCalendarData = $vcalendar->serialize();
        $stmt = $pdo->prepare(
            'UPDATE calendarobjects SET calendardata = ?, lastmodified = ? WHERE id = ?'
        );
        $stmt->execute([$updatedCalendarData, time(), $calendarObject['id']]);
        
        // Record the response
        $stmt = $pdo->prepare(
            'INSERT INTO rsvp_responses (token, response, responded_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE response = ?, responded_at = ?'
        );
        $now = time();
        $stmt->execute([$tokenData['token'], $response, $now, $response, $now]);
        
        // Send notification to organizer if configured
        sendOrganizerNotification($eventData, $recipientEmail, $response, $config);
        
        $responseText = [
            'ACCEPTED' => 'Yes, you will attend',
            'DECLINED' => 'No, you will not attend',
            'TENTATIVE' => 'Maybe, you might attend',
        ];
        
        return [
            'success' => true,
            'message' => 'Thank you! Your response "' . $responseText[$response] . '" has been recorded.'
        ];
    } catch (\Exception $e) {
        error_log('Error processing RSVP: ' . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while processing your response.'];
    }
}

/**
 * Send notification to event organizer about RSVP response.
 */
function sendOrganizerNotification($eventData, $recipientEmail, $response, $config)
{
    if (!isset($eventData['organizer_email']) || empty($config['system']['invite_from'])) {
        return;
    }
    
    $responseText = [
        'ACCEPTED' => 'accepted',
        'DECLINED' => 'declined',
        'TENTATIVE' => 'tentatively accepted',
    ];
    
    $subject = 'RSVP: ' . $recipientEmail . ' ' . $responseText[$response] . ' - ' . $eventData['summary'];
    
    $message = "Calendar Event RSVP Notification\n\n";
    $message .= "Event: " . $eventData['summary'] . "\n";
    $message .= "Attendee: " . $recipientEmail . "\n";
    $message .= "Response: " . ucfirst($responseText[$response]) . "\n\n";
    
    if (isset($eventData['start_date'])) {
        $message .= "When: " . $eventData['start_date'];
        if (!$eventData['all_day'] && isset($eventData['start_time'])) {
            $message .= " at " . $eventData['start_time'];
        }
        $message .= "\n";
    }
    
    $headers = [
        'From: Baïkal CalDAV <' . $config['system']['invite_from'] . '>',
        'Reply-To: ' . $recipientEmail,
    ];
    
    mail($eventData['organizer_email'], $subject, $message, implode("\r\n", $headers));
}
