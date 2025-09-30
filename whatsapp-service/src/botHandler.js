const db = require("./database");
const axios = require("axios");
const SalesBot = require("./salesBot");
const appointmentBot = require("./appointmentBot");
const AppointmentBot = require("./appointmentBot");

class BotHandler {
  constructor(whatsappClient = null) {
    this.config = null;
    this.conocimientos = [];
    this.conversaciones = new Map();
    this.whatsappClient = whatsappClient;
    this.loadConfig();
    this.salesBot = null;
    this.appointmentBot = null;

    // Recargar configuración cada 5 minutos
    setInterval(() => this.loadConfig(), 30 * 1000);
  }

  async loadConfig() {
    try {
      const [configRows] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE id = 1");

      if (configRows.length > 0) {
        this.config = configRows[0];
        this.config.palabras_activacion = JSON.parse(
          this.config.palabras_activacion || "[]"
        );

        // console.log("✅ Configuración del bot cargada desde BD");
        // console.log("   - Bot activo:", this.config.activo ? "SÍ" : "NO");
        const [globalConfig] = await db
          .getPool()
          .execute(
            "SELECT clave, valor FROM configuracion_plataforma WHERE clave IN ('openai_api_key', 'openai_modelo', 'openai_temperatura', 'openai_max_tokens')"
          );

        this.globalConfig = {};
        globalConfig.forEach((row) => {
          this.globalConfig[row.clave] = row.valor;
        });
        // console.log(
        //   "   - API Key Global:",
        //   this.globalConfig.openai_api_key ? "Configurada" : "NO CONFIGURADA"
        // );
        // console.log(
        //   "   - System prompt:",
        //   this.config.system_prompt ? "Configurado" : "NO configurado"
        // );
        // console.log(
        //   "   - Business info:",
        //   this.config.business_info ? "Configurada" : "NO configurada"
        // );
        // console.log(
        //   "   - Palabras activación:",
        //   this.config.palabras_activacion.length
        // );
      } else {
        console.log("❌ No se encontró configuración del bot en la BD");
      }

      if (this.config && this.config.tipo_bot === "ventas") {
        if (!this.salesBot) {
          this.salesBot = new SalesBot(this.config.empresa_id || 1, this);
          await this.salesBot.loadCatalog(); // Cargar catálogo aquí
        } else {
          // Actualizar solo la configuración sin recrear
          await this.salesBot.loadCatalog(); // Recargar catálogo
        }
      } else {
        this.salesBot = null;
      }

      if (this.config && this.config.tipo_bot === "citas") {
        if (!this.appointmentBot) {
          this.appointmentBot = new AppointmentBot(
            this.config.empresa_id || 1,
            this
          );
          await this.appointmentBot.loadConfig(); // Cargar config aquí
        } else {
          // Actualizar solo la configuración sin recrear
          await this.appointmentBot.loadConfig(); // Recargar config
        }
      } else {
        this.appointmentBot = null;
      }

      this.conocimientos = [];
      // console.log("✅ Bot configurado sin base de conocimiento adicional");
    } catch (error) {
      // console.error("Error cargando configuración del bot:", error);
    }
  }

