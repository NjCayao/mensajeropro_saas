RESUMEN FASE 1: MIGRACIÓN A SAAS MULTI-EMPRESA
Objetivo cumplido:
Transformar MensajeroPro de sistema mono-empresa a plataforma SaaS que permite múltiples empresas independientes, cada una con su propio WhatsApp y datos aislados.
Cambios principales implementados:
1. Base de Datos

Nueva BD: mensajeropro_saas
Agregado empresa_id a todas las tablas de datos
Nueva tabla empresas para gestión de clientes
Nueva tabla whatsapp_sesiones_empresa con soporte de puertos
Constraints de integridad referencial

2. Arquitectura de aplicación

Estructura reorganizada: /public para archivos públicos, /app para lógica
Sistema de rutas centralizado con app.php
Funciones helper: auth.php, multi_tenant.php, whatsapp_ports.php
Variables globales: API_URL, APP_URL, WHATSAPP_API_URL

3. Sistema de autenticación

Login identifica empresa del usuario
Sesión guarda empresa_id
Función getEmpresaActual() disponible globalmente
Sistema de registro para nuevas empresas

4. Módulos actualizados
Todos los módulos ahora filtran por empresa_id:

Dashboard con métricas por empresa
Contactos, categorías, mensajes, plantillas
Historial y mensajes programados
WhatsApp con puerto dinámico

5. WhatsApp multi-instancia

Cada empresa tiene puerto asignado (3001, 3002, etc)
Sesiones independientes por empresa
Servicio Node.js acepta parámetros: puerto y empresa_id
Gestión automática de puertos


CHANGELOG DETALLADO
[1.0.0] - 2024-01-XX - Migración completa a SaaS
Base de datos

Added: Nueva BD mensajeropro_saas
Added: Campo empresa_id en: contactos, categorías, plantillas_mensajes, mensajes_programados, historial_mensajes, cola_mensajes, configuracion_bot, conocimiento_bot, conversaciones_bot, estados_conversacion, logs_sistema
Added: Tabla empresas con campos: id, nombre_empresa, telefono, email, fecha_registro, activo, plan
Added: Tabla whatsapp_sesiones_empresa con campo puerto
Changed: Usuarios ahora tienen relación con empresa_id
Changed: Todas las PKs y FKs actualizadas para integridad

Estructura de archivos

Added: /public - Archivos públicamente accesibles
Added: /app - Lógica de aplicación protegida
Added: public/app.php - Router principal
Added: includes/multi_tenant.php - Funciones multi-empresa
Added: includes/whatsapp_ports.php - Gestión de puertos
Moved: Todos los módulos de / a /sistema/cliente/modulos/

Sistema de autenticación

Added: public/registro.php - Registro de nuevas empresas
Changed: Login ahora carga empresa_id en sesión
Changed: Todas las páginas verifican empresa activa
Added: Función global getEmpresaActual()

APIs actualizadas

Changed: Todas las APIs en /api/v1/ ahora requieren multi_tenant.php
Changed: Queries filtradas por empresa_id
Changed: Inserts incluyen empresa_id
Added: Verificación de pertenencia en updates/deletes

WhatsApp Service

Changed: index.js acepta puerto y empresa_id como parámetros
Changed: Sesiones nombradas como empresa-{id}
Changed: Base de datos actualizada a mensajeropro_saas
Changed: Todas las queries incluyen filtro por empresa
Added: Puerto dinámico en tabla whatsapp_sesiones_empresa

Módulos del sistema

Changed: Dashboard muestra solo datos de la empresa actual
Changed: Contactos filtrados por empresa
Changed: Categorías con empresa_id
Changed: Mensajes usan puerto dinámico de WhatsApp
Changed: Plantillas aisladas por empresa
Changed: Programados procesan solo mensajes de cada empresa
Changed: WhatsApp conecta en puerto específico

Cron Jobs

Changed: procesar_programados.php procesa todas las empresas
Changed: procesar_cola.php respeta empresa_id
Added: Verificación de empresa activa antes de procesar

Seguridad

Added: Verificación de empresa activa en login
Added: Aislamiento completo de datos por empresa
Added: Validación de pertenencia en todas las operaciones
Fixed: URLs relativas para evitar problemas de rutas

1. Estructura Multi-Tenant Implementada

✅ Cada empresa tiene su propio espacio aislado con empresa_id
✅ Sistema de autenticación con sesiones por empresa
✅ Filtrado automático de datos usando getEmpresaActual()

2. Sistema de Rutas y Router

