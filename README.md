# 🚀 Mega Soft Gateway para WooCommerce - PRODUCCIÓN

[![Versión](https://img.shields.io/badge/version-3.0.0-blue.svg)](https://github.com/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-purple.svg)](https://woocommerce.com/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)

Pasarela de pago profesional para WooCommerce que integra completamente con Mega Soft Computación C.A. (Venezuela). Desarrollado para **uso en producción** con todas las características empresariales necesarias.

## ✨ Características Principales

### 🔄 **Integración Completa**
- ✅ **Modalidad Universal** completa según documentación v4.24
- ✅ Soporte para **TDC Nacional/Internacional** y **Pago Móvil**
- ✅ **Pre-registro automático** con validación de seguridad
- ✅ **Consulta de estado** en tiempo real
- ✅ **Webhooks automáticos** para actualizaciones instantáneas
- ✅ **Reembolsos** directos desde WooCommerce

### 📋 **Validación de Documentos**
- ✅ **Tipos de documento completos**: V, E, J, G, P, C (nuevo)
- ✅ **Validación automática** por tipo de documento
- ✅ **Guardado opcional** para clientes registrados
- ✅ **Cumplimiento regulatorio** venezolano

### 💳 **Opciones de Pago**
- ✅ **Cuotas configurables** (3, 6, 12, 18, 24 meses)
- ✅ **Monto mínimo** configurable para cuotas
- ✅ **Cálculo automático** de montos por cuota
- ✅ **Preautorización y captura** manual opcional

### 🛡️ **Seguridad Avanzada**
- ✅ **Validación de números de control** en retornos
- ✅ **Autenticación Basic Auth** con credenciales encriptadas
- ✅ **Sanitización completa** de datos de entrada
- ✅ **Logs de auditoría** detallados
- ✅ **Verificación SSL** obligatoria en producción

### 📊 **Dashboard Administrativo**
- ✅ **Panel de control** con estadísticas en tiempo real
- ✅ **Gestión de transacciones** con filtros avanzados
- ✅ **Sistema de logs** con múltiples niveles
- ✅ **Reportes gráficos** con Chart.js
- ✅ **Herramientas de mantenimiento** y diagnóstico

### 🔄 **Automatización**
- ✅ **Sincronización automática** cada hora
- ✅ **Reintentos de webhooks** fallidos
- ✅ **Limpieza automática** de datos antiguos
- ✅ **Notificaciones por email** para errores críticos
- ✅ **Tareas cron** programadas

## 📦 Estructura del Plugin

```
woocommerce-megasoft-gateway/
├── woocommerce-megasoft-gateway.php     # Archivo principal
├── includes/                            # Clases del sistema
│   ├── class-megasoft-logger.php       # Sistema de logs
│   ├── class-megasoft-api.php          # Comunicación con API
│   ├── class-megasoft-webhook.php      # Manejo de webhooks
│   └── class-megasoft-admin.php        # Panel administrativo
├── assets/                              # Recursos estáticos
│   ├── css/
│   │   ├── admin.css                   # Estilos del admin
│   │   └── checkout.css                # Estilos del checkout
│   ├── js/
│   │   ├── admin.js                    # JavaScript del admin
│   │   └── checkout.js                 # JavaScript del checkout
│   └── images/
│       └── megasoft-icon.png           # Icono del plugin
├── languages/                           # Traducciones
└── README.md                           # Esta documentación
```

## ⚡ Instalación

### 1. **Requisitos del Sistema**
- WordPress 5.8 o superior
- WooCommerce 6.0 o superior
- PHP 7.4 o superior
- MySQL 5.7 o superior
- SSL (HTTPS) para producción
- cURL habilitado
- OpenSSL habilitado

### 2. **Instalación del Plugin**
```bash
# Opción 1: Subir via WordPress Admin
1. Descargar el archivo ZIP del plugin
2. Ir a Plugins > Añadir nuevo > Subir plugin
3. Seleccionar archivo y activar

# Opción 2: Instalación manual
1. Subir carpeta al directorio /wp-content/plugins/
2. Activar desde el panel de WordPress
```

### 3. **Configuración Inicial**
```bash
# Después de activar el plugin:
1. Ir a WooCommerce > Ajustes > Pagos
2. Buscar "Mega Soft Gateway" y hacer clic en "Configurar"
3. Completar las credenciales proporcionadas por Mega Soft
4. Activar el gateway
5. Realizar pruebas de conexión
```

## 🔧 Configuración

### **Credenciales API**
```php
// Ambiente de Prueba
Código de Afiliación: 20250508
Usuario API: multimuniv  
Contraseña API: Caracas123.1
URL Base: https://paytest.megasoft.com.ve/action/

// Ambiente de Producción
Código de Afiliación: [Proporcionado por Mega Soft]
Usuario API: [Proporcionado por Mega Soft]
Contraseña API: [Proporcionado por Mega Soft]
URL Base: https://pay.megasoft.com.ve/action/
```

### **URLs Requeridas por Mega Soft**
```
URL de Retorno:
https://tu-sitio.com/wc-api/WC_Gateway_MegaSoft_Universal?control=@control@&factura=@facturatrx@

URL de Webhook (Opcional):
https://tu-sitio.com/wc-api/megasoft_webhook
```

### **Configuraciones Avanzadas**

#### **Documentos de Identidad**
- ✅ **Requerir documento**: Obligatorio para cumplir regulaciones
- ✅ **Guardar documentos**: Permite reutilización para clientes registrados
- ✅ **Tipos soportados**: V, E, J, G, P, C

#### **Sistema de Cuotas**
- ✅ **Cuotas máximas**: 3, 6, 12, 18, 24 meses
- ✅ **Monto mínimo**: Configurable por el comercio
- ✅ **Cálculo automático**: Muestra monto por cuota en tiempo real

#### **Logs y Depuración**
- ✅ **Niveles de log**: Debug, Info, Warning, Error
- ✅ **Retención**: 30 días por defecto
- ✅ **Exportación**: CSV con filtros de fecha
- ✅ **Limpieza automática**: Configurable

## 🎯 Uso del Plugin

### **Para Administradores**

#### **Dashboard Principal**
```
Mega Soft > Dashboard
- Estado del gateway en tiempo real
- Estadísticas de transacciones (hoy, semana, mes)
- Acciones rápidas (test, sync, limpieza)
- Transacciones recientes
```

#### **Gestión de Transacciones**
```
Mega Soft > Transacciones
- Lista filtrable de todas las transacciones
- Estados: Pendiente, Aprobada, Fallida
- Búsqueda por orden, control, cliente
- Sincronización manual individual
- Exportación de datos
```

#### **Sistema de Logs**
```
Mega Soft > Logs
- Logs detallados por nivel (Error, Warning, Info, Debug)
- Filtros por fecha y tipo
- Búsqueda de logs específicos
- Exportación y limpieza automática
```

#### **Reportes Gráficos**
```
Mega Soft > Reportes
- Gráficos de transacciones por día
- Distribución por métodos de pago
- Tasas de aprobación/rechazo
- Top 10 transacciones
- Períodos configurables
```

#### **Herramientas del Sistema**
```
Mega Soft > Herramientas
- Prueba de conexión con API
- Sincronización manual masiva
- Limpieza de datos antiguos
- Exportar/importar configuración
- Información del sistema
- Generador de datos de prueba
```

### **Para Clientes (Checkout)**

#### **Flujo de Pago**
1. **Selección de método**: Cliente elige "Tarjeta de Crédito/Débito y Pago Móvil"
2. **Datos de documento**: Ingresa tipo y número de documento
3. **Opciones de cuotas**: Selecciona número de cuotas si aplica
4. **Confirmación**: Revisa datos y confirma
5. **Redirección**: Va a la pasarela de Mega Soft
6. **Pago**: Completa el pago en el sitio seguro
7. **Retorno**: Regresa automáticamente a la tienda
8. **Confirmación**: Ve el comprobante y detalles

## 🔐 Seguridad y Cumplimiento

### **Medidas de Seguridad**
- ✅ **Validación de entrada**: Todos los datos son sanitizados
- ✅ **Números de control**: Verificación cruzada en retornos
- ✅ **Logs de auditoría**: Registro completo de actividades
- ✅ **Encriptación**: Credenciales y datos sensibles protegidos
- ✅ **SSL obligatorio**: Verificación automática en producción

### **Cumplimiento Regulatorio**
- ✅ **Documentos de identidad**: Según regulaciones venezolanas
- ✅ **Comprobantes**: Vouchers imprimibles obligatorios
- ✅ **Trazabilidad**: Logs completos de todas las transacciones
- ✅ **Retención de datos**: Configurable según normativas

## 🧪 Pruebas y Certificación

### **Script de Pruebas Mega Soft**
El plugin incluye datos de prueba según el script oficial:

| ID | Transacción | Tipo | Datos | Monto | Resultado Esperado |
|----|-------------|------|-------|-------|-------------------|
| 1 | Compra Crédito | Crédito | TDC 5420070695259279 | 0.01 | Aprobada |
| 2 | Compra Crédito | Crédito | TDC 5420070695259279 | 10100.51 | Negada (Fondos) |
| 3 | Compra Crédito | Crédito | TDC 5420070695259279 | 33500.01 | Time Out |
| 4 | Pago C2P | C2P | Teléfono 0412-1234571 | 3330 | Variable |
| 5 | Pago C2P | C2P | Teléfono 0412-1234572 | 33500.01 | Variable |
| 6 | Verificación P2C | Verificacion | Teléfono 0412-1234569 | 1000 | Aprobada |
| 7 | Verificación P2C | Verificacion | Teléfono 0412-1234571 | 25300.02 | Negada |
| 8 | Verificación P2C | Verificacion | Teléfono 0412-1234572 | 25300.03 | Negada |
| 9 | P2C Inactivo | Verificacion | Error simulado | N/A | Error de plataforma |

### **Evidencias de Certificación**
Para completar la certificación con Mega Soft:

1. **Ejecutar todas las pruebas** del script
2. **Capturar pantallas** de cada voucher generado
3. **Documentar en PDF** cada evidencia con su ID
4. **Enviar a Mega Soft** para aprobación final

## 🔧 Mantenimiento

### **Tareas Automáticas**
```php
// Configuradas automáticamente:
- Sincronización de transacciones: Cada hora
- Limpieza de logs antiguos: Diaria  
- Procesamiento de webhooks fallidos: Cada 5 minutos
- Verificación de estado del gateway: Cada hora
```

### **Mantenimiento Manual**
```bash
# Via Dashboard:
1. Mega Soft > Herramientas > Limpieza de Datos
2. Mega Soft > Logs > Limpiar Antiguos  
3. Mega Soft > Herramientas > Prueba de Conexión
4. Mega Soft > Transacciones > Sincronizar
```

### **Monitoreo**
```php
// Alertas automáticas por email:
- Errores críticos de conexión
- Transacciones pendientes > 24h
- Fallos de webhook > 5 intentos
- Problemas de configuración
```

## 📞 Soporte y Solución de Problemas

### **Problemas Comunes**

#### **Error de Conexión**
```
Síntoma: "Error de conexión con la pasarela"
Solución:
1. Verificar credenciales en configuración
2. Probar conexión desde Herramientas
3. Verificar que las IPs estén autorizadas
4. Revisar logs para detalles específicos
```

#### **Transacciones Pendientes**
```
Síntoma: Transacciones que no se actualizan
Solución:
1. Usar sincronización manual desde Transacciones
2. Verificar webhooks en Logs
3. Revisar número de control en orden
4. Contactar a Mega Soft si persiste
```

#### **Vouchers No Aparecen**
```
Síntoma: Comprobantes vacíos o incompletos
Solución:
1. Verificar respuesta XML en logs
2. Revisar formato de voucher en código
3. Probar con transacción de prueba
4. Verificar configuración de impresión
```

### **Información de Debug**
```php
// Activar logs de depuración:
1. WooCommerce > Ajustes > Pagos > Mega Soft > Configurar
2. Activar "Modo Debug" 
3. Seleccionar nivel "Debug"
4. Reproducir problema
5. Revisar Mega Soft > Logs
```

### **Contacto de Soporte**
- **Email**: merchant@megasoft.com.ve
- **Teléfono**: +58 (212) XXX-XXXX
- **Documentación**: [Manual técnico v4.24]
- **Horario**: Lunes a Viernes, 8:00 AM - 5:00 PM (VET)

## 🚀 Actualizaciones

### **Versión 3.0.0** (Actual)
- ✅ Dashboard administrativo completo
- ✅ Sistema de webhooks automático  
- ✅ Reportes gráficos avanzados
- ✅ Herramientas de mantenimiento
- ✅ Validación completa de documentos
- ✅ Soporte para cuotas automático
- ✅ Logs estructurados en base de datos
- ✅ Exportación e importación de configuración

### **Próximas Características**
- 🔄 Integración con otros bancos venezolanos
- 🔄 API REST para integraciones externas  
- 🔄 Panel de estadísticas para clientes
- 🔄 Notificaciones push en tiempo real
- 🔄 Integración con sistemas contables

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior. Ver archivo `LICENSE` para más detalles.

---

**Desarrollado por:** [Tu Nombre/Empresa]  
**Versión:** 3.0.0  
**Última actualización:** Agosto 2025  
**Compatibilidad:** WordPress 5.8+ | WooCommerce 6.0+ | PHP 7.4+  

---

## 🎯 ¿Listo para Producción?

Este plugin ha sido diseñado específicamente para **uso en producción** con todas las características empresariales que necesita una tienda online profesional:

- ✅ **Completamente funcional** con Mega Soft API v4.24
- ✅ **Interfaz administrativa profesional** 
- ✅ **Sistema de monitoreo** y alertas automáticas
- ✅ **Logs detallados** para auditoría y debugging
- ✅ **Webhooks automáticos** para sincronización instantánea
- ✅ **Herramientas de mantenimiento** integradas
- ✅ **Certificación completa** según script de Mega Soft
- ✅ **Cumplimiento regulatorio** venezolano

**¡Tu tienda online está lista para procesar pagos de forma segura y profesional! 🚀**