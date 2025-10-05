🎯 Objetivo
Verificar y corregir todo el sistema existente SIN crear nuevas tablas, solo limpiando y arreglando lo que ya está.

FASE 1: Base de Datos - Estructura y Duplicidades
Archivos a presentar:

mensajeropro_saas.sql (dump completo actualizado)

Lo que verificaremos:

Tablas duplicadas o innecesarias
Columnas duplicadas (fecha_expiracion_trial vs suscripciones.fecha_fin)
Relaciones (FOREIGN KEYS) correctas
Índices faltantes
Campos con nombres inconsistentes

Resultado esperado:

Lista de columnas a eliminar
Lista de tablas a limpiar
SQL para ejecutar correcciones


# FASE 2: Web Pública - Landing y Autenticación
Archivos a presentar:
public/index.php
public/login.php
public/registro.php
public/verificar-email.php
sistema/api/v1/auth/registro.php
sistema/api/v1/auth/login.php
sistema/api/v1/auth/google-oauth.php
includes/auth.php
Lo que verificaremos:

Flujo de registro funciona
Flujo de login funciona
Google OAuth funciona
Verificación de email funciona
Sesiones se crean correctamente
Redirecciones correctas según rol

Resultado esperado:

Bugs identificados
Código corregido
Flujo documentado


# FASE 3: Panel Cliente - Dashboard y Navegación
Archivos a presentar:
sistema/cliente/dashboard.php
sistema/cliente/layouts/header.php
sistema/cliente/layouts/sidebar.php
sistema/cliente/layouts/footer.php
includes/multi_tenant.php
includes/plan-limits.php
Lo que verificaremos:

Dashboard carga correctamente
Sidebar muestra módulos según plan
Multi-tenancy funciona (cada empresa ve solo sus datos)
Límites de plan se respetan
Estadísticas son correctas

Resultado esperado:

Dashboard funcional
Navegación limpia
Validaciones correctas


