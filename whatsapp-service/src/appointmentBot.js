// whatsapp-service/src/appointmentBot.js
const db = require("./database");
const moment = require("moment");
const axios = require("axios");

moment.locale("es");

class AppointmentBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.horarios = [];
    this.servicios = [];
    this.citasEnProceso = new Map();
    this.botHandler = botHandler; // Recibir botHandler como parámetro
    // this.loadConfig();
  }

  async loadConfig() {
    try {
      const [horariosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM horarios_atencion WHERE empresa_id = ? AND activo = 1 ORDER BY dia_semana",
          [this.empresaId]
        );
      this.horarios = horariosRows;

      const [serviciosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM servicios_disponibles WHERE empresa_id = ? AND activo = 1",
          [this.empresaId]
        );
      this.servicios = serviciosRows;

      // console.log(
      //   `✅ Bot de citas configurado: ${this.servicios.length} servicios, ${this.horarios.length} días activos`
      // );
    } catch (error) {
      console.error("Error cargando configuración de citas:", error);
    }
  }

  async procesarMensajeCita(mensaje, numero) {
    let cita = this.citasEnProceso.get(numero);
    const mensajeLower = mensaje.toLowerCase();

    // ============================================
    // HARDCODE: Procesos técnicos de agendamiento
    // ============================================

    // Si ya está en proceso de agendar
    if (cita) {
      switch (cita.estado) {
        case "esperando_servicio":
          return await this.procesarSeleccionServicio(mensaje, numero, cita);
        case "esperando_fecha":
          return await this.procesarSeleccionFecha(mensaje, numero, cita);
        case "esperando_hora":
          return await this.procesarSeleccionHora(mensaje, numero, cita);
        case "esperando_nombre":
          return await this.procesarNombreCliente(mensaje, numero, cita);
        case "esperando_confirmacion":
          return await this.procesarConfirmacion(mensaje, numero, cita);
      }
    }

    // Detectar intención de agendar
    if (
      mensajeLower.includes("cita") ||
      mensajeLower.includes("turno") ||
      mensajeLower.includes("reserva") ||
      mensajeLower.includes("agendar")
    ) {
      return await this.iniciarProcesoCita(numero, cita || {});
    }

    // Manejar cancelaciones
    if (mensajeLower.includes("cancelar")) {
      return await this.procesarCancelacion(mensaje, numero);
    }

    // ============================================
    // IA: TODO LO DEMÁS usa OpenAI
    // ============================================
    return await this.usarOpenAI(mensaje, numero);
  }

  async usarOpenAI(mensaje, numero) {
    if (!this.botHandler) {
      console.error("❌ BotHandler no disponible en AppointmentBot");
      return {
        respuesta: "Lo siento, tuve un problema al procesar tu mensaje.",
        tipo: "error",
      };
    }

    try {
      // Obtener contexto
      const contexto = await this.botHandler.getContexto(numero);

      // Agregar info de servicios al contexto si existe
      let businessInfoOriginal = this.botHandler.config.business_info;
      const infoServicios = this.generarInfoServicios();
      this.botHandler.config.business_info += `\n\n${infoServicios}`;

      // Generar respuesta con IA directamente (sin pasar por processMessage)
      const respuestaIA = await this.botHandler.generateResponse(
        mensaje,
        contexto
      );

      // Restaurar business_info original
      this.botHandler.config.business_info = businessInfoOriginal;

      // Guardar conversación
      await this.botHandler.saveConversation(numero, mensaje, respuestaIA);

      return {
        respuesta: respuestaIA.content,
        tipo: "bot",
        tokens: respuestaIA.tokens,
        tiempo: respuestaIA.tiempo,
      };
    } catch (error) {
      console.error("Error en usarOpenAI:", error);
      return {
        respuesta: "Lo siento, tuve un problema al procesar tu mensaje.",
        tipo: "error",
      };
    }
  }

  generarInfoServicios() {
    let info = "\n📅 SERVICIOS Y HORARIOS:\n\n";

    if (this.servicios.length > 0) {
      info += "🏥 SERVICIOS DISPONIBLES:\n";
      this.servicios.forEach((servicio, index) => {
        info += `${index + 1}. ${servicio.nombre_servicio} (${
          servicio.duracion_minutos
        } min)\n`;
        if (servicio.requiere_preparacion) {
          info += `   ⚠️ ${servicio.requiere_preparacion}\n`;
        }
      });
      info += "\n";
    }

    if (this.horarios.length > 0) {
      info += "🕐 HORARIOS DE ATENCIÓN:\n";
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
        info += `${dias[h.dia_semana]}: ${h.hora_inicio.substring(
          0,
          5
        )} - ${h.hora_fin.substring(0, 5)}\n`;
      });
    }

    return info;
  }

  // ============================================
  // Métodos técnicos de agendamiento (hardcode necesario)
  // ============================================

  async iniciarProcesoCita(numero, cita) {
    if (this.servicios.length === 0) {
      return {
        respuesta:
          "Lo siento, no hay servicios disponibles en este momento. Por favor, contacta directamente con el establecimiento.",
        tipo: "sin_servicios",
      };
    }

    let respuesta = "📋 *SERVICIOS DISPONIBLES*\n\n";

    this.servicios.forEach((servicio, index) => {
      respuesta += `${index + 1}. *${servicio.nombre_servicio}* (${
        servicio.duracion_minutos
      } min)\n`;
      if (servicio.requiere_preparacion) {
        respuesta += `   ⚠️ ${servicio.requiere_preparacion}\n`;
      }
      respuesta += "\n";
    });

    respuesta += "Por favor, escribe el *número* del servicio que deseas.";

    cita.estado = "esperando_servicio";
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: respuesta,
      tipo: "seleccion_servicio",
    };
  }

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
          "😔 Lo siento, no hay disponibilidad en los próximos días. Por favor, contacta directamente con nosotros.",
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
        ).format(
          "dddd D [de] MMMM"
        )}.\nPor favor, contacta directamente al establecimiento.`,
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
    cita.estado = "esperando_confirmacion";
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

  async procesarConfirmacion(mensaje, numero, cita) {
    const respuesta = mensaje.toLowerCase().trim();

    if (respuesta === "si" || respuesta === "sí" || respuesta === "yes") {
      try {
        const [result] = await db.getPool().execute(
          `INSERT INTO citas_bot 
         (empresa_id, numero_cliente, nombre_cliente, fecha_cita, hora_cita, 
          tipo_servicio, estado, notas)
         VALUES (?, ?, ?, ?, ?, ?, 'agendada', ?)`,
          [
            this.empresaId,
            numero,
            cita.nombre,
            cita.fecha,
            cita.hora + ":00",
            cita.servicio.nombre_servicio,
            cita.servicio.requiere_preparacion || null,
          ]
        );

        const citaId = result.insertId;

        // Notificar cita si está configurado
        const config = this.botHandler?.config;
        if (
          config?.notificar_citas &&
          config?.numeros_notificacion &&
          this.botHandler?.whatsappClient
        ) {
          try {
            const numeros = JSON.parse(config.numeros_notificacion);

            let notificacion = `📅 *NUEVA CITA #${citaId}*\n\n`;
            notificacion += `👤 Paciente: ${cita.nombre}\n`;
            notificacion += `📱 Teléfono: ${numero.replace("@c.us", "")}\n`;
            notificacion += `📆 Fecha: ${moment(cita.fecha).format(
              "dddd D [de] MMMM"
            )}\n`;
            notificacion += `🕐 Hora: ${cita.hora}\n`;
            notificacion += `💼 Servicio: ${cita.servicio.nombre_servicio}\n`;
            notificacion += `⏱️ Duración: ${cita.servicio.duracion_minutos} minutos\n`;

            if (cita.servicio.requiere_preparacion) {
              notificacion += `\n⚠️ Preparación: ${cita.servicio.requiere_preparacion}\n`;
            }

            notificacion += `\n💬 Contactar: https://wa.me/${numero.replace(
              "@c.us",
              ""
            )}`;

            for (const numeroNotificar of numeros) {
              await this.botHandler.whatsappClient.client.sendText(
                numeroNotificar.includes("@")
                  ? numeroNotificar
                  : `${numeroNotificar}@c.us`,
                notificacion
              );
              console.log(
                `📢 Notificación de cita enviada a ${numeroNotificar}`
              );
            }
          } catch (error) {
            console.error("Error enviando notificación de cita:", error);
          }
        }

        await this.sincronizarConGoogleCalendar(citaId, cita);

        this.citasEnProceso.delete(numero);

        let respuestaFinal = `✅ *CITA CONFIRMADA*\n\n`;
        respuestaFinal += `Tu cita ha sido agendada exitosamente.\n`;
        respuestaFinal += `📋 Número de cita: #${citaId}\n\n`;
        respuestaFinal += `📱 Recibirás recordatorios:\n`;
        respuestaFinal += `• Un día antes\n`;
        respuestaFinal += `• 2 horas antes\n\n`;
        respuestaFinal += `❌ Para cancelar, escribe "cancelar cita #${citaId}"\n\n`;
        respuestaFinal += `¡Te esperamos! 😊`;

        return {
          respuesta: respuestaFinal,
          tipo: "cita_confirmada",
          citaId: citaId,
        };
      } catch (error) {
        console.error("Error guardando cita:", error);
        return {
          respuesta:
            "❌ Hubo un error al guardar tu cita. Por favor, intenta nuevamente o contacta directamente.",
          tipo: "error_guardado",
        };
      }
    } else if (respuesta === "no") {
      this.citasEnProceso.delete(numero);
      return {
        respuesta:
          "❌ Cita cancelada. Si deseas agendar una cita más adelante, escribe 'agendar cita'.",
        tipo: "cita_cancelada",
      };
    } else {
      return {
        respuesta:
          "Por favor responde *SÍ* para confirmar o *NO* para cancelar.",
        tipo: "respuesta_invalida",
      };
    }
  }

  async sincronizarConGoogleCalendar(citaId, citaData) {
    try {
      const [configRows] = await db.getPool().execute(
        `SELECT google_calendar_activo, sincronizar_citas, empresa_id
       FROM configuracion_bot WHERE empresa_id = ?`,
        [this.empresaId]
      );

      if (
        configRows.length === 0 ||
        !configRows[0].google_calendar_activo ||
        !configRows[0].sincronizar_citas
      ) {
        return;
      }

      const citaCompleta = {
        id: citaId,
        tipo_servicio: citaData.servicio.nombre_servicio,
        nombre_cliente: citaData.nombre,
        numero_cliente: citaData.numero,
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

  async procesarCancelacion(mensaje, numero) {
    const match = mensaje.match(/#(\d+)/);

    if (!match) {
      return {
        respuesta:
          "Para cancelar una cita, escribe: cancelar cita #NUMERO\nEjemplo: cancelar cita #123",
        tipo: "formato_cancelacion",
      };
    }

    const citaId = match[1];

    try {
      const [rows] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
       WHERE id = ? AND numero_cliente = ? AND empresa_id = ? 
       AND estado IN ('agendada', 'confirmada')`,
        [citaId, numero, this.empresaId]
      );

      if (rows.length === 0) {
        return {
          respuesta:
            "No se encontró la cita #" +
            citaId +
            " o ya fue cancelada/completada.",
          tipo: "cita_no_encontrada",
        };
      }

      const cita = rows[0];

      await db
        .getPool()
        .execute("UPDATE citas_bot SET estado = 'cancelada' WHERE id = ?", [
          citaId,
        ]);

      if (cita.google_event_id) {
        try {
          const apiUrl =
            process.env.API_BASE_URL || "http://localhost/mensajeroprov2";
          await axios.post(
            `${apiUrl}/sistema/api/v1/bot/cancelar-evento-google.php`,
            {
              cita_id: citaId,
            }
          );
        } catch (error) {
          console.error("Error eliminando evento de Google:", error.message);
        }
      }

      return {
        respuesta:
          `✅ Cita #${citaId} cancelada exitosamente.\n\n` +
          `Servicio: ${cita.tipo_servicio}\n` +
          `Fecha: ${moment(cita.fecha_cita).format("DD/MM/YYYY")}\n` +
          `Hora: ${cita.hora_cita}\n\n` +
          `Si deseas agendar una nueva cita, escribe 'agendar cita'.`,
        tipo: "cancelacion_exitosa",
      };
    } catch (error) {
      console.error("Error cancelando cita:", error);
      return {
        respuesta:
          "Hubo un error al cancelar la cita. Por favor, contacta directamente.",
        tipo: "error_cancelacion",
      };
    }
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
}

module.exports = AppointmentBot;
