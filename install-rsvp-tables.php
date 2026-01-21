#!/usr/bin/env php
<?php

/***************************************************************
*  RSVP Extension Database Installer for Baïkal
*
*  This script creates the necessary database tables for the
*  RSVP functionality in Baïkal.
*
*  Usage: php install-rsvp-tables.php
***************************************************************/

define("BAIKAL_CONTEXT", true);

// Determine project root
if (file_exists(__DIR__ . "/Core")) {
    define("PROJECT_PATH_ROOT", __DIR__ . "/");
} else {
    define("PROJECT_PATH_ROOT", dirname(__DIR__) . "/");
}

require PROJECT_PATH_ROOT . 'vendor/autoload.php';

// Bootstrap
\Flake\Framework::bootstrap();
\Baikal\Framework::bootstrap();

use Symfony\Component\Yaml\Yaml;

try {
    $config = Yaml::parseFile(PROJECT_PATH_CONFIG . "baikal.yaml");
} catch (\Exception $e) {
    die("Error: Unable to read configuration file: " . $e->getMessage() . "\n");
}

$pdo = $GLOBALS['DB']->getPDO();
$backend = $config['database']['backend'] ?? 'sqlite';

echo "Baïkal RSVP Extension - Database Installer\n";
echo "==========================================\n\n";
echo "Database backend: " . strtoupper($backend) . "\n";

// Determine SQL file path
$sqlFile = '';
switch ($backend) {
    case 'mysql':
        $sqlFile = PROJECT_PATH_ROOT . 'Core/Resources/Db/MySQL/rsvp.sql';
        break;
    case 'sqlite':
        $sqlFile = PROJECT_PATH_ROOT . 'Core/Resources/Db/SQLite/rsvp.sql';
        break;
    case 'pgsql':
        $sqlFile = PROJECT_PATH_ROOT . 'Core/Resources/Db/PgSQL/rsvp.sql';
        break;
    default:
        die("Error: Unsupported database backend: $backend\n");
}

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found: $sqlFile\n");
}

// Read SQL file
$sql = file_get_contents($sqlFile);

try {
    // Check if tables already exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM rsvp_tokens LIMIT 1");
    echo "\nWarning: RSVP tables already exist. Skipping installation.\n";
    echo "If you want to reinstall, please drop the tables manually first.\n";
    exit(0);
} catch (\PDOException $e) {
    // Tables don't exist, proceed with installation
}

echo "\nCreating RSVP tables...\n";

try {
    // For SQLite, we need to execute statements one by one
    if ($backend === 'sqlite') {
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
    } else {
        // For MySQL and PostgreSQL, we can execute all at once
        $pdo->exec($sql);
    }
    
    echo "✓ RSVP tables created successfully!\n\n";
    echo "The following tables were created:\n";
    echo "  - rsvp_tokens: Stores RSVP invitation tokens\n";
    echo "  - rsvp_responses: Stores attendee responses\n\n";
    echo "RSVP extension is now ready to use.\n";
    echo "\nTo enable RSVP functionality, ensure the following in config/baikal.yaml:\n";
    echo "  system:\n";
    echo "    rsvp_enabled: true\n";
    echo "    invite_from: 'your-email@example.com'\n\n";
    
} catch (\PDOException $e) {
    die("Error creating tables: " . $e->getMessage() . "\n");
}
