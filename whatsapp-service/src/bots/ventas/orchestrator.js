// whatsapp-service/src/bots/ventas/orchestrator.js - VERSIÓN DINÁMICA
const axios = require("axios");
const db = require("../../database");
const ContextManager = require("../../shared/contextManager");
const VentasIntentRouter = require("./intentRouter");
const VentasGPTTeacher = require("./gptTeacher");

class VentasOrchestrator {
  constructor(salesBot, whatsappClient = null) {
    this.salesBot = salesBot;
    this.whatsappClient = whatsappClient;
    this.contextManager = new ContextManager();
    this.intentRouter = new VentasIntentRouter(salesBot);
    this.gptTeacher = new VentasGPTTeacher();

    // ✅ TODO DINÁMICO - Se carga desde BD
    this.ML_ENGINE_URL = null;
    this.UMBRAL_CONFIANZA = 0.8; // Default, se sobrescribe
    this.AUTO_RETRAIN_EXAMPLES = 50; // Default, se sobrescribe

    // Cache de configuración
    this.configCache = new Map();
    this.platformConfigCache = null;
    this.platformConfigTimestamp = 0;

    // Cargar config inicial
    this.cargarConfiguracionPlataforma();

    // Limpiar cache cada 5 minutos
    setInterval(() => {
      this.contextManager.limpiarCache();
      this.configCache.clear();
    }, 300000);

    // Recargar config de plataforma cada 2 minutos
    setInterval(() => {
      this.cargarConfiguracionPlataforma();
    }, 120000);
  }

  /**
   * ✅ NUEVO: Carga configuración dinámica desde BD
   */
  async cargarConfiguracionPlataforma() {
    try {
      const [rows] = await db.getPool().execute(
        `SELECT clave, valor FROM configuracion_plataforma 
            WHERE clave IN ('ml_engine_port', 'ml_umbral_confianza', 'ml_auto_retrain_examples')`
      );

      const config = {};
      rows.forEach((row) => {
        config[row.clave] = row.valor;
      });

      // ✅ SIMPLE: Siempre localhost (mismo servidor)
      const mlPort = config.ml_engine_port || "5000";
      this.ML_ENGINE_URL = `http://localhost:${mlPort}`;

      // Umbrales dinámicos
      this.UMBRAL_CONFIANZA = parseFloat(config.ml_umbral_confianza || "0.80");
      this.AUTO_RETRAIN_EXAMPLES = parseInt(
        config.ml_auto_retrain_examples || "50"
      );

      console.log("✅ Config plataforma cargada:");
      console.log(`   ML Engine URL: ${this.ML_ENGINE_URL}`);
      console.log(`   Umbral confianza: ${this.UMBRAL_CONFIANZA}`);
      console.log(`   Auto-retrain ejemplos: ${this.AUTO_RETRAIN_EXAMPLES}`);
    } catch (error) {
      console.error("❌ Error cargando config plataforma:", error);
      // Valores por defecto
      this.ML_ENGINE_URL = "http://localhost:5000";
      this.UMBRAL_CONFIANZA = 0.8;
      this.AUTO_RETRAIN_EXAMPLES = 50;
    }
  }

  /**
   * ✅ NUEVO: Detecta si estamos en local o producción
   */
  esEntornoLocal() {
    // Método 1: Variable de entorno
    if (process.env.NODE_ENV === "production") {
      return false;
    }

    // Método 2: Detectar localhost en hostname
    const hostname = require("os").hostname().toLowerCase();
    if (hostname.includes("localhost") || hostname.includes("127.0.0.1")) {
      return true;
    }

    // Método 3: Detectar Windows (desarrollo) vs Linux (producción)
    const platform = process.platform;
    if (platform === "win32") {
      return true; // Windows = local
    }

    // Por defecto, asumir local si no está explícito
    return true;
  }

