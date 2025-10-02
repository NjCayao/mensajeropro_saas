// whatsapp-service/src/appointmentBot.js
const db = require("./database");
const moment = require("moment");
const axios = require("axios");

moment.locale("es");

class AppointmentBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.botHandler = botHandler;

    // Configuración desde BD
    this.infoBot = {};
    this.infoNegocio = {};
    this.horarios = [];
    this.servicios = [];
    this.notificaciones = {};

    // Control de flujo
    this.citasEnProceso = new Map();
    this.citasCompletadas = new Map();

    // Configuración IA
    this.maxTokens = 150;
    this.temperature = 0.7;

    // Limpieza automática
    setInterval(() => this.limpiarCitasInactivas(), 5 * 60 * 1000);
    setInterval(() => this.limpiarCitasCompletadas(), 5 * 60 * 1000);
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
      }

      // 2. Información del negocio
      const [negocio] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (negocio[0]) {
        this.infoNegocio = negocio[0];
      }

      // 3. Horarios de atención
      const [horariosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM horarios_atencion WHERE empresa_id = ? AND activo = 1 ORDER BY dia_semana",
          [this.empresaId]
        );
      this.horarios = horariosRows;

      // 4. Servicios disponibles
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

      // 6. Configuración de tokens y temperatura
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
      console.error("❌ Error cargando configuración de citas:", error);
    }
  }

  getFunctions() {
    return [
      {
        name: "listar_servicios",
        description:
          "Muestra los servicios disponibles cuando el cliente pregunta qué servicios hay",
      },
      {
        name: "verificar_disponibilidad",
        description:
          "Verifica horarios disponibles para una fecha específica. Úsalo cuando el cliente pregunte '¿qué días/horas tienen?' o mencione una fecha",
        parameters: {
          type: "object",
          properties: {
            fecha: {
              type: "string",
              description: "Fecha en formato YYYY-MM-DD",
            },
            servicio: {
              type: "string",
              description: "Nombre del servicio (opcional)",
            },
          },
          required: ["fecha"],
        },
      },
      {
        name: "iniciar_agendamiento",
        description:
          "SOLO úsalo cuando el cliente diga explícitamente que quiere agendar/reservar una cita",
      },
      {
        name: "cancelar_cita",
        description:
          "Cancela una cita existente. Úsalo cuando el cliente diga 'cancelar mi cita' o 'ya no puedo ir'",
        parameters: {
          type: "object",
          properties: {
            cita_id: {
              type: "integer",
              description: "ID de la cita (opcional si solo tiene una)",
            },
          },
        },
      },
    ];
  }

  async procesarMensajeCita(mensaje, numero) {
    // 1. Verificar citas completadas recientemente (despedida post-cita)
    if (this.citasCompletadas.has(numero)) {
      const citaInfo = this.citasCompletadas.get(numero);
      const tiempoTranscurrido = Date.now() - citaInfo.timestamp;

      if (tiempoTranscurrido < 3 * 60 * 1000) {
        const respuestas = [
          "¡Nos vemos en tu cita! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "Tu cita ya está agendada y confirmada 😊",
        ];

        const respuesta =
          respuestas[Math.floor(Math.random() * respuestas.length)];

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_cita" };
      } else {
        this.citasCompletadas.delete(numero);
      }
    }

    // 2. Verificar si tiene cita pendiente Y está intentando agendar otra
    const citasPendientes = await this.verificarCitasPendientes(numero);

    if (citasPendientes.length > 0 && this.intentaAgendar(mensaje)) {
      console.log(
        "⚠️ Cliente con cita pendiente intenta agendar otra → Recordar"
      );
      return await this.recordarCitaPendiente(numero, citasPendientes[0]);
    }

    // 3. Manejo de flujos técnicos
    const cita = this.citasEnProceso.get(numero);

    if (cita) {
      cita.ultimaActividad = Date.now();

      switch (cita.estado) {
        case "confirmar_cancelacion":
          return await this.manejarCancelacion(mensaje, numero, cita);

        case "esperando_servicio":
          return await this.procesarSeleccionServicio(mensaje, numero, cita);

        case "esperando_fecha":
          return await this.procesarSeleccionFecha(mensaje, numero, cita);

        case "esperando_hora":
          return await this.procesarSeleccionHora(mensaje, numero, cita);

        case "esperando_nombre":
          return await this.procesarNombreCliente(mensaje, numero, cita);

        case "esperando_confirmacion_final":
          return await this.manejarConfirmacionFinal(mensaje, numero, cita);
      }
    }

    // 4. TODO LO DEMÁS usa OpenAI con Function Calling
    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  intentaAgendar(mensaje) {
    const msgLower = mensaje.toLowerCase();
    return msgLower.match(
      /\b(cita|agendar|reservar|turno|apartar|consulta|hora)\b/
    );
  }

  async verificarCitasPendientes(numero) {
    try {
      const [rows] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
         WHERE numero_cliente = ? 
         AND empresa_id = ?
         AND estado IN ('agendada', 'confirmada')
         AND fecha_cita >= CURDATE()
         ORDER BY fecha_cita ASC`,
        [numero, this.empresaId]
      );

      return rows;
    } catch (error) {
      console.error("Error verificando citas pendientes:", error);
      return [];
    }
  }

  async recordarCitaPendiente(numero, cita) {
    const fechaCita = moment(cita.fecha_cita).format("dddd D [de] MMMM");

    let respuesta = `📅 Ya tienes una cita agendada:\n\n`;
    respuesta += `• Servicio: ${cita.tipo_servicio}\n`;
    respuesta += `• Fecha: ${fechaCita}\n`;
    respuesta += `• Hora: ${cita.hora_cita.substring(0, 5)}\n\n`;
    respuesta += `¿Deseas *cancelar* esta cita para agendar una nueva?\n`;
    respuesta += `O responde *"mantener"* para conservar tu cita actual.`;

    this.citasEnProceso.set(numero, {
      estado: "confirmar_cancelacion",
      citaExistente: cita,
      ultimaActividad: Date.now(),
    });

    return { respuesta, tipo: "recordatorio_cita_existente" };
  }

  async manejarCancelacion(mensaje, numero, procesoActual) {
    const msgLower = mensaje.toLowerCase().trim();

    if (msgLower.match(/^(si|sí|yes|ok|cancelar|dale|confirmo|está bien)$/)) {
      const citaId = procesoActual.citaExistente.id;

      await db
        .getPool()
        .execute("UPDATE citas_bot SET estado = 'cancelada' WHERE id = ?", [
          citaId,
        ]);

      await this.cancelarEventoGoogle(citaId);

      this.citasEnProceso.delete(numero);

      return {
        respuesta: `✅ Cita cancelada exitosamente.\n\n¿Deseas agendar una nueva cita ahora?`,
        tipo: "cita_cancelada",
        citaId,
      };
    }

    if (msgLower.match(/\b(mantener|no|conservar|quedar)\b/)) {
      this.citasEnProceso.delete(numero);

      const fechaCita = moment(procesoActual.citaExistente.fecha_cita).format(
        "dddd D [de] MMMM"
      );

      return {
        respuesta: `Perfecto, tu cita del ${fechaCita} a las ${procesoActual.citaExistente.hora_cita.substring(
          0,
          5
        )} se mantiene. ¡Te esperamos! 😊`,
        tipo: "cita_mantenida",
      };
    }

    return {
      respuesta:
        "Por favor responde *SÍ* para cancelar tu cita actual, o *MANTENER* para conservarla.",
      tipo: "respuesta_invalida",
    };
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
        ultimoMensaje.includes("Cita #") &&
        ultimoMensaje.includes("agendada exitosamente");

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

      const systemPrompt = await this.construirPromptGenerico(numero);

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
          JSON.parse(response.function_call.arguments),
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

  async construirPromptGenerico(numero) {
    let prompt = "";

    if (this.infoBot.system_prompt) {
      prompt += `${this.infoBot.system_prompt}\n\n`;
    }

    if (this.infoBot.prompt_citas) {
      prompt += `${this.infoBot.prompt_citas}\n\n`;
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

    prompt += `🏥 SERVICIOS DISPONIBLES:\n`;
    if (this.servicios.length > 0) {
      this.servicios.forEach((s) => {
        prompt += `• ${s.nombre_servicio} (${s.duracion_minutos} min)\n`;
        if (s.requiere_preparacion) {
          prompt += `  ⚠️ ${s.requiere_preparacion}\n`;
        }
      });
      prompt += "\n";
    } else {
      prompt += "No hay servicios configurados\n\n";
    }

    prompt += `🕐 HORARIOS DE ATENCIÓN:\n`;
    if (this.horarios.length > 0) {
      const dias = [
        "",
        "Lunes",
        "Martes",
        "Miércoles",
        "Jueves",
        "Viernes",
        "Sábado",
        "Domingo",
      ];

      this.horarios.forEach((h) => {
        prompt += `${dias[h.dia_semana]}: ${h.hora_inicio.substring(
          0,
          5
        )} - ${h.hora_fin.substring(0, 5)}\n`;
      });
      prompt += "\n";
    } else {
      prompt += "No hay horarios configurados\n\n";
    }

    const citasPendientes = await this.verificarCitasPendientes(numero);

    if (citasPendientes.length > 0) {
      const cita = citasPendientes[0];
      prompt += `⚠️ IMPORTANTE: Este cliente YA tiene una cita agendada:\n`;
      prompt += `• Fecha: ${moment(cita.fecha_cita).format(
        "dddd D [de] MMMM"
      )}\n`;
      prompt += `• Hora: ${cita.hora_cita.substring(0, 5)}\n`;
      prompt += `• Servicio: ${cita.tipo_servicio}\n\n`;
      prompt += `Si el cliente intenta agendar OTRA cita:\n`;
      prompt += `1. Recuérdale que ya tiene una cita\n`;
      prompt += `2. Pregunta si quiere cancelar la actual\n`;
      prompt += `3. NO uses iniciar_agendamiento sin antes cancelar\n\n`;
      prompt += `Si solo pregunta sobre servicios/horarios → responde normalmente.\n\n`;
    }

    prompt += `🎯 REGLAS PARA USO DE FUNCIONES:\n\n`;
    prompt += `⚠️ CRÍTICO: Cuando uses una función, NO generes texto conversacional adicional.\n\n`;
    prompt += `1. **listar_servicios**: Solo cuando pregunte "¿qué servicios tienen?"\n`;
    prompt += `2. **verificar_disponibilidad**: Solo cuando pregunte por fechas/horas disponibles\n`;
    prompt += `3. **iniciar_agendamiento**: SOLO cuando diga "quiero una cita/turno/reserva"\n`;
    prompt += `4. **cancelar_cita**: Solo cuando diga "cancelar mi cita"\n\n`;
    prompt += `❌ NO HAGAS:\n`;
    prompt += `- Agendar citas sin que el cliente lo pida explícitamente\n`;
    prompt += `- Generar texto conversacional cuando llamas funciones\n`;
    prompt += `- Agendar otra cita si ya tiene una pendiente\n\n`;
    prompt += `✅ HAZ:\n`;
    prompt += `- Responde preguntas normalmente sin usar funciones innecesariamente\n`;
    prompt += `- Usa funciones solo cuando sea estrictamente necesario\n`;
    prompt += `- Máximo ${this.maxTokens} caracteres en respuestas sin funciones\n`;

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
      case "listar_servicios":
        return await this.funcionListarServicios();

      case "verificar_disponibilidad":
        return await this.funcionVerificarDisponibilidad(args.fecha, numero);

      case "iniciar_agendamiento":
        return await this.funcionIniciarAgendamiento(numero);

      case "cancelar_cita":
        return await this.funcionCancelarCita(numero, args.cita_id);

      default:
        return { respuesta: "¿En qué puedo ayudarte?", tipo: "error" };
    }
  }

  async funcionListarServicios() {
    if (this.servicios.length === 0) {
      return {
        respuesta:
          "Lo siento, no hay servicios disponibles en este momento. Por favor, contacta directamente.",
        tipo: "sin_servicios",
      };
    }

    let msg = "🏥 SERVICIOS DISPONIBLES:\n\n";

    this.servicios.forEach((s, index) => {
      msg += `${index + 1}. *${s.nombre_servicio}* (${
        s.duracion_minutos
      } min)\n`;
      if (s.requiere_preparacion) {
        msg += `   ⚠️ ${s.requiere_preparacion}\n`;
      }
      msg += "\n";
    });

    msg += "¿Cuál te interesa?";

    return { respuesta: msg, tipo: "lista_servicios" };
  }

  async funcionVerificarDisponibilidad(fecha, numero) {
    const fechaMoment = moment(fecha);
    if (!fechaMoment.isValid() || fechaMoment.isBefore(moment(), "day")) {
      return {
        respuesta: "Por favor indica una fecha válida (desde hoy en adelante).",
        tipo: "fecha_invalida",
      };
    }

    const diaSemana = fechaMoment.isoWeekday();
    const horarioDelDia = this.horarios.find((h) => h.dia_semana === diaSemana);

    if (!horarioDelDia) {
      return {
        respuesta: `Lo siento, no atendemos los ${fechaMoment.format(
          "dddd"
        )}s.`,
        tipo: "dia_no_disponible",
      };
    }

    const slots = await this.generarSlotsDisponibles(fecha, horarioDelDia, 30);

    if (slots.length === 0) {
      return {
        respuesta: `No hay horarios disponibles el ${fechaMoment.format(
          "dddd D [de] MMMM"
        )}. ¿Te interesa otra fecha?`,
        tipo: "sin_disponibilidad",
      };
    }

    let msg = `📅 Horarios disponibles - ${fechaMoment.format(
      "dddd D [de] MMMM"
    )}:\n\n`;
    msg += slots.join(", ");
    msg += "\n\n¿Cuál prefieres?";

    return { respuesta: msg, tipo: "horarios_disponibles" };
  }

  async funcionIniciarAgendamiento(numero) {
    const citasPendientes = await this.verificarCitasPendientes(numero);

    if (citasPendientes.length > 0) {
      const cita = citasPendientes[0];
      return {
        respuesta: `Ya tienes una cita agendada el ${moment(
          cita.fecha_cita
        ).format("D/MM")} a las ${cita.hora_cita.substring(
          0,
          5
        )}.\n\n¿Deseas cancelarla para agendar una nueva?`,
        tipo: "tiene_cita_pendiente",
      };
    }

    if (this.servicios.length === 0) {
      return {
        respuesta:
          "Lo siento, no hay servicios disponibles. Contacta directamente.",
        tipo: "sin_servicios",
      };
    }

    let msg = "📋 SERVICIOS DISPONIBLES:\n\n";

    this.servicios.forEach((s, index) => {
      msg += `${index + 1}. *${s.nombre_servicio}*\n`;
    });

    msg += "\nEscribe el *número* del servicio que deseas.";

    this.citasEnProceso.set(numero, {
      estado: "esperando_servicio",
      ultimaActividad: Date.now(),
    });

    return { respuesta: msg, tipo: "iniciar_agendamiento" };
  }

  async funcionCancelarCita(numero, citaId = null) {
    const citasPendientes = await this.verificarCitasPendientes(numero);

    if (citasPendientes.length === 0) {
      return {
        respuesta: "No tienes citas pendientes para cancelar.",
        tipo: "sin_citas",
      };
    }

    const cita = citaId
      ? citasPendientes.find((c) => c.id === citaId)
      : citasPendientes[0];

    if (!cita) {
      return {
        respuesta: "No se encontró esa cita.",
        tipo: "cita_no_encontrada",
      };
    }

    await db
      .getPool()
      .execute("UPDATE citas_bot SET estado = 'cancelada' WHERE id = ?", [
        cita.id,
      ]);

    await this.cancelarEventoGoogle(cita.id);

    return {
      respuesta: `✅ Cita cancelada:\n\nServicio: ${
        cita.tipo_servicio
      }\nFecha: ${moment(cita.fecha_cita).format(
        "D/MM"
      )}\nHora: ${cita.hora_cita.substring(
        0,
        5
      )}\n\n¿Deseas agendar una nueva?`,
      tipo: "cita_cancelada",
      citaId: cita.id,
    };
  }

  // ============================================
  // MÉTODOS TÉCNICOS DE AGENDAMIENTO
  // ============================================

  async procesarSeleccionServicio(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());

    if (isNaN(opcion) || opcion < 1 || opcion > this.servicios.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${this.servicios.length}`,
        tipo: "servicio_invalido",
      };
    }

    cita.servicio = this.servicios[opcion - 1];
    cita.estado = "esperando_fecha";
    this.citasEnProceso.set(numero, cita);

    return await this.mostrarDiasDisponibles(numero, cita);
  }

  async mostrarDiasDisponibles(numero, cita) {
    const diasDisponibles = [];
    const hoy = moment();

    for (let i = 1; i <= 30; i++) {
      const fecha = moment().add(i, "days");
      const diaSemana = fecha.isoWeekday();

      const horarioDelDia = this.horarios.find(
        (h) => h.dia_semana === diaSemana
      );

      if (horarioDelDia) {
        const disponible = await this.verificarDisponibilidadDia(
          fecha.format("YYYY-MM-DD"),
          cita.servicio.duracion_minutos
        );

        if (disponible) {
          diasDisponibles.push({
            fecha: fecha.format("YYYY-MM-DD"),
            display: fecha.format("dddd D [de] MMMM"),
            diaSemana: diaSemana,
          });
        }
      }

      if (diasDisponibles.length >= 7) break;
    }

    if (diasDisponibles.length === 0) {
      return {
        respuesta:
          "😔 Lo siento, no hay disponibilidad en los próximos días. Por favor, contacta directamente.",
        tipo: "sin_disponibilidad",
      };
    }

    let respuesta = "📅 *DÍAS DISPONIBLES*\n\n";
    diasDisponibles.forEach((dia, index) => {
      respuesta += `${index + 1}. ${dia.display}\n`;
    });
    respuesta += "\nEscribe el *número* del día que prefieres.";

    cita.diasDisponibles = diasDisponibles;
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: respuesta,
      tipo: "seleccion_fecha",
    };
  }

  async procesarSeleccionFecha(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());

    if (isNaN(opcion) || opcion < 1 || opcion > cita.diasDisponibles.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${cita.diasDisponibles.length}`,
        tipo: "fecha_invalida",
      };
    }

    const diaSeleccionado = cita.diasDisponibles[opcion - 1];
    cita.fecha = diaSeleccionado.fecha;
    cita.diaSemana = diaSeleccionado.diaSemana;
    cita.estado = "esperando_hora";
    this.citasEnProceso.set(numero, cita);

    return await this.mostrarHorariosDisponibles(numero, cita);
  }

  async mostrarHorariosDisponibles(numero, cita) {
    const horarioDelDia = this.horarios.find(
      (h) => h.dia_semana === cita.diaSemana
    );

    if (!horarioDelDia) {
      return {
        respuesta: "Error: No hay horario configurado para este día.",
        tipo: "error_horario",
      };
    }

    const slotsDisponibles = await this.generarSlotsDisponibles(
      cita.fecha,
      horarioDelDia,
      cita.servicio.duracion_minutos
    );

    if (slotsDisponibles.length === 0) {
      return {
        respuesta: `😔 No hay horarios disponibles para ${moment(
          cita.fecha
        ).format("dddd D [de] MMMM")}.\nPor favor, contacta directamente.`,
        tipo: "sin_disponibilidad_total",
      };
    }

    let respuesta = `🕐 *HORARIOS DISPONIBLES* - ${moment(cita.fecha).format(
      "dddd D [de] MMMM"
    )}\n\n`;

    slotsDisponibles.forEach((slot, index) => {
      respuesta += `${index + 1}. ${slot}\n`;
    });

    respuesta += "\nEscribe el *número* del horario que prefieres.";

    cita.slotsDisponibles = slotsDisponibles;
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: respuesta,
      tipo: "seleccion_hora",
    };
  }

  async procesarSeleccionHora(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());

    if (isNaN(opcion) || opcion < 1 || opcion > cita.slotsDisponibles.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${cita.slotsDisponibles.length}`,
        tipo: "hora_invalida",
      };
    }

    cita.hora = cita.slotsDisponibles[opcion - 1];
    cita.estado = "esperando_nombre";
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta:
        "👤 Por favor, escribe tu *nombre completo* para registrar la cita:",
      tipo: "solicitar_nombre",
    };
  }

  async procesarNombreCliente(mensaje, numero, cita) {
    const nombre = mensaje.trim();

    if (nombre.length < 3) {
      return {
        respuesta: "Por favor, escribe un nombre válido (mínimo 3 caracteres).",
        tipo: "nombre_invalido",
      };
    }

    cita.nombre = nombre;
    cita.estado = "esperando_confirmacion_final";
    this.citasEnProceso.set(numero, cita);

    let respuesta = "📋 *RESUMEN DE TU CITA*\n\n";
    respuesta += `👤 *Nombre:* ${cita.nombre}\n`;
    respuesta += `🏥 *Servicio:* ${cita.servicio.nombre_servicio}\n`;
    respuesta += `📅 *Fecha:* ${moment(cita.fecha).format(
      "dddd D [de] MMMM [de] YYYY"
    )}\n`;
    respuesta += `🕐 *Hora:* ${cita.hora}\n`;
    respuesta += `⏱️ *Duración:* ${cita.servicio.duracion_minutos} minutos\n`;

    if (cita.servicio.requiere_preparacion) {
      respuesta += `\n⚠️ *Importante:* ${cita.servicio.requiere_preparacion}\n`;
    }

    respuesta +=
      "\n¿Confirmas la cita? Responde *SÍ* para confirmar o *NO* para cancelar.";

    return {
      respuesta: respuesta,
      tipo: "confirmar_cita",
    };
  }

  async manejarConfirmacionFinal(mensaje, numero, cita) {
    const msgLower = mensaje.toLowerCase().trim();

    if (msgLower.match(/^(si|sí|yes|ok|confirmo|dale|listo)$/)) {
      const citaId = await this.guardarCitaBD(numero, cita);

      await this.sincronizarConGoogleCalendar(citaId, cita);

      await this.notificarCita(citaId, cita, numero);

      this.citasCompletadas.set(numero, {
        citaId,
        timestamp: Date.now(),
      });

      this.citasEnProceso.delete(numero);

      let msg = `✅ *CITA AGENDADA EXITOSAMENTE*\n\n`;
      msg += `📋 Número de cita: #${citaId}\n\n`;
      msg += `• Servicio: ${cita.servicio.nombre_servicio}\n`;
      msg += `• Fecha: ${moment(cita.fecha).format("dddd D [de] MMMM")}\n`;
      msg += `• Hora: ${cita.hora}\n`;
      msg += `• Duración: ${cita.servicio.duracion_minutos} min\n\n`;

      if (cita.servicio.requiere_preparacion) {
        msg += `⚠️ Importante: ${cita.servicio.requiere_preparacion}\n\n`;
      }

      msg += `📱 Recibirás recordatorios:\n`;
      msg += `• 24 horas antes\n`;
      msg += `• 2 horas antes\n\n`;
      msg += `¡Te esperamos! 😊`;

      return { respuesta: msg, tipo: "cita_confirmada", citaId };
    }

    if (msgLower.match(/^(no|cancelar)$/)) {
      this.citasEnProceso.delete(numero);
      return {
        respuesta: "Cita no agendada. ¿Te ayudo con algo más?",
        tipo: "cita_no_confirmada",
      };
    }

    return {
      respuesta: "Por favor responde *SÍ* para confirmar o *NO* para cancelar.",
      tipo: "respuesta_invalida",
    };
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

    const horasOcupadas = citasExistentes.map((c) =>
      c.hora_cita.substring(0, 5)
    );

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

  async verificarDisponibilidadDia(fecha, duracionMinutos) {
    try {
      const [rows] = await db.getPool().execute(
        `SELECT COUNT(*) as total FROM citas_bot 
         WHERE empresa_id = ? AND fecha_cita = ? 
         AND estado IN ('agendada', 'confirmada')`,
        [this.empresaId, fecha]
      );

      return rows[0].total < 20;
    } catch (error) {
      console.error("Error verificando disponibilidad:", error);
      return true;
    }
  }

  async guardarCitaBD(numero, cita) {
    const [result] = await db.getPool().execute(
      `INSERT INTO citas_bot 
       (empresa_id, numero_cliente, nombre_cliente, dni_cedula, fecha_cita, hora_cita, 
        tipo_servicio, estado, notas, direccion_completa)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'agendada', ?, ?)`,
      [
        this.empresaId,
        numero,
        cita.nombre || numero.replace("@c.us", ""),
        cita.dni_cedula || null,
        cita.fecha,
        cita.hora + ":00",
        cita.servicio.nombre_servicio,
        cita.servicio.requiere_preparacion || null,
        cita.direccion || null,
      ]
    );

    return result.insertId;
  }

  async sincronizarConGoogleCalendar(citaId, citaData) {
    try {
      if (
        !this.infoBot.google_calendar_activo ||
        !this.infoBot.sincronizar_citas
      ) {
        return;
      }

      const citaCompleta = {
        id: citaId,
        tipo_servicio: citaData.servicio.nombre_servicio,
        nombre_cliente: citaData.nombre || "Cliente",
        numero_cliente: citaData.numero || "Sin número",
        fecha_cita: citaData.fecha,
        hora_cita: citaData.hora + ":00",
        duracion_minutos: citaData.servicio.duracion_minutos,
        empresa_id: this.empresaId,
      };

      const apiUrl =
        process.env.API_BASE_URL || "http://localhost/mensajeroprov2";

      const response = await axios.post(
        `${apiUrl}/sistema/api/v1/bot/crear-evento-google.php`,
        citaCompleta,
        { headers: { "Content-Type": "application/json" } }
      );

      if (response.data.success) {
        await db
          .getPool()
          .execute("UPDATE citas_bot SET google_event_id = ? WHERE id = ?", [
            response.data.event_id,
            citaId,
          ]);

        console.log(`✅ Cita #${citaId} sincronizada con Google Calendar`);
      }
    } catch (error) {
      console.error("Error sincronizando con Google Calendar:", error.message);
    }
  }

  async cancelarEventoGoogle(citaId) {
    try {
      const [rows] = await db
        .getPool()
        .execute(
          "SELECT google_event_id FROM citas_bot WHERE id = ? AND empresa_id = ?",
          [citaId, this.empresaId]
        );

      if (rows[0]?.google_event_id) {
        const apiUrl =
          process.env.API_BASE_URL || "http://localhost/mensajeroprov2";

        await axios.post(
          `${apiUrl}/sistema/api/v1/bot/cancelar-evento-google.php`,
          { cita_id: citaId },
          { headers: { "Content-Type": "application/json" } }
        );

        console.log(
          `✅ Evento de Google Calendar eliminado para cita #${citaId}`
        );
      }
    } catch (error) {
      console.error("Error cancelando evento de Google:", error.message);
    }
  }

  async notificarCita(citaId, cita, numero) {
    try {
      if (!this.notificaciones.notificar_citas) {
        return;
      }

      let numeros;
      try {
        numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
      } catch (e) {
        console.error("Error parseando números de notificación:", e);
        return;
      }

      if (!Array.isArray(numeros) || numeros.length === 0) {
        return;
      }

      let msg =
        this.notificaciones.mensaje_citas ||
        `📅 Nueva cita #${citaId} agendada`;

      msg = msg
        .replace("{nombre_cliente}", cita.nombre || numero.replace("@c.us", ""))
        .replace("{servicio}", cita.servicio.nombre_servicio)
        .replace("{fecha_cita}", moment(cita.fecha).format("DD/MM/YYYY"))
        .replace("{hora_cita}", cita.hora)
        .replace("{telefono}", numero.replace("@c.us", ""))
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

          console.log(`📢 Notificación de cita enviada a ${num}`);
        } catch (error) {
          console.error(`Error enviando notificación a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("Error en notificarCita:", error);
    }
  }

  limpiarCitasInactivas() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000;

    for (const [numero, cita] of this.citasEnProceso.entries()) {
      if (ahora - cita.ultimaActividad > timeout) {
        this.citasEnProceso.delete(numero);
        console.log(`🧹 Proceso de cita limpiado por inactividad: ${numero}`);
      }
    }
  }

  limpiarCitasCompletadas() {
    const ahora = Date.now();
    const timeout = 3 * 60 * 1000;

    for (const [numero, citaInfo] of this.citasCompletadas.entries()) {
      if (ahora - citaInfo.timestamp > timeout) {
        this.citasCompletadas.delete(numero);
        console.log(`🧹 Cita completada limpiada: ${numero}`);
      }
    }
  }

  // Inicia agendamiento con datos prellenados
  async iniciarAgendamientoConDatos(datosPrevios) {
    const { numero, nombre, dni_cedula, direccion, servicio } = datosPrevios;

    // Buscar el servicio por nombre
    const servicioEncontrado = this.servicios.find(
      (s) =>
        s.nombre_servicio.toLowerCase().includes("instalación") ||
        s.nombre_servicio.toLowerCase().includes("visita")
    );

    if (!servicioEncontrado) {
      return {
        respuesta: "Error: No hay servicios de instalación disponibles.",
        tipo: "error_servicio",
      };
    }

    // Crear proceso de cita con datos prellenados
    this.citasEnProceso.set(numero, {
      estado: "esperando_fecha",
      nombre: nombre,
      dni_cedula: dni_cedula,
      direccion: direccion,
      servicio: servicioEncontrado, // Usar el servicio completo de BD
      ultimaActividad: Date.now(),
    });

    // Mostrar días disponibles directamente
    return await this.mostrarDiasDisponibles(
      numero,
      this.citasEnProceso.get(numero)
    );
  }
}

module.exports = AppointmentBot;
