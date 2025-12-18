<?php
/**
 * Installation Process Test
 *
 * This script simulates the browser-based installation process:
 * 1. Configure database connection (setup_db_connection.php)
 * 2. Create database tables and admin user (setup_clear_db.php)
 * 3. Verify installation completed correctly
 *
 * This is run as a standalone script in the GitHub Actions workflow.
 */

declare(strict_types=1);

// Get database configuration from environment
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'efacloud_install_test';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: 'testinstall';

echo "=== efaCloud Installation Process Test ===\n\n";
echo "Database Configuration:\n";
echo "  Host: $dbHost:$dbPort\n";
echo "  Database: $dbName\n";
echo "  User: $dbUser\n\n";

// Step 1: Test database connection directly
echo "Step 1: Testing database connection...\n";
try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    echo "  ✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "  ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Create the settings_db configuration file
echo "Step 2: Creating database configuration file...\n";
$configDir = __DIR__ . '/../../config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

// Create settings_db in the format expected by efaCloud
$dbConfig = [
    'db_host' => $dbHost,
    'db_name' => $dbName,
    'db_user' => $dbUser,
    'db_up' => $dbPass  // Note: In production this would be swapped/encoded
];

$configContent = base64_encode(serialize($dbConfig));
$settingsDbPath = $configDir . '/settings_db';
file_put_contents($settingsDbPath, $configContent);
echo "  ✓ Created settings_db configuration\n\n";

// Step 3: Load the application classes and initialize database
echo "Step 3: Loading application classes...\n";

// Set up environment for the application
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/test';
$_SERVER['HTTPS'] = 'off';

// Store the repository root path
$repoRoot = realpath(__DIR__ . '/../..');

// Create log directory if it doesn't exist
$logDir = $repoRoot . '/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Change to the install directory to simulate installation context
// efaCloud uses relative paths like '../classes/' which require running from a subdirectory
chdir($repoRoot . '/install');

// Include required files (paths are relative to install/ directory)
require_once '../classes/init_i18n.php';
require_once '../classes/tfyh_toolbox.php';

$toolbox = new Tfyh_toolbox();
echo "  ✓ Toolbox initialized\n";

// Include socket class (path relative to install/ directory)
require_once '../classes/tfyh_socket.php';
$socket = new Tfyh_socket($toolbox);

// Test socket connection
$connected = $socket->open_socket();
if ($connected !== true) {
    echo "  ✗ Socket connection failed: $connected\n";
    exit(1);
}
echo "  ✓ Socket connection established\n\n";

// Step 4: Initialize the database with efa tables
echo "Step 4: Initializing database tables...\n";

// Set up a mock admin session user
$adminUser = [
    '@id' => 1,
    'Vorname' => 'Test',
    'Nachname' => 'Admin',
    'EMail' => 'test@example.com',
    'efaCloudUserID' => '1142',
    'efaAdminName' => 'testadmin',
    'Passwort_Hash' => password_hash('123Test!', PASSWORD_DEFAULT),
    'Rolle' => 'admin'
];
$toolbox->users->set_session_user($adminUser);
echo "  ✓ Admin session configured\n";

// Load efa_tools and initialize database (path relative to install/ directory)
require_once '../classes/efa_tools.php';
$efa_tools = new Efa_tools($toolbox, $socket);

// Initialize all efa2 tables, efaCloud tables, and efaCloudUsers
echo "  Initializing efa2 tables, efaCloud tables, and users...\n";
$result = $efa_tools->init_efa_data_base(true, true, true);
echo "  " . strip_tags(str_replace('<br>', "\n  ", $result)) . "\n";
echo "  ✓ Database initialization complete\n\n";

// Step 5: Verify tables were created
echo "Step 5: Verifying database tables...\n";

$expectedTables = [
    'efaCloudUsers',
    'efa2boats',
    'efa2persons',
    'efa2logbook',
    'efa2boatreservations',
    'efa2boatdamages',
    'efa2boatstatus',
    'efa2destinations',
    'efa2groups',
    'efa2waters'
];

$tableResult = $mysqli->query("SHOW TABLES");
$existingTables = [];
while ($row = $tableResult->fetch_array()) {
    $existingTables[] = $row[0];
}

echo "  Found " . count($existingTables) . " tables in database:\n";
foreach ($existingTables as $table) {
    echo "    - $table\n";
}

$missingTables = [];
foreach ($expectedTables as $expectedTable) {
    if (!in_array($expectedTable, $existingTables)) {
        $missingTables[] = $expectedTable;
    }
}

if (count($missingTables) > 0) {
    echo "\n  ✗ Missing tables: " . implode(', ', $missingTables) . "\n";
    exit(1);
}
echo "  ✓ All expected tables exist\n\n";

// Step 6: Verify admin user was created
echo "Step 6: Verifying admin user...\n";

$adminResult = $mysqli->query("SELECT * FROM efaCloudUsers WHERE efaCloudUserID = '1142'");
if ($adminResult && $adminResult->num_rows > 0) {
    $admin = $adminResult->fetch_assoc();
    echo "  ✓ Admin user found:\n";
    echo "    - ID: " . $admin['efaCloudUserID'] . "\n";
    echo "    - Name: " . ($admin['Vorname'] ?? 'N/A') . " " . ($admin['Nachname'] ?? 'N/A') . "\n";
    echo "    - Role: " . ($admin['Rolle'] ?? 'N/A') . "\n";
} else {
    echo "  ✗ Admin user not found in efaCloudUsers table\n";
    exit(1);
}

// Step 7: Test that core tables have correct structure
echo "\nStep 7: Verifying table structures...\n";

$structureTests = [
    'efa2boats' => ['Id', 'ValidFrom', 'Name'],
    'efa2persons' => ['Id', 'ValidFrom', 'FirstName', 'LastName'],
    'efa2logbook' => ['EntryId', 'Logbookname', 'Date'],
];

foreach ($structureTests as $tableName => $requiredColumns) {
    $columnsResult = $mysqli->query("DESCRIBE $tableName");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $missing = array_diff($requiredColumns, $columns);
    if (count($missing) > 0) {
        echo "  ✗ $tableName missing columns: " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "  ✓ $tableName has required columns\n";
}

echo "\n=== Installation Test PASSED ===\n";
echo "All installation steps completed successfully.\n";
echo "The efaCloud installation process is working correctly.\n\n";

// Cleanup
$mysqli->close();
$socket->close();

exit(0);
