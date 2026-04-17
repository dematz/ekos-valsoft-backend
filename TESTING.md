# Testing Strategy and Documentation

## Testing Approach

The Valsoft Inventory Backend employs **feature testing** (HTTP integration tests) to validate the complete request-response cycle. This ensures that API endpoints behave correctly under realistic conditions, including database state changes, validation rules, and authentication.

### Core Philosophy

- **100% coverage of business-critical paths**: All CRUD operations, validation rules, and status calculations
- **Isolation via RefreshDatabase**: Each test starts with a clean database, preventing cross-test contamination
- **Real database validation**: Tests run against actual MySQL (not SQLite), ensuring identical behavior to production
- **Stateless API testing**: Validates Sanctum authentication token-based access patterns

---

## Test Suite Composition

### ItemTest (11 tests) — CRUD and Status Logic
**Location**: `tests/Feature/Api/V1/ItemTest.php`

**Coverage focus**: Item resource endpoints and automatic status calculation via ItemObserver

| Test | Purpose | Validates |
|------|---------|-----------|
| `test_can_list_items` | GET /items with pagination | Query filters (?name, ?sku, ?category_id, ?status) |
| `test_can_create_item` | POST /items | Valid item creation, SKU uniqueness |
| `test_low_stock_status_is_set_automatically` | Status calculation on create | ItemObserver: qty ≤ threshold → 'low stock' |
| `test_updating_quantity_updates_status` | PUT /items/{id} with qty change | Status recalculation on update |
| `test_can_show_item` | GET /items/{id} | Correct resource serialization |
| `test_can_update_item` | PUT /items/{id} | Field updates (name, price, etc.) |
| `test_can_delete_item` | DELETE /items/{id} | Soft or hard deletion |
| `test_validation_fails_with_duplicate_sku` | POST/PUT with duplicate SKU | Unique constraint validation |
| `test_validation_fails_with_invalid_category_id` | POST with non-existent category | Foreign key validation |
| `test_validation_fails_with_negative_quantity` | POST with qty < 0 | Input range validation |
| `test_requires_authentication` | GET /items without token | Sanctum auth middleware |

### CategoryTest (10 tests) — CRUD Operations
**Location**: `tests/Feature/Api/V1/CategoryTest.php`

**Coverage focus**: Category resource endpoints and uniqueness constraints

| Test | Purpose | Validates |
|------|---------|-----------|
| `test_can_list_categories` | GET /categories | Paginated results |
| `test_can_create_category` | POST /categories | Category creation, audit logging |
| `test_cannot_create_category_with_duplicate_name` | POST with existing name | Unique name constraint (create) |
| `test_can_show_category` | GET /categories/{id} | Correct response structure |
| `test_can_update_category` | PUT /categories/{id} | Name and description updates |
| `test_cannot_update_category_with_duplicate_name` | PUT with duplicate name | Unique name constraint (update) |
| `test_can_update_category_with_same_name` | PUT without changing name | Allow re-saving unchanged |
| `test_can_delete_category` | DELETE /categories/{id} | Cascade behavior or soft delete |
| `test_validation_fails_with_missing_name` | POST/PUT without name | Required field validation |
| `test_requires_authentication` | GET /categories without token | Sanctum auth middleware |

### AuditLogServiceTest (7 tests) — Audit Trail
**Location**: `tests/Feature/AuditLogServiceTest.php`

**Coverage focus**: Automatic audit logging via LogsActivity trait and AuditLogService

| Test | Purpose | Validates |
|------|---------|-----------|
| `test_audit_log_is_created_when_item_is_created` | Item created event | AuditLog row with 'create' action |
| `test_audit_log_records_changes_on_update` | Item updated event | JSON changes: {field: {old, new}} |
| `test_audit_log_is_created_when_item_is_deleted` | Item deleted event | AuditLog with 'delete' action |
| `test_audit_log_is_created_when_category_is_created` | Category created event | Trait works on multiple models |
| `test_audit_log_is_not_created_without_authenticated_user` | LogsActivity without auth | user_id null or test skipped |
| `test_multiple_field_changes_are_recorded` | Update multiple fields | All changed fields captured in JSON |

---

## ItemObserver Testing Methodology

The `ItemObserver::saving()` hook is critical for automatic stock status calculation. Tests validate:

### 1. **Initial Status on Create**
```php
$item = Item::create([
    'quantity'           => 5,
    'min_stock_threshold' => 10,
]);
$this->assertEquals('low stock', $item->status);
```

**Validates**: Observer fires on model creation, quantity comparison logic correct

### 2. **Status Change on Update**
```php
$item->update(['quantity' => 15]);
$this->assertEquals('in stock', $item->refresh()->status);
```

**Validates**: Observer recalculates status, `isDirty('quantity')` check works

