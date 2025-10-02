// whatsapp-service/src/supportBot.js
const db = require("./database");
const AppointmentBot = require("./appointmentBot");
const axios = require("axios");

class SupportBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.botHandler = botHandler;

    // Solo reutiliza appointmentBot para instalaciones/visitas
    this.appointmentBot = new AppointmentBot(empresaId, botHandler);

    // Configuración
    this.infoBot = {};
    this.infoNegocio = {};
    this.planes = [];
    this.servicios = [];
    this.moneda = { codigo: "PEN", simbolo: "S/" };
    this.notificaciones = {};
    this.zonasCobertura = [];

    // Estados de flujos activos
    this.procesosVenta = new Map();
    this.tickets = new Map();
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
        
        // Extraer zonas de cobertura desde business_info
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

      // 3. Cargar planes/servicios desde catálogo
      const [catalogoRows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (catalogoRows[0] && catalogoRows[0].datos_json) {
        const datos = JSON.parse(catalogoRows[0].datos_json);
        this.planes = datos.productos || [];
      }

      // 4. Cargar servicios disponibles (para visitas técnicas)
      const [serviciosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM servicios_disponibles WHERE empresa_id = ? AND activo = 1 ORDER BY id",
          [this.empresaId]
        );
      this.servicios = serviciosRows;

      // 5. Notificaciones
      const [notifRows] = await db
        .getPool()
        .execute("SELECT * FROM notificaciones_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (notifRows[0]) {
        this.notificaciones = notifRows[0];
      }

      // 6. Cargar configuración de appointmentBot
      await this.appointmentBot.loadConfig();

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
    } catch (error) {
      console.error("❌ Error cargando configuración de soporte:", error);
    }
  }

  extraerZonasCobertura(businessInfo) {
    // Buscar la línea que contenga "Zonas de cobertura:"
    const regex = /zonas?\s+de\s+cobertura\s*:\s*([^\n]+)/i;
    const match = businessInfo.match(regex);
    
    if (match && match[1]) {
      // Extraer zonas separadas por comas
      return match[1]
        .split(',')
        .map(z => z.trim())
        .filter(z => z.length > 0);
    }
    
    return [];
  }

  verificarCobertura(direccion) {
    if (this.zonasCobertura.length === 0) {
      // Si no hay zonas definidas, asumir que hay cobertura
      return true;
    }

    const direccionLower = direccion.toLowerCase();
    
    // Buscar si alguna zona está mencionada en la dirección
    return this.zonasCobertura.some(zona => 
      direccionLower.includes(zona.toLowerCase())
    );
  }

  getFunctions() {
    return [
      // === VENTAS ===
      {
        name: "mostrar_planes",
        description: "Muestra planes/servicios disponibles cuando el cliente pregunta por precios o quiere contratar"
      },
      {
        name: "iniciar_contratacion",
        description: "Inicia proceso de contratación. SOLO úsalo cuando el cliente diga explícitamente que quiere contratar/comprar"
      },
      
      // === SOPORTE TÉCNICO ===
      {
        name: "diagnosticar_problema",
        description: "Cliente reporta un problema con su servicio actual. Úsalo para ayudar a diagnosticar"
      },
      {
        name: "crear_ticket",
        description: "Crea ticket de soporte cuando el problema no se puede resolver por chat"
      },
      {
        name: "agendar_visita",
        description: "Agenda visita técnica presencial"
      },
      
      // === PAGOS ===
      {
        name: "confirmar_pago",
        description: "Cliente dice que ya realizó un pago y quiere confirmarlo"
      }
    ];
  }

  async procesarMensajeSoporte(mensaje, numero) {
    // Verificar despedidas post-venta
    if (this.ventasCompletadas.has(numero)) {
      const ventaInfo = this.ventasCompletadas.get(numero);
      const tiempoTranscurrido = Date.now() - ventaInfo.timestamp;

      if (tiempoTranscurrido < 3 * 60 * 1000) {
        const respuestas = [
          "¡Nos vemos! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "Tu cita ya está agendada 😊",
        ];
        const respuesta =
          respuestas[Math.floor(Math.random() * respuestas.length)];

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

    // Verificar flujos técnicos activos
    const procesoVenta = this.procesosVenta.get(numero);
    
    if (procesoVenta) {
      procesoVenta.ultimaActividad = Date.now();

      switch (procesoVenta.estado) {
        case "esperando_zona":
          return await this.procesarZona(mensaje, numero, procesoVenta);

        case "seleccionar_plan":
          return await this.procesarSeleccionPlan(mensaje, numero, procesoVenta);

        case "esperando_direccion":
          return await this.procesarDireccion(mensaje, numero, procesoVenta);

        case "esperando_datos_personales":
          return await this.procesarDatosPersonales(mensaje, numero, procesoVenta);

        case "confirmar_datos":
          return await this.confirmarDatosVenta(mensaje, numero, procesoVenta);
      }
    }

    const ticket = this.tickets.get(numero);
    
    if (ticket) {
      ticket.ultimaActividad = Date.now();

      switch (ticket.estado) {
        case "esperando_descripcion":
          return await this.procesarDescripcionProblema(mensaje, numero, ticket);

        case "ofrecer_visita":
          return await this.procesarRespuestaVisita(mensaje, numero, ticket);
      }
    }

    // TODO LO DEMÁS usa Function Calling con GPT
    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  async respuestaConFunctionCalling(mensaje, numero) {
    if (!this.botHandler) {
      return { respuesta: "¿En qué puedo ayudarte?", tipo: "bot" };
    }

    try {
      const contexto = await this.botHandler.getContexto(numero);

      const ultimoMensaje =
        contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";
      const acabaDeAgendar =
        ultimoMensaje.includes("Cita #") ||
        ultimoMensaje.includes("agendada exitosamente") ||
        ultimoMensaje.includes("instalación agendada");

      if (acabaDeAgendar) {
        const respuestas = [
          "¡Nos vemos! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "Tu cita ya está confirmada 😊",
        ];

        const respuesta =
          respuestas[Math.floor(Math.random() * respuestas.length)];

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_cita" };
      }

      const systemPrompt = this.construirPromptGenerico();

      const messages = [{ role: "system", content: systemPrompt }];

      contexto.slice(-4).forEach((c) => {
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
          numero
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

  construirPromptGenerico() {
    let prompt = "";

    if (this.infoBot.system_prompt) {
      prompt += `${this.infoBot.system_prompt}\n\n`;
    }

    if (this.infoBot.prompt_ventas) {
      prompt += `${this.infoBot.prompt_ventas}\n\n`;
    }

    if (this.infoBot.business_info) {
      prompt += `INFORMACIÓN DEL NEGOCIO:\n${this.infoBot.business_info}\n\n`;
    }

    prompt += `📍 DATOS DE CONTACTO:\n`;
    if (this.infoNegocio.nombre_negocio) {
      prompt += `• Nombre: ${this.infoNegocio.nombre_negocio}\n`;
    }
    if (this.infoNegocio.telefono) {
      prompt += `• Teléfono: ${this.infoNegocio.telefono}\n`;
    }
    if (this.infoNegocio.direccion) {
      prompt += `• Dirección: ${this.infoNegocio.direccion}\n`;
    }
    prompt += "\n";

    prompt += `📦 PLANES/SERVICIOS DISPONIBLES:\n`;
    if (this.planes.length > 0) {
      this.planes.forEach((plan, index) => {
        prompt += `${index + 1}. ${plan.producto} - ${this.moneda.simbolo}${plan.precio}\n`;
        if (plan.descripcion) prompt += `   ${plan.descripcion}\n`;
      });
    } else {
      prompt += "No hay planes configurados\n";
    }
    prompt += "\n";

    if (this.servicios.length > 0) {
      prompt += `🔧 SERVICIOS TÉCNICOS:\n`;
      this.servicios.forEach((s) => {
        prompt += `• ${s.nombre_servicio} (${s.duracion_minutos} min)\n`;
      });
      prompt += "\n";
    }

    if (
      this.infoNegocio.metodos_pago_array &&
      this.infoNegocio.metodos_pago_array.length > 0
    ) {
      const metodos = this.infoNegocio.metodos_pago_array
        .map((m) => m.tipo)
        .join(", ");
      prompt += `💳 Métodos de pago: ${metodos}\n\n`;
    }

    prompt += `🎯 REGLAS PARA USO DE FUNCIONES:\n\n`;
    prompt += `⚠️ CRÍTICO: Cuando uses una función, NO generes texto conversacional adicional.\n\n`;
    prompt += `1. **mostrar_planes**: Solo cuando pregunte por precios o servicios disponibles\n`;
    prompt += `2. **iniciar_contratacion**: SOLO cuando diga "quiero contratar/comprar"\n`;
    prompt += `3. **diagnosticar_problema**: Cuando reporte un problema técnico\n`;
    prompt += `4. **crear_ticket**: Si el problema no se puede resolver por chat\n`;
    prompt += `5. **agendar_visita**: Para agendar visita técnica presencial\n`;
    prompt += `6. **confirmar_pago**: Cuando diga que ya realizó un pago\n\n`;
    prompt += `❌ NO HAGAS:\n`;
    prompt += `- Usar funciones sin que el cliente lo solicite\n`;
    prompt += `- Generar texto cuando llamas funciones\n`;
    prompt += `- Dar soluciones técnicas específicas (router, software, etc) sin contexto del negocio\n\n`;
    prompt += `✅ HAZ:\n`;
    prompt += `- Mantén una conversación natural, responde preguntas antes de pedir datos\n`;
    prompt += `- Si preguntan algo (como métodos de pago), responde eso primero\n`;
    prompt += `- Máximo ${this.maxTokens} caracteres en respuestas sin funciones\n`;
    prompt += `- Adapta tus respuestas al tipo de negocio del cliente\n`;

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

  async ejecutarFuncion(nombre, args, numero) {
    console.log(`🔧 Ejecutando función: ${nombre}`, args);

    switch (nombre) {
      case "mostrar_planes":
        return await this.funcionMostrarPlanes();

      case "iniciar_contratacion":
        return await this.funcionIniciarContratacion(numero);

      case "diagnosticar_problema":
        return await this.funcionDiagnosticarProblema(numero);

      case "crear_ticket":
        return await this.funcionCrearTicket(numero);

      case "agendar_visita":
        return await this.funcionAgendarVisita(numero);

      case "confirmar_pago":
        return await this.funcionConfirmarPago(numero);

      default:
        return { respuesta: "¿En qué puedo ayudarte?", tipo: "error" };
    }
  }

  // ===========================================
  // FUNCIONES DEL BOT
  // ===========================================

  async funcionMostrarPlanes() {
    if (this.planes.length === 0) {
      return {
        respuesta:
          "Lo siento, no hay planes disponibles en este momento. Contacta directamente.",
        tipo: "sin_planes",
      };
    }

    let msg = "📦 PLANES/SERVICIOS DISPONIBLES:\n\n";
    this.planes.forEach((plan, index) => {
      msg += `${index + 1}. *${plan.producto}*\n`;
      msg += `   ${this.moneda.simbolo}${plan.precio}/mes\n`;
      if (plan.descripcion) msg += `   ${plan.descripcion}\n`;
      msg += "\n";
    });
    msg += "Escribe el *número* del plan que te interesa.";

    return { respuesta: msg, tipo: "lista_planes" };
  }

  async funcionIniciarContratacion(numero) {
    if (this.planes.length === 0) {
      return {
        respuesta:
          "Lo siento, no hay planes disponibles. Contacta directamente.",
        tipo: "sin_planes",
      };
    }

    // Primero verificar cobertura
    this.procesosVenta.set(numero, {
      estado: "esperando_zona",
      datos: {},
      ultimaActividad: Date.now(),
    });

    return {
      respuesta: "Para verificar disponibilidad, ¿en qué zona o distrito te encuentras?",
      tipo: "solicitar_zona",
    };
  }

  async funcionDiagnosticarProblema(numero) {
    let msg = "Voy a ayudarte a resolver el problema.\n\n";
    msg += "Por favor, descríbeme con detalle:\n";
    msg += "• ¿Qué problema tienes?\n";
    msg += "• ¿Desde cuándo ocurre?\n";
    msg += "• ¿Has intentado algo?";

    this.tickets.set(numero, {
      estado: "esperando_descripcion",
      datos: {},
      ultimaActividad: Date.now(),
    });

    return { respuesta: msg, tipo: "diagnosticar_problema" };
  }

  async funcionCrearTicket(numero) {
    const ticketId = await this.crearTicketEnBD(numero, {
      descripcion: "Problema reportado vía chat",
    });

    let msg = `✅ Ticket #${ticketId} creado\n`;
    msg += `Prioridad: Alta\n\n`;
    msg += "¿Necesitas *visita técnica*?\n";
    msg += "Responde SÍ o NO";

    this.tickets.set(numero, {
      estado: "ofrecer_visita",
      ticket_id: ticketId,
      ultimaActividad: Date.now(),
    });

    return { respuesta: msg, tipo: "ticket_creado", ticketId };
  }

  async funcionAgendarVisita(numero) {
    return await this.appointmentBot.procesarMensajeCita("", numero);
  }

  async funcionConfirmarPago(numero) {
    return {
      respuesta:
        "📷 Por favor, envía tu *comprobante de pago*\n(Foto o captura de pantalla)",
      tipo: "solicitar_comprobante",
    };
  }

  // ===========================================
  // FLUJOS TÉCNICOS
  // ===========================================

  async procesarZona(mensaje, numero, proceso) {
    const zona = mensaje.trim();
    const hayCobertura = this.verificarCobertura(zona);

    if (!hayCobertura) {
      this.procesosVenta.delete(numero);
      
      let msg = `Lo siento, aún no tenemos cobertura en ${zona}. 😔\n\n`;
      
      if (this.zonasCobertura.length > 0) {
        msg += `Actualmente atendemos en:\n`;
        this.zonasCobertura.forEach(z => {
          msg += `• ${z}\n`;
        });
      }
      
      msg += `\n¡Pronto llegaremos a más zonas!`;
      
      return {
        respuesta: msg,
        tipo: "sin_cobertura",
      };
    }

    // Hay cobertura, mostrar planes
    proceso.datos.zona = zona;
    proceso.estado = "seleccionar_plan";
    this.procesosVenta.set(numero, proceso);

    let msg = `¡Excelente! Tenemos cobertura en ${zona} 🎉\n\n`;
    msg += "📦 PLANES DISPONIBLES:\n\n";
    this.planes.forEach((plan, index) => {
      msg += `${index + 1}. *${plan.producto}*\n`;
      msg += `   ${this.moneda.simbolo}${plan.precio}/mes\n`;
      if (plan.descripcion) msg += `   ${plan.descripcion}\n`;
      msg += "\n";
    });
    msg += "Escribe el *número* del plan que deseas contratar.";

    return { respuesta: msg, tipo: "mostrar_planes_con_cobertura" };
  }

  async procesarSeleccionPlan(mensaje, numero, proceso) {
    const numeroSeleccionado = parseInt(mensaje.trim());

    if (isNaN(numeroSeleccionado) || numeroSeleccionado < 1 || numeroSeleccionado > this.planes.length) {
      return {
        respuesta: `Por favor, escribe el *número* del plan (1 al ${this.planes.length})`,
        tipo: "seleccion_invalida",
      };
    }

    const planSeleccionado = this.planes[numeroSeleccionado - 1];
    proceso.datos.plan_seleccionado = planSeleccionado;
    proceso.estado = "esperando_direccion";
    this.procesosVenta.set(numero, proceso);

    return {
      respuesta: `✅ Perfecto, elegiste *${planSeleccionado.producto}*\n\n¿Cuál es tu dirección completa para la instalación?`,
      tipo: "solicitar_direccion",
    };
  }

  async procesarDireccion(mensaje, numero, proceso) {
    proceso.datos.direccion = mensaje;
    proceso.datos.numero_celular = numero.replace("@c.us", "");
    proceso.estado = "esperando_datos_personales";
    this.procesosVenta.set(numero, proceso);

    return {
      respuesta: `✅ Dirección registrada\n\nPara continuar, necesito:\n• Tu *nombre completo*\n• Tu *DNI o Cédula*\n\nEjemplo: Juan Pérez Torres, DNI 12345678`,
      tipo: "solicitar_datos",
    };
  }

  async procesarDatosPersonales(mensaje, numero, proceso) {
    const datos = await this.extraerDatosPersonales(mensaje);

    if (!datos.nombre || !datos.dni) {
      return {
        respuesta:
          "No pude identificar tu nombre o documento. Intenta así:\nJuan Pérez Torres, DNI 12345678",
        tipo: "datos_invalidos",
      };
    }

    proceso.datos.nombre_completo = datos.nombre;
    proceso.datos.dni_cedula = datos.dni;
    proceso.estado = "confirmar_datos";
    this.procesosVenta.set(numero, proceso);

    let resumen = "📋 *RESUMEN DE TU SOLICITUD*\n\n";
    resumen += `Plan: ${proceso.datos.plan_seleccionado.producto}\n`;
    resumen += `Precio: ${this.moneda.simbolo}${proceso.datos.plan_seleccionado.precio}/mes\n`;
    resumen += `Nombre: ${proceso.datos.nombre_completo}\n`;
    resumen += `Documento: ${proceso.datos.dni_cedula}\n`;
    resumen += `Dirección: ${proceso.datos.direccion}\n\n`;
    resumen += "¿Todo correcto? Responde *SÍ* para agendar instalación.";

    return { respuesta: resumen, tipo: "confirmar_datos" };
  }

  async confirmarDatosVenta(mensaje, numero, proceso) {
    const msgLower = mensaje.toLowerCase().trim();

    if (msgLower.match(/^(si|sí|yes|ok|confirmo|correcto|dale)$/)) {
      const resultado =
        await this.appointmentBot.iniciarAgendamientoConDatos({
          numero: numero,
          nombre: proceso.datos.nombre_completo,
          dni_cedula: proceso.datos.dni_cedula,
          direccion: proceso.datos.direccion,
          servicio: `Instalación ${proceso.datos.plan_seleccionado.producto}`,
        });

      this.ventasCompletadas.set(numero, {
        timestamp: Date.now(),
        plan: proceso.datos.plan_seleccionado,
      });

      this.procesosVenta.delete(numero);
      return resultado;
    }

    if (msgLower.match(/^(no|cancelar)$/)) {
      this.procesosVenta.delete(numero);
      return {
        respuesta: "Solicitud cancelada. ¿Te ayudo con algo más?",
        tipo: "cancelado",
      };
    }

    return {
      respuesta: "Por favor responde *SÍ* para confirmar o *NO* para cancelar.",
      tipo: "respuesta_invalida",
    };
  }

  async procesarDescripcionProblema(mensaje, numero, ticket) {
    ticket.datos.descripcion_completa = mensaje;

    const ticketId = await this.crearTicketEnBD(numero, ticket.datos);

    let msg = `✅ Ticket #${ticketId} creado\n`;
    msg += `Tu problema ha sido registrado\n\n`;
    msg += "¿Necesitas *visita técnica*?\n";
    msg += "Responde SÍ o NO";

    ticket.estado = "ofrecer_visita";
    ticket.ticket_id = ticketId;
    this.tickets.set(numero, ticket);

    await this.notificarEscalamiento(ticketId, numero, ticket.datos);

    return { respuesta: msg, tipo: "ticket_creado", ticketId };
  }

  async procesarRespuestaVisita(mensaje, numero, ticket) {
    const msgLower = mensaje.toLowerCase().trim();

    if (msgLower.match(/^(si|sí|yes|ok)$/)) {
      this.tickets.delete(numero);
      return await this.appointmentBot.procesarMensajeCita(mensaje, numero);
    }

    this.tickets.delete(numero);
    return {
      respuesta: `Entendido. Te contactaremos pronto para resolver tu problema.\n\nTicket #${ticket.ticket_id}`,
      tipo: "sin_visita",
    };
  }

  // ===========================================
  // MÉTODOS AUXILIARES
  // ===========================================

  async extraerDatosPersonales(mensaje) {
    try {
      const prompt = `Extrae el nombre completo y documento de identidad (DNI, Cédula, CI, etc) del siguiente mensaje:
    
"${mensaje}"

Responde SOLO en formato JSON:
{
  "nombre": "Nombre Completo Apellido",
  "dni": "12345678"
}

Si no encuentras alguno, usa null.`;

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
      return { nombre: null, dni: null };
    }
  }

  async crearTicketEnBD(numero, datos) {
    try {
      const [result] = await db.getPool().execute(
        `INSERT INTO tickets_soporte 
         (empresa_id, numero_cliente, tipo_problema, descripcion, prioridad, estado)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [
          this.empresaId,
          numero,
          "Problema técnico",
          datos.descripcion_completa || datos.descripcion || "Sin descripción",
          "alta",
          "abierto",
        ]
      );

      return result.insertId;
    } catch (error) {
      console.error("Error creando ticket:", error);
      return 0;
    }
  }

  async notificarEscalamiento(ticketId, numero, datos) {
    try {
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

      let msg =
        this.notificaciones.mensaje_escalamiento ||
        `🚨 Ticket #${ticketId} - Problema reportado`;

      msg = msg
        .replace("{nombre_cliente}", numero.replace("@c.us", ""))
        .replace("{numero_cliente}", numero.replace("@c.us", ""))
        .replace(
          "{motivo_escalamiento}",
          datos.descripcion_completa || "Sin descripción"
        )
        .replace("{fecha_hora}", new Date().toLocaleString("es-PE"));

      for (const num of numeros) {
        try {
          let numeroLimpio = num.replace(/[^\d]/g, "");
          if (!numeroLimpio.includes("@")) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }

          if (this.botHandler.whatsappClient?.client?.client?.sendText) {
            await this.botHandler.whatsappClient.client.client.sendText(
              numeroLimpio,
              msg
            );
          } else if (this.botHandler.whatsappClient?.client?.sendText) {
            await this.botHandler.whatsappClient.client.sendText(
              numeroLimpio,
              msg
            );
          }
        } catch (error) {
          console.error(`Error enviando notificación a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("Error en notificarEscalamiento:", error);
    }
  }

  limpiarProcesosInactivos() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000;

    for (const [numero, proceso] of this.procesosVenta.entries()) {
      if (ahora - proceso.ultimaActividad > timeout) {
        this.procesosVenta.delete(numero);
        console.log(`🧹 Proceso de venta limpiado: ${numero}`);
      }
    }

    for (const [numero, ticket] of this.tickets.entries()) {
      if (ahora - ticket.ultimaActividad > timeout) {
        this.tickets.delete(numero);
        console.log(`🧹 Ticket limpiado: ${numero}`);
      }
    }
  }

  limpiarVentasCompletadas() {
    const ahora = Date.now();
    const timeout = 3 * 60 * 1000;

    for (const [numero, ventaInfo] of this.ventasCompletadas.entries()) {
      if (ahora - ventaInfo.timestamp > timeout) {
        this.ventasCompletadas.delete(numero);
        console.log(`🧹 Venta completada limpiada: ${numero}`);
      }
    }
  }
}

module.exports = SupportBot;