FASE 4: Módulo Contactos y Categorías
Archivos a presentar:
sistema/cliente/modulos/contactos.php
sistema/cliente/modulos/categorias.php
sistema/api/v1/contactos/crear.php
sistema/api/v1/contactos/listar.php
sistema/api/v1/contactos/actualizar.php
sistema/api/v1/contactos/eliminar.php
sistema/api/v1/categorias/*.php (todos)
Lo que verificaremos:

CRUD de contactos completo
CRUD de categorías completo
Importación CSV funciona
Filtros funcionan
No hay duplicados

Resultado esperado:

Módulos funcionando 100%
Código limpio


FASE 5: Módulo Mensajería
Archivos a presentar:
sistema/cliente/modulos/mensajes.php
sistema/cliente/modulos/programados.php
sistema/cliente/modulos/plantillas.php
sistema/api/v1/mensajes/*.php (todos)
cron/procesar_cola.php
cron/procesar_programados.php
Lo que verificaremos:

Envío individual funciona
Envío masivo funciona
Programados funcionan
Cola funciona
Plantillas se aplican correctamente


FASE 6: WhatsApp y Bot IA
Archivos a presentar:
sistema/cliente/modulos/whatsapp.php
sistema/cliente/modulos/bot-config.php
sistema/api/v1/whatsapp/*.php (todos)
whatsapp-service/src/index.js
whatsapp-service/src/botAgent.js
Lo que verificaremos:

Conexión WhatsApp funciona
Multi-sesión funciona (puertos dinámicos)
Bot responde correctamente
Configuración del bot se guarda
Notificaciones funcionan


FASE 7: Planes y Suscripciones
Archivos a presentar:
sistema/cliente/modulos/mi-plan.php
sistema/api/v1/suscripciones/*.php (todos)
sistema/api/v1/pagos/*.php (todos)
cron/check-payments.php
Lo que verificaremos:

Límites por plan funcionan
Trial se controla correctamente
Cambios de plan funcionan
Pagos se registran
Vencimientos se detectan


FASE 8: Panel SuperAdmin
Archivos a presentar:
sistema/superadmin/modulos/empresas.php
sistema/superadmin/modulos/planes.php
sistema/superadmin/modulos/pagos.php
sistema/superadmin/modulos/configuracion.php
sistema/superadmin/modulos/emails.php
sistema/superadmin/modulos/logs.php
Lo que verificaremos:

Gestión de empresas funciona
Gestión de planes funciona
Suspensión/activación funciona
Configuración global funciona
Plantillas email funcionan
Logs se registran


FASE 9: Seguridad y Permisos
Archivos a presentar:
includes/auth.php
includes/superadmin_session_check.php
includes/security.php
config/app.php
Lo que verificaremos:

Validación de sesiones correcta
Multi-tenancy no se puede bypassear
CSRF tokens funcionan
SQL injection prevenido
XSS prevenido
SuperAdmin tiene acceso total


FASE 10: Cron Jobs y Automatización
Archivos a presentar:
cron/check-payments.php
cron/send-reminders.php
cron/clean-sessions.php
cron/procesar_cola.php
cron/procesar_programados.php
Lo que verificaremos:

Todos los cron ejecutan sin errores
Lógica es correcta
No suspenden al SuperAdmin
Emails se envían correctamente


📝 Metodología de Trabajo
Para cada fase:

Presentas los archivos solicitados
Pruebas la funcionalidad manualmente
Me dices qué errores encontraste (si hay)
Yo reviso el código
Identifico problemas y doy soluciones
Implementas correcciones
Vuelves a probar
Pasamos a la siguiente fase


⚠️ Reglas importantes:

NO crearemos tablas nuevas
NO rehaceremos desde cero
Solo CORREGIMOS lo existente
Avanzamos fase por fase, no saltamos

_______________________

# CHANGELOGS

📝 CHANGELOG - FASE 1: AUDITORÍA DE BASE DE DATOS
Cambios ejecutados el 04-10-2025

✅ PASO 1: Eliminación de columna duplicada
sqlALTER TABLE `empresas` DROP COLUMN `fecha_expiracion_trial`;
Razón: Duplicaba funcionalidad de suscripciones.fecha_fin. Control de fechas centralizado en tabla suscripciones.

✅ PASO 2: Eliminación de tablas duplicadas/obsoletas
A) Tabla de suscripciones duplicada:
sqlDROP TABLE `suscripciones_pago`;
Razón: Duplicaba suscripciones. Funcionalidad consolidada en una sola tabla.
B) Columna agregada para IDs externos:
sqlALTER TABLE `suscripciones` 
ADD COLUMN `referencia_externa` VARCHAR(100) AFTER `metodo_pago`,
ADD INDEX `idx_referencia_externa` (`referencia_externa`);
Razón: Para almacenar IDs de MercadoPago/PayPal sin necesidad de tabla separada.
C) Tabla obsoleta de sistema mono-empresa:
sqlDROP TABLE `whatsapp_sesion`;
Razón: Sistema viejo mono-empresa. Ahora se usa whatsapp_sesiones_empresa.

✅ PASO 3: Eliminación de tabla sin uso
sqlDROP TABLE `conocimiento_bot`;
Razón: Nunca se implementó. Funcionalidad cubierta por configuracion_bot.

✅ PASO 4: Foreign Keys agregadas (Integridad Referencial)
sqlALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categorias_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `configuracion_bot`
  ADD CONSTRAINT `fk_config_bot_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `conversaciones_bot`
  ADD CONSTRAINT `fk_conversaciones_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `estados_conversacion`
  ADD CONSTRAINT `fk_estados_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `cola_mensajes`
  ADD CONSTRAINT `fk_cola_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `historial_mensajes`
  ADD CONSTRAINT `fk_historial_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `mensajes_programados`
  ADD CONSTRAINT `fk_programados_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

ALTER TABLE `plantillas_mensajes`
  ADD CONSTRAINT `fk_plantillas_empresa` 
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;
Razón: Asegurar integridad referencial. Si se elimina una empresa, todos sus datos se eliminan automáticamente.

✅ PASO 5: Corrección de UNIQUE Keys
sql-- Antes: nombre único globalmente
-- Después: nombre único por empresa
ALTER TABLE `categorias`
  DROP KEY `nombre_unique`,
  ADD UNIQUE KEY `nombre_empresa_unique` (`empresa_id`, `nombre`);

ALTER TABLE `contactos`
  DROP KEY `numero_unique`,
  ADD UNIQUE KEY `numero_empresa_unique` (`empresa_id`, `numero`);
Razón: Permitir que diferentes empresas usen los mismos nombres de categorías o números de contacto.

✅ PASO 6: Columna faltante
sqlALTER TABLE `cola_mensajes` 
ADD COLUMN `prioridad` TINYINT(1) DEFAULT 1 
COMMENT '1=Normal, 2=Alta, 3=Urgente' 
AFTER `estado`;
Razón: Corregir error en cron/procesar_cola.php que intentaba ordenar por columna inexistente.

📊 Resumen de impacto

Tablas eliminadas: 3 (suscripciones_pago, whatsapp_sesion, conocimiento_bot)
Columnas eliminadas: 1 (empresas.fecha_expiracion_trial)
Columnas agregadas: 2 (suscripciones.referencia_externa, cola_mensajes.prioridad)
Foreign keys agregadas: 8
UNIQUE keys corregidas: 2
Total de tablas en BD: 42 (antes: 45)


⚠️ Archivos PHP que necesitan actualización
Debido a la eliminación de empresas.fecha_expiracion_trial, estos archivos deben modificarse:

cron/check-payments.php (reemplazar referencias a fecha_expiracion_trial)
cron/send-reminders.php (idem)
sistema/superadmin/modulos/empresas.php (cambiar query con JOIN a suscripciones)
Cualquier API que lea fecha_expiracion_trial

Estado: Pendiente de corrección en FASE 2+