✅ Router principal en web/app.php que mapea URLs limpias
✅ URLs públicas sin /sistema/
✅ Constantes JavaScript configuradas:

javascript  const APP_URL = 'http://localhost/mensajeroprov2';
  const API_URL = 'http://localhost/mensajeroprov2/api/v1';
3. Módulos Principales Funcionando

✅ Dashboard: Estadísticas por empresa
✅ Contactos: CRUD completo con importación CSV
✅ Categorías: Gestión completa con colores y precios
✅ Mensajes: Envío individual, por categoría y masivo
✅ Programados: Sistema de mensajes programados
✅ Plantillas: Sistema de plantillas reutilizables
✅ WhatsApp: Conexión multi-puerto por empresa
✅ Perfil: Gestión de usuario

4. APIs RESTful Completas
Todas las APIs en /sistema/api/v1/ con:

✅ Autenticación por sesión
✅ Validaciones de datos
✅ Respuestas JSON estandarizadas
✅ Logs de actividad

5. Correcciones Específicas Realizadas
Mensajes.php

✅ Contador de contactos "TODOS" funcionando
✅ Creación de contactos/count.php
✅ Actualización de updateDestinatarios()

Programados.php

✅ Corrección de errores jQuery en funciones AJAX
✅ Estandarización de tiempo mínimo a 3 minutos
✅ Funciones de editar, cancelar y ver detalles

WhatsApp Service

✅ Sistema multi-puerto (cada empresa su puerto)
✅ Gestión de sesiones por empresa
✅ Bot IA integrado (preparado para Fase 3)

6. Base de Datos

✅ Todas las tablas con campo empresa_id
✅ Relaciones foráneas configuradas
✅ Índices para optimización

7. Seguridad

✅ Validación de sesiones
✅ Sanitización de inputs
✅ Verificación de permisos por empresa
✅ Logs de auditoría

8. Variables de Plantillas

✅ {{nombre}} - Nombre del contacto
✅ {{nombreWhatsApp}} - Nombre de WhatsApp
✅ {{categoria}} - Categoría del contacto
✅ {{precio}} - Precio de la categoría
✅ {{fecha}} - Fecha actual
✅ {{hora}} - Hora actual

9. Funcionalidades Especiales

✅ Importación masiva de contactos CSV
✅ Envío con archivos (imágenes/documentos)
✅ Sistema de cola de mensajes
✅ Delay anti-spam automático
✅ Vista previa en tiempo real

📊 Estado del Proyecto
Fase 1: COMPLETADA ✅

Sistema base multi-empresa
Gestión de contactos y mensajería
WhatsApp integrado

Próximas Fases:

Fase 2: Dashboard mejorado, reportes, estadísticas
Fase 3: Bot IA completamente funcional
Fase 4: Sistema de planes y facturación

🚀 El sistema está listo para:

Gestionar múltiples empresas
Enviar mensajes masivos por WhatsApp
Programar envíos
Importar contactos masivamente
Usar plantillas personalizadas
Conectar WhatsApp por empresa



✅ Archivos Creados:

includes/plan-limits.php - Sistema completo de validación de límites

obtenerLimitesPlan() - Obtiene límites del plan actual
tieneEscalamiento() - Valida acceso a módulo escalamiento
tieneCatalogoBot() - Valida acceso a catálogo bot
tieneHorariosBot() - Valida acceso a horarios/citas
tieneGoogleCalendar() - Valida acceso a Google Calendar
verificarLimiteContactos() - Verifica límite de contactos
verificarLimiteMensajes() - Verifica límite de mensajes
obtenerResumenLimites() - Resumen completo de límites y uso
verificarAccesoModulo() - Bloquea acceso a módulos restringidos



✅ Archivos Modificados:

sistema/cliente/layouts/sidebar.php

Carga plan-limits.php
Oculta "Escalados" si no tiene plan Profesional
Oculta "Catálogo Bot" si no tiene plan Profesional O si no es bot de ventas
Oculta "Horarios Bot" si no tiene plan Profesional O si no es bot de citas


sistema/cliente/modulos/escalados.php

Agregado require_once plan-limits.php
Agregado verificarAccesoModulo('escalados') - redirecciona si no tiene acceso


sistema/cliente/modulos/catalogo-bot.php

Agregado require_once plan-limits.php
Agregado verificarAccesoModulo('catalogo-bot')


sistema/cliente/modulos/horarios-bot.php

Agregado require_once plan-limits.php
Agregado verificarAccesoModulo('horarios-bot')


sistema/cliente/modulos/bot-config.php

