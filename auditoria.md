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


FASE 2: Web Pública - Landing y Autenticación
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


FASE 3: Panel Cliente - Dashboard y Navegación
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

