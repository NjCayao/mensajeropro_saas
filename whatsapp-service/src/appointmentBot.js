// whatsapp-service/src/appointmentBot.js
const db = require("./database");
const moment = require("moment");
moment.locale("es");

class AppointmentBot {
  constructor(empresaId) {
    this.empresaId = empresaId;
    this.horarios = [];
    this.servicios = [];
    this.citasEnProceso = new Map();
    this.loadConfig();
  }

  async loadConfig() {
    try {
      // Cargar horarios de atención
      const [horariosRows] = await db.getPool().execute(
        "SELECT * FROM horarios_atencion WHERE empresa_id = ? AND activo = 1 ORDER BY dia_semana",
        [this.empresaId]
      );
      this.horarios = horariosRows;

      // Cargar servicios disponibles
      const [serviciosRows] = await db.getPool().execute(
        "SELECT * FROM servicios_disponibles WHERE empresa_id = ? AND activo = 1",
        [this.empresaId]
      );
      this.servicios = serviciosRows;

      console.log(`✅ Bot de citas configurado: ${this.servicios.length} servicios, ${this.horarios.length} días activos`);
    } catch (error) {
      console.error("Error cargando configuración de citas:", error);
    }
  }

  async procesarMensajeCita(mensaje, numero) {
    // Obtener o crear sesión de cita
    let cita = this.citasEnProceso.get(numero) || {
      estado: "inicial",
      servicio: null,
      fecha: null,
      hora: null,
      nombre: null
    };

    const mensajeLower = mensaje.toLowerCase();

    // Detectar intención inicial
    if (cita.estado === "inicial") {
      if (mensajeLower.includes("cita") || mensajeLower.includes("turno") || 
          mensajeLower.includes("reserva") || mensajeLower.includes("agendar")) {
        return await this.iniciarProcesoCita(numero, cita);
      }

      // Si no menciona cita, dar opciones generales
      return {
        respuesta: `🤖 Hola! Soy el asistente de citas. Puedo ayudarte con:\n\n` +
                   `📅 Agendar una nueva cita\n` +
                   `❓ Consultar disponibilidad\n` +
                   `❌ Cancelar una cita existente\n\n` +
                   `¿Qué deseas hacer?`,
        tipo: "menu_inicial"
      };
    }

    // Procesar según estado de la cita
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

    // Manejar cancelaciones
    if (mensajeLower.includes("cancelar")) {
      return await this.procesarCancelacion(mensaje, numero);
    }

    return {
      respuesta: "No entendí tu solicitud. ¿Deseas agendar una cita? Por favor, escribe 'agendar cita'.",
      tipo: "no_entendido"
    };
  }