Agregado require_once plan-limits.php
Tab "Escalamiento" envuelto en <?php if (tieneEscalamiento()): ?>
Muestra mensaje "Plan insuficiente" si no tiene acceso


sistema/cliente/modulos/mi-plan.php - COMPLETAMENTE RENOVADO

Usa obtenerResumenLimites() para datos en tiempo real
Muestra uso actual vs límites con porcentajes
Visualización de módulos disponibles/bloqueados
Comparación mejorada de planes
Diseño modernizado con iconos y badges


Base de datos - Tabla planes

Plan 1 (Trial): 50 contactos, 100 mensajes, TODO habilitado por 48h
Plan 2 (Básico): 500 contactos, 2000 mensajes, sin módulos avanzados
Plan 3 (Profesional): 2000 contactos, 10000 mensajes, TODO habilitado
JSON actualizado con claves consistentes



📊 Lógica de Límites Implementada:
Plan Trial (ID=1):

✅ Escalamiento (solo si trial activo)
✅ Catálogo Bot (solo si trial activo)
✅ Horarios Bot (solo si trial activo)
✅ Google Calendar (solo si trial activo)

Plan Básico (ID=2):

❌ Escalamiento
❌ Catálogo Bot
❌ Horarios Bot
❌ Google Calendar

Plan Profesional (ID=3):

✅ Escalamiento
✅ Catálogo Bot (10 MB)
✅ Horarios Bot
✅ Google Calendar



# 01-10-25

CHANGELOG - Sistema de Bot IA Multi-Tipo
Versión 2.0.0 - Refactorización de Notificaciones y Tipos de Bot
🎯 Objetivo
Implementar soporte para 3 tipos de bot (Ventas, Citas, Soporte) y separar la lógica de notificaciones en una tabla independiente.

📊 Cambios en Base de Datos
Tablas Modificadas
configuracion_bot
Agregado:

Ninguno

Modificado:

tipo_bot → Cambiado de ENUM('ventas','citas') a ENUM('ventas','citas','soporte')

Eliminado:

respuestas_rapidas (LONGTEXT JSON)
notificar_escalamiento (TINYINT)
notificar_ventas (TINYINT)
notificar_citas (TINYINT)
numeros_notificacion (LONGTEXT JSON)
mensaje_notificacion (TEXT)

bot_templates
Agregado:

mensaje_notificacion_escalamiento (TEXT)
mensaje_notificacion_ventas (TEXT)
mensaje_notificacion_citas (TEXT)

Modificado:

tipo_bot → Cambiado de ENUM('ventas','citas') a ENUM('ventas','citas','soporte')

Eliminado:

respuestas_rapidas_template (LONGTEXT JSON)

