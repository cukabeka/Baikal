#!/usr/bin/env php
<?php

/***************************************************************
*  RSVP Extension Test Script
*  
*  This script tests the RSVP extension components without
*  requiring a full Baïkal installation.
***************************************************************/

echo "Baïkal RSVP Extension - Component Test\n";
echo "======================================\n\n";

// Test 1: Check PHP version
echo "1. Checking PHP version...\n";
$phpVersion = phpversion();
echo "   PHP version: $phpVersion\n";
if (version_compare($phpVersion, '8.2.0', '>=')) {
    echo "   ✓ PHP version OK\n";
} else {
    echo "   ✗ PHP 8.2 or higher required\n";
    exit(1);
}

// Test 2: Check required extensions
echo "\n2. Checking required PHP extensions...\n";
$requiredExtensions = ['pdo', 'dom', 'zlib'];
$missing = [];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ $ext\n";
    } else {
        echo "   ✗ $ext (missing)\n";
        $missing[] = $ext;
    }
}

if (!empty($missing)) {
    echo "\n   Missing extensions: " . implode(', ', $missing) . "\n";
    exit(1);
}

// Test 3: Check if vendor directory exists
echo "\n3. Checking Composer dependencies...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "   ✓ Vendor directory found\n";
    require __DIR__ . '/vendor/autoload.php';
} else {
    echo "   ✗ Vendor directory not found\n";
    echo "   Run: composer install\n";
    exit(1);
}

// Test 4: Check if our files exist
echo "\n4. Checking RSVP extension files...\n";
$files = [
    'Core/Frameworks/Baikal/Core/Schedule/RSVPIMipPlugin.php',
    'html/rsvp.php',
    'Core/Resources/templates/rsvp/email_invitation.html.twig',
    'Core/Resources/templates/rsvp/email_invitation.txt.twig',
    'Core/Resources/templates/rsvp/rsvp_page.html.twig',
    'Core/Resources/Db/MySQL/rsvp.sql',
    'Core/Resources/Db/SQLite/rsvp.sql',
    'Core/Resources/Db/PgSQL/rsvp.sql',
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✓ $file\n";
    } else {
        echo "   ✗ $file (missing)\n";
        exit(1);
    }
}

// Test 5: Test Twig template rendering
echo "\n5. Testing Twig template engine...\n";
try {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/Core/Resources/templates/rsvp');
    $twig = new \Twig\Environment($loader);
    
    $testData = [
        'summary' => 'Test Event',
        'sender' => 'Test Organizer',
        'start_date' => 'Monday, January 20, 2026',
        'start_time' => '2:00 PM',
        'end_time' => '3:00 PM',
        'all_day' => false,
        'location' => 'Test Location',
        'description' => 'This is a test event',
        'rsvp_url' => 'https://example.com/rsvp.php?token=test',
        'rsvp_yes_url' => 'https://example.com/rsvp.php?token=test&response=ACCEPTED',
        'rsvp_no_url' => 'https://example.com/rsvp.php?token=test&response=DECLINED',
        'rsvp_maybe_url' => 'https://example.com/rsvp.php?token=test&response=TENTATIVE',
        'token' => 'test123',
        'organizer_name' => 'Test Organizer',
        'organizer_email' => 'organizer@example.com',
    ];
    
    $html = $twig->render('email_invitation.html.twig', $testData);
    $text = $twig->render('email_invitation.txt.twig', $testData);
    $page = $twig->render('rsvp_page.html.twig', $testData);
    
    if (strlen($html) > 100 && strlen($text) > 50 && strlen($page) > 100) {
        echo "   ✓ Templates render successfully\n";
        echo "   - HTML email: " . strlen($html) . " bytes\n";
        echo "   - Text email: " . strlen($text) . " bytes\n";
        echo "   - RSVP page: " . strlen($page) . " bytes\n";
    } else {
        echo "   ✗ Templates too short or empty\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ✗ Template rendering failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Test token generation
echo "\n6. Testing token generation...\n";
try {
    $token = bin2hex(random_bytes(32));
    if (strlen($token) === 64) {
        echo "   ✓ Token generation works\n";
        echo "   Sample token: " . substr($token, 0, 16) . "...\n";
    } else {
        echo "   ✗ Token generation failed\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ✗ Token generation error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Check SQL files are valid
echo "\n7. Checking SQL schema files...\n";
$sqlFiles = [
    'MySQL' => 'Core/Resources/Db/MySQL/rsvp.sql',
    'SQLite' => 'Core/Resources/Db/SQLite/rsvp.sql',
    'PostgreSQL' => 'Core/Resources/Db/PgSQL/rsvp.sql',
];

foreach ($sqlFiles as $db => $file) {
    $sql = file_get_contents(__DIR__ . '/' . $file);
    if (strpos($sql, 'rsvp_tokens') !== false && strpos($sql, 'rsvp_responses') !== false) {
        echo "   ✓ $db schema OK\n";
    } else {
        echo "   ✗ $db schema invalid\n";
        exit(1);
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✓ All tests passed!\n";
echo "\nThe RSVP extension is properly installed.\n";
echo "\nNext steps:\n";
echo "1. Run: php install-rsvp-tables.php\n";
echo "2. Edit config/baikal.yaml and set:\n";
echo "   - rsvp_enabled: true\n";
echo "   - invite_from: 'your-email@example.com'\n";
echo "3. Restart your web server\n";
echo "4. Test by creating a calendar event with attendees\n";
echo "\nFor more information, see RSVP_README.md\n";
