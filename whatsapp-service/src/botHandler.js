const db = require("./database");
const axios = require("axios");

class BotHandler {
  constructor() {
    this.config = null;
    this.conocimientos = [];
    this.conversaciones = new Map();
    this.loadConfig();

    // Recargar configuración cada 5 minutos
    setInterval(() => this.loadConfig(), 5 * 60 * 1000);
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

        console.log("✅ Configuración del bot cargada desde BD");
        console.log("   - Bot activo:", this.config.activo ? "SÍ" : "NO");
        console.log(
          "   - API Key:",
          this.config.openai_api_key ? "Configurada" : "NO CONFIGURADA"
        );
        console.log(
          "   - System prompt:",
          this.config.system_prompt ? "Configurado" : "NO configurado"
        );
        console.log(
          "   - Business info:",
          this.config.business_info ? "Configurada" : "NO configurada"
        );
        console.log(
          "   - Palabras activación:",
          this.config.palabras_activacion.length
        );
      } else {
        console.log("❌ No se encontró configuración del bot en la BD");
      }

      this.conocimientos = [];
      console.log("✅ Bot configurado sin base de conocimiento adicional");
    } catch (error) {
      console.error("Error cargando configuración del bot:", error);
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

      console.log(
        "📝 [processMessage] shouldRespond resultado:",
        shouldRespond
      );

      if (!shouldRespond) {
        console.log("📝 [processMessage] Bot no debe responder, terminando");
        return null;
      }

      if (shouldRespond === "fuera_horario") {
        console.log("📝 [processMessage] Fuera de horario");
        return {
          respuesta:
            this.config.mensaje_fuera_horario ||
            "Gracias por tu mensaje. Nuestro horario de atención ha finalizado. Te responderemos lo antes posible.",
          tipo: "fuera_horario",
        };
      }

      // Detectar si necesita escalamiento
      const necesitaEscalamiento = await this.checkEscalamiento(mensaje);

      if (necesitaEscalamiento) {
        console.log("🚨 ESCALAMIENTO DETECTADO - Marcando conversación");

        try {
          await db.getPool().execute(
            `INSERT INTO estados_conversacion (numero_cliente, estado, fecha_escalado, motivo_escalado) 
             VALUES (?, 'escalado_humano', NOW(), ?)
             ON DUPLICATE KEY UPDATE 
                estado = 'escalado_humano', 
                fecha_escalado = NOW(),
                motivo_escalado = ?`,
            [numero, mensaje, mensaje]
          );

          console.log(`✅ Conversación marcada como escalada en BD`);
        } catch (error) {
          console.error("❌ Error marcando escalamiento:", error);
        }

        // AQUÍ LEE EL MENSAJE DE ESCALAMIENTO DESDE LA BD
        const mensajeEscalamiento =
          this.config.mensaje_escalamiento ||
          "Tu consulta requiere atención personalizada. Un asesor humano te atenderá en breve.";

        return {
          respuesta: mensajeEscalamiento,
          tipo: "escalamiento",
        };
      }

      console.log(
        "📝 [processMessage] Aplicando delay de",
        this.config.delay_respuesta,
        "segundos"
      );

      await new Promise((resolve) =>
        setTimeout(resolve, this.config.delay_respuesta * 1000)
      );

      console.log("📝 [processMessage] Obteniendo contexto");
      const contexto = await this.getContexto(numero);

      console.log("📝 [processMessage] Generando respuesta con IA");
      const respuestaIA = await this.generateResponse(mensaje, contexto);

      console.log("📝 [processMessage] Guardando conversación");
      await this.saveConversation(numero, mensaje, respuestaIA);

      console.log(
        "📝 [processMessage] Respuesta lista:",
        respuestaIA.content.substring(0, 50) + "..."
      );
      return {
        respuesta: respuestaIA.content,
        tipo: "bot",
        tokens: respuestaIA.tokens,
        tiempo: respuestaIA.tiempo,
      };
    } catch (error) {
      console.error(
        "❌ [processMessage] Error procesando mensaje:",
        error.message
      );
      console.error("Stack trace:", error.stack);

      let mensajeError =
        "Lo siento, tuve un problema al procesar tu mensaje 😅";

      if (error.message.includes("API Key")) {
        mensajeError =
          "Ups! Parece que hay un problema con la configuración 🔧 Por favor contacta al administrador.";
      } else if (error.message.includes("créditos")) {
        mensajeError =
          "Oh no! Parece que se agotaron los créditos de IA 💸 Por favor contacta al administrador.";
      } else if (error.message.includes("rate")) {
        mensajeError =
          "Estoy recibiendo muchos mensajes ahora mismo 😵 Dame unos segundos y vuelve a intentar.";
      }

      return {
        respuesta: mensajeError,
        tipo: "error",
      };
    }
  }

  async checkEscalamiento(mensaje) {
    // AQUÍ DEBERÍAMOS LEER LAS FRASES DE ESCALAMIENTO DESDE LA BD
    // Por ahora uso un campo JSON en la configuración
    let frasesEscalamiento = [];

    try {
      if (this.config.frases_escalamiento) {
        frasesEscalamiento = JSON.parse(this.config.frases_escalamiento);
      }
    } catch (e) {
      // Si no es JSON válido, intentar split por comas
      if (this.config.frases_escalamiento) {
        frasesEscalamiento = this.config.frases_escalamiento
          .split(",")
          .map((f) => f.trim())
          .filter((f) => f.length > 0);
      }
    }

    // Si no hay frases configuradas, no escalar
    if (frasesEscalamiento.length === 0) {
      return false;
    }

    const mensajeLower = mensaje.toLowerCase();

    const necesitaEscalar = frasesEscalamiento.some((frase) => {
      const contieneFrase = mensajeLower.includes(frase.toLowerCase());
      if (contieneFrase) {
        console.log(`🔍 Frase de escalamiento detectada: "${frase}"`);
      }
      return contieneFrase;
    });

    console.log(
      `📋 Resultado verificación escalamiento: ${
        necesitaEscalar ? "SÍ ESCALAR" : "NO ESCALAR"
      }`
    );

    return necesitaEscalar;
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
    if (!this.config.system_prompt || !this.config.openai_api_key) {
      throw new Error("Bot no configurado correctamente");
    }

    // TODO viene de la BD
    let systemPrompt = this.config.system_prompt;

    // Agregar información del negocio si existe
    if (this.config.business_info) {
      systemPrompt += `\n\nINFORMACIÓN DEL NEGOCIO:\n${this.config.business_info}`;
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
          model: this.config.modelo_ai || "gpt-3.5-turbo",
          messages: messages,
          temperature: parseFloat(this.config.temperatura) || 0.7,
          max_tokens: parseInt(this.config.max_tokens) || 150,
        },
        {
          headers: {
            Authorization: `Bearer ${this.config.openai_api_key}`,
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

module.exports = BotHandler;