Tablas Nuevas
notificaciones_bot
sqlCREATE TABLE notificaciones_bot (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_id INT UNIQUE NOT NULL,
    
    -- Números compartidos
    numeros_notificacion LONGTEXT CHECK (json_valid(numeros_notificacion)),
    
    -- Escalamiento (todos los bots)
    notificar_escalamiento TINYINT(1) DEFAULT 1,
    mensaje_escalamiento TEXT,
    
    -- Ventas (solo ventas y soporte)
    notificar_ventas TINYINT(1) DEFAULT 1,
    mensaje_ventas TEXT,
    
    -- Citas (solo citas y soporte)
    notificar_citas TINYINT(1) DEFAULT 1,
    mensaje_citas TEXT,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

📁 Archivos Modificados
Backend - APIs
✅ sistema/api/v1/bot/configurar.php
Cambios:

Eliminadas variables: $respuestas_rapidas, $notificar_escalamiento, $numeros_notificacion, $mensaje_notificacion
Eliminadas estas columnas del UPDATE SQL
Eliminadas estas columnas del INSERT SQL
Eliminados valores correspondientes de los execute()

✅ sistema/api/v1/bot/cargar-template.php
Cambios:

Eliminado parseo de respuestas_rapidas_template
Solo parsea configuracion_adicional

✅ sistema/api/v1/bot/notificar-escalamiento.php
Cambios:

Cambiado query para leer desde notificaciones_bot en lugar de configuracion_bot
SELECT ahora usa: SELECT notificar_escalamiento, numeros_notificacion, mensaje_escalamiento FROM notificaciones_bot

✅ sistema/api/v1/bot/verificar-config.php
Cambios:

Agregado query para obtener estado de notificaciones desde notificaciones_bot
Cambiado 'notificaciones_activas' para leer desde la nueva tabla

🆕 sistema/api/v1/bot/guardar-notificaciones.php (NUEVO)
Función:

Guarda/actualiza configuración de notificaciones en tabla notificaciones_bot
Validación crítica: Solo guarda notificaciones que corresponden al tipo de bot activo

Bot Ventas → Guarda escalamiento + ventas (fuerza citas a 0)
Bot Citas → Guarda escalamiento + citas (fuerza ventas a 0)
Bot Soporte → Guarda escalamiento + ventas + citas




Frontend - Módulos
✅ sistema/cliente/modulos/bot-config.php (REESCRITO COMPLETO)
Cambios principales:
1. Tipo de Bot:

Agregado selector visual para 3 tipos: Ventas, Citas, Soporte
Cada tipo muestra icono y descripción distintiva

2. Tab Templates:

Filtrado dinámico según tipo_bot seleccionado
Muestra solo plantillas relevantes al tipo actual

3. Tab Personalización IA:

Sección de instrucciones cambia dinámicamente:

Ventas → Muestra "Estrategia de Ventas"
Citas → Muestra "Protocolo de Agendamiento"
Soporte → Muestra AMBOS campos (ventas + citas)



4. Tab Notificaciones (NUEVO):

Separado del tab de Escalamiento
3 tarjetas independientes: Escalamiento, Ventas, Citas
Visibilidad dinámica:

Bot Ventas → Muestra: Escalamiento + Ventas
Bot Citas → Muestra: Escalamiento + Citas
Bot Soporte → Muestra: Escalamiento + Ventas + Citas


Números de WhatsApp compartidos entre todos los tipos
Mensajes personalizables por cada tipo de notificación

5. JavaScript:

Función actualizarUISegunTipo(tipo) controla toda la UI según el bot seleccionado
Función actualizarNotificacionesSegunTipo(tipo) muestra/oculta tarjetas
Función guardarNotificaciones() separada para guardar en tabla notificaciones_bot
Carga de templates ahora incluye mensajes de notificación

Eliminado:

Toda la sección de "Respuestas Rápidas"
Referencias a campos de notificación en configuracion_bot


🎨 Lógica de Negocio
Tipos de Bot
Bot de Ventas

Propósito: Vender productos, tomar pedidos, gestionar delivery
Templates: Restaurante, Tienda, Farmacia, Ferretería
Notificaciones activas: Escalamiento + Ventas
Campos usados: prompt_ventas, business_info

Bot de Citas

Propósito: Agendar citas, reservas, turnos
Templates: Clínica Médica, Salón de Belleza, Clínica Dental
Notificaciones activas: Escalamiento + Citas
Campos usados: prompt_citas, business_info

Bot de Soporte (NUEVO)

Propósito: Soporte técnico, ISP, SaaS, mesa de ayuda
Templates: ISP, Soporte Técnico, SaaS/Software
Notificaciones activas: Escalamiento + Ventas + Citas
Campos usados: prompt_ventas, prompt_citas, business_info

Validación de Notificaciones
Backend valida y fuerza valores según tipo:
Bot Ventas:
  ✓ notificar_escalamiento (según checkbox)
  ✓ notificar_ventas (según checkbox)
  ✗ notificar_citas = 0 (forzado)

Bot Citas:
  ✓ notificar_escalamiento (según checkbox)
  ✗ notificar_ventas = 0 (forzado)
  ✓ notificar_citas (según checkbox)

Bot Soporte:
  ✓ notificar_escalamiento (según checkbox)
  ✓ notificar_ventas (según checkbox)
  ✓ notificar_citas (según checkbox)
Esto previene que se notifiquen eventos que nunca ocurrirán en ese tipo de bot.

🔒 Seguridad

Validación en backend previene manipulación del HTML del frontend
Incluso si un usuario modifica el DOM y envía notificar_citas=1 en un bot de ventas, el backend lo rechaza



⚠️ Breaking Changes

Columnas eliminadas de configuracion_bot: Cualquier código que intente leer/escribir respuestas_rapidas, notificar_escalamiento, notificar_ventas, notificar_citas, numeros_notificacion, o mensaje_notificacion causará errores
Nueva tabla requerida: notificaciones_bot debe existir antes de usar el sistema
Migración de datos: Si había datos en las columnas eliminadas, deben migrarse a notificaciones_bot antes de eliminarlas

# para agregar mas datos 
agregar botones al bot para dar a elegir al cliente soporte pagos como lista. como bot.
poner si un humano esta en conversaion de chat con el cliente el bot no debe de responder colocar un input de espera. 