  async iniciarProcesoCita(numero, cita) {
    if (this.servicios.length === 0) {
      return {
        respuesta: "Lo siento, no hay servicios disponibles en este momento. Por favor, contacta directamente con el establecimiento.",
        tipo: "sin_servicios"
      };
    }

    // Mostrar servicios disponibles
    let respuesta = "📋 *SERVICIOS DISPONIBLES*\n\n";
    
    this.servicios.forEach((servicio, index) => {
      respuesta += `${index + 1}. *${servicio.nombre_servicio}* (${servicio.duracion_minutos} min)\n`;
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
      tipo: "seleccion_servicio"
    };
  }

  async procesarSeleccionServicio(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());
    
    if (isNaN(opcion) || opcion < 1 || opcion > this.servicios.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${this.servicios.length}`,
        tipo: "servicio_invalido"
      };
    }

    cita.servicio = this.servicios[opcion - 1];
    cita.estado = "esperando_fecha";
    this.citasEnProceso.set(numero, cita);

    // Mostrar días disponibles
    return await this.mostrarDiasDisponibles(numero, cita);
  }

  async mostrarDiasDisponibles(numero, cita) {
    // Obtener próximos 30 días con disponibilidad
    const diasDisponibles = [];
    const hoy = moment();
    
    for (let i = 1; i <= 30; i++) {
      const fecha = moment().add(i, "days");
      const diaSemana = fecha.isoWeekday(); // 1=Lunes, 7=Domingo
      
      // Verificar si hay horario para este día
      const horarioDelDia = this.horarios.find(h => h.dia_semana === diaSemana);
      
      if (horarioDelDia) {
        // Verificar que no esté completamente ocupado
        const disponible = await this.verificarDisponibilidadDia(fecha.format("YYYY-MM-DD"), cita.servicio.duracion_minutos);
        
        if (disponible) {
          diasDisponibles.push({
            fecha: fecha.format("YYYY-MM-DD"),
            display: fecha.format("dddd D [de] MMMM"),
            diaSemana: diaSemana
          });
        }
      }
      
      // Limitar a 7 días disponibles mostrados
      if (diasDisponibles.length >= 7) break;
    }

    if (diasDisponibles.length === 0) {
      return {
        respuesta: "😔 Lo siento, no hay disponibilidad en los próximos días. Por favor, contacta directamente con nosotros.",
        tipo: "sin_disponibilidad"
      };
    }

    let respuesta = "📅 *DÍAS DISPONIBLES*\n\n";
    diasDisponibles.forEach((dia, index) => {
      respuesta += `${index + 1}. ${dia.display}\n`;
    });
    respuesta += "\nEscribe el *número* del día que prefieres.";

    // Guardar días disponibles en la cita
    cita.diasDisponibles = diasDisponibles;
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: respuesta,
      tipo: "seleccion_fecha"
    };
  }

  async procesarSeleccionFecha(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());
    
    if (isNaN(opcion) || opcion < 1 || opcion > cita.diasDisponibles.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${cita.diasDisponibles.length}`,
        tipo: "fecha_invalida"
      };
    }

    const diaSeleccionado = cita.diasDisponibles[opcion - 1];
    cita.fecha = diaSeleccionado.fecha;
    cita.diaSemana = diaSeleccionado.diaSemana;
    cita.estado = "esperando_hora";
    this.citasEnProceso.set(numero, cita);

