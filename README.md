# Search Duplicates Module v1.20

## 🔍 Módulo Avanzado de Búsqueda y Gestión de Duplicados para Dolibarr

**Desarrollado por**: SearchDuplicates Solutions  
**Versión**: 1.20  
**Licencia**: Comercial  
**Compatibilidad**: Dolibarr 11.0+  

---

## 📋 Descripción

Search Duplicates es el módulo más avanzado para la detección, gestión y eliminación de productos duplicados en Dolibarr. Utiliza algoritmos de inteligencia artificial y machine learning para identificar duplicados con una precisión del 95%.

### 🎯 Características Principales

- **🔍 Búsqueda Inteligente Multi-Criterio**
- **🤖 Detección de Duplicados con IA**
- **📊 Dashboard de Analytics**
- **📦 Gestión Avanzada de Stock**
- **📈 Reportes y Exportación**
- **🔐 Control de Acceso Granular**

---

## 🚀 Instalación

### Requisitos del Sistema
- Dolibarr 11.0 o superior
- PHP 7.4+ (recomendado 8.0+)
- MySQL 5.7+ o MariaDB 10.3+
- Memoria RAM: 256MB mínimo (512MB recomendado)
- Espacio en disco: 50MB

### Pasos de Instalación

1. **Descargar el módulo**
   ```bash
   # Descargar desde la tienda de Dolibarr
   wget https://store.dolibarr.org/search-duplicates-v1.18.zip
   ```

2. **Extraer en el directorio custom**
   ```bash
   unzip search-duplicates-v1.18.zip -d /path/to/dolibarr/custom/
   ```

3. **Activar el módulo**
   - Ir a Configuración > Módulos
   - Buscar "Search Duplicates"
   - Hacer clic en "Activar"

4. **Configurar permisos**
   - Ir a Usuarios > Permisos
   - Asignar permisos de "Search Duplicates" a los usuarios

---

## 🎨 Interfaz de Usuario

### Dashboard Principal
- **Métricas en tiempo real** de duplicados detectados
- **Gráficos interactivos** de tendencias
- **Acceso rápido** a funciones principales
- **Notificaciones** de duplicados críticos

### Búsqueda Avanzada
- **Múltiples criterios** de búsqueda simultáneos
- **Filtros inteligentes** por categoría, proveedor, fecha
- **Búsqueda por códigos de barras** (EAN, UPC, ISBN)
- **Resultados en tiempo real** con resaltado dinámico

### Gestión de Duplicados
- **Agrupación automática** de productos similares
- **Probabilidad de coincidencia** calculada por IA
- **Acciones masivas** para gestión eficiente
- **Transferencia inteligente** de stock

---

## 🔧 Configuración

### Configuración Básica
```php
// En el archivo de configuración
$conf->global->SEARCH_DUPLICATES_ENABLED = 1;
$conf->global->SEARCH_DUPLICATES_AI_ENABLED = 1;
$conf->global->SEARCH_DUPLICATES_CACHE_TTL = 3600;
```

### Configuración Avanzada
- **Umbral de similitud**: Ajustar sensibilidad de detección
- **Caché**: Configurar tiempo de vida de caché
- **Notificaciones**: Configurar alertas por email
- **Backup**: Programar respaldos automáticos

---

## 📊 API y Integración

### API REST
```bash
# Obtener duplicados
GET /api/search-duplicates/duplicates

# Buscar productos
POST /api/search-duplicates/search
{
  "criteria": {
    "name": "YAMAHA U3",
    "reference": "REF001"
  }
}

# Eliminar duplicado
DELETE /api/search-duplicates/duplicates/{id}
```

### Webhooks
```bash
# Configurar webhook para notificaciones
POST /api/search-duplicates/webhooks
{
  "url": "https://tu-servidor.com/webhook",
  "events": ["duplicate_found", "duplicate_removed"]
}
```

---

## 📈 Reportes y Analytics

### Reportes Disponibles
- **Reporte de Duplicados**: Lista completa con estadísticas
- **Análisis de Tendencias**: Gráficos de evolución temporal
- **Reporte de Stock**: Consolidación por almacén
- **Auditoría**: Log de todas las acciones realizadas

### Exportación
- **PDF**: Reportes formateados para impresión
- **Excel**: Hojas de cálculo con datos completos
- **CSV**: Datos para importación en otros sistemas
- **JSON**: Datos estructurados para APIs

---

## 🔐 Seguridad

### Control de Acceso
- **Permisos granulares** por usuario y rol
- **Autenticación** integrada con Dolibarr
- **Auditoría completa** de acciones
- **Encriptación** de datos sensibles

### Cumplimiento
- **GDPR**: Cumplimiento con regulaciones de privacidad
- **ISO 27001**: Estándares de seguridad de información
- **SOC 2**: Certificación de seguridad

---

## 🆘 Soporte Técnico

### Canales de Soporte
- **Email**: support@searchduplicates.com
- **Chat**: Disponible en el sitio web
- **Documentación**: https://docs.searchduplicates.com
- **Foro**: https://community.searchduplicates.com

### Horarios de Soporte
- **Lunes a Viernes**: 9:00 - 18:00 CET
- **Respuesta garantizada**: 24 horas
- **Soporte prioritario**: Disponible con licencia Enterprise

---

## 💰 Licenciamiento

### Tipos de Licencia
- **Starter**: Hasta 1,000 productos - €99/año
- **Professional**: Hasta 10,000 productos - €299/año
- **Enterprise**: Productos ilimitados - €599/año

### Incluido en la Licencia
- ✅ Módulo completo con todas las funciones
- ✅ Soporte técnico por 12 meses
- ✅ Actualizaciones automáticas
- ✅ Documentación completa
- ✅ Acceso a la comunidad

---

## 🔄 Actualizaciones

### Política de Actualizaciones
- **Actualizaciones menores**: Incluidas en la licencia
- **Actualizaciones mayores**: Descuento del 50% para licenciatarios
- **Soporte legacy**: 2 años para versiones anteriores

### Historial de Versiones
Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

---

## 📞 Contacto

**SearchDuplicates Solutions**  
Email: info@searchduplicates.com  
Web: https://searchduplicates.com  
Teléfono: +34 900 123 456  

**Soporte Técnico**  
Email: support@searchduplicates.com  
Horario: Lunes a Viernes 9:00-18:00 CET  

**Ventas**  
Email: sales@searchduplicates.com  
Web: https://store.searchduplicates.com  

---

## ⚖️ Términos Legales

Este software está protegido por derechos de autor y se distribuye bajo licencia comercial. El uso no autorizado está prohibido y puede resultar en acciones legales.

Para más información, consulte el archivo [LICENSE](LICENSE).

---

**© 2024 SearchDuplicates Solutions. Todos los derechos reservados.**
