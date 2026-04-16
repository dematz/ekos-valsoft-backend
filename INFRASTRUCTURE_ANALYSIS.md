# Análisis de Infraestructura Docker y Configuración de Testing

## 📊 Estado Actual

### **1. Docker Compose - Servicios Disponibles**

| Servicio | Imagen | Propósito | Estado |
|----------|--------|----------|--------|
| **app** | php:8.3-fpm | Aplicación Laravel | ✅ Configurado |
| **db** | mysql:8.0 | BD Producción/Desarrollo | ✅ Configurado |
| **redis** | redis:7-alpine | Cache/Queue | ✅ Configurado |
| **db_test** | ❌ NO EXISTE | BD Testing | ⚠️ FALTA |

### **2. Configuración de Base de Datos - Comparativa**

#### **.env (Desarrollo - Local, fuera de Docker)**
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1          ← Localhost (fuera de Docker)
DB_PORT=3306
DB_DATABASE=valsoft_inventory
DB_USERNAME=root
DB_PASSWORD=<vacío>
```

#### **docker-compose.yml (App en Docker)**
```yaml
db_host: db                ← Nombre del servicio (dentro de Docker network)
db_port: 3306
db_database: valsoft_inventory
db_username: valsoft
db_password: secret
```

#### **phpunit.xml (Testing - Local, fuera de Docker)**
```xml
<env name="DB_HOST" value="127.0.0.1"/>      ← Localhost (fuera de Docker)
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
```

### **3. Matriz de Inconsistencias**

| Parámetro | .env | docker-compose | phpunit.xml | ¿Coinciden? |
|-----------|------|-----------------|-------------|------------|
| **DB_HOST** | 127.0.0.1 | db | 127.0.0.1 | ❌ NO |
| **DB_PORT** | 3306 | 3306 | 3306 | ✅ SÍ |
| **DB_USERNAME** | root | valsoft | root | ❌ NO |
| **DB_PASSWORD** | (vacío) | secret | (vacío) | ❌ NO |
| **Ambiente** | local | dockerizado | testing | ❌ NO SINCRONIZADO |

### **4. Ubicación de Base de Datos Actualmente**

```
┌─────────────────────────────────────────────────────────┐
│ .env (Desarrollo Local)                                  │
│ DB_HOST=127.0.0.1:3306 → MySQL local del OS             │
│ (Fuera de Docker)                                        │
└─────────────────────────────────────────────────────────┘

┌──────────────────────────────────┐
│ docker-compose.yml (Producción)  │
│                                   │
│ ┌──────────────────────────────┐ │
│ │ Service: app                 │ │
│ │ Conecta a: db:3306          │ │
│ │ (Dentro de Docker network)   │ │
│ └──────────────────────────────┘ │
│                                   │
│ ┌──────────────────────────────┐ │
│ │ Service: db (MySQL 8.0)      │ │
│ │ Puerto: 127.0.0.1:3306      │ │
│ │ (Mapeado al host)            │ │
│ └──────────────────────────────┘ │
└──────────────────────────────────┘

┌──────────────────────────────────┐
│ phpunit.xml (Testing)            │
│ DB_HOST=127.0.0.1:3306          │
│ (Espera MySQL local del OS)      │
│ (Mismo que .env)                 │
└──────────────────────────────────┘
```

---

## ⚠️ Problemas Identificados

### **Problema 1: No existe servicio mysql_test en Docker**
- ❌ Docker-compose solo tiene un servicio `db` para desarrollo/producción
- ❌ Los tests usan una BD MySQL local fuera de Docker
- ❌ Esto crea inconsistencia entre entornos

### **Problema 2: Configuración de testing no sincronizada con Docker**
- El archivo `phpunit.xml` espera MySQL en `127.0.0.1:3306`
- Pero cuando corres la app con `docker-compose up`, está en la red Docker (`db:3306`)
- Esto causa que los tests no puedan conectarse si se ejecutan dentro del contenedor

### **Problema 3: Credenciales inconsistentes**
- **Desarrollo** (.env): root / (sin password)
- **Docker** (compose): valsoft / secret
- **Testing** (phpunit.xml): root / (sin password)

### **Problema 4: BD de testing aislada de desarrollo**
- El .env y phpunit.xml usan la misma BD `valsoft_inventory` (diferentes nombres pero mismo servidor)
- Esto está parcialmente mitigado pero no es ideal

---

## ✅ Solución Recomendada

### **Opción A: Agregar servicio mysql_test a docker-compose.yml (RECOMENDADO)**

Crear un servicio `db_test` completamente separado para testing:

```yaml
db_test:
  image: mysql:8.0
  container_name: valsoft_db_test
  restart: unless-stopped
  ports:
    - "3307:3306"                    # Puerto diferente
  environment:
    MYSQL_DATABASE: "valsoft_inventory_test"
    MYSQL_ROOT_PASSWORD: "test_secret"
    MYSQL_USER: "test_user"
    MYSQL_PASSWORD: "test_secret"
  volumes:
    - valsoft_db_test_data:/var/lib/mysql
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    interval: 5s
    timeout: 5s
    retries: 10
  networks:
    - valsoft_network
```

### **Actualizar phpunit.xml para usar el nuevo servicio:**

```xml
<env name="DB_HOST" value="db_test"/>      <!-- Usa nombre del servicio Docker -->
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="valsoft_inventory_test"/>
<env name="DB_USERNAME" value="test_user"/>
<env name="DB_PASSWORD" value="test_secret"/>
```

### **Ventajas:**
- ✅ BD de testing completamente aislada
- ✅ Funciona dentro y fuera de Docker
- ✅ Coherente con la arquitectura de desarrollo
- ✅ Fácil de escalar y mantener
- ✅ Entorno de testing idéntico al de desarrollo

---

## 🔄 Alternativa B: Usar SQLite en Testing (Más ligero, menos realista)

Si prefieres tests más rápidos sin dependencia de MySQL:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value="storage/testing.sqlite"/>
```

**Pros:** Más rápido, sin servidor externo
**Contras:** SQLite ≠ MySQL, comportamiento diferente

---

## 📋 Pasos para Implementar Opción A

1. **Actualizar docker-compose.yml** (agregar servicio db_test)
2. **Actualizar phpunit.xml** (cambiar credenciales)
3. **Ejecutar tests:**
   ```bash
   docker-compose up -d db_test
   php artisan test
   ```

---

## 🎯 Resumen Ejecutivo

| Aspecto | Estado | Acción |
|--------|--------|--------|
| **BD de desarrollo** | ✅ Configurada | Mantener |
| **BD de testing** | ⚠️ Usa localhost | **→ Agregar servicio docker** |
| **Sincronización** | ❌ Inconsistente | **→ Unificar credenciales** |
| **Aislamiento** | ⚠️ Parcial | **→ Mejorar con db_test** |
| **Compatibilidad Docker** | ❌ Débil | **→ Fortalecer** |

**Recomendación:** Implementar Opción A para mantener coherencia entre entornos de desarrollo y testing dentro de la arquitectura Docker.
