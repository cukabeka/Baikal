<?php

#################################################################
#  Copyright notice
#
#  (c) 2026 Baïkal Contributors
#  All rights reserved
#
#  http://sabre.io/baikal
#
#  This script is part of the Baïkal Server project. The Baïkal
#  Server project is free software; you can redistribute it
#  and/or modify it under the terms of the GNU General Public
#  License as published by the Free Software Foundation; either
#  version 2 of the License, or (at your option) any later version.
#
#  The GNU General Public License can be found at
#  http://www.gnu.org/copyleft/gpl.html.
#
#  This script is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  This copyright notice MUST APPEAR in all copies of the script!
#################################################################

namespace Baikal\Core\Schedule;

use Sabre\CalDAV\Schedule\IMipPlugin;
use Sabre\VObject\ITip;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

/**
 * Extended iMIP handler with RSVP web interface support.
 *
 * This class extends SabreDAV's IMipPlugin to provide:
 * - Web-based RSVP pages for calendar invitations
 * - Customizable email templates using Twig
 * - Token-based RSVP tracking
 * - Google Calendar-style default templates
 *
 * @copyright Copyright (C) Baïkal Contributors
 * @license http://sabre.io/license/ GPLv3
 */
class RSVPIMipPlugin extends IMipPlugin
{
    /**
     * Default token expiration time in seconds (90 days).
     */
    const TOKEN_EXPIRATION_SECONDS = 90 * 24 * 60 * 60;

    /**
     * PDO database connection.
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * Base URI for RSVP links.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Twig template engine.
     *
     * @var Environment
     */
    protected $twig;

    /**
     * Creates the RSVP-enabled email handler.
     *
     * @param string $senderEmail Email address for From: header
     * @param \PDO $pdo Database connection for token storage
     * @param string $baseUri Base URI for generating RSVP links
     */
    public function __construct($senderEmail, \PDO $pdo, $baseUri)
    {
        parent::__construct($senderEmail);
        $this->pdo = $pdo;
        $this->baseUri = rtrim($baseUri, '/');
        
        // Initialize Twig with template directory
        $templateDir = PROJECT_PATH_ROOT . 'Core/Resources/templates/rsvp';
        $loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($loader, [
            'cache' => false, // Disable cache for easier template development
            'autoescape' => 'html',
        ]);
    }

    /**
     * Event handler for the 'schedule' event.
     * 
     * Extends parent to add RSVP link generation and custom email templates.
     */
    public function schedule(ITip\Message $iTipMessage)
    {
        // Skip if not significant
        if (!$iTipMessage->significantChange) {
            if (!$iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
            }
            return;
        }

        // Validate email addresses
        if ('mailto' !== parse_url($iTipMessage->sender, PHP_URL_SCHEME)) {
            return;
        }
        if ('mailto' !== parse_url($iTipMessage->recipient, PHP_URL_SCHEME)) {
            return;
        }

        $sender = substr($iTipMessage->sender, 7);
        $recipient = substr($iTipMessage->recipient, 7);

        // Only send RSVP-enabled emails for REQUEST method
        if (strtoupper($iTipMessage->method) === 'REQUEST') {
            $this->sendRSVPRequest($iTipMessage, $sender, $recipient);
        } else {
            // For other methods (REPLY, CANCEL), use parent implementation
            parent::schedule($iTipMessage);
        }
    }