  async shouldRespond(mensaje, numero) {
    console.log("🔍 Verificando si el bot debe responder...");

    if (!this.config || !this.config.activo) {
      console.log("❌ Bot no está activo");
      return false;
    }

    // Verificar estado de escalamiento
    try {
      const [estadoRows] = await db
        .getPool()
        .execute(
          "SELECT estado FROM estados_conversacion WHERE numero_cliente = ?",
          [numero]
        );

      if (estadoRows.length > 0 && estadoRows[0].estado === "escalado_humano") {
        console.log(
          `📵 Conversación escalada a humano para ${numero}, bot no responde`
        );
        return false;
      }
    } catch (error) {
      console.error("Error verificando estado de escalamiento:", error);
    }

    // Verificar horario
    if (!this.isInSchedule()) {
      console.log("🕐 Fuera de horario");
      return "fuera_horario";
    }

    // Verificar si es un número registrado
    const [contactoRows] = await db
      .getPool()
      .execute("SELECT id FROM contactos WHERE numero = ?", [
        numero.replace("@c.us", ""),
      ]);

    const esRegistrado = contactoRows.length > 0;
    console.log(`📱 Número ${esRegistrado ? "SÍ" : "NO"} está registrado`);

    if (!esRegistrado && !this.config.responder_no_registrados) {
      console.log("❌ No responder a no registrados");
      return false;
    }

    // Verificar palabras de activación
    if (
      this.config.palabras_activacion &&
      this.config.palabras_activacion.length > 0
    ) {
      const mensajeLower = mensaje.toLowerCase();
      const coincide = this.config.palabras_activacion.some((palabra) =>
        mensajeLower.includes(palabra.toLowerCase())
      );

      if (!coincide) {
        console.log("❌ No coincide con palabras de activación");
        return false;
      }
    }

    console.log("✅ Bot SÍ debe responder");
    return true;
  }

  isInSchedule() {
    if (!this.config.horario_inicio || !this.config.horario_fin) {
      return true;
    }

    const ahora = new Date();
    const horaActual = ahora.getHours() * 60 + ahora.getMinutes();

    const [horaInicio, minInicio] = this.config.horario_inicio
      .split(":")
      .map(Number);
    const [horaFin, minFin] = this.config.horario_fin.split(":").map(Number);

    const inicioMinutos = horaInicio * 60 + minInicio;
    const finMinutos = horaFin * 60 + minFin;

    if (finMinutos >= inicioMinutos) {
      return horaActual >= inicioMinutos && horaActual <= finMinutos;
    } else {
      return horaActual >= inicioMinutos || horaActual <= finMinutos;
    }
  }

