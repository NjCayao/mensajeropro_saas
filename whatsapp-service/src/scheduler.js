const db = require("./database");
const path = require("path");

class Scheduler {
  constructor(messageHandler) {
    this.messageHandler = messageHandler;
    this.isProcessing = false;
    this.checkInterval = 30000; // Verificar cada 30 segundos
  }

  start() {
    console.log("📅 Iniciando scheduler de mensajes programados...");
    console.log("⏱️  Verificando cada", this.checkInterval / 1000, "segundos");

    // Verificar inmediatamente
    this.checkScheduledMessages();

    // Luego verificar periódicamente
    this.intervalId = setInterval(() => {
      this.checkScheduledMessages();
    }, this.checkInterval);
  }

  async checkScheduledMessages() {
    if (this.isProcessing) {
      // console.log('⚠️  Ya hay un proceso en ejecución');
      return;
    }

    try {
      this.isProcessing = true;

      // Solo mostrar log cuando hay mensajes para procesar
      const empresaId = global.EMPRESA_ID || 1;
      const [mensajes] = await db.getPool().execute(
        `
          SELECT * FROM mensajes_programados 
          WHERE estado = 'pendiente' 
          AND fecha_programada <= NOW()
          AND empresa_id = ?
          ORDER BY fecha_programada ASC
          LIMIT 5
      `,
        [empresaId]
      );

      if (mensajes.length === 0) {
        // logs que se repiten cada 30 segundos
        // console.log('🔍 Verificando mensajes a las', new Date().toLocaleString('es-PE', {timeZone: 'America/Lima'}));

        // Solo mostrar el próximo mensaje una vez cada 10 verificaciones
        if (!this.checkCount) this.checkCount = 0;
        this.checkCount++;

        if (this.checkCount % 10 === 0) {
          const empresaId = global.EMPRESA_ID || 1;
          const [proximo] = await db.getPool().execute(
            `
              SELECT id, titulo, fecha_programada 
              FROM mensajes_programados 
              WHERE estado = 'pendiente'
              AND empresa_id = ?
              ORDER BY fecha_programada ASC
              LIMIT 1
          `,
            [empresaId]
          );

          if (proximo.length > 0) {
            const fechaProgramada = new Date(proximo[0].fecha_programada);
            console.log(
              `⏳ Próximo mensaje: "${
                proximo[0].titulo
              }" - ${fechaProgramada.toLocaleString("es-PE")}`
            );
          }
        }
        return;
      }

      // Mostrar log solo cuando hay trabajo que hacer
      console.log(
        `\n📬 Procesando ${mensajes.length} mensaje(s) programado(s)`
      );

      for (const mensaje of mensajes) {
        await this.procesarMensajeProgramado(mensaje);
      }
    } catch (error) {
      console.error("❌ Error en scheduler:", error);
    } finally {
      this.isProcessing = false;
    }
  }

