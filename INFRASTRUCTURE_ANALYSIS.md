# Infrastructure Analysis — Valsoft Inventory Backend

## Overview

This document explains the system architecture, containerization strategy, and key technology decisions behind the Valsoft Inventory Management System.

---

## Docker Architecture

The application runs in a **multi-container environment** using Docker Compose with the following services:

### Service Topology

```
┌─────────────────────────────────────────────────────┐
│ Client (REST API Consumer)                          │
└──────────────────────┬──────────────────────────────┘
                       │ HTTP:8000
                       ▼
┌─────────────────────────────────────────────────────┐
│ valsoft_app (PHP 8.3 + Laravel 11)                  │
│ - FPM Service                                       │
│ - Routes requests to services                       │
│ - Connects to db, db_test, redis                    │
└──────────────┬──────────────────────────┬───────────┘
               │                          │
      ┌────────▼─────────┐      ┌────────▼──────────┐
      │ valsoft_db       │      │ valsoft_redis     │
      │ (MySQL 8.0)      │      │ (Redis 7)         │
      │ Port: 3306       │      │ Port: 6379        │
      │ Dev Database     │      │ Cache/Sessions    │
      └──────────────────┘      └───────────────────┘
               │
      ┌────────▼─────────┐
      │ valsoft_db_test  │
      │ (MySQL 8.0)      │
      │ Port: 3307       │
      │ Test Database    │
      └──────────────────┘
```

### Service Details

#### **valsoft_app**
- **Image**: `php:8.3-fpm`
- **Purpose**: Application runtime
- **Mount Points**: Current directory mounted as `/var/www/html`
- **Dependencies**: Waits for `db` service health check
- **Network**: Connected to `valsoft_network`

#### **valsoft_db** (Development)
- **Image**: `mysql:8.0`
- **Purpose**: Primary application database
- **Port**: 3306 (internal), mapped to host if needed
- **Database**: `valsoft_inventory`
- **Credentials**: user `valsoft` / password `secret`
- **Health Check**: Enabled (command: `mysqladmin ping`)

#### **valsoft_db_test** (Testing)
- **Image**: `mysql:8.0`
- **Purpose**: Isolated test database
- **Port**: 3306 (internal), 3307 (external, for local test connections)
- **Database**: `valsoft_inventory_test`
- **Credentials**: user `test_user` / password `test_secret`
- **Health Check**: Enabled
- **Note**: Separate instance prevents test data pollution of development database

#### **valsoft_redis**
- **Image**: `redis:7-alpine`
- **Purpose**: Caching and session storage
- **Port**: 6379
- **Persistence**: Not configured (data lost on restart)

---

## Why Redis for Cache & Sessions?

### Performance Benefits

1. **Sub-millisecond latency**: Redis operations complete in microseconds vs. milliseconds for disk I/O
2. **In-memory storage**: Eliminates disk I/O overhead for frequently accessed data
3. **Atomic operations**: Built-in support for counters, sets, and sorted sets
4. **Expiration handling**: Keys automatically expire without application intervention

### Use Cases in This System

| Use Case | Configuration | Reason |
|----------|---|---|
| **Query Cache** | `CACHE_DRIVER=redis` | Cache complex queries (item listings, category hierarchies) |
| **Session Storage** | `SESSION_DRIVER=redis` | Stateless API with Sanctum tokens; Redis holds session data |
| **Queue Backend** | `QUEUE_CONNECTION=redis` | Future job queueing (low-stock notifications) |

### Architecture Decision

Choosing Redis over file-based caching (`CACHE_DRIVER=file`) provides:
- **Scalability**: File storage doesn't scale across multiple application instances
- **Concurrency**: Redis handles simultaneous requests without file lock contention
- **Consistency**: Guaranteed atomic operations prevent race conditions
- **Memory efficiency**: Automatic eviction policies (LRU) manage memory automatically

---

## AI Integration: Migration from Gemini to Groq

### Previous Architecture (Gemini)

The system originally integrated **Google Gemini 1.5 Flash** via the `google-gemini-php/client` SDK:

```php
$client = Gemini::client($apiKey);
$response = $client->geminiFlash()->generateContent($prompt);
```

**Issues encountered:**
- API version incompatibility: `models/gemini-1.5-flash` deprecated on v1beta endpoint
- Latency: ~2-3 seconds per prediction request (blocking operations)
- Cost: Higher per-request pricing for production scale
- Deprecated method: `geminiFlash()` ignored model configuration

### New Architecture (Groq)

Migration to **Groq's Llama 3.3 70B** via OpenAI-compatible SDK:

```php
use OpenAI\Laravel\Facades\OpenAI;

$response = OpenAI::chat()->create([
    'model'    => 'llama-3.3-70b-versatile',
    'messages' => [
        ['role' => 'system', 'content' => '...'],
        ['role' => 'user', 'content' => $prompt],
    ],
]);
```

### Why Groq?

| Factor | Gemini | Groq | Winner |
|--------|--------|------|--------|
| **Latency** | 2-3 sec | 300-500 ms | ✅ Groq |
| **Model Quality** | Gemini 1.5 Flash | Llama 3.3 70B | ✅ Comparable |
| **Cost** | $0.075/million tokens | $0.02/million tokens | ✅ Groq (73% cheaper) |
| **API Compatibility** | Proprietary | OpenAI standard | ✅ Groq (easier integration) |
| **Rate Limits** | Conservative | 600 RPM free tier | ✅ Groq |
| **Reliability** | High | High | ✅ Tie |

### Integration Details

