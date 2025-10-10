// whatsapp-service/src/bots/ventas/gptTeacher.js
const axios = require("axios");
const db = require("../../database");

class VentasGPTTeacher {
  constructor() {
    this.config = null;
    this.globalConfig = null;
  }

  /**
   * Carga configuración desde BD
   */
  async cargarConfiguracion(empresaId) {
    try {
      // Config de bot
      const [botConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          empresaId,
        ]);

      // Config negocio
      const [negocioConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          empresaId,
        ]);

      // Config global OpenAI
      const [globalRows] = await db.getPool().execute(
        `SELECT clave, valor FROM configuracion_plataforma 
                WHERE clave IN ('openai_api_key', 'openai_modelo', 'openai_temperatura', 'openai_max_tokens')`
      );

      this.globalConfig = {};
      globalRows.forEach((row) => {
        this.globalConfig[row.clave] = row.valor;
      });

      this.config = {
        bot: botConfig[0] || {},
        negocio: negocioConfig[0] || {},
      };

      return true;
    } catch (error) {
      console.error("❌ Error cargando config GPT:", error);
      return false;
    }
  }

  /**
   * GPT modo MAESTRO - Analiza, responde y aprende
   */
  async procesarComoMaestro(
    mensaje,
    numero,
    empresaId,
    contexto,
    intencionML = null,
    confianzaML = 0
  ) {
    console.log("🧠 [GPT Teacher] Modo MAESTRO activado");
    console.log(
      `   Intención ML: ${intencionML} (${(confianzaML * 100).toFixed(0)}%)`
    );

    await this.cargarConfiguracion(empresaId);

    if (!this.globalConfig.openai_api_key) {
      throw new Error("OpenAI API Key no configurada");
    }

    const inicio = Date.now();

    try {
      // 1. Obtener catálogo de productos
      const catalogo = await this.obtenerCatalogo(empresaId);

      // 2. Construir prompt del sistema
      const systemPrompt = await this.construirSystemPrompt(catalogo);

      // 3. Construir mensajes con contexto
      const messages = this.construirMensajes(
        systemPrompt,
        contexto,
        mensaje,
        intencionML,
        confianzaML
      );

      // 4. Llamar a OpenAI
      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: this.globalConfig.openai_modelo || "gpt-3.5-turbo",
          messages: messages,
          temperature: parseFloat(this.globalConfig.openai_temperatura || 0.7),
          max_tokens: parseInt(this.globalConfig.openai_max_tokens || 300),
          functions: this.definirFunctionCalling(),
          function_call: "auto",
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
      const gptResponse = response.data.choices[0];

      // 5. Procesar respuesta (puede ser texto o function call)
      let respuestaFinal;
      let intencionDetectada = intencionML || "conversacion_general";

      if (gptResponse.message.function_call) {
        // GPT quiere ejecutar una función (ej: agregar al carrito)
        respuestaFinal = await this.ejecutarFuncion(
          gptResponse.message.function_call,
          numero,
          empresaId
        );
        intencionDetectada = gptResponse.message.function_call.name;
      } else {
        respuestaFinal = gptResponse.message.content;
      }

      // 6. Guardar ejemplo para reentrenamiento ML
      await this.guardarEjemploEntrenamiento(
        mensaje,
        intencionDetectada,
        empresaId,
        contexto,
        confianzaML
      );

      // 7. Retornar resultado
      return {
        respuesta: respuestaFinal,
        intencion: intencionDetectada,
        confianza: 1.0, // GPT siempre tiene alta confianza
        tokens: response.data.usage.total_tokens,
        tiempo: tiempo,
        fuente: "gpt_teacher",
      };
    } catch (error) {
      console.error("❌ Error en GPT Teacher:", error);

      if (error.response) {
        console.error("OpenAI Status:", error.response.status);
        console.error("OpenAI Error:", error.response.data);
      }

      throw error;
    }
  }

  /**
   * Construye system prompt dinámico
   */
  async construirSystemPrompt(catalogo) {
    let prompt =
      this.config.bot.system_prompt ||
      "Eres un asistente virtual de ventas profesional, amable y servicial.";

    // Agregar personalidad específica de ventas
    if (this.config.bot.prompt_ventas) {
      prompt += "\n\n" + this.config.bot.prompt_ventas;
    }

    // Agregar info del negocio
    if (this.config.bot.business_info) {
      prompt += "\n\n" + this.config.bot.business_info;
    }

    // Agregar info de negocio desde BD
    if (this.config.negocio.nombre_negocio) {
      prompt += `\n\nNombre del negocio: ${this.config.negocio.nombre_negocio}`;
    }

    if (this.config.negocio.direccion) {
      prompt += `\nDirección: ${this.config.negocio.direccion}`;
    }

    if (this.config.negocio.telefono) {
      prompt += `\nTeléfono: ${this.config.negocio.telefono}`;
    }

    // Agregar métodos de pago
    if (this.config.negocio.cuentas_pago) {
      const cuentas = JSON.parse(this.config.negocio.cuentas_pago);
      prompt += "\n\nMétodos de pago disponibles:";
      if (cuentas.yape) prompt += `\n- Yape: ${cuentas.yape}`;
      if (cuentas.plin) prompt += `\n- Plin: ${cuentas.plin}`;
      if (cuentas.banco)
        prompt += `\n- Transferencia bancaria: ${cuentas.banco.nombre} - ${cuentas.banco.numero}`;
      if (cuentas.efectivo) prompt += "\n- Efectivo en tienda o contraentrega";
    }

    // Agregar catálogo de productos
    if (catalogo && catalogo.productos && catalogo.productos.length > 0) {
      prompt += "\n\nCATÁLOGO DE PRODUCTOS DISPONIBLES:\n";
      catalogo.productos.forEach((prod) => {
        prompt += `\n- ${prod.nombre}: S/ ${prod.precio}`;
        if (prod.descripcion) prompt += ` (${prod.descripcion})`;
      });
    }

    // Agregar promociones
    if (catalogo?.promociones && catalogo.promociones.length > 0) {
      prompt += "\n\nPROMOCIONES ACTIVAS:\n";
      catalogo.promociones.forEach((promo) => {
        prompt += `\n- ${promo.nombre}: De S/ ${promo.precio_regular} a S/ ${promo.precio_promocion}`;
      });
    }

    // Instrucciones finales
    prompt += "\n\nINSTRUCCIONES IMPORTANTES:";
    prompt += "\n- Siempre responde en español de Perú";
    prompt += "\n- Usa emojis con moderación";
    prompt += "\n- Sé conversacional, natural y amigable";
    prompt +=
      "\n- Si el cliente pregunta por un producto, ofrécele agregarlo al carrito";
    prompt +=
      "\n- Si detectas una queja o problema grave, sugiere hablar con un humano";
    prompt +=
      "\n- NUNCA inventes información de productos que no están en el catálogo";
    prompt += "\n- Si no sabes algo, admítelo y ofrece ayuda humana";

    return prompt;
  }

  /**
   * Construye array de mensajes para OpenAI
   */
  construirMensajes(
    systemPrompt,
    contexto,
    mensajeActual,
    intencionML,
    confianzaML
  ) {
    const messages = [{ role: "system", content: systemPrompt }];

    // Agregar contexto previo
    contexto.forEach((conv) => {
      messages.push({ role: "user", content: conv.mensaje_cliente });
      if (conv.respuesta_bot) {
        messages.push({ role: "assistant", content: conv.respuesta_bot });
      }
    });

    // Agregar mensaje actual con hint del ML
    let contenidoActual = mensajeActual;
    if (intencionML && confianzaML > 0) {
      contenidoActual += `\n\n[Nota interna: ML detectó posible intención "${intencionML}" con ${(
        confianzaML * 100
      ).toFixed(0)}% confianza]`;
    }

    messages.push({ role: "user", content: contenidoActual });

    return messages;
  }

  /**
   * Define function calling para acciones del carrito
   */
  definirFunctionCalling() {
    return [
      {
        name: "agregar_producto",
        description: "Agrega un producto al carrito del cliente",
        parameters: {
          type: "object",
          properties: {
            nombre_producto: {
              type: "string",
              description: "Nombre del producto a agregar",
            },
            cantidad: {
              type: "integer",
              description: "Cantidad de unidades",
              default: 1,
            },
          },
          required: ["nombre_producto"],
        },
      },
      {
        name: "ver_carrito",
        description: "Muestra el carrito actual del cliente",
        parameters: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "vaciar_carrito",
        description: "Vacía completamente el carrito",
        parameters: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "escalar_humano",
        description:
          "Escala la conversación a un humano cuando hay quejas, problemas o solicitudes complejas",
        parameters: {
          type: "object",
          properties: {
            motivo: {
              type: "string",
              description: "Motivo del escalamiento",
            },
          },
          required: ["motivo"],
        },
      },
    ];
  }

  /**
   * Ejecuta función solicitada por GPT
   */
  async ejecutarFuncion(functionCall, numero, empresaId) {
    const funcionNombre = functionCall.name;
    const argumentos = JSON.parse(functionCall.arguments);

    console.log(`🔧 [GPT Teacher] Ejecutando función: ${funcionNombre}`);

    // Aquí delegarías al salesBot o intentRouter según la función
    // Por ahora retorno texto simple

    switch (funcionNombre) {
      case "agregar_producto":
        return `✅ Perfecto! Agregando *${argumentos.nombre_producto}* (x${
          argumentos.cantidad || 1
        }) al carrito. ¿Deseas algo más?`;

      case "ver_carrito":
        return "Déjame revisar tu carrito... (función por implementar)";

      case "vaciar_carrito":
        return "Carrito vaciado correctamente. ¿Empezamos de nuevo?";

      case "escalar_humano":
        await this.escalarConversacion(numero, empresaId, argumentos.motivo);
        return "👤 Entiendo, te conecto con un asesor humano en breve.";

      default:
        return "Función en desarrollo...";
    }
  }

  /**
   * Guarda ejemplo en training_samples para reentrenar ML
   */
  async guardarEjemploEntrenamiento(
    mensaje,
    intencionDetectada,
    empresaId,
    contexto,
    confianzaML
  ) {
    try {
      await db.getPool().execute(
        `INSERT INTO training_samples 
                (empresa_id, texto_usuario, intencion_detectada, intencion_confirmada, 
                 confianza, contexto_previo, estado, usado_entrenamiento)
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente', 0)`,
        [
          empresaId,
          mensaje,
          intencionDetectada, // Lo que ML dijo
          intencionDetectada, // Lo que GPT confirmó/corrigió
          confianzaML,
          JSON.stringify(contexto),
        ]
      );

      console.log("💾 [GPT Teacher] Ejemplo guardado para reentrenamiento");

      // Verificar si hay suficientes ejemplos para reentrenar
      await this.verificarReentrenamiento();
    } catch (error) {
      console.error("Error guardando ejemplo:", error);
    }
  }

  /**
   * Verifica si hay 50+ ejemplos para reentrenar ML
   */
  async verificarReentrenamiento() {
    try {
      const [rows] = await db.getPool().execute(
        `SELECT COUNT(*) as count FROM training_samples 
            WHERE estado = 'pendiente' AND usado_entrenamiento = 0`
      );

      const count = rows[0].count;

      // ✅ Leer umbral dinámico desde BD
      const [configRows] = await db
        .getPool()
        .execute(
          `SELECT valor FROM configuracion_plataforma WHERE clave = 'ml_auto_retrain_examples'`
        );

      const umbralRetrain = parseInt(configRows[0]?.valor || "50");

      if (count >= umbralRetrain) {
        console.log(
          `🎓 [GPT Teacher] Hay ${count} ejemplos (umbral: ${umbralRetrain}). Iniciando reentrenamiento ML...`
        );

        // ✅ Obtener URL dinámica del ML Engine
        const [mlUrlConfig] = await db
          .getPool()
          .execute(
            `SELECT valor FROM configuracion_plataforma WHERE clave = 'ml_engine_port'`
          );

        const mlPort = mlUrlConfig[0]?.valor || "5000";
        const isLocal = process.platform === "win32"; // Windows = local
        const mlUrl = isLocal
          ? `http://localhost:${mlPort}`
          : `http://127.0.0.1:${mlPort}`;

        try {
          await axios.post(`${mlUrl}/train`, {
            trigger: "automatico",
          });
          console.log("✅ Reentrenamiento iniciado correctamente");
        } catch (error) {
          console.error("Error llamando a ML Engine:", error.message);
        }
      }
    } catch (error) {
      console.error("Error verificando reentrenamiento:", error);
    }
  }

  /**
   * Escala conversación a humano
   */
  async escalarConversacion(numero, empresaId, motivo) {
    await db.getPool().execute(
      `INSERT INTO estados_conversacion 
            (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado)
            VALUES (?, ?, 'escalado_humano', NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                estado = 'escalado_humano',
                fecha_escalado = NOW(),
                motivo_escalado = ?`,
      [empresaId, numero, motivo, motivo]
    );
  }

  /**
   * Obtiene catálogo desde BD
   */
  async obtenerCatalogo(empresaId) {
    try {
      const [rows] = await db
        .getPool()
        .execute("SELECT datos_json FROM catalogo_bot WHERE empresa_id = ?", [
          empresaId,
        ]);

      if (rows[0]?.datos_json) {
        return JSON.parse(rows[0].datos_json);
      }
    } catch (error) {
      console.error("Error obteniendo catálogo:", error);
    }
    return null;
  }
}

module.exports = VentasGPTTeacher;