    // Mostrar horarios disponibles
    return await this.mostrarHorariosDisponibles(numero, cita);
  }

  async mostrarHorariosDisponibles(numero, cita) {
    const horarioDelDia = this.horarios.find(h => h.dia_semana === cita.diaSemana);
    
    if (!horarioDelDia) {
      return {
        respuesta: "Error: No hay horario configurado para este día.",
        tipo: "error_horario"
      };
    }

    // Generar slots de tiempo disponibles
    const slotsDisponibles = await this.generarSlotsDisponibles(
      cita.fecha,
      horarioDelDia,
      cita.servicio.duracion_minutos
    );

    if (slotsDisponibles.length === 0) {
      return {
        respuesta: "😔 No hay horarios disponibles para este día. Por favor, selecciona otro día.",
        tipo: "dia_completo"
      };
    }

    let respuesta = `🕐 *HORARIOS DISPONIBLES* - ${moment(cita.fecha).format("dddd D [de] MMMM")}\n\n`;
    
    slotsDisponibles.forEach((slot, index) => {
      respuesta += `${index + 1}. ${slot}\n`;
    });
    
    respuesta += "\nEscribe el *número* del horario que prefieres.";

    cita.slotsDisponibles = slotsDisponibles;
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: respuesta,
      tipo: "seleccion_hora"
    };
  }

  async procesarSeleccionHora(mensaje, numero, cita) {
    const opcion = parseInt(mensaje.trim());
    
    if (isNaN(opcion) || opcion < 1 || opcion > cita.slotsDisponibles.length) {
      return {
        respuesta: `Por favor, escribe un número válido del 1 al ${cita.slotsDisponibles.length}`,
        tipo: "hora_invalida"
      };
    }

    cita.hora = cita.slotsDisponibles[opcion - 1];
    cita.estado = "esperando_nombre";
    this.citasEnProceso.set(numero, cita);

    return {
      respuesta: "👤 Por favor, escribe tu *nombre completo* para registrar la cita:",
      tipo: "solicitar_nombre"
    };
  }

  async procesarNombreCliente(mensaje, numero, cita) {
    const nombre = mensaje.trim();
    
    if (nombre.length < 3) {
      return {
        respuesta: "Por favor, escribe un nombre válido (mínimo 3 caracteres).",
        tipo: "nombre_invalido"
      };
    }

    cita.nombre = nombre;
    cita.estado = "esperando_confirmacion";
    this.citasEnProceso.set(numero, cita);

    // Mostrar resumen
    let respuesta = "📋 *RESUMEN DE TU CITA*\n\n";
    respuesta += `👤 *Nombre:* ${cita.nombre}\n`;
    respuesta += `🏥 *Servicio:* ${cita.servicio.nombre_servicio}\n`;
    respuesta += `📅 *Fecha:* ${moment(cita.fecha).format("dddd D [de] MMMM [de] YYYY")}\n`;
    respuesta += `🕐 *Hora:* ${cita.hora}\n`;
    respuesta += `⏱️ *Duración:* ${cita.servicio.duracion_minutos} minutos\n`;
    
    if (cita.servicio.requiere_preparacion) {
      respuesta += `\n⚠️ *Importante:* ${cita.servicio.requiere_preparacion}\n`;
    }
    
    respuesta += "\n¿Confirmas la cita? Responde *SÍ* para confirmar o *NO* para cancelar.";

    return {
      respuesta: respuesta,
      tipo: "confirmar_cita"
    };
  }

  async procesarConfirmacion(mensaje, numero, cita) {
    const respuesta = mensaje.toLowerCase().trim();
    
    if (respuesta === "si" || respuesta === "sí" || respuesta === "yes") {
      // Guardar cita en base de datos
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
            cita.servicio.requiere_preparacion || null
          ]
        );

        const citaId = result.insertId;
        
        // Sincronizar con Google Calendar si está configurado
        await this.sincronizarConGoogleCalendar(citaId, cita);
        
        // Limpiar sesión
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
          citaId: citaId
        };

      } catch (error) {
        console.error("Error guardando cita:", error);
        return {
          respuesta: "❌ Hubo un error al guardar tu cita. Por favor, intenta nuevamente o contacta directamente.",
          tipo: "error_guardado"
        };
      }
      
    } else if (respuesta === "no") {
      // Cancelar proceso
      this.citasEnProceso.delete(numero);
      return {
        respuesta: "❌ Cita cancelada. Si deseas agendar una cita más adelante, escribe 'agendar cita'.",
        tipo: "cita_cancelada"
      };
    } else {
      return {
        respuesta: "Por favor responde *SÍ* para confirmar o *NO* para cancelar.",
        tipo: "respuesta_invalida"
      };
    }
  }

  async sincronizarConGoogleCalendar(citaId, citaData) {
    try {
      // Verificar si Google Calendar está activo
      const [configRows] = await db.getPool().execute(
        `SELECT google_calendar_activo, google_refresh_token, google_calendar_id, sincronizar_citas 
         FROM configuracion_bot WHERE empresa_id = ?`,
        [this.empresaId]
      );

      if (configRows.length === 0 || !configRows[0].google_calendar_activo || 
          !configRows[0].sincronizar_citas || !configRows[0].google_refresh_token) {
        return; // No sincronizar si no está configurado
      }

      const config = configRows[0];

      // Preparar datos del evento
      const evento = {
        summary: `${citaData.servicio.nombre_servicio} - ${citaData.nombre}`,
        description: `Cliente: ${citaData.nombre}\nTeléfono: ${citaData.numero}\nServicio: ${citaData.servicio.nombre_servicio}\n\nCita #${citaId}`,
        start: {
          dateTime: `${citaData.fecha}T${citaData.hora}:00`,
          timeZone: 'America/Lima'
        },
        end: {
          dateTime: moment(`${citaData.fecha} ${citaData.hora}`)
            .add(citaData.servicio.duracion_minutos, 'minutes')
            .format('YYYY-MM-DDTHH:mm:ss'),
          timeZone: 'America/Lima'
        },
        reminders: {
          useDefault: false,
          overrides: [
            {method: 'popup', minutes: 10},
            {method: 'popup', minutes: 60}
          ]
        }
      };

      // Aquí deberías hacer la llamada a la API de Google Calendar
      // Por simplicidad, solo logueamos
      console.log('Sincronizando con Google Calendar:', evento);
      
      // En producción, aquí iría la llamada real a Google Calendar API
      // usando el refresh_token para obtener un access_token
      // y luego crear el evento

    } catch (error) {
      console.error('Error sincronizando con Google Calendar:', error);
      // No fallar la cita si Google Calendar falla
    }
  }

  async procesarCancelacion(mensaje, numero) {
    // Buscar número de cita en el mensaje
    const match = mensaje.match(/#(\d+)/);
    
    if (!match) {
      return {
        respuesta: "Para cancelar una cita, escribe: cancelar cita #NUMERO\nEjemplo: cancelar cita #123",
        tipo: "formato_cancelacion"
      };
    }

    const citaId = match[1];
    
    try {
      // Verificar que la cita existe y pertenece a este número
      const [rows] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
         WHERE id = ? AND numero_cliente = ? AND empresa_id = ? 
         AND estado IN ('agendada', 'confirmada')`,
        [citaId, numero, this.empresaId]
      );

      if (rows.length === 0) {
        return {
          respuesta: "No se encontró la cita #" + citaId + " o ya fue cancelada/completada.",
          tipo: "cita_no_encontrada"
        };
      }

      const cita = rows[0];
      
      // Actualizar estado
      await db.getPool().execute(
        "UPDATE citas_bot SET estado = 'cancelada' WHERE id = ?",
        [citaId]
      );

      return {
        respuesta: `✅ Cita #${citaId} cancelada exitosamente.\n\n` +
                   `Servicio: ${cita.tipo_servicio}\n` +
                   `Fecha: ${moment(cita.fecha_cita).format("DD/MM/YYYY")}\n` +
                   `Hora: ${cita.hora_cita}\n\n` +
                   `Si deseas agendar una nueva cita, escribe 'agendar cita'.`,
        tipo: "cancelacion_exitosa"
      };

    } catch (error) {
      console.error("Error cancelando cita:", error);
      return {
        respuesta: "Hubo un error al cancelar la cita. Por favor, contacta directamente.",
        tipo: "error_cancelacion"
      };
    }
  }

  async verificarDisponibilidadDia(fecha, duracionMinutos) {
    try {
      // Contar citas del día en la BD
      const [rows] = await db.getPool().execute(
        `SELECT COUNT(*) as total FROM citas_bot 
         WHERE empresa_id = ? AND fecha_cita = ? 
         AND estado IN ('agendada', 'confirmada')`,
        [this.empresaId, fecha]
      );

      // Verificar también en Google Calendar si está configurado
      const disponibleGoogle = await this.verificarDisponibilidadGoogle(fecha);
      
      // Por ahora, asumimos que si hay menos de 20 citas el día está disponible
      // Y que Google Calendar no lo bloquea
      return rows[0].total < 20 && disponibleGoogle;

    } catch (error) {
      console.error("Error verificando disponibilidad:", error);
      return true; // En caso de error, mostrar como disponible
    }
  }

  async verificarDisponibilidadGoogle(fecha) {
    try {
      // Verificar si Google Calendar está activo
      const [configRows] = await db.getPool().execute(
        `SELECT google_calendar_activo, google_refresh_token, google_calendar_id 
         FROM configuracion_bot WHERE empresa_id = ?`,
        [this.empresaId]
      );

      if (configRows.length === 0 || !configRows[0].google_calendar_activo || 
          !configRows[0].google_refresh_token) {
        return true; // Si no está configurado, asumir disponible
      }

      // En una implementación real, aquí consultarías la API de Google Calendar
      // para verificar si hay eventos que bloqueen ese día
      
      return true; // Por simplicidad, retornamos true
      
    } catch (error) {
      console.error('Error verificando Google Calendar:', error);
      return true;
    }
  }

  async generarSlotsDisponibles(fecha, horario, duracionServicio) {
    const slots = [];
    const horaInicio = moment(fecha + " " + horario.hora_inicio);
    const horaFin = moment(fecha + " " + horario.hora_fin);
    const duracionSlot = horario.duracion_cita;

    // Obtener citas existentes del día
    const [citasExistentes] = await db.getPool().execute(
      `SELECT hora_cita FROM citas_bot 
       WHERE empresa_id = ? AND fecha_cita = ? 
       AND estado IN ('agendada', 'confirmada')
       ORDER BY hora_cita`,
      [this.empresaId, fecha]
    );

    const horasOcupadas = citasExistentes.map(c => c.hora_cita.substring(0, 5));

    // Generar slots cada X minutos según duración configurada
    let horaActual = horaInicio.clone();
    
    while (horaActual.isBefore(horaFin)) {
      const horaFormato = horaActual.format("HH:mm");
      
      // Verificar si el slot está disponible
      if (!horasOcupadas.includes(horaFormato)) {
        // Verificar que haya tiempo suficiente antes del cierre
        const tiempoRestante = horaFin.diff(horaActual, "minutes");
        if (tiempoRestante >= duracionServicio) {
          slots.push(horaFormato);
        }
      }
      
      // Avanzar al siguiente slot
      horaActual.add(duracionSlot, "minutes");
    }

    return slots;
  }

  // Método para enviar recordatorios (llamado por un cron job)
  async enviarRecordatorios() {
    try {
      // Recordatorios de 24 horas
      const [citas24h] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
         WHERE empresa_id = ? 
         AND fecha_cita = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
         AND estado = 'confirmada'
         AND recordatorio_24h = 0`,
        [this.empresaId]
      );

      for (const cita of citas24h) {
        const mensaje = `⏰ *RECORDATORIO DE CITA*\n\n` +
                       `Hola ${cita.nombre_cliente}, te recordamos tu cita:\n\n` +
                       `📅 Mañana ${moment(cita.fecha_cita).format("dddd D [de] MMMM")}\n` +
                       `🕐 Hora: ${cita.hora_cita.substring(0, 5)}\n` +
                       `🏥 Servicio: ${cita.tipo_servicio}\n\n` +
                       `Para cancelar responde: cancelar cita #${cita.id}`;

        // Aquí deberías enviar el mensaje por WhatsApp
        console.log(`Enviando recordatorio 24h a ${cita.numero_cliente}`);

        // Marcar como enviado
        await db.getPool().execute(
          "UPDATE citas_bot SET recordatorio_24h = 1 WHERE id = ?",
          [cita.id]
        );
      }

      // Recordatorios de 2 horas
      const ahora = moment();
      const en2Horas = ahora.clone().add(2, "hours");

      const [citas2h] = await db.getPool().execute(
        `SELECT * FROM citas_bot 
         WHERE empresa_id = ? 
         AND fecha_cita = CURDATE()
         AND TIME(CONCAT(hora_cita)) BETWEEN ? AND ?
         AND estado = 'confirmada'
         AND recordatorio_2h = 0`,
        [
          this.empresaId,
          ahora.format("HH:mm:ss"),
          en2Horas.format("HH:mm:ss")
        ]
      );

      for (const cita of citas2h) {
        const mensaje = `⏰ *RECORDATORIO - 2 HORAS*\n\n` +
                       `${cita.nombre_cliente}, tu cita es en 2 horas:\n\n` +
                       `🕐 Hora: ${cita.hora_cita.substring(0, 5)}\n` +
                       `🏥 ${cita.tipo_servicio}\n`;

        if (cita.notas) {
          mensaje += `\n⚠️ Recuerda: ${cita.notas}`;
        }

        console.log(`Enviando recordatorio 2h a ${cita.numero_cliente}`);

        await db.getPool().execute(
          "UPDATE citas_bot SET recordatorio_2h = 1 WHERE id = ?",
          [cita.id]
        );
      }

    } catch (error) {
      console.error("Error enviando recordatorios:", error);
    }
  }
}

module.exports = AppointmentBot;