  async processMessage(mensaje, numero) {
    try {
      console.log("📝 [processMessage] Iniciando procesamiento");
      const shouldRespond = await this.shouldRespond(mensaje, numero);

      if (!shouldRespond) {
        return null;
      }

      if (shouldRespond === "fuera_horario") {
        return {
          respuesta:
            this.config.mensaje_fuera_horario ||
            "Gracias por tu mensaje. Nuestro horario de atención ha finalizado.",
          tipo: "fuera_horario",
        };
      }

      // Verificar modo prueba
      if (this.config.modo_prueba && this.config.numero_prueba) {
        // Limpiar números para comparar
        const numeroPrueba = this.config.numero_prueba.replace(/\D/g, "");
        const numeroActual = numero.replace(/\D/g, "").replace(/^51/, ""); // Quitar código país

        if (
          !numeroActual.includes(numeroPrueba) &&
          !numeroPrueba.includes(numeroActual)
        ) {
          console.log("🔒 Modo prueba activo - número no autorizado");
          return null;
        }
      }

      // Verificar respuestas rápidas primero
      const respuestaRapida = await this.checkRespuestaRapida(mensaje);
      if (respuestaRapida) {
        console.log("⚡ Respuesta rápida encontrada");
        return {
          respuesta: respuestaRapida,
          tipo: "respuesta_rapida",
        };
      }

      // Si es bot de ventas, delegar al salesBot
      if (this.config.tipo_bot === "ventas" && this.salesBot) {
        const ventaResponse = await this.salesBot.procesarMensajeVenta(
          mensaje,
          numero
        );

        // Si necesita enviar archivo PDF
        if (ventaResponse.archivo) {
          // El WhatsApp client manejará el envío del PDF
          return {
            respuesta: ventaResponse.respuesta,
            tipo: ventaResponse.tipo,
            archivo: ventaResponse.archivo,
          };
        }

        return {
          respuesta: ventaResponse.respuesta,
          tipo: ventaResponse.tipo,
        };
      }

      if (this.config.tipo_bot === "citas" && this.appointmentBot) {
        const citaResponse = await this.appointmentBot.procesarMensajeCita(
          mensaje,
          numero
        );

        return {
          respuesta: citaResponse.respuesta,
          tipo: citaResponse.tipo,
        };
      }

      // Detectar si necesita escalamiento
      const necesitaEscalamiento = await this.checkEscalamiento(mensaje);

      if (necesitaEscalamiento) {
        // Usar configuración de escalamiento
        const escalamientoConfig = JSON.parse(
          this.config.escalamiento_config || "{}"
        );
        const mensajeEscalamiento =
          escalamientoConfig.mensaje_escalamiento ||
          this.config.mensaje_escalamiento ||
          "Tu consulta requiere atención personalizada. Un asesor te atenderá en breve.";

        // Marcar conversación como escalada
        await db.getPool().execute(
          `INSERT INTO estados_conversacion (numero_cliente, estado, fecha_escalado, motivo_escalado, empresa_id) 
                 VALUES (?, 'escalado_humano', NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE 
                    estado = 'escalado_humano', 
                    fecha_escalado = NOW(),
                    motivo_escalado = ?`,
          [numero, mensaje, getEmpresaActual(), mensaje]
        );

        // Registrar métrica de escalamiento
        await this.registrarMetrica("escalamiento");

        // Notificar escalamiento si está configurado
        if (
          this.config.notificar_escalamiento &&
          this.config.numeros_notificacion &&
          this.whatsappClient
        ) {
          try {
            const numeros = JSON.parse(this.config.numeros_notificacion);
            if (numeros.length > 0) {
              // Preparar mensaje de notificación
              let mensajeNotificacion =
                this.config.mensaje_notificacion ||
                '🚨 *ESCALAMIENTO URGENTE*\n\nCliente: {numero}\nMensaje: "{ultimo_mensaje}"\nHora: {hora}';

              // Reemplazar variables
              mensajeNotificacion = mensajeNotificacion
                .replace("{numero}", numero.replace("@c.us", ""))
                .replace("{ultimo_mensaje}", mensaje)
                .replace("{motivo}", "Palabra clave detectada")
                .replace(
                  "{hora}",
                  new Date().toLocaleTimeString("es-PE", {
                    hour: "2-digit",
                    minute: "2-digit",
                  })
                );

              // Enviar notificación a cada número configurado
              for (const numeroNotificar of numeros) {
                console.log(
                  `📢 Enviando notificación de escalamiento a ${numeroNotificar}`
                );
                await this.whatsappClient.sendMessage(
                  numeroNotificar,
                  mensajeNotificacion
                );
              }
            }
          } catch (error) {
            console.error(
              "Error enviando notificaciones de escalamiento:",
              error
            );
          }
        }

        return {
          respuesta: mensajeEscalamiento,
          tipo: "escalamiento",
        };
      }

      // Generar respuesta con IA
      console.log(
        "📝 [processMessage] Aplicando delay de",
        this.config.delay_respuesta,
        "segundos"
      );
      await new Promise((resolve) =>
        setTimeout(resolve, this.config.delay_respuesta * 1000)
      );

      const contexto = await this.getContexto(numero);
      const respuestaIA = await this.generateResponse(mensaje, contexto);

      await this.saveConversation(numero, mensaje, respuestaIA);

      // Registrar métrica
      await this.registrarMetrica("conversacion_completada");

      return {
        respuesta: respuestaIA.content,
        tipo: "bot",
        tokens: respuestaIA.tokens,
        tiempo: respuestaIA.tiempo,
      };
    } catch (error) {
      console.error("❌ [processMessage] Error procesando mensaje:", error);
      return {
        respuesta: "Lo siento, tuve un problema al procesar tu mensaje 😅",
        tipo: "error",
      };
    }
  }

  async checkRespuestaRapida(mensaje) {
    try {
      const respuestasRapidas = JSON.parse(
        this.config.respuestas_rapidas || "{}"
      );
      const mensajeLower = mensaje.toLowerCase();

      for (const [pregunta, respuesta] of Object.entries(respuestasRapidas)) {
        if (mensajeLower.includes(pregunta.toLowerCase())) {
          return respuesta;
        }
      }
    } catch (e) {
      console.error("Error procesando respuestas rápidas:", e);
    }
    return null;
  }

