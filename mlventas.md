CONTEXTO DEL PROYECTO:
Estoy construyendo un bot de ventas conversacional para WhatsApp con arquitectura híbrida ML + GPT.

ARQUITECTURA:
1. Motor ML (Python + scikit-learn): Detecta intenciones con TF-IDF + Logistic Regression
2. WhatsApp Service (Node.js): Orquesta la conversación
3. GPT (OpenAI): Genera respuestas naturales contextuales y actúa como maestro

CARACTERÍSTICAS CLAVE:
✅ 100% conversacional (NO menús tipo 1,2,3)
✅ Aprende continuamente (se reentrena cada 50 ejemplos)
✅ Maneja objeciones y problemas (sin yape, sin stock, etc.)
✅ Escala inteligentemente a humanos
✅ Notifica ventas por WhatsApp
✅ Recuerda contexto conversacional
✅ Lee TODO desde BD (0% hardcoded)

FLUJO DE MENSAJE:
1. Mensaje llega → ML clasifica intención + confianza
2. Si confianza >=0.80 → Ejecuta acción directa
3. Si confianza <0.80 → GPT analiza (modo maestro)
4. GPT genera respuesta consultando BD (productos, precios, métodos pago)
5. Guarda conversación para reentrenamiento
6. Detecta cuándo escalar o notificar venta

COMPORTAMIENTO ESPERADO:
- Si cliente dice "hola, que promociones hay?" → Lista promos amigablemente
- Si cliente dice "no hay otra cosa" → Ofrece catálogo regular naturalmente
- Si cliente dice "no tengo yape" → Ofrece alternativas de pago sin perder venta
- Si cliente frustrado → Escala a humano automáticamente
- Si venta completada → Envía notificación WhatsApp a números configurados

BASES DE DATOS:
- configuracion_bot: Personalidad, prompts, horarios (por empresa_id)
- configuracion_negocio: Datos negocio, métodos pago (por empresa_id)
- catalogo_bot: Productos, precios, promociones (por empresa_id)
- conversaciones_bot: Historial de mensajes
- training_samples: Ejemplos para ML con intención detectada/confirmada
- ventas_bot: Ventas confirmadas
- estados_conversacion: Control de escalamientos
- notificaciones_bot: Config de notificaciones WhatsApp

INTENCIONES PRINCIPALES:
- saludo, despedida, agradecimiento
- consultar_precio, consultar_catalogo, consultar_disponibilidad
- consultar_pago, consultar_delivery, consultar_promociones
- agregar_producto, ver_carrito, modificar_cantidad, vaciar_carrito
- confirmar_pedido, cancelar_pedido, confirmar_pago
- solicitar_humano, queja_reclamo, problema_pago

REQUISITOS TÉCNICOS:
- Nada hardcoded (todo desde BD)
- Contexto de últimos 5 mensajes siempre
- GPT usa temperatura y max_tokens desde configuracion_plataforma
- Modelo OpenAI desde configuracion_plataforma
- Function calling para acciones del carrito
- Notificaciones solo si notificaciones_bot.notificar_ventas=1

NECESITO QUE ME AYUDES A:
1. Diseñar la arquitectura de archivos completa
2. Crear el motor ML en Python (FastAPI)
3. Rehacer completamente salesBot.js con el nuevo enfoque
4. Crear intentRouter.js (ejecutor de acciones)
5. Crear gptTeacher.js (maestro + fallback)
6. Crear contextManager.js (maneja historial)
7. Integrar todo el flujo de notificaciones
8. Sistema de escalamiento inteligente

El bot debe ser MIL VECES MEJOR que cualquier otro bot: natural, inteligente, persistente, y que realmente VENDA.


📂 ARCHIVOS QUE DEBES MOSTRARME
Para empezar a construir el bot, necesito ver estos archivos en orden de prioridad:
🔴 PRIORIDAD ALTA (necesarios ahora)

Base de datos actual:

mensajeropro_saas.sql (ya lo tienes arriba, pero confirmame si hay cambios)


Configuración actual:

config/app.php - Para ver constantes y configuración base
config/database.php - Conexión a BD


WhatsApp Service actual:

whatsapp-service/src/index.js - Entry point del servicio
whatsapp-service/src/botHandler.js - Manejador actual del bot
whatsapp-service/package.json - Dependencias actuales


API de procesamiento de mensajes:

sistema/api/v1/bot/configurar.php - Cómo se guarda config
sistema/api/v1/bot/guardar-notificaciones.php - Cómo se guardan notifs



🟡 PRIORIDAD MEDIA (para integración)

Includes importantes:

includes/functions.php - Funciones helper
includes/auth.php - Sistema de autenticación


Ejemplo de template actual:

sistema/api/v1/bot/cargar-template.php - Para entender estructura



🟢 PRIORIDAD BAJA (para mejorar después)

Módulos relacionados:

sistema/cliente/modulos/catalogo-bot.php (ya lo tienes arriba)
sistema/cliente/modulos/bot-config.php (ya lo tienes arriba)




