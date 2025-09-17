# Guía de Instalación - Search Duplicates Module v1.18

## 📋 Requisitos Previos

### Sistema Operativo
- **Linux**: Ubuntu 18.04+, CentOS 7+, Debian 9+
- **Windows**: Windows Server 2016+
- **macOS**: macOS 10.14+

### Servidor Web
- **Apache**: 2.4+ con mod_rewrite
- **Nginx**: 1.16+ con PHP-FPM
- **IIS**: 10+ con PHP

### Base de Datos
- **MySQL**: 5.7+ o 8.0+
- **MariaDB**: 10.3+ o 10.4+
- **Percona**: 5.7+ o 8.0+

### PHP
- **Versión**: 7.4+ (recomendado 8.0+)
- **Extensiones requeridas**:
  - mysqli
  - pdo_mysql
  - json
  - mbstring
  - curl
  - gd
  - zip
  - xml

### Dolibarr
- **Versión mínima**: 11.0
- **Versión recomendada**: 16.0+
- **Módulos requeridos**: Ninguno
- **Módulos compatibles**: Todos los módulos oficiales

---

## 🚀 Instalación Paso a Paso

### Paso 1: Descargar el Módulo

#### Opción A: Desde la Tienda de Dolibarr
1. Acceder a [store.dolibarr.org](https://store.dolibarr.org)
2. Buscar "Search Duplicates"
3. Comprar la licencia
4. Descargar el archivo ZIP

#### Opción B: Desde el Sitio Web
1. Visitar [searchduplicates.com](https://searchduplicates.com)
2. Crear una cuenta
3. Comprar la licencia
4. Descargar desde el área de cliente

### Paso 2: Preparar el Servidor

#### Verificar Requisitos
```bash
# Verificar versión de PHP
php -v

# Verificar extensiones PHP
php -m | grep -E "(mysqli|pdo_mysql|json|mbstring|curl|gd|zip|xml)"

# Verificar permisos del directorio custom
ls -la /path/to/dolibarr/custom/
```

#### Configurar Permisos
```bash
# Dar permisos de escritura al directorio custom
chmod 755 /path/to/dolibarr/custom/
chown -R www-data:www-data /path/to/dolibarr/custom/
```

### Paso 3: Instalar el Módulo

#### Extraer Archivos
```bash
# Navegar al directorio de Dolibarr
cd /path/to/dolibarr/

# Extraer el módulo
unzip search-duplicates-v1.18.zip

# Verificar estructura
ls -la custom/search_duplicates/
```

#### Estructura de Archivos Esperada
```
custom/search_duplicates/
├── admin/
├── class/
├── core/
├── img/
├── langs/
├── lib/
├── index.php
├── edit.php
├── README.md
├── LICENSE
└── CHANGELOG.md
```

### Paso 4: Activar el Módulo

#### Desde la Interfaz Web
1. Acceder a Dolibarr como administrador
2. Ir a **Configuración > Módulos**
3. Buscar "Search Duplicates"
4. Hacer clic en **"Activar"**
5. Confirmar la activación

#### Desde Línea de Comandos
```bash
# Activar módulo via CLI
php /path/to/dolibarr/scripts/cli/activate_module.php search_duplicates
```

### Paso 5: Configurar Permisos

#### Asignar Permisos a Usuarios
1. Ir a **Usuarios > Permisos**
2. Seleccionar el usuario
3. En la sección "Search Duplicates":
   - ✅ **Leer**: Ver resultados de búsqueda
   - ✅ **Escribir**: Editar productos y stock
   - ✅ **Eliminar**: Eliminar duplicados

#### Configurar Roles
```php
// En el archivo de configuración
$conf->global->SEARCH_DUPLICATES_ADMIN_USERS = "1,2,3"; // IDs de usuarios admin
$conf->global->SEARCH_DUPLICATES_READ_USERS = "4,5,6";  // IDs de usuarios lectura
```

### Paso 6: Configuración Inicial

#### Configuración Básica
1. Ir a **Configuración > Módulos > Search Duplicates**
2. Configurar parámetros básicos:
   - **Umbral de similitud**: 85% (recomendado)
   - **Caché habilitado**: Sí
   - **Tiempo de caché**: 3600 segundos
   - **IA habilitada**: Sí

#### Configuración Avanzada
```php
// Configuración personalizada en conf/conf.php
$conf->global->SEARCH_DUPLICATES_AI_ENABLED = 1;
$conf->global->SEARCH_DUPLICATES_CACHE_TTL = 3600;
$conf->global->SEARCH_DUPLICATES_SIMILARITY_THRESHOLD = 85;
$conf->global->SEARCH_DUPLICATES_MAX_RESULTS = 1000;
```

---

## 🔧 Configuración del Servidor

### Apache (.htaccess)
```apache
# Agregar al .htaccess de Dolibarr
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^busqueda-avanzada$ custom/search_duplicates/index.php [L]
</IfModule>
```

### Nginx
```nginx
# Agregar al archivo de configuración de Nginx
location /busqueda-avanzada {
    try_files $uri $uri/ /custom/search_duplicates/index.php?$query_string;
}
```

### PHP (php.ini)
```ini
; Configuración recomendada para Search Duplicates
memory_limit = 512M
max_execution_time = 300
max_input_vars = 3000
post_max_size = 100M
upload_max_filesize = 100M
```

---

## 🧪 Verificación de la Instalación

### Test de Funcionalidad
1. Acceder a **Buscar > Search Duplicates**
2. Realizar una búsqueda de prueba
3. Verificar que aparezcan resultados
4. Probar la edición de un producto
5. Verificar la detección de duplicados

### Test de Rendimiento
```bash
# Verificar tiempo de respuesta
curl -w "@curl-format.txt" -o /dev/null -s "https://tu-dolibarr.com/custom/search_duplicates/index.php"

# Verificar uso de memoria
php -r "echo memory_get_usage(true) / 1024 / 1024 . ' MB';"
```

### Test de Base de Datos
```sql
-- Verificar tablas creadas
SHOW TABLES LIKE '%search_duplicates%';

-- Verificar índices
SHOW INDEX FROM llx_product WHERE Key_name LIKE '%search%';
```

---

## 🚨 Solución de Problemas

### Problemas Comunes

#### Error: "Module not found"
```bash
# Verificar que el módulo esté en la ubicación correcta
ls -la /path/to/dolibarr/custom/search_duplicates/

# Verificar permisos
chmod -R 755 /path/to/dolibarr/custom/search_duplicates/
```

#### Error: "Permission denied"
```bash
# Corregir permisos
chown -R www-data:www-data /path/to/dolibarr/custom/
chmod -R 755 /path/to/dolibarr/custom/
```

#### Error: "Database connection failed"
```php
// Verificar configuración de base de datos en conf/conf.php
$dolibarr_main_db_host = 'localhost';
$dolibarr_main_db_port = '3306';
$dolibarr_main_db_name = 'dolibarr';
$dolibarr_main_db_user = 'dolibarr_user';
$dolibarr_main_db_pass = 'password';
```

#### Error: "Memory limit exceeded"
```ini
# Aumentar límite de memoria en php.ini
memory_limit = 1024M
```

### Logs de Depuración
```bash
# Habilitar logs de depuración
echo "log_errors = On" >> /etc/php/8.0/apache2/php.ini
echo "error_log = /var/log/php_errors.log" >> /etc/php/8.0/apache2/php.ini

# Ver logs
tail -f /var/log/php_errors.log
```

---

## 📞 Soporte Técnico

### Canales de Soporte
- **Email**: support@searchduplicates.com
- **Chat**: Disponible en searchduplicates.com
- **Teléfono**: +34 900 123 456
- **Foro**: community.searchduplicates.com

### Información Requerida para Soporte
- Versión de Dolibarr
- Versión de PHP
- Sistema operativo
- Logs de error
- Descripción detallada del problema

---

## ✅ Lista de Verificación Post-Instalación

- [ ] Módulo extraído correctamente
- [ ] Módulo activado en Dolibarr
- [ ] Permisos configurados
- [ ] Configuración básica completada
- [ ] Test de funcionalidad exitoso
- [ ] Test de rendimiento satisfactorio
- [ ] Logs sin errores críticos

---

**¡Instalación completada exitosamente!** 🎉

Para más información, consulte la [documentación completa](README.md) o contacte con nuestro [soporte técnico](mailto:support@searchduplicates.com).