  async checkEscalamiento(mensaje) {
    const escalamientoConfig = JSON.parse(
      this.config.escalamiento_config || "{}"
    );
    const palabrasClave = escalamientoConfig.palabras_clave || [];

    if (palabrasClave.length === 0) {
      // Si no hay configuración, usar las del campo anterior
      let frasesEscalamiento = [];
      try {
        if (this.config.frases_escalamiento) {
          frasesEscalamiento = JSON.parse(this.config.frases_escalamiento);
        }
      } catch (e) {
        if (this.config.frases_escalamiento) {
          frasesEscalamiento = this.config.frases_escalamiento
            .split(",")
            .map((f) => f.trim())
            .filter((f) => f.length > 0);
        }
      }

      if (frasesEscalamiento.length === 0) {
        return false;
      }

      palabrasClave.push(...frasesEscalamiento);
    }

    const mensajeLower = mensaje.toLowerCase();

    const necesitaEscalar = palabrasClave.some((frase) => {
      const contieneFrase = mensajeLower.includes(frase.toLowerCase());
      if (contieneFrase) {
        console.log(`🔍 Palabra de escalamiento detectada: "${frase}"`);
      }
      return contieneFrase;
    });

    // Verificar también si excedió mensajes sin resolver
    if (!necesitaEscalar && escalamientoConfig.max_mensajes_sin_resolver) {
      // Contar mensajes recientes sin respuesta satisfactoria
      const [rows] = await db.getPool().execute(
        `
            SELECT COUNT(*) as count 
            FROM conversaciones_bot 
            WHERE numero_cliente = ? 
            AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND empresa_id = ?
        `,
        [numero, getEmpresaActual()]
      );

      if (rows[0].count >= escalamientoConfig.max_mensajes_sin_resolver) {
        console.log("🔍 Escalamiento por múltiples mensajes sin resolver");
        return true;
      }
    }

    return necesitaEscalar;
  }

