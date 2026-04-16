# Testing Guide - Valsoft Inventory

Este documento explica cómo ejecutar los Feature Tests del proyecto.

## Requisitos Previos

### 1. Base de Datos MySQL para Testing (Dockerizada)
Los tests usan un servicio MySQL separado en Docker (`db_test`) para no afectar la BD de desarrollo.

#### Opción A: Ejecutar con Docker (RECOMENDADO)
```bash
# Iniciar el servicio db_test
docker-compose up -d db_test

# Esperar a que esté listo (~5 segundos)
sleep 5

# Ejecutar tests
php artisan test
```

#### Opción B: Ejecutar Localmente (sin Docker)
```bash
# 1. Asegúrate de que db_test está corriendo en Docker
docker-compose up -d db_test

# 2. Ejecutar tests contra db_test (puerto 3307)
bash run-tests.sh local
```

#### Opción C: Ejecutar Dentro del Contenedor App
```bash
# 1. Iniciar todos los servicios
docker-compose up -d

# 2. Ejecutar tests dentro del contenedor
docker-compose exec app php artisan test
```

### 2. Configuración de Testing
El proyecto incluye dos configuraciones PHPUnit:

#### **phpunit.xml** (Para Docker/Contenedor)
```xml
<env name="DB_HOST" value="db_test"/>        ← Servicio Docker
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

#### **phpunit.local.xml** (Para desarrollo local)
```xml
<env name="DB_HOST" value="127.0.0.1"/>      ← Localhost
<env name="DB_PORT" value="3307"/>           ← Puerto mapeado de db_test
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

## Ejecutar los Tests

### ⚡ Quick Start (Recomendado)

```bash
# 1. Iniciar servicio de testing
docker-compose up -d db_test

# 2. Ejecutar todos los tests
bash run-tests.sh docker
```

### 📍 Opción 1: Mediante Script Helper (Recomendado)

```bash
# Ejecutar con configuración Docker (phpunit.xml)
bash run-tests.sh docker

# Ejecutar con configuración local (phpunit.local.xml - puerto 3307)
bash run-tests.sh local

# Ejecutar dentro del contenedor app
bash run-tests.sh container

# Ejecutar test específico
bash run-tests.sh docker tests/Feature/Api/V1/ItemTest.php
bash run-tests.sh local tests/Feature/Api/V1/CategoryTest.php
```

### 📍 Opción 2: Comandos Directos

#### Usando Docker (db_test en red Docker)
```bash
docker-compose up -d db_test
php artisan test                                    # Todos los tests
php artisan test tests/Feature                     # Solo Feature tests
php artisan test tests/Feature/Api/V1/ItemTest.php # Test específico
```

#### Usando Local (db_test puerto 3307)
```bash
docker-compose up -d db_test
php -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.local.xml
php -d memory_limit=-1 vendor/bin/phpunit --configuration phpunit.local.xml tests/Feature/Api/V1/ItemTest.php
```

#### Dentro del Contenedor App
```bash
docker-compose up -d
docker-compose exec app php artisan test
docker-compose exec app php artisan test tests/Feature/Api/V1/ItemTest.php
```

### 📊 Opciones Avanzadas

```bash
# Con salida detallada
php artisan test --verbose

# Con coverage (requiere Xdebug)
php artisan test --coverage

# Solo Feature Tests
php artisan test tests/Feature

# Tests paralelos
php artisan test --parallel

# Tests en paralelo con múltiples procesos
php artisan test --parallel --processes=4
```

## Tests Incluidos

### ItemTest (11 tests)
- ✅ `test_can_list_items` — Listar items con paginación
- ✅ `test_can_create_item` — Crear item
- ✅ `test_low_stock_status_is_set_automatically` — **CRÍTICO**: Status automático
- ✅ `test_updating_quantity_updates_status` — **CRÍTICO**: Status recalculado en update
- ✅ `test_can_show_item` — Ver item específico
- ✅ `test_can_update_item` — Actualizar item
- ✅ `test_can_delete_item` — Eliminar item
- ✅ `test_validation_fails_with_duplicate_sku` — Validación de SKU único
- ✅ `test_validation_fails_with_invalid_category_id` — Validación de FK
- ✅ `test_validation_fails_with_negative_quantity` — Validación de rango
- ✅ `test_requires_authentication` — Protección auth:sanctum

