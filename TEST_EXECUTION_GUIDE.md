# Test Execution Guide

This guide provides exact commands for running tests within the Docker environment and interpreting results.

---

## Quick Start

```bash
# 1. Start test database
docker-compose up -d db_test

# 2. Run all tests
php artisan test

# 3. Expected result: 28 tests pass
```

---

## Prerequisites

Verify your environment is ready:

```bash
docker --version          # Docker >= 24.x
docker-compose --version  # Docker Compose >= 2.x
php -v                    # PHP 8.3+
composer --version        # Composer 2.x+
```

---

## Running Tests in Docker

### Option 1: Standard Docker Execution (Recommended)

```bash
# Start the test database service
docker-compose up -d db_test

# Wait for database to be ready (~5-10 seconds)
# The health check ensures it's accessible

# Run tests
php artisan test
```

**What this does:**
- Uses `phpunit.xml` configuration
- Connects to `db_test` service via Docker network
- Runs all 28 feature tests
- Tests are isolated with RefreshDatabase trait

### Option 2: Container Execution

Run tests inside the Laravel application container:

```bash
# Start all services
docker-compose up -d

# Execute tests inside the container
docker-compose exec app php artisan test
```

**Use this when:**
- Testing environment parity (production-like)
- Debugging container-specific issues

### Option 3: Local Execution Against Docker Database

Run tests locally against the `db_test` service on localhost:3307:

```bash
# Start test database
docker-compose up -d db_test

# Execute with local config
php -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.local.xml
```

**Use this when:**
- Running inside an IDE debugger
- Local performance is critical

---

## Command Reference

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Class
```bash
php artisan test tests/Feature/Api/V1/ItemTest.php
php artisan test tests/Feature/Api/V1/CategoryTest.php
php artisan test tests/Feature/AuditLogServiceTest.php
```

### Run All Feature Tests
```bash
php artisan test tests/Feature
```

### Run with Verbose Output
```bash
php artisan test --verbose
```

Shows test method names and timing:
```
  PASS  Tests\Feature\Api\V1\ItemTest
  ✓ can list items                          0.245s
  ✓ can create item                         0.182s
  ...
```

### Run Tests in Parallel
```bash
php artisan test --parallel
php artisan test --parallel --processes=4
```

**Performance**: ~12-19 seconds sequential → ~4-7 seconds parallel

### Run with Code Coverage
```bash
php artisan test --coverage
```

Requires Xdebug/PCOV. Generates HTML coverage report in `coverage/` directory.

---

## Expected Output

### Success (All 28 Tests Pass)

```
  PASS  Tests\Feature\Api\V1\ItemTest
  ✓ can list items                                                           0.25s
  ✓ can create item                                                          0.18s
  ✓ low stock status is set automatically                                    0.21s
  ✓ updating quantity updates status                                         0.19s
  ✓ can show item                                                            0.15s
  ✓ can update item                                                          0.17s
  ✓ can delete item                                                          0.16s
  ✓ validation fails with duplicate sku                                      0.14s
  ✓ validation fails with invalid category id                                0.13s
  ✓ validation fails with negative quantity                                  0.14s
  ✓ requires authentication                                                  0.12s

  PASS  Tests\Feature\Api\V1\CategoryTest
  ✓ can list categories                                                      0.19s
  ✓ can create category                                                      0.17s
  ✓ cannot create category with duplicate name                               0.15s
  ✓ can show category                                                        0.14s
  ✓ can update category                                                      0.16s
  ✓ cannot update category with duplicate name                               0.15s
  ✓ can update category with same name                                       0.13s
  ✓ can delete category                                                      0.14s
  ✓ validation fails with missing name                                       0.13s
  ✓ requires authentication                                                  0.12s

  PASS  Tests\Feature\AuditLogServiceTest
  ✓ audit log is created when item is created                                0.21s
  ✓ audit log records changes on update                                      0.18s
  ✓ audit log is created when item is deleted                                0.16s
  ✓ audit log is created when category is created                            0.15s
  ✓ audit log is not created without authenticated user                       0.14s
  ✓ multiple field changes are recorded                                      0.17s

  Tests:  28 passed (144 assertions)
  Duration:  5.23s
```

### Interpreting Results

| Line | Meaning |
|------|---------|
| `PASS` | All tests in this class passed |
| `✓` | Individual test passed |
| `✗` | Individual test failed |
| `28 passed` | Total number of successful tests |
| `144 assertions` | Total assertions executed (each test contains multiple assertions) |
| `Duration: 5.23s` | Total execution time |

### Failure Example

If a test fails, you'll see:

