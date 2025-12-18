# efaCloud Testing Infrastructure

This directory contains the testing infrastructure for efaCloud development. These files are **not required for production installations** and are automatically excluded from ZIP downloads via `.gitattributes`.

## Directory Structure

```
tests/
├── Unit/                    # Unit tests (no database required)
│   └── EfaTablesTest.php    # Tests for Efa_tables class
├── Integration/             # Integration tests (requires MySQL)
│   └── InstallationTest.php # Tests the installation process
├── fixtures/                # Test data and database schemas
│   └── test_database.sql    # Minimal test database schema
├── bootstrap.php            # PHPUnit bootstrap file
└── README.md                # This file
```

## Requirements for Running Tests

- PHP 8.1 or higher
- Composer (PHP dependency manager)
- MySQL 8.0 (for integration tests)
- Docker (optional, for containerized testing)

## Running Tests Locally

### 1. Install Dependencies

```bash
composer install
```

### 2. Run Unit Tests (No Database Required)

```bash
vendor/bin/phpunit --testsuite unit
```

### 3. Run Integration Tests (Requires MySQL)

First, set up a MySQL database:

```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE efacloud_test;"
mysql -u root -p efacloud_test < tests/fixtures/test_database.sql
```

Then run the tests:

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=efacloud_test DB_USERNAME=root DB_PASSWORD=yourpassword \
vendor/bin/phpunit --testsuite integration
```

### 4. Run All Tests

```bash
vendor/bin/phpunit
```

## Running Tests with Docker

For a consistent test environment, use Docker:

```bash
# Start the test environment
docker-compose -f docker-compose.test.yml up -d

# Run tests inside the container
docker-compose -f docker-compose.test.yml exec php-test vendor/bin/phpunit

# Stop the environment
docker-compose -f docker-compose.test.yml down
```

## Static Analysis

### PHPStan (Type Checking)

```bash
vendor/bin/phpstan analyse
```

### Psalm (Security Analysis)

```bash
vendor/bin/psalm --taint-analysis
```

## GitHub Actions

Tests run automatically on every push to any branch via GitHub Actions. The workflows are defined in:

- `.github/workflows/continuous-integration.yml` - Main test pipeline
- `.github/workflows/security-scanning.yml` - Security vulnerability scanning

### Workflow Jobs

| Job | Description | Trigger |
|-----|-------------|---------|
| PHP Syntax Validation | Checks all PHP files for syntax errors | Every push |
| Static Code Analysis | Runs PHPStan for type checking | Every push |
| Unit Tests | Runs PHPUnit unit test suite | Every push |
| Integration Tests | Runs PHPUnit with MySQL | Every push |
| Installation Process Test | Simulates full installation | Every push |
| Security Scanning | Snyk, OWASP, Psalm taint analysis | Weekly + master pushes |

## Writing New Tests

### Unit Test Example

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

### Integration Test Example

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private $mysqli;

    protected function setUp(): void
    {
        $this->mysqli = new \mysqli(
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') ?: '',
            getenv('DB_DATABASE') ?: 'efacloud_test'
        );
    }

    protected function tearDown(): void
    {
        $this->mysqli->close();
    }

    public function testDatabaseConnection(): void
    {
        $this->assertFalse($this->mysqli->connect_error);
    }
}
```

## Test Database Fixtures

The `fixtures/test_database.sql` file contains a minimal database schema for testing. It includes:

- `efaCloudUsers` - User accounts table
- `efa2boats` - Boats table
- `efa2persons` - Persons table
- `efa2boatstatus` - Boat status table
- `efa2logbook` - Logbook entries table

Sample data is included for basic testing scenarios.

## Troubleshooting

### "Class not found" Errors

Make sure you've run `composer install` and that the autoloader is working:

```bash
composer dump-autoload
```

### MySQL Connection Errors

1. Verify MySQL is running
2. Check environment variables are set correctly
3. Ensure the test database exists

### PHPUnit Not Found

```bash
composer install --dev
```

## Contributing

When adding new features to efaCloud:

1. Write unit tests for new classes/methods
2. Write integration tests for database operations
3. Run the full test suite before submitting a pull request
4. Ensure all tests pass in GitHub Actions
