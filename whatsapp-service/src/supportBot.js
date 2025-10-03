// whatsapp-service/src/supportBot.js - VERSIÓN CORREGIDA CON MENÚ INICIAL
const db = require("./database");
const axios = require("axios");
const moment = require("moment");
const path = require("path");
const fs = require("fs").promises;

moment.locale("es");

class SupportBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.botHandler = botHandler;

    // Configuración
    this.infoBot = {};
    this.infoNegocio = {};
    this.planes = [];
    this.servicios = [];
    this.horarios = [];
    this.moneda = { codigo: "PEN", simbolo: "S/" };
    this.notificaciones = {};
    this.zonasCobertura = [];

    // Estados de procesos activos
    this.procesosActivos = new Map();
    this.ventasCompletadas = new Map();

    // Configuración IA
    this.maxTokens = 150;
    this.temperature = 0.7;

    // Limpieza automática
    setInterval(() => this.limpiarProcesosInactivos(), 5 * 60 * 1000);
    setInterval(() => this.limpiarVentasCompletadas(), 5 * 60 * 1000);
  }

  async loadConfig() {
    try {
      // 1. Configuración del bot
      const [botConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (botConfig[0]) {
        this.infoBot = botConfig[0];
        
        if (botConfig[0].business_info) {
          this.zonasCobertura = this.extraerZonasCobertura(botConfig[0].business_info);
        }
      }

      // 2. Información del negocio
      const [negocio] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (negocio[0]) {
        this.infoNegocio = negocio[0];

        if (negocio[0].cuentas_pago) {
          try {
            const cuentas = JSON.parse(negocio[0].cuentas_pago);
            if (cuentas.moneda && cuentas.simbolo) {
              this.moneda = {
                codigo: cuentas.moneda,
                simbolo: cuentas.simbolo,
              };
            }
            this.infoNegocio.metodos_pago_array = cuentas.metodos || [];
          } catch (e) {
            console.error("Error parseando cuentas_pago:", e);
          }
        }
      }

      // 3. Planes desde catálogo
      const [catalogoRows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (catalogoRows[0] && catalogoRows[0].datos_json) {
        const datos = JSON.parse(catalogoRows[0].datos_json);
        this.planes = datos.productos || [];
      }

      // 4. Servicios técnicos
      const [serviciosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM servicios_disponibles WHERE empresa_id = ? AND activo = 1 ORDER BY id",
          [this.empresaId]
        );
      this.servicios = serviciosRows;

      // 5. Horarios de atención
      const [horariosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM horarios_atencion WHERE empresa_id = ? AND activo = 1 ORDER BY dia_semana",
          [this.empresaId]
        );
      this.horarios = horariosRows;

      // 6. Notificaciones
      const [notifRows] = await db
        .getPool()
        .execute("SELECT * FROM notificaciones_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (notifRows[0]) {
        this.notificaciones = notifRows[0];
      }

      // 7. Configuración de tokens
      const [tokenConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_max_tokens'"
        );

      if (tokenConfig[0]?.valor) {
        this.maxTokens = parseInt(tokenConfig[0].valor);
      }

      const [tempConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_temperatura'"
        );

      if (tempConfig[0]?.valor) {
        this.temperature = parseFloat(tempConfig[0].valor);
      }

      console.log("✅ SupportBot configurado correctamente");
    } catch (error) {
      console.error("❌ Error cargando configuración de soporte:", error);
    }
  }

  extraerZonasCobertura(businessInfo) {
    const regex = /zonas?\s+de\s+cobertura\s*:\s*([^\n]+)/i;
    const match = businessInfo.match(regex);
    
    if (match && match[1]) {
      return match[1]
        .split(',')
        .map(z => z.trim().toLowerCase())
        .filter(z => z.length > 0);
    }
    
    return [];
  }

  construirMenuInicial() {
    const nombreNegocio = this.infoNegocio.nombre_negocio || 'nuestro negocio';
    
    let menu = `Hola, soy el asistente virtual de ${nombreNegocio}\n\n`;
    menu += `¿En qué puedo ayudarte?\n\n`;
    
    // Opción 1: Si hay planes o servicios
    if (this.planes.length > 0 || this.servicios.length > 0) {
      menu += `1️⃣ Planes y servicios\n`;
    }
    
    // Opción 2: Soporte técnico (siempre)
    menu += `2️⃣ Soporte técnico\n`;
    
    // Opción 3: Solo si hay métodos de pago
    if (this.infoNegocio.metodos_pago_array && this.infoNegocio.metodos_pago_array.length > 0) {
      menu += `3️⃣ Consultar pagos\n`;
    }
    
    menu += `\nEscribe el número de la opción.`;
    
    return menu;
  }

  getFunctions() {
    return [
      {
        name: "mostrar_planes",
        description: "Muestra planes disponibles"
      },
      {
        name: "verificar_cobertura",
        description: "Verifica cobertura en una zona",
        parameters: {
          type: "object",
          properties: {
            zona: {
              type: "string",
              description: "Zona mencionada"
            }
          },
          required: ["zona"]
        }
      },
      {
        name: "iniciar_contratacion",
        description: "Inicia contratación de servicio",
        parameters: {
          type: "object",
          properties: {
            plan: {
              type: "string",
              description: "Plan seleccionado"
            }
          }
        }
      },
      {
        name: "mostrar_metodos_pago",
        description: "Muestra métodos de pago"
      },
      {
        name: "diagnosticar_problema",
        description: "Cliente reporta problema técnico"
      }
    ];
  }

  async procesarMensajeSoporte(mensaje, numero) {
    console.log(`🤖 SupportBot procesando: "${mensaje.substring(0, 50)}..."`);

    const contexto = await this.botHandler.getContexto(numero);
    
    // 1. PRIMERA INTERACCIÓN → MOSTRAR MENÚ
    if (contexto.length === 0) {
      const menu = this.construirMenuInicial();
      
      await this.botHandler.saveConversation(numero, mensaje, {
        content: menu,
        tokens: 0,
        tiempo: 0,
      });
      
      return { respuesta: menu, tipo: "menu_inicial" };
    }

    // 2. DETECTAR SELECCIÓN DE MENÚ (solo primeros 2 mensajes)
    const msgTrim = mensaje.trim();
    const proceso = this.procesosActivos.get(numero);
    
    if (contexto.length <= 2 && !proceso) {
      if (msgTrim === "1") {
        return await this.iniciarFlujoVentas(numero);
      }
      
      if (msgTrim === "2") {
        return await this.iniciarFlujoSoporte(numero);
      }
      
      if (msgTrim === "3") {
        return await this.iniciarFlujoPagos(numero);
      }
    }

    // 3. DETECTAR SELECCIÓN DE PLAN DIRECTA (si acaba de ver lista de planes)
    if (proceso?.flujo === "ventas" && !proceso.plan) {
      const ultimoMensajeBot = contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";
      
      // Si el último mensaje del bot mostró planes
      if (ultimoMensajeBot.includes("PLANES DISPONIBLES")) {
        const msgLower = mensaje.toLowerCase();
        
        // Detectar por número (1, 2)
        if (msgTrim === "1" || msgTrim === "2") {
          const numeroPlan = parseInt(msgTrim);
          if (numeroPlan > 0 && numeroPlan <= this.planes.length) {
            proceso.plan = this.planes[numeroPlan - 1];
            this.procesosActivos.set(numero, proceso);
            
            console.log(`✅ Plan seleccionado por número: ${proceso.plan.producto}`);
            
            return {
              respuesta: "¿En qué zona estás?",
              tipo: "solicitar_zona"
            };
          }
        }
        
        // Detectar por velocidad ("50 mbps", "el de 50", "plan de 50")
        const matchVelocidad = msgLower.match(/(\d+)\s*mbps/);
        if (matchVelocidad) {
          const velocidad = matchVelocidad[1];
          const plan = this.planes.find(p => p.producto.toLowerCase().includes(velocidad));
          if (plan) {
            proceso.plan = plan;
            this.procesosActivos.set(numero, proceso);
            
            console.log(`✅ Plan seleccionado por velocidad: ${plan.producto}`);
            
            return {
              respuesta: "¿En qué zona estás?",
              tipo: "solicitar_zona"
            };
          }
        }
        
        // Detectar "el 1", "el 2", "el plan 1", etc
        const matchOpcion = msgLower.match(/el\s+(?:plan\s+)?([12])/);
        if (matchOpcion) {
          const numeroPlan = parseInt(matchOpcion[1]);
          if (numeroPlan > 0 && numeroPlan <= this.planes.length) {
            proceso.plan = this.planes[numeroPlan - 1];
            this.procesosActivos.set(numero, proceso);
            
            console.log(`✅ Plan seleccionado: ${proceso.plan.producto}`);
            
            return {
              respuesta: "¿En qué zona estás?",
              tipo: "solicitar_zona"
            };
          }
        }
      }
    }

    // 4. Verificar despedidas post-venta
    if (this.ventasCompletadas.has(numero)) {
      const ventaInfo = this.ventasCompletadas.get(numero);
      const tiempoTranscurrido = Date.now() - ventaInfo.timestamp;

      if (tiempoTranscurrido < 3 * 60 * 1000) {
        const respuestas = ["Nos vemos", "Hasta pronto", "Gracias a ti"];
        const respuesta = respuestas[Math.floor(Math.random() * respuestas.length)];

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_venta" };
      } else {
        this.ventasCompletadas.delete(numero);
      }
    }

    // 4. Verificar instalación pendiente
    const instalacionPendiente = await this.verificarInstalacionPendiente(numero);
    
    if (instalacionPendiente && proceso?.flujo === "ventas") {
      const intencion = await this.detectarIntencionSoporte(mensaje, numero);
      
      if (intencion === "QUIERE_CONTRATAR") {
        return await this.recordarInstalacionPendiente(numero, instalacionPendiente);
      }
    }

    // 5. Manejo de estados técnicos
    if (proceso) {
      proceso.ultimaActividad = Date.now();
      
      // Estado: Esperando comprobante
      if (proceso.estado === "esperando_comprobante_imagen") {
        return {
          respuesta: "Esperando que envíes la imagen del comprobante...",
          tipo: "esperando_imagen"
        };
      }
      
      // Estado: Esperando datos de pago
      if (proceso.estado === "esperando_datos_pago") {
        return await this.procesarDatosPago(mensaje, numero, proceso);
      }
      
      // Estado: Esperando selección de día
      if (proceso.esperando_seleccion_dia) {
        return await this.procesarSeleccionDia(mensaje, numero, proceso);
      }
      
      // Estado: Esperando selección de hora
      if (proceso.esperando_seleccion_hora) {
        return await this.procesarSeleccionHora(mensaje, numero, proceso);
      }

      // Estado: Confirmación final
      if (proceso.esperando_confirmacion_final) {
        return await this.manejarConfirmacionFinal(mensaje, numero, proceso);
      }
    }

    // 6. Procesamiento con GPT y Function Calling
    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  async iniciarFlujoVentas(numero) {
    this.procesosActivos.set(numero, {
      flujo: "ventas",
      ultimaActividad: Date.now()
    });
    
    return await this.funcionMostrarPlanes();
  }

  async iniciarFlujoSoporte(numero) {
    this.procesosActivos.set(numero, {
      flujo: "soporte",
      ultimaActividad: Date.now()
    });
    
    return {
      respuesta: "¿Qué problema técnico tienes con tu servicio?",
      tipo: "iniciar_soporte"
    };
  }

  async iniciarFlujoPagos(numero) {
    this.procesosActivos.set(numero, {
      flujo: "pagos",
      ultimaActividad: Date.now()
    });
    
    return await this.funcionMostrarMetodosPago(numero);
  }

  async manejarComprobanteRecibido(numero, mediaPath) {
    console.log(`📸 Comprobante recibido de ${numero}`);
    
    const proceso = this.procesosActivos.get(numero);
    
    if (!proceso || proceso.estado !== "esperando_comprobante_imagen") {
      return {
        respuesta: "No estaba esperando un comprobante. ¿En qué puedo ayudarte?",
        tipo: "comprobante_inesperado"
      };
    }

    proceso.comprobante_path = mediaPath;
    proceso.estado = "esperando_datos_pago";
    this.procesosActivos.set(numero, proceso);

    return {
      respuesta: "Para validar tu pago, necesito:\n\n• Tu nombre completo\n• Tu DNI o Cédula\n\nEjemplo: Juan Pérez Torres, DNI 12345678",
      tipo: "solicitar_datos_pago"
    };
  }

  async procesarDatosPago(mensaje, numero, proceso) {
    const datosExtraidos = await this.extraerDatosPersonales(mensaje);

    if (!datosExtraidos.nombre || !datosExtraidos.dni) {
      return {
        respuesta: "No pude identificar tu nombre o documento. Intenta así:\n\nJuan Pérez Torres, DNI 12345678",
        tipo: "datos_invalidos"
      };
    }

    proceso.nombre_pago = datosExtraidos.nombre;
    proceso.dni_pago = datosExtraidos.dni;
    proceso.tipo_documento = datosExtraidos.tipo_documento || "DNI";

    await this.escalarYNotificarPago(numero, proceso);

    this.procesosActivos.delete(numero);

    return {
      respuesta: "Comprobante recibido. Validaremos tu pago y te confirmaremos pronto.",
      tipo: "pago_escalado"
    };
  }

  async escalarYNotificarPago(numero, proceso) {
    const numeroLimpio = numero.replace("@c.us", "");

    const notasEscalamiento = `Nombre: ${proceso.nombre_pago} | ${proceso.tipo_documento}: ${proceso.dni_pago} | Comprobante: ${proceso.comprobante_path}`;
    
    await db.getPool().execute(
      `INSERT INTO estados_conversacion 
       (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado, notas)
       VALUES (?, ?, 'escalado_humano', NOW(), 'Validación de pago', ?)
       ON DUPLICATE KEY UPDATE 
         estado = 'escalado_humano',
         fecha_escalado = NOW(),
         motivo_escalado = 'Validación de pago',
         notas = ?`,
      [this.empresaId, numero, notasEscalamiento, notasEscalamiento]
    );

    console.log(`✅ Escalamiento creado para validación de pago: ${numero}`);

    if (!this.notificaciones.notificar_escalamiento) {
      return;
    }

    let numeros;
    try {
      numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
    } catch (e) {
      return;
    }

    if (!Array.isArray(numeros) || numeros.length === 0) {
      return;
    }

    let mensajeTexto = `🔔 VALIDACIÓN DE PAGO\n\n`;
    mensajeTexto += `Nombre: ${proceso.nombre_pago}\n`;
    mensajeTexto += `${proceso.tipo_documento}: ${proceso.dni_pago}\n`;
    mensajeTexto += `Teléfono: ${numeroLimpio}\n`;
    mensajeTexto += `Fecha: ${new Date().toLocaleString("es-PE")}\n\n`;
    mensajeTexto += `Valida desde el panel de Escalados`;

    for (const num of numeros) {
      try {
        let numeroNotificar = num.replace(/[^\d]/g, "");
        if (!numeroNotificar.includes("@")) {
          numeroNotificar = `${numeroNotificar}@c.us`;
        }

        if (this.botHandler.whatsappClient?.client?.client?.sendText) {
          await this.botHandler.whatsappClient.client.client.sendText(numeroNotificar, mensajeTexto);
        } else if (this.botHandler.whatsappClient?.client?.sendText) {
          await this.botHandler.whatsappClient.client.sendText(numeroNotificar, mensajeTexto);
        }

        if (proceso.comprobante_path) {
          if (this.botHandler.whatsappClient?.client?.client?.sendImage) {
            await this.botHandler.whatsappClient.client.client.sendImage(
              numeroNotificar, 
              proceso.comprobante_path,
              'comprobante',
              'Comprobante de pago'
            );
          }
        }

        console.log(`✅ Notificación enviada a ${num}`);
      } catch (error) {
        console.error(`❌ Error notificando a ${num}:`, error.message);
      }
    }
  }

  async verificarInstalacionPendiente(numero) {
    try {
      const [rows] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
         WHERE numero_cliente = ? 
         AND empresa_id = ?
         AND estado IN ('agendada', 'confirmada')
         AND fecha_cita >= CURDATE()
         ORDER BY fecha_cita ASC
         LIMIT 1`,
        [numero, this.empresaId]
      );

      return rows[0] || null;
    } catch (error) {
      console.error("Error verificando instalación:", error);
      return null;
    }
  }

  async recordarInstalacionPendiente(numero, cita) {
    const fechaCita = moment(cita.fecha_cita).format("dddd D [de] MMMM");

    let respuesta = `Ya tienes una instalación agendada:\n\n`;
    respuesta += `• Servicio: ${cita.tipo_servicio}\n`;
    respuesta += `• Fecha: ${fechaCita}\n`;
    respuesta += `• Hora: ${cita.hora_cita.substring(0, 5)}\n\n`;
    respuesta += `Si necesitas cambiarla, escribe "cancelar instalación"`;

    return { respuesta, tipo: "instalacion_pendiente" };
  }

  async respuestaConFunctionCalling(mensaje, numero) {
    if (!this.botHandler) {
      return { respuesta: "¿En qué puedo ayudarte?", tipo: "bot" };
    }

    try {
      const contexto = await this.botHandler.getContexto(numero);
      const proceso = this.procesosActivos.get(numero);

      const systemPrompt = this.construirPromptGenerico(numero, proceso);

      const messages = [{ role: "system", content: systemPrompt }];

      contexto.slice(-5).forEach((c) => {
        messages.push({ role: "user", content: c.mensaje_cliente });
        if (c.respuesta_bot) {
          messages.push({ role: "assistant", content: c.respuesta_bot });
        }
      });

      messages.push({ role: "user", content: mensaje });

      const response = await this.callOpenAIWithFunctions(messages);

      if (response.function_call) {
        const result = await this.ejecutarFuncion(
          response.function_call.name,
          JSON.parse(response.function_call.arguments || "{}"),
          numero,
          mensaje
        );

        await this.botHandler.saveConversation(numero, mensaje, {
          content: result.respuesta,
          tokens: response.usage?.total_tokens || 0,
          tiempo: 0,
        });

        return result;
      }

      await this.botHandler.saveConversation(numero, mensaje, {
        content: response.content,
        tokens: response.usage?.total_tokens || 0,
        tiempo: 0,
      });

      return {
        respuesta: response.content,
        tipo: "bot",
      };
    } catch (error) {
      console.error("❌ Error en Function Calling:", error);
      return {
        respuesta: "Disculpa, tuve un problema. ¿Me repites?",
        tipo: "error",
      };
    }
  }

  construirPromptGenerico(numero, proceso) {
    let prompt = "";

    if (this.infoBot.system_prompt) {
      prompt += `${this.infoBot.system_prompt}\n\n`;
    }

    // Agregar prompt específico según flujo
    if (proceso?.flujo === "ventas" && this.infoBot.prompt_ventas) {
      prompt += `CONTEXTO DE VENTAS:\n${this.infoBot.prompt_ventas}\n\n`;
    }
    
    if (proceso?.flujo === "soporte" && this.infoBot.prompt_citas) {
      prompt += `CONTEXTO DE SOPORTE:\n${this.infoBot.prompt_citas}\n\n`;
    }

    if (this.infoBot.business_info) {
      prompt += `INFORMACIÓN DEL NEGOCIO:\n${this.infoBot.business_info}\n\n`;
    }

    prompt += `📍 CONTACTO:\n`;
    if (this.infoNegocio.nombre_negocio) {
      prompt += `• Negocio: ${this.infoNegocio.nombre_negocio}\n`;
    }
    if (this.infoNegocio.telefono) {
      prompt += `• Teléfono: ${this.infoNegocio.telefono}\n`;
    }
    if (this.infoNegocio.direccion) {
      prompt += `• Dirección: ${this.infoNegocio.direccion}\n`;
    }
    prompt += "\n";

    if (proceso?.flujo === "ventas") {
      prompt += `📦 PLANES DISPONIBLES:\n`;
      if (this.planes.length > 0) {
        this.planes.forEach((plan, index) => {
          prompt += `${index + 1}. ${plan.producto} - ${this.moneda.simbolo}${plan.precio}\n`;
          if (plan.descripcion) prompt += `   ${plan.descripcion}\n`;
        });
      }
      prompt += "\n";

      if (this.zonasCobertura.length > 0) {
        prompt += `📍 ZONAS CON COBERTURA:\n`;
        this.zonasCobertura.forEach(z => {
          prompt += `• ${z}\n`;
        });
        prompt += "\n";
      }
    }

    if (proceso?.flujo === "pagos") {
      if (this.infoNegocio.metodos_pago_array && this.infoNegocio.metodos_pago_array.length > 0) {
        prompt += `💳 MÉTODOS DE PAGO:\n`;
        this.infoNegocio.metodos_pago_array.forEach(m => {
          prompt += `• ${m.tipo}: ${m.dato}\n`;
        });
        prompt += "\n";
      }
    }

    if (proceso) {
      prompt += `🔄 DATOS RECOPILADOS:\n`;
      if (proceso.zona) prompt += `• Zona: ${proceso.zona}\n`;
      if (proceso.plan) prompt += `• Plan: ${proceso.plan.producto}\n`;
      if (proceso.direccion) prompt += `• Dirección: ${proceso.direccion}\n`;
      if (proceso.nombre) prompt += `• Nombre: ${proceso.nombre}\n`;
      if (proceso.dni) prompt += `• DNI: ${proceso.dni}\n`;
      prompt += "\n";
    }

    prompt += `🎯 INSTRUCCIONES CRÍTICAS:\n\n`;
    
    prompt += `⚠️ LENGUAJE NATURAL Y HUMANO:\n`;
    prompt += `- Habla como un vendedor relajado y amigable\n`;
    prompt += `- USA contracciones: "estás" en vez de "te encuentras"\n`;
    prompt += `- NO uses: "Excelente", "Perfecto", "¡Genial!", "¡Fantástico!"\n`;
    prompt += `- NO repitas información que ya diste\n`;
    prompt += `- NO hagas preguntas que el cliente ya respondió\n\n`;
    
    prompt += `⚠️ DETECCIÓN AUTOMÁTICA:\n`;
    prompt += `- Si cliente dice "1" después de ver planes → YA eligió plan 1, NO preguntes de nuevo\n`;
    prompt += `- Si dice "20 Mbps" o "el de 20" → YA eligió ese plan\n`;
    prompt += `- Si menciona zona → Guarda y verifica cobertura INMEDIATAMENTE\n`;
    prompt += `- "Jr comercio 304 Nilson Jhonny 47468849" → Extrae TODO de una vez\n\n`;
    
    prompt += `⚠️ FLUJO DIRECTO SIN RODEOS:\n`;
    if (proceso?.flujo === "ventas") {
      prompt += `- Cliente elige plan → Ir directo a: "¿En qué zona estás?"\n`;
      prompt += `- Cliente da zona → Verificar cobertura y continuar\n`;
      prompt += `- NO preguntes "¿eres cliente actual o nuevo?"\n`;
      prompt += `- NO preguntes "¿qué velocidad buscas?" si ya eligió\n`;
    }
    prompt += `\n`;
    
    prompt += `⚠️ USA FUNCIONES, NO GENERES TEXTO:\n`;
    prompt += `- Cuando detectes intención clara, llama la función DIRECTAMENTE\n`;
    prompt += `- NO generes respuestas conversacionales innecesarias\n`;
    prompt += `- Las funciones ya tienen mensajes apropiados\n\n`;
    
    if (proceso?.flujo === "ventas") {
      prompt += `FUNCIONES DISPONIBLES:\n`;
      prompt += `- verificar_cobertura: Cuando mencione zona\n`;
      prompt += `- iniciar_contratacion: Cuando quiera contratar o ya eligió plan\n\n`;
    }
    
    prompt += `Máximo ${this.maxTokens} caracteres en respuestas SIN funciones.`;

    return prompt;
  }

  async callOpenAIWithFunctions(messages) {
    const response = await axios.post(
      "https://api.openai.com/v1/chat/completions",
      {
        model: this.botHandler.globalConfig.openai_modelo || "gpt-3.5-turbo",
        messages: messages,
        functions: this.getFunctions(),
        function_call: "auto",
        temperature: this.temperature,
        max_tokens: this.maxTokens,
      },
      {
        headers: {
          Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
          "Content-Type": "application/json",
        },
      }
    );

    const choice = response.data.choices[0];

    return {
      content: choice.message?.content,
      function_call: choice.message?.function_call,
      usage: response.data.usage,
    };
  }

  async detectarIntencionSoporte(mensaje, numero) {
    try {
      const contexto = await this.botHandler.getContexto(numero);
      const ultimoMensajeBot = contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";

      const prompt = `Detecta intención.

ÚLTIMO BOT: "${ultimoMensajeBot}"
CLIENTE: "${mensaje}"

Opciones:
- QUIERE_CONTRATAR
- PROBLEMA_TECNICO
- CONSULTA_PAGO
- CONVERSACION

Responde UNA palabra.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 20,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      return response.data.choices[0].message.content.trim().toUpperCase();
    } catch (error) {
      return "CONVERSACION";
    }
  }

  async ejecutarFuncion(nombre, args, numero, mensajeOriginal) {
    console.log(`🔧 Ejecutando: ${nombre}`, args);

    switch (nombre) {
      case "mostrar_planes":
        return await this.funcionMostrarPlanes();

      case "verificar_cobertura":
        return await this.funcionVerificarCobertura(args.zona, numero, mensajeOriginal);

      case "iniciar_contratacion":
        return await this.funcionIniciarContratacion(numero, args.plan, null, mensajeOriginal);

      case "mostrar_metodos_pago":
        return await this.funcionMostrarMetodosPago(numero);

      case "diagnosticar_problema":
        return await this.escalarConsulta(numero, `Problema técnico: ${mensajeOriginal}`);

      default:
        return { respuesta: "¿En qué puedo ayudarte?", tipo: "error" };
    }
  }

  async funcionMostrarPlanes() {
    if (this.planes.length === 0) {
      return {
        respuesta: "No hay planes disponibles. Contáctanos directamente.",
        tipo: "sin_planes",
      };
    }

    let msg = "📦 PLANES DISPONIBLES:\n\n";
    this.planes.forEach((plan, index) => {
      msg += `${index + 1}️⃣ ${plan.producto}\n`;
      msg += `   ${this.moneda.simbolo}${plan.precio}/mes\n`;
      if (plan.descripcion) msg += `   ${plan.descripcion}\n`;
      msg += "\n";
    });
    
    msg += "Escribe el *número* del plan que te interesa.";

    return { respuesta: msg, tipo: "lista_planes" };
  }

  async funcionVerificarCobertura(zona, numero, mensajeOriginal) {
    // Solo verificar si realmente hay una zona en el mensaje
    if (!zona && !mensajeOriginal) {
      return {
        respuesta: "¿En qué zona estás?",
        tipo: "solicitar_zona"
      };
    }

    const zonaExtraida = await this.extraerZonaDelMensaje(zona || mensajeOriginal);
    
    // Validar que se extrajo una zona válida
    if (!zonaExtraida || zonaExtraida === "desconocido" || zonaExtraida === "null") {
      return {
        respuesta: "¿En qué zona o distrito te encuentras?",
        tipo: "solicitar_zona"
      };
    }

    const hayCobertura = this.verificarCobertura(zonaExtraida);

    let proceso = this.procesosActivos.get(numero) || {
      flujo: "ventas",
      ultimaActividad: Date.now()
    };
    
    proceso.zona = zonaExtraida;
    this.procesosActivos.set(numero, proceso);

    if (!hayCobertura) {
      let msg = `Lo siento, aún no tenemos cobertura en ${zonaExtraida}.\n\n`;
      
      if (this.zonasCobertura.length > 0) {
        msg += `Atendemos en:\n`;
        this.zonasCobertura.forEach(z => {
          msg += `• ${z}\n`;
        });
      }
      
      return { respuesta: msg, tipo: "sin_cobertura" };
    }

    let msg = `Tenemos cobertura en ${zonaExtraida}\n\n`;
    
    if (this.planes.length > 0 && !proceso.plan) {
      msg += "📦 PLANES:\n\n";
      this.planes.forEach((plan, index) => {
        msg += `${index + 1}️⃣ ${plan.producto}\n`;
        msg += `   ${this.moneda.simbolo}${plan.precio}/mes\n`;
        if (plan.descripcion) msg += `   ${plan.descripcion}\n`;
        msg += "\n";
      });
      msg += "Escribe el *número* del plan.";
    } else if (proceso.plan) {
      // Si ya tiene plan, continuar con el flujo
      return await this.funcionIniciarContratacion(numero, null, null, mensajeOriginal);
    }

    return { respuesta: msg, tipo: "cobertura_disponible" };
  }

  async funcionIniciarContratacion(numero, planSeleccionado, zona, mensajeOriginal) {
    let proceso = this.procesosActivos.get(numero) || {
      flujo: "ventas",
      ultimaActividad: Date.now()
    };

    // DETECTAR plan automáticamente del mensaje
    const mensajeLower = mensajeOriginal.toLowerCase().trim();
    
    // Si dice "1" o "2" después de ver planes
    if (!proceso.plan && (mensajeLower === "1" || mensajeLower === "2")) {
      const numeroPlan = parseInt(mensajeLower);
      if (numeroPlan > 0 && numeroPlan <= this.planes.length) {
        proceso.plan = this.planes[numeroPlan - 1];
        console.log(`✅ Plan detectado automáticamente: ${proceso.plan.producto}`);
      }
    }
    
    // Si menciona velocidad (20 mbps, 50 mbps, etc)
    if (!proceso.plan) {
      const match = mensajeLower.match(/(\d+)\s*mbps/);
      if (match) {
        const velocidad = match[1];
        proceso.plan = this.planes.find(p => p.producto.toLowerCase().includes(velocidad));
        if (proceso.plan) {
          console.log(`✅ Plan detectado por velocidad: ${proceso.plan.producto}`);
        }
      }
    }

    if (planSeleccionado && !proceso.plan) {
      const plan = await this.identificarPlan(planSeleccionado);
      if (plan) {
        proceso.plan = plan;
      }
    }

    const datosExtraidos = await this.extraerDatosCompletosContratacion(mensajeOriginal);
    
    if (datosExtraidos.nombre) proceso.nombre = datosExtraidos.nombre;
    if (datosExtraidos.dni) proceso.dni = datosExtraidos.dni;
    if (datosExtraidos.direccion) proceso.direccion = datosExtraidos.direccion;
    if (datosExtraidos.zona && !proceso.zona) proceso.zona = datosExtraidos.zona;

    this.procesosActivos.set(numero, proceso);

    // Si ya tiene plan, ir directo a solicitar zona
    if (proceso.plan && !proceso.zona) {
      return {
        respuesta: "¿En qué zona estás?",
        tipo: "solicitar_zona"
      };
    }

    if (!proceso.zona) {
      return {
        respuesta: "¿En qué zona estás?",
        tipo: "solicitar_zona"
      };
    }

    if (!this.verificarCobertura(proceso.zona)) {
      this.procesosActivos.delete(numero);
      let msg = `No tenemos cobertura en ${proceso.zona}`;
      if (this.zonasCobertura.length > 0) {
        msg += `\n\nAtendemos en: ${this.zonasCobertura.join(", ")}`;
      }
      return { respuesta: msg, tipo: "sin_cobertura" };
    }

    if (!proceso.plan) {
      return {
        respuesta: "¿Cuál plan te interesa? Escribe el número.",
        tipo: "solicitar_plan"
      };
    }

    if (!proceso.direccion || !proceso.nombre || !proceso.dni) {
      let msg = "Para continuar necesito:\n\n";
      if (!proceso.direccion) msg += "• Tu dirección completa\n";
      if (!proceso.nombre) msg += "• Tu nombre completo\n";
      if (!proceso.dni) msg += "• Tu DNI o Cédula\n";
      msg += "\nPuedes enviarlo todo junto.";
      
      return { respuesta: msg, tipo: "solicitar_datos" };
    }

    return await this.mostrarResumenContratacion(numero, proceso);
  }

  async mostrarResumenContratacion(numero, proceso) {
    let msg = "📋 RESUMEN:\n\n";
    msg += `Plan: ${proceso.plan.producto}\n`;
    msg += `Precio: ${this.moneda.simbolo}${proceso.plan.precio}/mes\n`;
    
    if (proceso.plan.descripcion && proceso.plan.descripcion.includes("Instalación")) {
      const match = proceso.plan.descripcion.match(/S\/(\d+)/);
      if (match) {
        const costoInstalacion = parseFloat(match[1]);
        const costoMensual = parseFloat(proceso.plan.precio);
        msg += `\n💰 TOTAL:\n`;
        msg += `• Instalación: ${this.moneda.simbolo}${costoInstalacion}\n`;
        msg += `• Mensualidad: ${this.moneda.simbolo}${costoMensual}\n`;
        msg += `• TOTAL: ${this.moneda.simbolo}${(costoInstalacion + costoMensual).toFixed(2)}\n\n`;
      }
    }
    
    msg += `Nombre: ${proceso.nombre}\n`;
    msg += `Documento: ${proceso.dni}\n`;
    msg += `Dirección: ${proceso.direccion}\n\n`;
    msg += "¿Todo correcto? Responde SÍ para agendar instalación.";

    proceso.esperando_confirmacion_final = true;
    this.procesosActivos.set(numero, proceso);

    return { respuesta: msg, tipo: "confirmar_datos" };
  }

  async manejarConfirmacionFinal(mensaje, numero, proceso) {
    const msgLower = mensaje.toLowerCase().trim();

    if (msgLower.match(/^(si|sí|yes|ok|confirmo|correcto|dale|listo)$/)) {
      return await this.iniciarAgendamientoDias(numero, proceso);
    }

    if (msgLower.match(/^(no|cancelar)$/)) {
      this.procesosActivos.delete(numero);
      return {
        respuesta: "Solicitud cancelada. ¿Te ayudo con algo más?",
        tipo: "cancelado",
      };
    }

    return {
      respuesta: "Responde SÍ para confirmar o NO para cancelar.",
      tipo: "respuesta_invalida",
    };
  }

  async iniciarAgendamientoDias(numero, proceso) {
    const diasDisponibles = await this.obtenerDiasDisponibles();
    
    if (diasDisponibles.length === 0) {
      return {
        respuesta: "No hay disponibilidad. Contáctanos directamente.",
        tipo: "sin_disponibilidad",
      };
    }

    let msg = "📅 DÍAS DISPONIBLES:\n\n";
    diasDisponibles.forEach((dia, index) => {
      msg += `${index + 1}. ${dia.display}\n`;
    });
    msg += "\nEscribe el número del día.";

    proceso.diasDisponibles = diasDisponibles;
    proceso.esperando_seleccion_dia = true;
    proceso.esperando_confirmacion_final = false;
    this.procesosActivos.set(numero, proceso);

    return { respuesta: msg, tipo: "seleccion_fecha" };
  }

  async obtenerDiasDisponibles() {
    const dias = [];
    for (let i = 1; i <= 30; i++) {
      const fecha = moment().add(i, "days");
      const diaSemana = fecha.isoWeekday();

      const horarioDelDia = this.horarios.find(h => h.dia_semana === diaSemana);

      if (horarioDelDia) {
        dias.push({
          fecha: fecha.format("YYYY-MM-DD"),
          display: fecha.format("dddd D [de] MMMM"),
          diaSemana: diaSemana,
        });
      }

      if (dias.length >= 7) break;
    }

    return dias;
  }

  async procesarSeleccionDia(mensaje, numero, proceso) {
    const opcion = parseInt(mensaje.trim());

    if (isNaN(opcion) || opcion < 1 || opcion > proceso.diasDisponibles.length) {
      return {
        respuesta: `Escribe el número (1 al ${proceso.diasDisponibles.length})`,
        tipo: "seleccion_invalida",
      };
    }

    const diaSeleccionado = proceso.diasDisponibles[opcion - 1];
    proceso.fecha = diaSeleccionado.fecha;
    proceso.diaSemana = diaSeleccionado.diaSemana;
    proceso.esperando_seleccion_dia = false;
    proceso.esperando_seleccion_hora = true;
    
    this.procesosActivos.set(numero, proceso);

    return await this.mostrarHorariosDisponibles(numero, proceso);
  }

  async mostrarHorariosDisponibles(numero, proceso) {
    const horarioDelDia = this.horarios.find(h => h.dia_semana === proceso.diaSemana);

    if (!horarioDelDia) {
      return {
        respuesta: "No hay horario para este día.",
        tipo: "error_horario",
      };
    }

    const slotsDisponibles = await this.generarSlotsDisponibles(
      proceso.fecha,
      horarioDelDia,
      30
    );

    if (slotsDisponibles.length === 0) {
      return {
        respuesta: `No hay horarios disponibles`,
        tipo: "sin_horarios",
      };
    }

    let msg = `🕐 HORARIOS - ${moment(proceso.fecha).format("dddd D [de] MMMM")}\n\n`;
    slotsDisponibles.forEach((slot, index) => {
      msg += `${index + 1}. ${slot}\n`;
    });
    msg += "\nEscribe el número del horario.";

    proceso.slotsDisponibles = slotsDisponibles;
    this.procesosActivos.set(numero, proceso);

    return { respuesta: msg, tipo: "seleccion_hora" };
  }

  async procesarSeleccionHora(mensaje, numero, proceso) {
    const opcion = parseInt(mensaje.trim());

    if (isNaN(opcion) || opcion < 1 || opcion > proceso.slotsDisponibles.length) {
      return {
        respuesta: `Escribe el número (1 al ${proceso.slotsDisponibles.length})`,
        tipo: "hora_invalida",
      };
    }

    proceso.hora = proceso.slotsDisponibles[opcion - 1];
    
    const citaId = await this.guardarCitaBD(numero, proceso);
    
    await this.notificarInstalacion(citaId, proceso, numero);

    this.ventasCompletadas.set(numero, {
      timestamp: Date.now(),
      citaId: citaId,
    });

    this.procesosActivos.delete(numero);

    let msg = `INSTALACIÓN AGENDADA\n\n`;
    msg += `Cita #${citaId}\n`;
    msg += `• Plan: ${proceso.plan.producto}\n`;
    msg += `• Fecha: ${moment(proceso.fecha).format("dddd D [de] MMMM")}\n`;
    msg += `• Hora: ${proceso.hora}\n\n`;
    msg += `Te esperamos`;

    return { respuesta: msg, tipo: "cita_confirmada", citaId };
  }

  async funcionMostrarMetodosPago(numero) {
    if (!this.infoNegocio.metodos_pago_array || this.infoNegocio.metodos_pago_array.length === 0) {
      return await this.escalarConsulta(numero, "Solicita métodos de pago");
    }

    let msg = "💳 MÉTODOS DE PAGO:\n\n";
    this.infoNegocio.metodos_pago_array.forEach(metodo => {
      msg += `📱 ${metodo.tipo}\n`;
      msg += `   ${metodo.dato}\n`;
      if (metodo.instruccion) {
        msg += `   ${metodo.instruccion}\n`;
      }
      msg += "\n";
    });
    
    msg += "Una vez que pagues, escribe 'ya pagué' y envíame tu comprobante.";
    
    return { 
      respuesta: msg, 
      tipo: "metodos_pago"
    };
  }

  async escalarConsulta(numero, motivo) {
    const numeroLimpio = numero.replace("@c.us", "");

    await db.getPool().execute(
      `INSERT INTO estados_conversacion 
       (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado)
       VALUES (?, ?, 'escalado_humano', NOW(), ?)
       ON DUPLICATE KEY UPDATE 
         estado = 'escalado_humano',
         fecha_escalado = NOW(),
         motivo_escalado = ?`,
      [this.empresaId, numero, motivo, motivo]
    );

    await this.notificarEscalamiento(numeroLimpio, motivo);

    return {
      respuesta: "Un asesor te atenderá en breve.",
      tipo: "escalado"
    };
  }

  async notificarEscalamiento(numeroCliente, motivo) {
    if (!this.notificaciones.notificar_escalamiento) return;

    let numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
    if (!Array.isArray(numeros) || numeros.length === 0) return;

    let msg = `🔔 CONSULTA ESCALADA\n\n`;
    msg += `Cliente: ${numeroCliente}\n`;
    msg += `Motivo: ${motivo}\n`;
    msg += `Hora: ${new Date().toLocaleString("es-PE")}\n\n`;
    msg += `Atiende desde Escalados`;

    for (const num of numeros) {
      try {
        let numeroNotificar = num.replace(/[^\d]/g, "");
        if (!numeroNotificar.includes("@")) {
          numeroNotificar = `${numeroNotificar}@c.us`;
        }

        if (this.botHandler.whatsappClient?.client?.client?.sendText) {
          await this.botHandler.whatsappClient.client.client.sendText(numeroNotificar, msg);
        } else if (this.botHandler.whatsappClient?.client?.sendText) {
          await this.botHandler.whatsappClient.client.sendText(numeroNotificar, msg);
        }

        console.log(`✅ Notificación enviada a ${num}`);
      } catch (error) {
        console.error(`❌ Error notificando a ${num}:`, error.message);
      }
    }
  }

  async extraerZonaDelMensaje(mensaje) {
    try {
      const mensajeLower = mensaje.toLowerCase();
      
      for (const zona of this.zonasCobertura) {
        if (mensajeLower.includes(zona)) {
          return zona;
        }
      }

      const prompt = `Extrae SOLO el distrito o zona. Si no encuentras, responde "desconocido".

Mensaje: "${mensaje}"

Responde SOLO la zona en minúsculas, una palabra.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 10,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      return response.data.choices[0].message.content.trim().toLowerCase();
    } catch (error) {
      console.error("Error extrayendo zona:", error);
      return mensaje.toLowerCase();
    }
  }

  verificarCobertura(zona) {
    if (this.zonasCobertura.length === 0) {
      return true;
    }

    const zonaLower = zona.toLowerCase();
    return this.zonasCobertura.some(z => zonaLower.includes(z) || z.includes(zonaLower));
  }

  async identificarPlan(planTexto) {
    const numero = parseInt(planTexto);
    
    if (!isNaN(numero) && numero > 0 && numero <= this.planes.length) {
      return this.planes[numero - 1];
    }

    const planTextoLower = planTexto.toLowerCase();
    return this.planes.find(p => 
      p.producto.toLowerCase().includes(planTextoLower) ||
      planTextoLower.includes(p.producto.toLowerCase())
    );
  }

  async extraerDatosPersonales(mensaje) {
    try {
      const prompt = `Extrae nombre y documento.

Mensaje: "${mensaje}"

JSON:
{
  "nombre": "string o null",
  "dni": "string o null",
  "tipo_documento": "DNI|Cédula|Pasaporte"
}

Responde SOLO JSON válido.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 100,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const contenido = response.data.choices[0].message.content.trim();
      return JSON.parse(contenido);
    } catch (error) {
      console.error("Error extrayendo datos:", error);
      return { nombre: null, dni: null, tipo_documento: null };
    }
  }

  async extraerDatosCompletosContratacion(mensaje) {
    try {
      const prompt = `Extrae datos del mensaje. Responde SOLO JSON válido.

Mensaje: "${mensaje}"

JSON:
{
  "nombre": "string o null",
  "dni": "string o null",
  "direccion": "string o null",
  "zona": "string o null"
}

REGLAS:
- Dirección tiene "Jr", "Av", "Calle" o números de calle
- DNI son 8 dígitos consecutivos
- Zona es distrito mencionado (ventanilla, mi perú, etc)
- Nombre es texto sin números ni direcciones

Ejemplo:
"Jr comercio 304 Nilson Jhonny 47468849"
→ {"nombre": "Nilson Jhonny", "dni": "47468849", "direccion": "Jr comercio 304", "zona": null}

Responde SOLO JSON.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 150,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const contenido = response.data.choices[0].message.content.trim();
      console.log("🔍 GPT extrajo:", contenido);
      
      return JSON.parse(contenido);
    } catch (error) {
      console.error("Error extrayendo datos:", error);
      return { nombre: null, dni: null, direccion: null, zona: null };
    }
  }

  async generarSlotsDisponibles(fecha, horario, duracionServicio) {
    const slots = [];
    const horaInicio = moment(fecha + " " + horario.hora_inicio);
    const horaFin = moment(fecha + " " + horario.hora_fin);
    const duracionSlot = horario.duracion_cita;

    const [citasExistentes] = await db.getPool().execute(
      `SELECT hora_cita FROM citas_bot 
       WHERE empresa_id = ? AND fecha_cita = ? 
       AND estado IN ('agendada', 'confirmada')
       ORDER BY hora_cita`,
      [this.empresaId, fecha]
    );

    const horasOcupadas = citasExistentes.map(c => c.hora_cita.substring(0, 5));

    let horaActual = horaInicio.clone();

    while (horaActual.isBefore(horaFin)) {
      const horaFormato = horaActual.format("HH:mm");

      if (!horasOcupadas.includes(horaFormato)) {
        const tiempoRestante = horaFin.diff(horaActual, "minutes");
        if (tiempoRestante >= duracionServicio) {
          slots.push(horaFormato);
        }
      }

      horaActual.add(duracionSlot, "minutes");
    }

    return slots;
  }

  async guardarCitaBD(numero, proceso) {
    const [result] = await db.getPool().execute(
      `INSERT INTO citas_bot 
       (empresa_id, numero_cliente, nombre_cliente, dni_cedula, fecha_cita, hora_cita, 
        tipo_servicio, estado, direccion_completa)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'agendada', ?)`,
      [
        this.empresaId,
        numero,
        proceso.nombre,
        proceso.dni,
        proceso.fecha,
        proceso.hora + ":00",
        `Instalación ${proceso.plan.producto}`,
        proceso.direccion,
      ]
    );

    return result.insertId;
  }

  async notificarInstalacion(citaId, proceso, numero) {
    try {
      if (!this.notificaciones.notificar_citas) return;

      let numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
      if (!Array.isArray(numeros) || numeros.length === 0) return;

      let msg = this.notificaciones.mensaje_citas || `Nueva instalación #${citaId}`;

      msg = msg
        .replace("{nombre_cliente}", proceso.nombre)
        .replace("{servicio}", proceso.plan.producto)
        .replace("{fecha_cita}", moment(proceso.fecha).format("DD/MM/YYYY"))
        .replace("{hora_cita}", proceso.hora)
        .replace("{telefono}", numero.replace("@c.us", ""));

      for (const num of numeros) {
        try {
          let numeroLimpio = num.replace(/[^\d]/g, "");
          if (!numeroLimpio.includes("@")) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }

          if (this.botHandler.whatsappClient?.client?.client?.sendText) {
            await this.botHandler.whatsappClient.client.client.sendText(numeroLimpio, msg);
          } else if (this.botHandler.whatsappClient?.client?.sendText) {
            await this.botHandler.whatsappClient.client.sendText(numeroLimpio, msg);
          }
        } catch (error) {
          console.error(`Error notificando a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("Error en notificarInstalacion:", error);
    }
  }

  limpiarProcesosInactivos() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000;

    for (const [numero, proceso] of this.procesosActivos.entries()) {
      if (ahora - proceso.ultimaActividad > timeout) {
        this.procesosActivos.delete(numero);
      }
    }
  }

  limpiarVentasCompletadas() {
    const ahora = Date.now();
    const timeout = 3 * 60 * 1000;

    for (const [numero, ventaInfo] of this.ventasCompletadas.entries()) {
      if (ahora - ventaInfo.timestamp > timeout) {
        this.ventasCompletadas.delete(numero);
      }
    }
  }
}

module.exports = SupportBot;