# Valsoft Inventory Management System — Backend

REST API built with **Laravel 13** + **PHP 8.3** for the Valsoft Agentic Coding Challenge.  
Handles inventory management, role-based access control, AI-powered features, and real-time stock alerts.

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Database | MySQL 8.0 |
| Cache / Queues | Redis 7 |
| Authentication | Laravel Sanctum + Google SSO (Socialite) |
| Roles & Permissions | Spatie Laravel Permission |
| Audit Trail | owen-it/laravel-auditing |
| Import / Export | Maatwebsite Excel |
| Containerization | Docker + Docker Compose |
| CI/CD | GitHub Actions |

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

OPENAI_API_KEY=your_openai_key_here
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
```

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

## Demo credentials

After running seeders, these accounts are available:

| Role | Email | Password |
|---|---|---|
| Admin | admin@demo.com | password |
| Manager | manager@demo.com | password |
| Viewer | viewer@demo.com | password |

---

## API overview

Base URL: `http://localhost:8000/api/v1`

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| POST | `/auth/login` | Login, returns token | No |
| POST | `/auth/register` | Register new user | No |
| GET | `/auth/me` | Current user info | Yes |
| GET | `/items` | List items (search + filters) | Yes |
| POST | `/items` | Create item | Admin, Manager |
| PUT | `/items/{id}` | Update item | Admin, Manager |
| DELETE | `/items/{id}` | Delete item | Admin |
| GET | `/items/{id}/predict` | AI restock prediction | Yes |
| POST | `/items/suggest-category` | AI category suggestion | Yes |
| GET | `/categories` | List categories | Yes |
| GET | `/audit-logs` | Audit trail | Admin |
| GET | `/stats` | Dashboard metrics | Yes |
| POST | `/items/import` | CSV bulk import | Admin, Manager |
| GET | `/items/export` | CSV export | Yes |

---

## User roles

| Permission | Admin | Manager | Viewer |
|---|---|---|---|
| View inventory | ✓ | ✓ | ✓ |
| Create / Edit items | ✓ | ✓ | ✗ |
| Delete items | ✓ | ✗ | ✗ |
| Manage users | ✓ | ✗ | ✗ |
| View audit log | ✓ | ✗ | ✗ |
| Import / Export CSV | ✓ | ✓ | ✗ |

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

## Live demo

> URL: https://your-deploy-url.railway.app  
> Admin: admin@demo.com / password

---

## License

MIT