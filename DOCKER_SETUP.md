# Docker Setup & Infrastructure

Documentación completa de la infraestructura Docker para desarrollo y testing de Valsoft Inventory.

## 🏗️ Arquitectura

### Servicios Incluidos

```
valsoft_network (bridge)
├── app (php:8.3-fpm)
│   ├── Puerto: 8000
│   ├── Volumen: .:/var/www/html
│   └── Depende de: db
├── db (mysql:8.0) - DESARROLLO
│   ├── Contenedor: valsoft_db
│   ├── Puerto: 3306
│   ├── Usuario: valsoft / secret
│   ├── Base de datos: valsoft_inventory
│   └── Volumen: valsoft_db_data:/var/lib/mysql
├── db_test (mysql:8.0) - TESTING ⭐ NUEVO
│   ├── Contenedor: valsoft_db_test
│   ├── Puerto: 3307 (mapeado desde 3306)
│   ├── Usuario: test_user / test_secret
│   ├── Base de datos: valsoft_inventory_test
│   ├── Volumen: valsoft_db_test_data:/var/lib/mysql
│   └── Healthcheck: Habilitado
└── redis (redis:7-alpine) - CACHE/QUEUE
    ├── Contenedor: valsoft_redis
    ├── Puerto: 6379
    └── Volumen: Sin persistencia
```

## 🚀 Quick Start

### 1. Primer Uso

```bash
# Clonar repositorio
git clone <repo-url>
cd vim-backend

# Instalar dependencias
composer install

# Crear archivo .env
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Iniciar servicios Docker
docker-compose up -d

# Ejecutar migraciones (dentro del contenedor)
docker-compose exec app php artisan migrate:fresh --seed

# Acceder a la app
# Browser: http://localhost:8000
```

### 2. Ejecutar Tests

```bash
# Opción A: Usar script helper (recomendado)
bash run-tests.sh docker

# Opción B: Comandos directos
docker-compose up -d db_test
php artisan test
```

## 📋 Comandos Útiles

### Gestión de Servicios

```bash
# Iniciar todos los servicios
docker-compose up -d

# Iniciar servicio específico
docker-compose up -d db_test

# Parar todos los servicios
docker-compose down

# Parar y eliminar volúmenes
docker-compose down -v

# Ver logs de un servicio
docker-compose logs -f app
docker-compose logs -f db
docker-compose logs -f db_test
```

### Dentro del Contenedor App

```bash
# Ejecutar artisan
docker-compose exec app php artisan <comando>

# Acceder a bash
docker-compose exec app bash

# Ejecutar tests
docker-compose exec app php artisan test

# Ver estado
docker-compose exec app php artisan db:show
```

### Acceso a Bases de Datos

```bash
# MySQL Desarrollo (desde host)
mysql -h 127.0.0.1 -u valsoft -p -D valsoft_inventory

# MySQL Testing (desde host)
mysql -h 127.0.0.1 -u test_user -p -D valsoft_inventory_test

# MySQL Desarrollo (desde contenedor)
docker-compose exec db mysql -u root -p -D valsoft_inventory

# PhpMyAdmin (si necesitas UI)
docker-compose exec db mysql -h db -u root -p
```

## 🔧 Configuración

### Variables de Entorno (.env)

```env
# Aplicación
APP_NAME="Valsoft Inventory"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de datos (desarrollo)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1           # Localhost cuando se ejecuta fuera de Docker
DB_PORT=3306
DB_DATABASE=valsoft_inventory
DB_USERNAME=root
DB_PASSWORD=

# Puertos externos
APP_PORT=8000
DB_EXTERNAL_PORT=3306
REDIS_EXTERNAL_PORT=6379

# Otros servicios...
```

### phpunit.xml (Testing en Docker)

```xml
<env name="DB_HOST" value="db_test"/>           <!-- Servicio Docker -->
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

### phpunit.local.xml (Testing Local)

```xml
<env name="DB_HOST" value="127.0.0.1"/>         <!-- Localhost -->
<env name="DB_PORT" value="3307"/>              <!-- Puerto mapeado de db_test -->
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

## 🧪 Testing

### Tres Formas de Ejecutar Tests

#### Opción 1: Con Script Helper (Recomendado)
```bash
bash run-tests.sh docker                    # Docker
bash run-tests.sh local                     # Local
bash run-tests.sh container                 # Dentro del contenedor
```

#### Opción 2: Directamente
```bash
docker-compose up -d db_test
php artisan test                            # Todos los tests
php artisan test tests/Feature              # Solo Feature
php artisan test --parallel                 # Tests paralelos
```

#### Opción 3: Dentro del Contenedor
```bash
docker-compose up -d
docker-compose exec app php artisan test
```

## 🔍 Verificación de Estado

```bash
# Ver servicios corriendo
docker-compose ps

# Ver redes
docker network ls
docker network inspect valsoft_network

# Ver volúmenes
docker volume ls
docker volume inspect vim-backend_valsoft_db_data
docker volume inspect vim-backend_valsoft_db_test_data

# Verificar conectividad
docker-compose exec app ping db              # ✓ debe funcionar
docker-compose exec app ping db_test         # ✓ debe funcionar
docker-compose exec app ping redis           # ✓ debe funcionar
```

## 🐛 Solución de Problemas

### Los servicios no inician
```bash
# Ver logs detallados
docker-compose logs db
docker-compose logs db_test

# Reiniciar servicios
docker-compose down -v
docker-compose up -d
```

### No puedo conectar a la BD
```bash
# Verificar que db_test está corriendo
docker ps | grep valsoft_db

# Verificar puertos mapeados
docker port valsoft_db_test

# Probar conectividad
nc -zv 127.0.0.1 3307
```

### Tests fallan con "Connection refused"
```bash
# Asegurar que db_test está listo
docker-compose up -d db_test
sleep 10  # Esperar a que esté completamente inicializado

# O usar el script helper (maneja esto automáticamente)
bash run-tests.sh docker
```

### Espacio en disco lleno
```bash
# Limpiar volúmenes sin usar
docker volume prune

# O eliminación selectiva
docker-compose down -v
```

## 📚 Recursos

- [Docker Documentation](https://docs.docker.com)
- [Docker Compose Documentation](https://docs.docker.com/compose)
- [Laravel Docker Guide](https://laravel.com/docs/deployment#docker)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

## ✅ Checklist de Setup

- [ ] Docker y Docker Compose instalados
- [ ] Clonar repositorio
- [ ] `composer install`
- [ ] Copiar `.env.example` a `.env`
- [ ] `php artisan key:generate`
- [ ] `docker-compose up -d`
- [ ] `docker-compose exec app php artisan migrate:fresh --seed`
- [ ] Verificar http://localhost:8000
- [ ] Ejecutar tests: `bash run-tests.sh docker`
- [ ] ✅ Listo para desarrollar!