  // Registrar métricas
  async registrarMetrica(tipo) {
    try {
      const empresaId = getEmpresaActual();
      const fecha = new Date().toISOString().split("T")[0];

      // Verificar si existe registro de hoy
      const [existing] = await db
        .getPool()
        .execute(
          `SELECT id FROM bot_metricas WHERE empresa_id = ? AND fecha = ?`,
          [empresaId, fecha]
        );

      if (existing.length === 0) {
        // Crear registro
        await db
          .getPool()
          .execute(
            `INSERT INTO bot_metricas (empresa_id, fecha) VALUES (?, ?)`,
            [empresaId, fecha]
          );
      }

      // Actualizar métrica específica
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

  async getContexto(numero) {
    const [rows] = await db.getPool().execute(
      `SELECT mensaje_cliente, respuesta_bot, fecha_hora 
       FROM conversaciones_bot 
       WHERE numero_cliente = ? 
       ORDER BY fecha_hora DESC 
       LIMIT 5`,
      [numero]
    );

    return rows.reverse();
  }

  async generateResponse(mensaje, contexto) {
    const inicio = Date.now();

    // VERIFICAR configuración mínima
    if (!this.config.system_prompt || !this.globalConfig.openai_api_key) {
      throw new Error(
        "Bot no configurado correctamente - falta API Key global"
      );
    }

    // Usar prompt específico según tipo de bot
    let systemPrompt = this.config.system_prompt;

    if (this.config.tipo_bot === "ventas" && this.config.prompt_ventas) {
      systemPrompt += "\n\n" + this.config.prompt_ventas;
    } else if (this.config.tipo_bot === "citas" && this.config.prompt_citas) {
      systemPrompt += "\n\n" + this.config.prompt_citas;
    }

    // Agregar información del negocio si existe
    if (this.config.business_info) {
      systemPrompt += `\n\nINFORMACIÓN DEL NEGOCIO:\n${this.config.business_info}`;
    }

    // Agregar respuestas rápidas como referencia
    if (this.config.respuestas_rapidas) {
      const respuestasRapidas = JSON.parse(
        this.config.respuestas_rapidas || "{}"
      );
      if (Object.keys(respuestasRapidas).length > 0) {
        systemPrompt += "\n\nRESPUESTAS RÁPIDAS DISPONIBLES:\n";
        for (const [pregunta, respuesta] of Object.entries(respuestasRapidas)) {
          systemPrompt += `- ${pregunta}: ${respuesta}\n`;
        }
      }
    }

    // Construir mensajes con contexto
    const messages = [{ role: "system", content: systemPrompt }];

    // Agregar contexto de conversación
    contexto.forEach((conv) => {
      messages.push({ role: "user", content: conv.mensaje_cliente });
      if (conv.respuesta_bot) {
        messages.push({ role: "assistant", content: conv.respuesta_bot });
      }
    });

    // Agregar mensaje actual
    messages.push({ role: "user", content: mensaje });

    try {
      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: this.globalConfig.openai_modelo || "gpt-3.5-turbo",
          messages: messages,
          temperature: parseFloat(this.globalConfig.openai_temperatura || 0.7),
          max_tokens: parseInt(this.globalConfig.openai_max_tokens || 150),
        },
        {
          headers: {
            Authorization: `Bearer ${this.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
          timeout: 30000,
        }
      );

      const tiempo = Date.now() - inicio;

      return {
        content: response.data.choices[0].message.content,
        tokens: response.data.usage.total_tokens,
        tiempo: tiempo,
      };
    } catch (error) {
      console.error("❌ Error llamando a OpenAI:");

      if (error.response) {
        console.error("Status:", error.response.status);
        console.error("Data:", JSON.stringify(error.response.data, null, 2));

        if (error.response.status === 401) {
          throw new Error("API Key inválida o sin permisos");
        } else if (error.response.status === 429) {
          throw new Error("Límite de rate excedido o sin créditos");
        } else if (error.response.status === 400) {
          const mensaje =
            error.response.data?.error?.message || "Error en la petición";
          throw new Error(`OpenAI error: ${mensaje}`);
        }
      } else {
        console.error("Error de red:", error.message);
      }

      throw error;
    }
  }

  async saveConversation(numero, mensajeCliente, respuestaIA) {
    try {
      const contactoId = await this.getContactoId(numero);

      await db.getPool().execute(
        `INSERT INTO conversaciones_bot 
         (numero_cliente, mensaje_cliente, respuesta_bot, contexto_conversacion,
          es_cliente_registrado, tokens_usados, tiempo_respuesta)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [
          numero,
          mensajeCliente,
          respuestaIA.content,
          JSON.stringify(await this.getContexto(numero)),
          contactoId !== null,
          respuestaIA.tokens || 0,
          respuestaIA.tiempo || 0,
        ]
      );

      this.cleanConversationCache();
    } catch (error) {
      console.error("Error guardando conversación:", error);
    }
  }

  async getContactoId(numero) {
    const [rows] = await db
      .getPool()
      .execute("SELECT id FROM contactos WHERE numero = ?", [
        numero.replace("@c.us", ""),
      ]);

    return rows.length > 0 ? rows[0].id : null;
  }

  cleanConversationCache() {
    if (this.conversaciones.size > 100) {
      const entries = Array.from(this.conversaciones.entries());
      const toDelete = entries.slice(0, entries.length - 100);
      toDelete.forEach(([key]) => this.conversaciones.delete(key));
    }
  }

  // Método para manejar mensajes entrantes
  async handleIncomingMessage(from, body, isGroup = false) {
    const numero = from;
    const mensaje = body || "";

    console.log(`🤖 Bot evaluando mensaje de ${numero}: "${mensaje}"`);

    if (!mensaje || mensaje.trim() === "") {
      console.log("🤖 Mensaje vacío, ignorando");
      return null;
    }

    const respuesta = await this.processMessage(mensaje, numero);

    if (respuesta) {
      console.log(
        `🤖 Bot respondiendo con: "${respuesta.respuesta.substring(0, 50)}..."`
      );
      return respuesta;
    }

    return null;
  }
}

// Función helper para obtener empresa actual
function getEmpresaActual() {
  return global.EMPRESA_ID || 1;
}

module.exports = BotHandler;