```
FAIL  Tests\Feature\Api\V1\ItemTest
✗ can create item
  Expected status code 201 but received 422.

Expected
---------
201

Actual
------
422

Failure
-------
Expected response status code [201] but received [422].
Response:
{
    "message": "The given data was invalid.",
    "errors": {
        "sku": ["The sku must be unique."]
    }
}
```

**Interpreting failure output:**
- Test name: `can create item`
- Expected vs Actual: What the test expected vs. what it got
- Response payload: The actual API response
- Error details: Specific validation or logic errors

---

## Troubleshooting

### Error: "Connection refused on db_test:3306"

**Cause**: Test database service not running

**Fix**:
```bash
docker-compose up -d db_test
sleep 5
php artisan test
```

### Error: "Access denied for user 'test_user'@'db_test'"

**Cause**: Credentials mismatch or database not initialized

**Fix**:
```bash
# Restart the service completely
docker-compose down
docker volume rm vim-backend_valsoft_db_test_data
docker-compose up -d db_test
sleep 10
php artisan test
```

### Error: "Unknown database 'valsoft_inventory_test'"

**Cause**: Test database created but migrations didn't run

**Fix**:
```bash
# The RefreshDatabase trait runs migrations automatically
# If this still fails, ensure db_test is fully healthy
docker logs valsoft_db_test

# Check health status
docker-compose ps db_test  # Should show "healthy"
```

### Error: "SQLSTATE[HY000]: General error: 2006 MySQL has gone away"

**Cause**: Database connection timeout

**Fix**:
```bash
# Increase database timeout or restart
docker-compose restart db_test
sleep 10
php artisan test
```

### Tests run but some fail

**Debug steps**:
```bash
# 1. Check database is accessible
docker-compose exec db_test mysql -u test_user -ptest_secret -D valsoft_inventory_test -e "SELECT 1;"

# 2. Run tests with verbose output
php artisan test --verbose

# 3. Run single failing test
php artisan test tests/Feature/Api/V1/ItemTest.php::test_can_create_item
```

---

## Test Environment Details

### Database State

```
Before each test:
  ↓
RefreshDatabase drops all tables
  ↓
Migrations run (fresh schema)
  ↓
Test executes with clean database
  ↓
After test: Rollback (transaction)
```

**Result**: Tests don't interfere with each other; development database remains untouched

### Configuration

Tests use `phpunit.xml` environment variables:
- `DB_HOST=db_test` (Docker service name)
- `DB_DATABASE=valsoft_inventory_test`
- `DB_USERNAME=test_user`
- `DB_PASSWORD=test_secret`

### External API Calls

AI prediction tests (`AiPredictionService`):
- **Real API**: If `OPENAI_API_KEY` is set, tests make live Groq API calls
- **Mock/Skip**: If `OPENAI_API_KEY` is empty, tests return mock responses
- **Performance**: Live calls add 300-500ms per test

---

## Performance Expectations

### Execution Times

| Configuration | Time |
|---|---|
| Sequential (standard) | 12-19 seconds |
| Parallel (4 processes) | 4-7 seconds |
| With Xdebug coverage | 20-30 seconds |
| Inside container | 12-19 seconds |

### Database Operations Per Test Suite

- **ItemTest** (11 tests): ~22 migrations runs (RefreshDatabase × 11)
- **CategoryTest** (10 tests): ~20 migrations runs
- **AuditLogServiceTest** (7 tests): ~14 migrations runs

Total: ~56 migration runs (normal and fast for small schema)

---

## Continuous Integration Setup

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      db_test:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: valsoft_inventory_test
          MYSQL_USER: test_user
          MYSQL_PASSWORD: test_secret
          MYSQL_ROOT_PASSWORD: root
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_mysql
      
      - name: Install dependencies
        run: composer install
      
      - name: Copy .env
        run: cp .env.example .env
      
      - name: Generate key
        run: php artisan key:generate
      
      - name: Run tests
        run: php artisan test --parallel
```

---

## Post-Test Checklist

After all tests pass:

- ✅ 28/28 tests passed
- ✅ 144 assertions executed
- ✅ No database errors
- ✅ All endpoints responding correctly
- ✅ Audit logging working
- ✅ ItemObserver status calculation correct
- ✅ Authentication middleware enforced
- ✅ Validation rules applied

---

## Next Steps

Once tests pass consistently:

1. **Commit your code**: Tests validate correctness
2. **Set up CI/CD**: Use the GitHub Actions template above
3. **Monitor production**: Add health check endpoints
4. **Expand test coverage**: Add integration tests for AI predictions, imports/exports, etc.

---

## Documentation

- **TESTING.md**: Detailed testing strategy and methodology
- **INFRASTRUCTURE_ANALYSIS.md**: Docker architecture and technology rationale
- **README.md**: Installation and API overview