  /**
   * PUNTO DE ENTRADA PRINCIPAL
   */
  async procesarMensaje(mensaje, numero, empresaId) {
    const inicioTotal = Date.now();
    console.log("\n🎬 ============================================");
    console.log(`📱 MENSAJE DE: ${numero}`);
    console.log(`💬 CONTENIDO: "${mensaje}"`);
    console.log(`🏢 EMPRESA ID: ${empresaId}`);
    console.log("============================================\n");

    try {
      // Verificar que config está cargada
      if (!this.ML_ENGINE_URL) {
        await this.cargarConfiguracionPlataforma();
      }

      // 1. Verificar si bot debe responder
      const puedeResponder = await this.verificarSiDebeResponder(
        numero,
        empresaId
      );
      if (!puedeResponder.puede) {
        console.log(`⛔ Bot no debe responder: ${puedeResponder.razon}`);
        return puedeResponder.mensaje || null;
      }

      // 2. Aplicar delay de respuesta (simular escritura)
      await this.aplicarDelay(empresaId);

      // 3. Obtener contexto de conversación
      const contexto = await this.contextManager.obtenerContexto(
        numero,
        empresaId
      );
      console.log(`📚 Contexto obtenido: ${contexto.length} mensajes previos`);

      // 4. Clasificar intención con ML Engine
      const clasificacionML = await this.clasificarConML(
        mensaje,
        contexto,
        empresaId
      );
      console.log(`🤖 ML Engine respondió:`);
      console.log(`   Intención: ${clasificacionML.intencion}`);
      console.log(
        `   Confianza: ${(clasificacionML.confianza * 100).toFixed(1)}%`
      );
      console.log(
        `   Umbral requerido: ${(this.UMBRAL_CONFIANZA * 100).toFixed(1)}%`
      );

      let respuestaFinal;
      let tipoRespuesta;
      let tokensUsados = 0;

      // 5. DECISIÓN: ¿Confianza suficiente para ejecutar directamente?
      if (clasificacionML.confianza >= this.UMBRAL_CONFIANZA) {
        console.log(
          `✅ Confianza alta (>=${
            this.UMBRAL_CONFIANZA * 100
          }%) - Ejecutando con IntentRouter`
        );

        const resultado = await this.intentRouter.ejecutarAccion(
          clasificacionML.intencion,
          mensaje,
          numero,
          empresaId,
          contexto
        );

        if (resultado.procesado) {
          respuestaFinal = resultado.respuesta;
          tipoRespuesta = "intent_router";

          if (resultado.escalar) {
            await this.notificarEscalamiento(numero, empresaId, mensaje);
          }
        } else {
          console.log(`⚠️ IntentRouter no procesó, delegando a GPT Teacher`);
          const resultadoGPT = await this.gptTeacher.procesarComoMaestro(
            mensaje,
            numero,
            empresaId,
            contexto,
            clasificacionML.intencion,
            clasificacionML.confianza
          );

          respuestaFinal = resultadoGPT.respuesta;
          tipoRespuesta = "gpt_fallback";
          tokensUsados = resultadoGPT.tokens;
        }
      } else {
        console.log(
          `⚠️ Confianza baja (<${
            this.UMBRAL_CONFIANZA * 100
          }%) - GPT Teacher modo MAESTRO`
        );

        const resultadoGPT = await this.gptTeacher.procesarComoMaestro(
          mensaje,
          numero,
          empresaId,
          contexto,
          clasificacionML.intencion,
          clasificacionML.confianza
        );

        respuestaFinal = resultadoGPT.respuesta;
        tipoRespuesta = "gpt_teacher";
        tokensUsados = resultadoGPT.tokens;
      }

      // 6. Guardar conversación en BD
      const tiempoTotal = Date.now() - inicioTotal;
      await this.contextManager.guardarConversacion({
        empresaId,
        numero,
        mensajeCliente: mensaje,
        respuestaBot: respuestaFinal,
        intencionDetectada: clasificacionML.intencion,
        confianza: clasificacionML.confianza,
        tokensUsados,
        tiempoRespuesta: tiempoTotal,
        contexto: contexto,
      });

      // 7. Registrar métrica
      await this.registrarMetrica("conversacion_completada", empresaId);

      // 8. Retornar respuesta
      console.log(`\n✅ RESPUESTA GENERADA (${tipoRespuesta}):`);
      console.log(`💬 "${respuestaFinal.substring(0, 100)}..."`);
      console.log(`⏱️ Tiempo total: ${tiempoTotal}ms`);
      console.log(`🎯 Tokens usados: ${tokensUsados}`);
      console.log("============================================\n");

      return {
        respuesta: respuestaFinal,
        tipo: tipoRespuesta,
        intencion: clasificacionML.intencion,
        confianza: clasificacionML.confianza,
        tokens: tokensUsados,
        tiempo: tiempoTotal,
      };
    } catch (error) {
      console.error("❌ ERROR en VentasOrchestrator:", error);

      return {
        respuesta:
          "Disculpa, tuve un problema técnico 😅 ¿Podrías repetir tu mensaje?",
        tipo: "error",
        error: error.message,
      };
    }
  }