📋 INFORMACIÓN ADICIONAL QUE NECESITO
Además de los archivos, necesito saber:
1. Estructura actual del servidor:
¿Dónde está corriendo cada servicio?

- Node.js (WhatsApp): Puerto _____ 
- MySQL: Puerto _____
- Python (ML Engine) estará en: Puerto _____ (sugiero 5000)
- Nginx/Apache: Puerto _____
2. Credenciales OpenAI:
¿Cómo se guardan en BD?
- Tabla: configuracion_plataforma
- Claves: 
  - openai_api_key
  - openai_modelo (gpt-3.5-turbo, gpt-4, etc.)
  - openai_max_tokens
  - openai_temperatura
3. Sistema actual de notificaciones:
¿Cómo envía WhatsApp actualmente?
- Via wppconnect
- Via baileys
- Via API personalizada

¿Dónde está el código que envía mensajes?
4. Versión de dependencias:
- Node.js: v_____
- Python: v_____ (sugiero 3.9+)
- Ubuntu: 24.04 (confirmado)

🚀 PLAN DE TRABAJO SUGERIDO
Te propongo construirlo en este orden:
FASE 1: Motor ML (Python) ⏱️ ~2-3 horas

Crear estructura ml-engine/
Dataset base con 500 ejemplos de intenciones
Entrenador inicial (scikit-learn)
API FastAPI con endpoints /classify y /train
Probar clasificación básica

FASE 2: Integración WhatsApp Service ⏱️ ~3-4 horas

Crear botOrchestrator.js (orquestador principal)
Crear contextManager.js (manejo de historial)
Modificar entry point para usar nuevo orquestador
Probar flujo: mensaje → ML → respuesta

FASE 3: GPT Teacher + Intent Router ⏱️ ~2-3 horas

Crear gptTeacher.js (maestro + fallback)
Crear intentRouter.js (ejecuta acciones)
Integrar prompts dinámicos desde BD
Probar casos de alta y baja confianza

FASE 4: Sistema de Carrito ⏱️ ~2 horas

Manejo de estado de carrito en memoria
Function calling para acciones
Flujo completo de venta
Confirmación de pago

FASE 5: Escalamiento y Notificaciones ⏱️ ~1-2 horas

Lógica de escalamiento inteligente
Sistema de notificaciones WhatsApp
Estados de conversación
Pruebas de flujos completos

FASE 6: Panel SuperAdmin ⏱️ ~2 horas

sistema/superadmin/modulos/ml-control.php
APIs de entrenamiento y métricas
Cron jobs de auto-entrenamiento
Limpieza automática

TOTAL: ~12-15 horas de desarrollo

✅ SIGUIENTE PASO
Por favor comparte:

✅ Los archivos de PRIORIDAD ALTA listados arriba
✅ Responde las 4 preguntas de INFORMACIÓN ADICIONAL
✅ Confirma si el PLAN DE TRABAJO te parece bien o quieres ajustarlo

Cuando tenga esa información, empezaré con la FASE 1: Motor ML y te iré entregando código funcional fase por fase. 🚀

# 📋 CHANGELOG - FASE 5: Escalamiento y Notificaciones
🎯 Resumen
Sistema completo de escalamiento a humanos con detección automática de intervención para diferentes tamaños de negocio.

✨ Nuevas Funcionalidades
1. Sistema de Escalamiento Manual

Palabras clave configurables (ej: "hablar con humano", "queja")
Bot se pausa automáticamente al detectar palabra clave
Panel para ver conversaciones escaladas pendientes
Botón "Marcar como resuelto" para reactivar bot

2. Sistema de Intervención Humana (Opcional)

Detección automática cuando operador responde desde otro número
Bot se pausa sin intervención manual
Timeout configurable (default: 2 minutos)
Reactivación automática o manual
Ideal para negocios con múltiples operadores

3. Panel de Gestión

Vista unificada de escalamientos + intervenciones activas
Estadísticas en tiempo real (pendientes, resueltos hoy)
Historial de conversación por cliente
Auto-refresh cada 30 segundos
Botón directo para abrir WhatsApp con el cliente


🔧 Componentes Nuevos
Base de Datos
- Tabla: intervencion_humana (control de pausas automáticas)
- Mejoras: estados_conversacion (ya existía)
APIs
- reactivar-bot.php (reactivar bot manualmente)
- marcar-resuelto.php (resolver escalamiento)
- historial-conversacion.php (ya existía, mejorado)
WhatsApp Service
- whatsapp-wppconnect.js: detectarIntervencionOperador()
- botHandler.js: verificarIntervencionHumana()
Panel Cliente
- escalados.php: mejorado con tab de intervenciones
- bot-config.php: config de intervención humana

🎛️ Configuración
Opciones añadidas:

✅ Activar/desactivar detección automática
✅ Timeout de reactivación (30-600 segundos)
✅ Números de operadores (para detección)
✅ Palabras clave de escalamiento


🔨 Correcciones

✅ Moneda dinámica desde BD (eliminado hardcoding)
✅ Collation UTF8 corregido (intervencion_humana)
✅ Mejoras en notificaciones de escalamiento