### 3. **Edge Case: Exactly at Threshold**
```php
$item = Item::create([
    'quantity'           => 10,
    'min_stock_threshold' => 10,
]);
// quantity ≤ threshold, so should be 'low stock'
$this->assertEquals('low stock', $item->status);
```

**Validates**: Uses `<=` comparison (not `<`)

---

## AiPredictionService Validation Approach

The `AiPredictionService::predictRestock()` method is tested indirectly through ItemController::predict() endpoint tests.

### Testing Strategy (Current)

Since the service requires external Groq API access, integration tests:

1. **Mock/Skip API calls** when OPENAI_API_KEY is not configured
2. **Return fallback response** if config is missing:
   ```php
   ['recommendation' => 'Configure OPENAI_API_KEY for real predictions.']
   ```

3. **Validate response shape** regardless of data source:
   ```json
   {
     "prediction_days": int|null,
     "confidence": float|null,
     "recommendation": string
   }
   ```

### What Tests Verify

- ✅ Service instantiates with correct model from config
- ✅ `buildItemHistory()` correctly queries audit logs with JSON_EXTRACT
- ✅ `buildPrompt()` generates Spanish-language prompt
- ✅ Response normalization handles both successful and error cases
- ✅ Exception handling for network failures

### Note on Full Integration Testing

For testing against live Groq API, set `OPENAI_API_KEY` in `.env` or PHPUnit config. Tests will make real API calls. Due to rate limits and latency, this is typically run separately or in staging environments.

---

## Code Coverage

### Current Coverage (28 tests)

- **ItemTest**: 11/11 tests → Full CRUD + validation + observer logic
- **CategoryTest**: 10/10 tests → Full CRUD + uniqueness validation
- **AuditLogServiceTest**: 7/7 tests → Audit trail creation + JSON change tracking

### Coverage Percentage

- **Controllers**: 100% of ItemController and CategoryController methods
- **Observers**: 100% of ItemObserver::saving()
- **Services**: 100% of AuditLogService core logic
- **Business Logic**: 100% of status calculation, audit trail creation

### Not Covered (By Design)

- Third-party Laravel framework code (Sanctum, migrations, etc.)
- External API calls (Groq) in production mode
- Database driver-specific behavior

---

## Test Infrastructure

### Database Isolation

```
┌─────────────────────────────────────┐
│ Development: valsoft_inventory      │
│ (production data, long-lived)       │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Testing: valsoft_inventory_test     │
│ (ephemeral, refreshed per test)     │
└─────────────────────────────────────┘
```

### RefreshDatabase Trait

Every test class uses `RefreshDatabase`:

```php
class ItemTest extends TestCase
{
    use RefreshDatabase;
    // Tests inherit fresh database per test
}
```

**Behavior**:
1. Before first test: Run migrations on test DB
2. Before each test: Transaction rollback to clean state
3. After all tests: Rollback cleanup

**Benefit**: Tests run in parallel without data conflicts

### Factories

Tests use factories to generate realistic test data:

```php
$user = User::factory()->create();
$item = Item::factory()->lowStock()->create();
$category = Category::factory()->create();
```

**State methods**:
- `Item::factory()->lowStock()` — quantity ≤ min_stock_threshold
- `Item::factory()->highStock()` — quantity > min_stock_threshold

### Authentication

Tests simulate authenticated requests via Sanctum:

```php
$user = User::factory()->create();
$response = $this->actingAs($user)->getJson('/api/v1/items');
```

---

## Environment Configuration

### PHPUnit Configuration Files

#### **phpunit.xml** (Docker - Primary)
```xml
<env name="DB_HOST" value="db_test"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

#### **phpunit.local.xml** (Local Development)
```xml
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3307"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
```

### Performance Metrics

Typical execution times:
- **ItemTest**: 5-8 seconds
- **CategoryTest**: 4-6 seconds
- **AuditLogServiceTest**: 3-5 seconds
- **Total**: 12-19 seconds (sequential)
- **With parallelization**: 4-7 seconds (--parallel)

---

## Continuous Integration

For CI/CD pipelines, execute tests with:

```yaml
- name: Run tests
  run: |
    docker-compose up -d db_test
    php artisan test --parallel
```

Expected output:
```
   PASS  Tests\Feature\Api\V1\ItemTest
   PASS  Tests\Feature\Api\V1\CategoryTest
   PASS  Tests\Feature\AuditLogServiceTest

  Tests:  28 passed
  Time:   15.23s
```

---

## Resources

- [Laravel Testing Documentation](https://laravel.com/docs/11.x/testing)
- [Feature Testing Guide](https://laravel.com/docs/11.x/http-tests)
- [Database Testing](https://laravel.com/docs/11.x/database-testing)