    /**
     * Send an RSVP-enabled invitation email.
     *
     * @param ITip\Message $iTipMessage The iTIP message
     * @param string $sender Sender email address
     * @param string $recipient Recipient email address
     */
    protected function sendRSVPRequest(ITip\Message $iTipMessage, $sender, $recipient)
    {
        $vevent = $iTipMessage->message->VEVENT;
        
        // Generate RSVP token
        $token = $this->generateRSVPToken($iTipMessage, $recipient);
        
        // Extract event details
        $eventData = $this->extractEventData($vevent, $iTipMessage);
        $eventData['rsvp_url'] = $this->baseUri . '/rsvp.php?token=' . $token;
        $eventData['rsvp_yes_url'] = $eventData['rsvp_url'] . '&response=ACCEPTED';
        $eventData['rsvp_no_url'] = $eventData['rsvp_url'] . '&response=DECLINED';
        $eventData['rsvp_maybe_url'] = $eventData['rsvp_url'] . '&response=TENTATIVE';
        $eventData['sender'] = $iTipMessage->senderName ?: $sender;
        $eventData['recipient'] = $iTipMessage->recipientName ?: $recipient;

        // Render email template
        $htmlBody = $this->twig->render('email_invitation.html.twig', $eventData);
        $textBody = $this->twig->render('email_invitation.txt.twig', $eventData);

        // Prepare email
        $subject = 'Invitation: ' . $eventData['summary'];
        
        if ($iTipMessage->senderName) {
            $fromName = $iTipMessage->senderName;
        } else {
            $fromName = 'Calendar';
        }

        // Send multipart email (HTML + plain text + iCalendar)
        $this->sendMultipartEmail(
            $recipient,
            $subject,
            $htmlBody,
            $textBody,
            $iTipMessage->message->serialize(),
            $fromName,
            $sender
        );

        $iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip with RSVP';
    }

    /**
     * Extract event data from VEVENT component.
     *
     * @param \Sabre\VObject\Component\VEvent $vevent
     * @param ITip\Message $iTipMessage
     * @return array Event data for template rendering
     */
    protected function extractEventData($vevent, $iTipMessage)
    {
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
    }

    /**
     * Generate a unique RSVP token for tracking responses.
     *
     * @param ITip\Message $iTipMessage
     * @param string $recipient
     * @return string Token string
     */
    protected function generateRSVPToken($iTipMessage, $recipient)
    {
        $vevent = $iTipMessage->message->VEVENT;
        $uid = isset($vevent->UID) ? (string)$vevent->UID : uniqid('event-');
        
        // Generate token with retry on collision (extremely rare)
        $maxRetries = 3;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $token = bin2hex(random_bytes(32));
                
                // Store token in database
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rsvp_tokens (token, event_uid, recipient_email, created_at, expires_at) 
                     VALUES (?, ?, ?, ?, ?)'
                );
                
                $createdAt = time();
                $expiresAt = $createdAt + self::TOKEN_EXPIRATION_SECONDS;
                
                $stmt->execute([
                    $token,
                    $uid,
                    $recipient,
                    $createdAt,
                    $expiresAt
                ]);
                
                return $token;
            } catch (\PDOException $e) {
                // Token collision - try again
                if ($attempt === $maxRetries - 1) {
                    throw new \RuntimeException('Failed to generate unique RSVP token after ' . $maxRetries . ' attempts');
                }
            }
        }
    }

    /**
     * Send a multipart email with HTML, plain text, and iCalendar attachment.
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML body
     * @param string $textBody Plain text body
     * @param string $icalBody iCalendar data
     * @param string $fromName Sender name
     * @param string $replyTo Reply-to address
     */
    protected function sendMultipartEmail($to, $subject, $htmlBody, $textBody, $icalBody, $fromName, $replyTo)
    {
        $boundary = '----=_NextPart_' . md5(uniqid());
        $boundaryAlt = '----=_NextPart_Alt_' . md5(uniqid());
        
        $headers = [
            'From: ' . $fromName . ' <' . $this->senderEmail . '>',
            'Reply-To: ' . $replyTo,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        
        $message = "This is a multi-part message in MIME format.\r\n\r\n";
        
        // Alternative part (HTML + plain text)
        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"" . $boundaryAlt . "\"\r\n\r\n";
        
        // Plain text version
        $message .= "--" . $boundaryAlt . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";
        
        // HTML version
        $message .= "--" . $boundaryAlt . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        
        $message .= "--" . $boundaryAlt . "--\r\n\r\n";
        
        // iCalendar attachment
        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: text/calendar; charset=UTF-8; method=REQUEST\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $icalBody . "\r\n\r\n";
        
        $message .= "--" . $boundary . "--\r\n";
        
        mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
