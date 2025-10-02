// whatsapp-service/src/reminderService.js
const db = require("./database");
const moment = require("moment");

moment.locale("es");

class ReminderService {
  constructor(whatsappClient) {
    this.whatsappClient = whatsappClient;
  }

  async verificarRecordatorios() {
    console.log("🔔 Verificando recordatorios de citas...");

    try {
      // 1. Recordatorios de 24 horas
      await this.enviarRecordatorios24h();

      // 2. Recordatorios de 2 horas
      await this.enviarRecordatorios2h();
    } catch (error) {
      console.error("Error verificando recordatorios:", error);
    }
  }

  async enviarRecordatorios24h() {
    const manana = moment().add(1, "day").format("YYYY-MM-DD");

    const [citas] = await db.getPool().execute(
      `SELECT * FROM citas_bot 
       WHERE fecha_cita = ? 
       AND estado IN ('agendada', 'confirmada')
       AND recordatorio_24h = 0`,
      [manana]
    );

    for (const cita of citas) {
      try {
        const mensaje = `🔔 *RECORDATORIO DE CITA*\n\n` +
          `Hola ${cita.nombre_cliente},\n\n` +
          `Te recordamos tu cita de mañana:\n\n` +
          `• Servicio: ${cita.tipo_servicio}\n` +
          `• Hora: ${cita.hora_cita.substring(0, 5)}\n` +
          `• Lugar: ${await this.obtenerDireccionEmpresa(cita.empresa_id)}\n\n` +
          `¡Te esperamos! 😊`;

        await this.whatsappClient.client.sendText(cita.numero_cliente, mensaje);

        await db.getPool().execute(
          "UPDATE citas_bot SET recordatorio_24h = 1 WHERE id = ?",
          [cita.id]
        );

        console.log(`✅ Recordatorio 24h enviado para cita #${cita.id}`);
      } catch (error) {
        console.error(`Error enviando recordatorio 24h cita #${cita.id}:`, error);
      }
    }
  }

  async enviarRecordatorios2h() {
    const ahora = moment();
    const en2horas = moment().add(2, "hours");

    const [citas] = await db.getPool().execute(
      `SELECT * FROM citas_bot 
       WHERE fecha_cita = CURDATE() 
       AND estado IN ('agendada', 'confirmada')
       AND recordatorio_2h = 0`
    );

    for (const cita of citas) {
      const horaCita = moment(cita.fecha_cita + " " + cita.hora_cita);

      if (horaCita.isAfter(ahora) && horaCita.isBefore(en2horas)) {
        try {
          const mensaje = `⏰ *RECORDATORIO - Tu cita es en 2 horas*\n\n` +
            `• Servicio: ${cita.tipo_servicio}\n` +
            `• Hora: ${cita.hora_cita.substring(0, 5)}\n\n`;

          if (cita.notas) {
            mensaje += `⚠️ Recuerda: ${cita.notas}\n\n`;
          }

          mensaje += `¡Te esperamos! 😊`;

          await this.whatsappClient.client.sendText(cita.numero_cliente, mensaje);

          await db.getPool().execute(
            "UPDATE citas_bot SET recordatorio_2h = 1 WHERE id = ?",
            [cita.id]
          );

          console.log(`✅ Recordatorio 2h enviado para cita #${cita.id}`);
        } catch (error) {
          console.error(`Error enviando recordatorio 2h cita #${cita.id}:`, error);
        }
      }
    }
  }

  async obtenerDireccionEmpresa(empresaId) {
    const [rows] = await db.getPool().execute(
      "SELECT direccion FROM configuracion_negocio WHERE empresa_id = ?",
      [empresaId]
    );

    return rows[0]?.direccion || "Nuestro local";
  }
}

module.exports = ReminderService;