  /**
   * Clasifica intención con ML Engine (usando URL dinámica)
   */
  async clasificarConML(mensaje, contexto, empresaId) {
    try {
      const contextoML = this.contextManager.formatearParaML(contexto);

      console.log(`🔗 Llamando a ML Engine: ${this.ML_ENGINE_URL}/classify`);

      const response = await axios.post(
        `${this.ML_ENGINE_URL}/classify`,
        {
          texto: mensaje,
          contexto: contextoML,
        },
        {
          timeout: 5000,
          headers: {
            "Content-Type": "application/json",
          },
        }
      );

      if (response.data.success) {
        return {
          intencion: response.data.intencion,
          confianza: response.data.confianza,
          modelo_version: response.data.modelo_version,
        };
      } else {
        throw new Error("ML Engine no retornó éxito");
      }
    } catch (error) {
      console.error("⚠️ Error contactando ML Engine:", error.message);

      // Fallback: Asumir baja confianza y delegar a GPT
      return {
        intencion: "conversacion_general",
        confianza: 0.0,
        error: error.message,
      };
    }
  }

  // ... resto de métodos sin cambios (verificarSiDebeResponder, etc.) ...

  async verificarSiDebeResponder(numero, empresaId) {
    try {
      const config = await this.obtenerConfigBot(empresaId);

      if (!config || !config.activo) {
        return { puede: false, razon: "Bot desactivado" };
      }

      if (config.modo_prueba && config.numero_prueba) {
        const numeroPrueba = config.numero_prueba.replace(/\D/g, "");
        const numeroActual = numero.replace(/\D/g, "").replace(/^51/, "");

        if (
          !numeroActual.includes(numeroPrueba) &&
          !numeroPrueba.includes(numeroActual)
        ) {
          return { puede: false, razon: "Modo prueba - número no autorizado" };
        }
      }

      const [estadoRows] = await db
        .getPool()
        .execute(
          "SELECT estado FROM estados_conversacion WHERE numero_cliente = ? AND empresa_id = ?",
          [numero, empresaId]
        );

      if (estadoRows.length > 0 && estadoRows[0].estado === "escalado_humano") {
        return { puede: false, razon: "Conversación escalada a humano" };
      }

      if (!this.estaEnHorario(config)) {
        return {
          puede: false,
          razon: "Fuera de horario",
          mensaje: {
            respuesta:
              config.mensaje_fuera_horario ||
              "🕐 Gracias por tu mensaje. Nuestro horario de atención ha finalizado. Te responderemos en el próximo horario.",
            tipo: "fuera_horario",
          },
        };
      }

      if (!config.responder_no_registrados) {
        const [contactoRows] = await db
          .getPool()
          .execute(
            "SELECT id FROM contactos WHERE numero = ? AND empresa_id = ? AND activo = 1",
            [numero.replace("@c.us", ""), empresaId]
          );

        if (contactoRows.length === 0) {
          return { puede: false, razon: "Cliente no registrado" };
        }
      }

      return { puede: true };
    } catch (error) {
      console.error("Error verificando si debe responder:", error);
      return { puede: false, razon: "Error en validación" };
    }
  }

  estaEnHorario(config) {
    if (!config.horario_inicio || !config.horario_fin) {
      return true;
    }

    const ahora = new Date();
    const horaActual = ahora.getHours() * 60 + ahora.getMinutes();

    const [horaInicio, minInicio] = config.horario_inicio
      .split(":")
      .map(Number);
    const [horaFin, minFin] = config.horario_fin.split(":").map(Number);

    const inicioMinutos = horaInicio * 60 + minInicio;
    const finMinutos = horaFin * 60 + minFin;

    if (finMinutos >= inicioMinutos) {
      return horaActual >= inicioMinutos && horaActual <= finMinutos;
    } else {
      return horaActual >= inicioMinutos || horaActual <= finMinutos;
    }
  }

