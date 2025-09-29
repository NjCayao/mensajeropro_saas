const db = require("./database");
const moment = require("moment");
moment.locale("es");

// Este archivo debe ejecutarse cada hora con cron

async function enviarRecordatorios() {
  console.log(`⏰ [${moment().format('YYYY-MM-DD HH:mm:ss')}] Ejecutando cron de recordatorios...`);
  
  try {
    // Obtener todas las empresas activas
    const [empresas] = await db.getPool().execute(
      "SELECT DISTINCT e.id FROM empresas e INNER JOIN configuracion_bot c ON e.id = c.empresa_id WHERE c.activo = 1"
    );

    for (const empresa of empresas) {
      await enviarRecordatoriosEmpresa(empresa.id);
    }

    console.log("✅ Cron de recordatorios completado");
  } catch (error) {
    console.error("❌ Error en cron de recordatorios:", error);
  }
}

async function enviarRecordatoriosEmpresa(empresaId) {
  try {
    // Recordatorios de 24 horas
    const manana = moment().add(1, 'day').format('YYYY-MM-DD');
    
    const [citas24h] = await db.getPool().execute(
      `SELECT * FROM citas_bot 
       WHERE empresa_id = ? 
       AND fecha_cita = ?
       AND estado = 'confirmada'
       AND recordatorio_24h = 0`,
      [empresaId, manana]
    );

    for (const cita of citas24h) {
      const mensaje = `⏰ *RECORDATORIO DE CITA*\n\n` +
                     `Hola ${cita.nombre_cliente}, te recordamos tu cita:\n\n` +
                     `📅 Mañana ${moment(cita.fecha_cita).format("dddd D [de] MMMM")}\n` +
                     `🕐 Hora: ${cita.hora_cita.substring(0, 5)}\n` +
                     `🏥 Servicio: ${cita.tipo_servicio}\n`;

      // Aquí deberías conectar con el servicio de WhatsApp para enviar
      console.log(`📱 Enviando recordatorio 24h a ${cita.numero_cliente}`);
      
      // Marcar como enviado
      await db.getPool().execute(
        "UPDATE citas_bot SET recordatorio_24h = 1 WHERE id = ?",
        [cita.id]
      );
    }

    // Recordatorios de 2 horas
    const ahora = moment();
    const en2Horas = ahora.clone().add(2, 'hours');
    const hoy = ahora.format('YYYY-MM-DD');

    const [citas2h] = await db.getPool().execute(
      `SELECT * FROM citas_bot 
       WHERE empresa_id = ? 
       AND fecha_cita = ?
       AND TIME(hora_cita) BETWEEN ? AND ?
       AND estado = 'confirmada'
       AND recordatorio_2h = 0`,
      [
        empresaId,
        hoy,
        ahora.format('HH:mm:ss'),
        en2Horas.format('HH:mm:ss')
      ]
    );

    for (const cita of citas2h) {
      const horaFormateada = moment(cita.hora_cita, 'HH:mm:ss').format('HH:mm');
      
      const mensaje = `⏰ *RECORDATORIO - 2 HORAS*\n\n` +
                     `${cita.nombre_cliente}, tu cita es en 2 horas:\n\n` +
                     `🕐 Hora: ${horaFormateada}\n` +
                     `🏥 ${cita.tipo_servicio}`;

      console.log(`📱 Enviando recordatorio 2h a ${cita.numero_cliente}`);

      await db.getPool().execute(
        "UPDATE citas_bot SET recordatorio_2h = 1 WHERE id = ?",
        [cita.id]
      );
    }

  } catch (error) {
    console.error(`Error enviando recordatorios para empresa ${empresaId}:`, error);
  }
}

// Ejecutar si se llama directamente
if (require.main === module) {
  enviarRecordatorios()
    .then(() => process.exit(0))
    .catch(error => {
      console.error(error);
      process.exit(1);
    });
}

module.exports = { enviarRecordatorios };