#### Endpoint Configuration
```env
OPENAI_API_KEY=gsk_YOUR_KEY_HERE
OPENAI_BASE_URL=https://api.groq.com/openai/v1
OPENAI_MODEL=llama-3.3-70b-versatile
```

#### Service Implementation
Location: `app/Services/AiPredictionService.php`

**Key methods:**
- `predictRestock(Item $item)`: Orchestrates prediction pipeline
- `buildItemHistory(Item $item)`: Extracts quantity trends from audit logs
- `buildPrompt(Item $item, array $history)`: Constructs Spanish-language prompt
- `normalizeResponse(array $parsed)`: Validates and structures API response

**Prompt structure:**
```
System: Eres un sistema de análisis de inventario. Responde siempre con JSON puro...
User: Basado en este historial de inventario: [history], el stock actual es [qty]...
```

#### Response Format
The service returns a standardized JSON structure:
```json
{
  "prediction_days": 14,
  "confidence": 0.87,
  "recommendation": "Reorder 500 units within 7 days"
}
```

#### Error Handling
- **JSON parsing failure**: Logs raw response, returns `{'error': 'Could not parse AI response'}`
- **API unavailability**: Catches all exceptions, returns `{'error': 'AI service unavailable'}`
- **Missing API key**: Returns mock response with guidance message

---

## Database Schema Architecture

### Separation of Concerns

```
Development Database        Test Database           Audit Storage
valsoft_inventory          valsoft_inventory_test   Same schema
├── users                  ├── users                ├── audit_logs
├── categories             ├── categories           │   └── JSON changes
├── items                  ├── items
├── audit_logs             ├── audit_logs
└── (production data)       └── (test data only)
```

### Key Design Patterns

#### Audit Trail via JSON
The `audit_logs` table uses JSON columns to store field-level changes:

```json
{
  "quantity": {
    "old": 100,
    "new": 75
  },
  "price": {
    "old": 49.99,
    "new": 54.99
  }
}
```

This design:
- Captures complete change history without schema evolution
- Enables AI service to reconstruct item trends
- Supports compliance/auditing requirements

#### Automatic Status Calculation
The `ItemObserver` model hook automatically updates item status when quantity changes:

```
quantity > min_stock_threshold → status = 'in stock'
quantity ≤ min_stock_threshold → status = 'low stock'
```

This ensures status consistency without manual intervention.

---

## Development vs. Testing Environment

### Key Differences

| Aspect | Development | Testing |
|--------|---|---|
| **Database** | `db` service | `db_test` service (isolated) |
| **Config** | `phpunit.xml` with db_test | `phpunit.local.xml` with localhost:3307 |
| **Data Reset** | Manual or via seeders | Automatic via RefreshDatabase trait |
| **Isolation** | Shared state possible | Complete isolation per test |
| **External Calls** | Real API calls (Groq) | Mocked or skipped |

### Test Execution Flow

```
Run: php artisan test
  │
  ├─> Load phpunit.xml config
  ├─> Connect to db_test:3306
  ├─> Refresh database (drop/create/seed)
  ├─> Execute 28 feature tests
  │   ├─> ItemTest (11 tests)
  │   ├─> CategoryTest (10 tests)
  │   └─> AuditLogServiceTest (7 tests)
  ├─> Rollback/reset state
  └─> Report results
```

---

## Security Considerations

### API Authentication
- Uses **Laravel Sanctum** for token-based API authentication
- No session cookies; stateless token validation
- Tokens can be revoked per user

### Environment Variables
- Sensitive keys (API keys, DB passwords) stored in `.env` only
- `.env` excluded from version control via `.gitignore`
- `.env.example` provides template without secrets

### Database Access
- Dev and test databases use weak credentials (`secret`, `test_secret`) suitable for local development only
- Production should use strong passwords and network isolation
- Database connections internal to Docker network; no external exposure by default

---

## Deployment Recommendations

### For Production

1. **Use managed databases** instead of containerized MySQL
2. **Enable Redis persistence** (RDB/AOF) for cache durability
3. **Implement API rate limiting** on Groq calls
4. **Use environment-specific configs** (separate .env files)
5. **Enable HTTPS** with proper SSL certificates
6. **Implement health check endpoints** for load balancer integration
7. **Use secrets management** (AWS Secrets Manager, HashiCorp Vault)
8. **Enable database backups** and point-in-time recovery

### Scaling Considerations

- **Stateless app containers**: Multiple instances can run behind a load balancer
- **Shared Redis**: All app instances connect to same Redis cluster
- **Database replication**: Set up primary-replica for read scaling
- **Cache invalidation**: Implement proper TTLs and cache busting strategies

---

## Technology Rationale Summary

| Technology | Purpose | Alternative Considered | Why Chosen |
|---|---|---|---|
| **Laravel 11** | Framework | Symfony, Slim | Rapid development, rich ecosystem, strong conventions |
| **PHP 8.3** | Language | Python, Node.js | Established standard for Laravel, type safety |
| **MySQL 8.0** | Database | PostgreSQL, MongoDB | Industry standard, JSON support, relational data model |
| **Redis 7** | Cache/Sessions | Memcached, File | Superior performance, atomic operations, built-in expiry |
| **Groq/Llama 3.3** | AI Model | OpenAI GPT-4, Gemini | Fast inference (300-500ms), lower cost, reliable |
| **Laravel Sanctum** | API Auth | OAuth 2.0, JWT | Simple token management, Laravel integration, sufficient security |
| **Docker** | Containerization | Vagrant, local PHP | Reproducible environments, production parity, easy scaling |