  async aplicarDelay(empresaId) {
    const config = await this.obtenerConfigBot(empresaId);
    const delay = config?.delay_respuesta || 5;

    console.log(`⏳ Aplicando delay de ${delay}s...`);
    await new Promise((resolve) => setTimeout(resolve, delay * 1000));
  }

  async obtenerConfigBot(empresaId) {
    const cacheKey = `config_${empresaId}`;

    if (this.configCache.has(cacheKey)) {
      const cached = this.configCache.get(cacheKey);
      if (Date.now() - cached.timestamp < 60000) {
        return cached.config;
      }
    }

    try {
      const [rows] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          empresaId,
        ]);

      const config = rows[0] || null;

      if (config) {
        if (config.palabras_activacion) {
          config.palabras_activacion = JSON.parse(config.palabras_activacion);
        }
        if (config.escalamiento_config) {
          config.escalamiento_config = JSON.parse(config.escalamiento_config);
        }
      }

      this.configCache.set(cacheKey, {
        config,
        timestamp: Date.now(),
      });

      return config;
    } catch (error) {
      console.error("Error obteniendo config bot:", error);
      return null;
    }
  }

  async notificarEscalamiento(numero, empresaId, mensaje) {
    try {
      const [notif] = await db.getPool().execute(
        `SELECT numeros_notificacion, mensaje_escalamiento, notificar_escalamiento 
                FROM notificaciones_bot 
                WHERE empresa_id = ?`,
        [empresaId]
      );

      if (!notif[0] || !notif[0].notificar_escalamiento) {
        return;
      }

      const numeros = JSON.parse(notif[0].numeros_notificacion || "[]");
      if (numeros.length === 0) {
        return;
      }

      let mensajeNotif =
        notif[0].mensaje_escalamiento ||
        '🚨 *ESCALAMIENTO URGENTE*\n\nCliente: {numero}\nMensaje: "{mensaje}"\nHora: {hora}';

      mensajeNotif = mensajeNotif
        .replace("{numero}", numero.replace("@c.us", ""))
        .replace("{mensaje}", mensaje)
        .replace(
          "{hora}",
          new Date().toLocaleTimeString("es-PE", {
            hour: "2-digit",
            minute: "2-digit",
          })
        );

      if (this.whatsappClient) {
        for (const numeroNotif of numeros) {
          await this.whatsappClient.sendMessage(numeroNotif, mensajeNotif);
          console.log(`📢 Notificación enviada a ${numeroNotif}`);
        }
      }
    } catch (error) {
      console.error("Error enviando notificación escalamiento:", error);
    }
  }

  async registrarMetrica(tipo, empresaId) {
    try {
      const fecha = new Date().toISOString().split("T")[0];

      const [existing] = await db
        .getPool()
        .execute(
          "SELECT id FROM bot_metricas WHERE empresa_id = ? AND fecha = ?",
          [empresaId, fecha]
        );

      if (existing.length === 0) {
        await db
          .getPool()
          .execute(
            "INSERT INTO bot_metricas (empresa_id, fecha) VALUES (?, ?)",
            [empresaId, fecha]
          );
      }

      let updateQuery = "";
      switch (tipo) {
        case "conversacion_iniciada":
          updateQuery =
            "UPDATE bot_metricas SET conversaciones_iniciadas = conversaciones_iniciadas + 1";
          break;
        case "conversacion_completada":
          updateQuery =
            "UPDATE bot_metricas SET conversaciones_completadas = conversaciones_completadas + 1";
          break;
        case "escalamiento":
          updateQuery =
            "UPDATE bot_metricas SET escalamientos = escalamientos + 1";
          break;
      }

      if (updateQuery) {
        await db
          .getPool()
          .execute(`${updateQuery} WHERE empresa_id = ? AND fecha = ?`, [
            empresaId,
            fecha,
          ]);
      }
    } catch (error) {
      console.error("Error registrando métrica:", error);
    }
  }
}

module.exports = VentasOrchestrator;
