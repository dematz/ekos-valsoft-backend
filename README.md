# Valsoft Inventory Management System — Backend

REST API built with **Laravel 11** + **PHP 8.3** for scalable inventory management.  
Features real-time stock tracking, automatic low-stock detection, AI-powered restock predictions via Groq, and comprehensive audit logging.

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Language | PHP 8.3 |
| Database | MySQL 8.0 |
| Cache / Sessions / Queues | Redis 7 |
| Authentication | Laravel Sanctum |
| AI Integration | OpenAI SDK (Groq backend) — Llama 3.3 70B |
| Audit Trail | Custom LogsActivity trait + AuditLogService |
| Containerization | Docker + Docker Compose |
| Testing | PHPUnit (Feature tests) |

---

## Requirements

### Local (without Docker)
- PHP >= 8.3
- Composer >= 2.x
- MySQL >= 8.0
- Redis >= 7.0
- Extensions: `pdo_mysql`, `mbstring`, `zip`, `bcmath`, `xml`, `dom`

### With Docker (recommended)
- Docker >= 24.x
- Docker Compose >= 2.x

---

## Project structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/    # API controllers (Auth, Item, Category...)
│   │   ├── Requests/           # Form request validation
│   │   └── Resources/          # API resource transformers
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic (InventoryService, AiService...)
│   ├── Repositories/           # Data access layer
│   ├── Observers/              # Model observers (auto status update)
│   ├── Policies/               # Authorization policies
│   └── Jobs/                   # Queue jobs (LowStockNotification...)
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php                 # Versioned API routes /api/v1/
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

---

## Installation — Docker (recommended)

### 1. Clone the repository

```bash
git clone https://github.com/YOUR_USERNAME/valsoft-inventory-backend.git
cd valsoft-inventory-backend
```

### 2. Set up environment variables

```bash
cp .env.example .env
```

Edit `.env` and set your values — the defaults work with Docker out of the box:

```env
APP_NAME="Valsoft Inventory"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=valsoft_inventory
DB_USERNAME=valsoft
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

# AI Features (Groq via OpenAI-compatible API)
OPENAI_API_KEY=gsk_YOUR_GROQ_API_KEY_HERE
OPENAI_BASE_URL=https://api.groq.com/openai/v1
OPENAI_MODEL=llama-3.3-70b-versatile

# Google SSO (optional)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback
```

**Note:** The `OPENAI_API_KEY` requires a Groq account. Get a free key at [console.groq.com](https://console.groq.com).

### 3. Build and start containers

```bash
docker compose up --build -d
```

This starts 3 containers:
- `valsoft_app` — Laravel app on port **8000**
- `valsoft_db` — MySQL on port **3306**
- `valsoft_redis` — Redis on port **6379**

### 4. Generate application key

```bash
docker compose exec app php artisan key:generate
```

### 5. Run migrations and seeders

```bash
docker compose exec app php artisan migrate --seed
```

### 6. Verify the API is running

```bash
curl http://localhost:8000/api/v1/health
```

Expected response:
```json
{ "status": "ok", "version": "1.0.0" }
```

---

## Installation — Local (without Docker)

### 1. Clone and install dependencies

```bash
git clone https://github.com/YOUR_USERNAME/valsoft-inventory-backend.git
cd valsoft-inventory-backend
composer install
```

### 2. Set up environment

```bash
cp .env.example .env
```

Update `.env` with your local MySQL credentials:

```env
DB_HOST=127.0.0.1
DB_DATABASE=valsoft_inventory
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Generate key, migrate and seed

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API available at `http://localhost:8000`.

---

## API overview

Base URL: `http://localhost:8000/api/v1`

All endpoints (except those marked public) require an API token via `Authorization: Bearer <token>` header (Laravel Sanctum).

### Items
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/items` | List items (supports `?name`, `?category_id`, `?status`, `?sku` filters) | Required |
| POST | `/items` | Create item | Required |
| GET | `/items/{id}` | Get item details | Required |
| PUT | `/items/{id}` | Update item | Required |
| DELETE | `/items/{id}` | Delete item | Required |
| POST | `/items/{id}/predict` | AI-powered restock prediction (Groq/Llama-3) | Required |

### Categories
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/categories` | List all categories | Required |
| POST | `/categories` | Create category | Required |
| GET | `/categories/{id}` | Get category details | Required |
| PUT | `/categories/{id}` | Update category | Required |
| DELETE | `/categories/{id}` | Delete category | Required |

---

## Item status

Stock status is updated **automatically** via a model observer when quantity changes:

| Status | Condition |
|---|---|
| `in_stock` | `quantity > min_stock_threshold` |
| `low_stock` | `quantity <= min_stock_threshold` |
| `ordered` | Manually set when reorder is placed |
| `discontinued` | Manually set |

---

## AI-powered restock predictions

The system integrates **Groq's Llama 3.3 70B** model via the OpenAI SDK for intelligent inventory forecasting.

### How it works

When you call `POST /api/v1/items/{id}/predict`, the service:
1. Fetches the item's quantity change history from audit logs
2. Builds a prompt with historical trends and current stock level
3. Sends the data to Groq's API
4. Returns a JSON response with:
   - `prediction_days` — estimated days until stock depletion
   - `confidence` — prediction confidence score (0-1)
   - `recommendation` — actionable restock advice

### Example request

```bash
curl -X POST http://localhost:8000/api/v1/items/1/predict \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Example response

```json
{
  "prediction_days": 14,
  "confidence": 0.87,
  "recommendation": "Reorder 500 units within 7 days to avoid stockout"
}
```

### Configuration

Set these in `.env`:
- `OPENAI_API_KEY` — Your Groq API key
- `OPENAI_BASE_URL` — `https://api.groq.com/openai/v1`
- `OPENAI_MODEL` — `llama-3.3-70b-versatile`

Get a free Groq API key at [console.groq.com](https://console.groq.com).

---

## Running tests

```bash
# With Docker
docker compose exec app php artisan test

# Local
php artisan test
```

---

## Useful Docker commands

```bash
# View running containers
docker compose ps

# Stream app logs
docker compose logs -f app

# Access app container shell
docker compose exec app bash

# Run any artisan command
docker compose exec app php artisan <command>

# Stop all containers
docker compose down

# Stop and remove volumes (resets database)
docker compose down -v
```

---

## License

MIT