  async procesarMensajeProgramado(mensaje) {
    try {
      console.log(`\n📬 Procesando: ${mensaje.titulo}`);

      // Marcar como procesando
      await db.actualizarEstadoMensajeProgramado(mensaje.id, "procesando");

      // Obtener contactos según el tipo
      let contactos = [];

      // Verificar si es mensaje individual
      const [mensajeIndividual] = await db.getPool().execute(
        `
  SELECT c.id, c.nombre, c.numero 
  FROM mensajes_programados_individuales mpi
  JOIN contactos c ON mpi.contacto_id = c.id
  WHERE mpi.mensaje_programado_id = ? AND c.activo = 1 AND c.empresa_id = ?
  `,
        [mensaje.id, mensaje.empresa_id] // ← AGREGAR mensaje.empresa_id
      );

      if (mensajeIndividual.length > 0) {
        // Es mensaje individual
        contactos = mensajeIndividual;
        console.log(`📤 Enviando a: ${contactos[0].nombre}`);
      } else if (mensaje.enviar_a_todos) {
        // Enviar a todos DE LA MISMA EMPRESA
        const [rows] = await db
          .getPool()
          .execute(
            "SELECT id, nombre, numero FROM contactos WHERE activo = 1 AND empresa_id = ?",
            [mensaje.empresa_id]
          ); // ← AGREGAR filtro
        contactos = rows;
        console.log(`📤 Enviando a: TODOS (${contactos.length} contactos)`);
      } else if (mensaje.categoria_id && mensaje.categoria_id > 0) {
        // Enviar por categoría DE LA MISMA EMPRESA
        const [rows] = await db.getPool().execute(
          "SELECT id, nombre, numero FROM contactos WHERE categoria_id = ? AND activo = 1 AND empresa_id = ?",
          [mensaje.categoria_id, mensaje.empresa_id] // ← AGREGAR mensaje.empresa_id
        );
        contactos = rows;
        console.log(
          `📤 Enviando a: Categoría ID ${mensaje.categoria_id} (${contactos.length} contactos)`
        );
      }

      if (contactos.length === 0) {
        throw new Error("No se encontraron contactos activos");
      }

      console.log(`📝 Mensaje: ${mensaje.mensaje.substring(0, 50)}...`);
      if (mensaje.imagen_path) {
        console.log(`📎 Con imagen: ${mensaje.imagen_path}`);
      }

      let enviados = 0;
      let errores = 0;

      // Enviar a cada contacto
      for (let i = 0; i < contactos.length; i++) {
        const contacto = contactos[i];

        try {
          console.log(`📱 Intentando enviar a: ${contacto.numero}`);

          // DEBUG: Ver qué tiene el contacto
          console.log("DEBUG - Contacto completo:", contacto);

          // Personalizar mensaje con nombre de WhatsApp si es posible
          let mensajePersonalizado = mensaje.mensaje;

          // Intentar obtener nombre de WhatsApp
          let nombreWhatsApp = contacto.nombre; // Por defecto usar nombre de BD

          console.log(
            "DEBUG - Verificando messageHandler:",
            !!this.messageHandler
          );
          console.log(
            "DEBUG - Verificando whatsappClient:",
            !!this.messageHandler?.whatsappClient
          );
          console.log(
            "DEBUG - Verificando getContactInfo:",
            !!this.messageHandler?.whatsappClient?.getContactInfo
          );

          if (
            this.messageHandler &&
            this.messageHandler.whatsappClient &&
            this.messageHandler.whatsappClient.getContactInfo
          ) {
            try {
              console.log("DEBUG - Llamando a getContactInfo...");
              const waInfo =
                await this.messageHandler.whatsappClient.getContactInfo(
                  contacto.numero
                );
              console.log("DEBUG - Resultado de getContactInfo:", waInfo);

              if (waInfo && waInfo.pushname) {
                nombreWhatsApp = waInfo.pushname;
                console.log(
                  `   ✅ Nombre WhatsApp obtenido: ${nombreWhatsApp}`
                );
              } else {
                console.log("   ⚠️ No se encontró pushname en la respuesta");
              }
            } catch (e) {
              console.log(
                `   ❌ Error obteniendo nombre de WhatsApp:`,
                e.message
              );
            }
          } else {
            console.log("   ❌ No se puede acceder a getContactInfo");
          }

          console.log(
            "DEBUG - Mensaje antes de reemplazar:",
            mensajePersonalizado
          );
          console.log("DEBUG - Variables a reemplazar:", {
            nombre: contacto.nombre,
            nombreWhatsApp: nombreWhatsApp,
            categoria: contacto.categoria_nombre || "N/A",
            precio: contacto.precio || "N/A",
          });

          // Reemplazar todas las variables
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{nombre}}/g,
            contacto.nombre
          );
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{nombreWhatsApp}}/g,
            nombreWhatsApp
          );
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{whatsapp}}/g,
            nombreWhatsApp
          );
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{fecha}}/g,
            new Date().toLocaleDateString("es-PE")
          );
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{hora}}/g,
            new Date().toLocaleTimeString("es-PE", {
              hour: "2-digit",
              minute: "2-digit",
            })
          );

          // Si hay categoría, agregar esas variables también
          if (contacto.categoria_nombre) {
            mensajePersonalizado = mensajePersonalizado.replace(
              /{{categoria}}/g,
              contacto.categoria_nombre
            );
          }
          if (contacto.precio) {
            mensajePersonalizado = mensajePersonalizado.replace(
              /{{precio}}/g,
              contacto.precio
            );
          }

          console.log(
            "DEBUG - Mensaje después de reemplazar:",
            mensajePersonalizado
          );

          // Verificar que el cliente esté disponible
          if (!this.messageHandler) {
            console.log("❌ MessageHandler no disponible");
            throw new Error("MessageHandler no está inicializado");
          }

          console.log(
            "   Cliente WhatsApp:",
            this.messageHandler.whatsappClient ? "OK" : "NO"
          );
          console.log(
            "   Cliente conectado:",
            this.messageHandler.whatsappClient?.isReady ? "OK" : "NO"
          );

          let result;

          if (mensaje.imagen_path) {
            // Construir path completo de la imagen
            const imagePath = path.join(
              __dirname,
              "..",
              "..",
              "uploads",
              "mensajes",
              mensaje.imagen_path
            );
            console.log("   Ruta imagen:", imagePath);

            // Verificar que el archivo existe
            const fs = require("fs");
            if (!fs.existsSync(imagePath)) {
              throw new Error(`Archivo no encontrado: ${imagePath}`);
            }

            result = await this.messageHandler.sendImageMessage(
              contacto.numero,
              imagePath,
              mensajePersonalizado
            );
          } else {
            result = await this.messageHandler.sendTextMessage(
              contacto.numero,
              mensajePersonalizado
            );
          }

          if (result && result.success) {
            enviados++;
            console.log(`   ✅ Enviado exitosamente`);

            // Registrar en historial
            await db.registrarMensaje(
              contacto.id,
              mensajePersonalizado,
              "saliente",
              "enviado",
              mensaje.id,
              mensaje.empresa_id
            );
          } else {
            throw new Error("Envío falló sin error específico");
          }

          // Actualizar progreso cada 5 mensajes
          if ((i + 1) % 5 === 0 || i === contactos.length - 1) {
            await db.actualizarEstadoMensajeProgramado(
              mensaje.id,
              "procesando",
              enviados
            );
            console.log(`Progreso: ${enviados}/${contactos.length}`);
          }

          // Delay anti-spam
          if (i < contactos.length - 1) {
            const delay = Math.random() * 5000 + 3000; // 3-8 segundos
            console.log(
              `   Esperando ${Math.round(delay / 1000)}s antes del siguiente...`
            );
            await new Promise((resolve) => setTimeout(resolve, delay));
          }
        } catch (error) {
          console.error(`   Error detallado:`, error.message);
          console.error(`   Stack:`, error.stack);
          errores++;

          // Registrar error en historial
          await db.registrarMensaje(
            contacto.id,
            mensaje.mensaje,
            "saliente",
            "error",
            mensaje.id,
            mensaje.empresa_id
          );
        }
      }

      // Actualizar estado final
      const estadoFinal = enviados === 0 ? "error" : "completado";
      await db.actualizarEstadoMensajeProgramado(
        mensaje.id,
        estadoFinal,
        enviados
      );

      console.log(`\n✅ Mensaje programado completado:`);
      console.log(`   - Enviados: ${enviados}`);
      console.log(`   - Errores: ${errores}`);
      console.log(`   - Estado: ${estadoFinal}`);
    } catch (error) {
      console.error(
        `❌ Error procesando mensaje ${mensaje.id}:`,
        error.message
      );
      await db.actualizarEstadoMensajeProgramado(mensaje.id, "error");
    }
  }

  stop() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      console.log("📅 Scheduler detenido");
    }
  }
}

module.exports = Scheduler;