### CategoryTest (10 tests)
- ✅ `test_can_list_categories` — Listar categorías
- ✅ `test_can_create_category` — Crear categoría
- ✅ `test_cannot_create_category_with_duplicate_name` — Nombres únicos en create
- ✅ `test_can_show_category` — Ver categoría
- ✅ `test_can_update_category` — Actualizar categoría
- ✅ `test_cannot_update_category_with_duplicate_name` — Nombres únicos en update
- ✅ `test_can_update_category_with_same_name` — Permite su propio nombre
- ✅ `test_can_delete_category` — Eliminar categoría
- ✅ `test_validation_fails_with_missing_name` — Campo requerido
- ✅ `test_requires_authentication` — Protección auth:sanctum

### AuditLogServiceTest (7 tests)
- ✅ `test_audit_log_is_created_when_item_is_created` — Auditoría en create
- ✅ `test_audit_log_records_changes_on_update` — Cambios capturados en JSON
- ✅ `test_audit_log_is_created_when_item_is_deleted` — Auditoría en delete
- ✅ `test_audit_log_is_created_when_category_is_created` — Auditoría para Category
- ✅ `test_audit_log_is_not_created_without_authenticated_user` — Protección sin auth
- ✅ `test_multiple_field_changes_are_recorded` — Múltiples cambios registrados

## Solución de Problemas

### Error: "could not find driver (Connection: mysql)"
**Solución:** Instalar extensión MySQL para PHP:
```bash
# Ubuntu/Debian
sudo apt-get install php-mysql

# macOS con Brew
brew install php-mysql

# Verificar instalación
php -m | grep -i mysql
```

### Error: "Access denied for user 'test_user'@'db_test'"
**Significa:** El servicio `db_test` no está corriendo

**Solución:**
```bash
# Iniciar el servicio
docker-compose up -d db_test

# Verificar que está corriendo
docker ps | grep valsoft_db_test

# Ver logs si hay error
docker logs valsoft_db_test
```

### Error: "Connection refused 127.0.0.1:3307"
**Significa:** Estás usando `phpunit.local.xml` pero `db_test` no está mapeado correctamente

**Solución:**
```bash
# Verificar que db_test está corriendo y puerto está mapeado
docker-compose up -d db_test
docker port valsoft_db_test 3306

# Debería mostrar: 0.0.0.0:3307
```

### Error: "Unknown database 'valsoft_inventory_test'"
**Significa:** El servicio `db_test` se inició pero la BD no se creó

**Solución:**
```bash
# Eliminar y reiniciar el servicio
docker-compose down
docker volume rm vim-backend_valsoft_db_test_data
docker-compose up -d db_test

# Esperar a que esté listo
sleep 10
```

### Error: "Can't connect to MySQL server on 'db_test'"
**Significa:** Estás corriendo los tests dentro del contenedor pero no pasaste la red Docker correctamente

**Solución:**
```bash
# Usar el script helper (maneja automáticamente)
bash run-tests.sh docker

# O asegúrate de que all services are on the same network
docker-compose up -d
docker-compose exec app php artisan test
```

### Los tests tardan mucho
**Optimización:** Usar modo paralelo
```bash
php artisan test --parallel --processes=4
```

### RefreshDatabase borra los datos entre tests
**Esto es intencional.** Cada test comienza con una BD limpia para garantizar aislamiento y evitar efectos secundarios entre tests.

## Características de los Tests

### RefreshDatabase Trait
Todos los tests usan `RefreshDatabase` para:
- Ejecutar migraciones antes de cada test
- Limpiar la BD después de cada test
- Aislar tests entre sí

### Factories
Los tests usan factories para generar datos realistas:
- `User::factory()` — Crea usuario de test
- `Category::factory()` — Crea categoría
- `Item::factory()` — Crea item
  - `Item::factory()->lowStock()` — Item con stock bajo
  - `Item::factory()->highStock()` — Item con stock alto

### Autenticación
Los tests usan `actingAs($user)` para simular usuarios autenticados:
```php
$this->actingAs($user)->getJson('/api/v1/items');
```

## Performance

El tiempo de ejecución total aproximado es:
- **ItemTest**: ~5-8 segundos
- **CategoryTest**: ~4-6 segundos
- **AuditLogServiceTest**: ~3-5 segundos
- **Total**: ~15 segundos

## Integración Continua

Para CI/CD (GitHub Actions, etc.), añade a tu workflow:

```yaml
- name: Run tests
  run: |
    mysql -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS valsoft_inventory_test;"
    php artisan test
```

## Recursos Adicionales

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Feature Tests Guide](https://laravel.com/docs/http-tests)
- [Database Testing](https://laravel.com/docs/database-testing)
