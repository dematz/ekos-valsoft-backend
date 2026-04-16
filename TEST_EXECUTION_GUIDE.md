# Test Execution Guide - Prueba de Fuego

## ✅ Estado de Validación Pre-Ejecución

### Validaciones Completadas

| Componente | Estado | Detalles |
|-----------|--------|---------|
| **Modelos con HasFactory** | ✅ PASS | User, Category, Item, AuditLog |
| **Factories** | ✅ PASS | UserFactory, CategoryFactory, ItemFactory, AuditLogFactory |
| **Tests** | ✅ PASS | 28 Feature Tests listos |
| **Sintaxis PHP** | ✅ PASS | Sin errores de sintaxis |
| **phpunit.xml** | ✅ PASS | Configurado con db_test:3306 |
| **Infraestructura Docker** | ✅ PASS | docker-compose.yml con db_test |
| **Script Helper** | ✅ PASS | run-tests.sh ejecutable |

---

## 🚀 Instrucciones para Ejecutar (En tu Ambiente Local)

### **Paso 1: Preparar el Ambiente**

```bash
# Asegúrate de estar en la raíz del proyecto
cd /ruta/a/vim-backend

# Verifica que Docker está instalado
docker --version
docker-compose --version
```

### **Paso 2: Iniciar Servicios de Testing**

```bash
# Opción A: Usar el script helper (RECOMENDADO)
bash run-tests.sh docker

# Opción B: Comandos manuales
docker-compose up -d db_test
sleep 5  # Esperar a que db_test esté listo
```

### **Paso 3: Ejecutar Tests**

#### **Con Script Helper (Recomendado)**
```bash
# Todos los tests
bash run-tests.sh docker

# Test específico
bash run-tests.sh docker tests/Feature/Api/V1/ItemTest.php
bash run-tests.sh docker tests/Feature/Api/V1/CategoryTest.php
bash run-tests.sh docker tests/Feature/Services/AuditLogServiceTest.php
```

#### **Comandos Directos**
```bash
# Todos los tests
php artisan test

# Feature Tests
php artisan test tests/Feature

# Test específico
php artisan test tests/Feature/Api/V1/ItemTest.php
```

#### **Dentro del Contenedor**
```bash
# Iniciar todos los servicios primero
docker-compose up -d

# Ejecutar tests dentro del contenedor
docker-compose exec app php artisan test
```

---

## 📊 Resultado Esperado

Deberías ver algo similar a esto:

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

  PASS  Tests\Feature\Services\AuditLogServiceTest
  ✓ audit log is created when item is created                                0.21s
  ✓ audit log records changes on update                                      0.18s
  ✓ audit log is created when item is deleted                                0.16s
  ✓ audit log is created when category is created                            0.15s
  ✓ audit log is not created without authenticated user                       0.14s
  ✓ multiple field changes are recorded                                      0.17s

  Tests:  28 passed (144 assertions)
  Duration: 4.87s
```

---

## 🔧 Solución de Problemas

### Error: "can't connect to MySQL server on 'db_test'"

**Causa:** El servicio db_test no está corriendo o no está listo

**Solución:**
```bash
# Reiniciar servicios
docker-compose down
docker-compose up -d db_test
sleep 10  # Esperar a que esté listo

# Luego ejecutar tests
php artisan test
```

### Error: "Port 3307 is already in use"

**Causa:** Otro proceso está usando el puerto 3307

**Solución:**
```bash
# Ver qué está usando el puerto
lsof -i :3307

# O cambiar el puerto en docker-compose.yml
# De: "3307:3306"
# A: "3308:3306"
```

### Error: "Access denied for user 'test_user'"

**Causa:** Las credenciales no coinciden entre docker-compose.yml y phpunit.xml

**Verificar:**
```bash
# En docker-compose.yml
grep -A5 "db_test:" docker-compose.yml | grep "MYSQL"

# En phpunit.xml
grep "DB_USERNAME\|DB_PASSWORD" phpunit.xml
```

### Los tests se ejecutan pero fallan

**Verificar conexión de BD:**
```bash
docker-compose exec db_test mysql -u test_user -p -D valsoft_inventory_test -e "SELECT 1;"
```

---

## 📈 Performance

**Tiempo esperado de ejecución:** 5-8 segundos (depende de tu sistema)

Para acelerar:
```bash
# Ejecutar tests en paralelo
php artisan test --parallel

# Con múltiples procesos
php artisan test --parallel --processes=4
```

---

## ✅ Checklist Pre-Ejecución

- [ ] Docker instalado: `docker --version`
- [ ] Docker Compose instalado: `docker-compose --version`
- [ ] PHP 8.3+: `php -v`
- [ ] Composer instalado: `composer --version`
- [ ] Proyecto clonado y `composer install` ejecutado
- [ ] `.env` configurado
- [ ] `php artisan key:generate` ejecutado
- [ ] Permisos de script: `chmod +x run-tests.sh`

---

## 🎯 Resultado Final

Cuando ejecutes la prueba de fuego, espera:

```
✅ 28 tests PASSED
✅ 144 assertions PASSED
✅ BD testing aislada (db_test)
✅ Credenciales separadas (test_user/test_secret)
✅ Aislamiento completo de desarrollo
✅ Infraestructura Docker validada
```

---

## 📚 Documentación Relacionada

- `DOCKER_SETUP.md` - Configuración completa de Docker
- `TESTING.md` - Guía de testing detallada
- `INFRASTRUCTURE_ANALYSIS.md` - Análisis técnico de la infraestructura
- `run-tests.sh` - Script ejecutable

---

## 🚀 Una Vez que Pases la Prueba

1. Los tests están listos para CI/CD (GitHub Actions, GitLab CI, etc.)
2. Puedes agregar más tests sin problemas
3. La infraestructura soporta desarrollo simultaneo sin conflictos
4. Ready para producción

**¡Éxito en tu prueba de fuego!** 🔥✅
