const { Client, LocalAuth } = require("whatsapp-web.js");
const qrcode = require("qrcode-terminal");
const db = require("./database");
const MessageHandler = require("./messageHandler");

class WhatsAppClient {
  constructor() {
    this.client = null;
    this.isReady = false;
    this.messageHandler = null;
    this.messageQueue = [];
    this.isProcessingQueue = false;
  }

  async initialize() {
    console.log("🔄 Inicializando WhatsApp...");
    console.log("📍 Directorio actual:", process.cwd());
    console.log("📍 Session path:", process.env.SESSION_PATH || ".wwebjs_auth");

    try {
      console.log("📦 Creando cliente...");

      this.client = new Client({
        authStrategy: new LocalAuth({
          dataPath: process.env.SESSION_PATH || ".wwebjs_auth",
          clientId: "client-one",
        }),
        puppeteer: {
          headless: true,
          args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-accelerated-2d-canvas",
            "--no-first-run",
            "--no-zygote",
            "--disable-gpu",
            "--disable-web-security",
            "--disable-features=IsolateOrigins,site-per-process",
          ],
          timeout: 0, // Sin timeout
        },
        webVersionCache: {
          type: "none",
        },
      });

      console.log("✅ Cliente creado");
      console.log("🔧 Configurando event handlers...");

      this.setupEventHandlers();

      console.log("🚀 Llamando a client.initialize()...");
      await this.client.initialize();
      console.log("✅ client.initialize() completado");
    } catch (error) {
      console.error("❌ Error en initialize:", error);
      console.error("Stack completo:", error.stack);
    }
  }

  setupEventHandlers() {
    // QR Code
    this.client.on("qr", async (qr) => {
      console.log("📱 QR Code generado");
      console.log("QR String:", qr); // Agregar este log

      // Mostrar en consola
      qrcode.generate(qr, { small: true });

      // IMPORTANTE: Guardar QR en base de datos
      try {
        await db.updateWhatsAppStatus("qr_pendiente", qr);
        console.log("✅ QR guardado en base de datos");
      } catch (error) {
        console.error("❌ Error guardando QR:", error);
      }
    });

    // Cliente listo
    this.client.on("ready", async () => {
      console.log("✅ WhatsApp conectado!");
      this.isReady = true;

      // Inicializar MessageHandler
      this.messageHandler = new MessageHandler(this);

      const info = this.client.info;
      await db.updateWhatsAppStatus("conectado", null, info.wid.user);

      console.log(`📱 Conectado como: ${info.pushname} (${info.wid.user})`);

      // Iniciar procesamiento de cola
      this.startQueueProcessor();
    });

    // Mensajes entrantes
    this.client.on("message", async (msg) => {
      console.log("📩 Mensaje recibido:", msg.from, msg.body);

      // Guardar mensaje entrante en BD
      try {
        // Buscar contacto por número
        const numero = msg.from.replace("@c.us", "");
        const [rows] = await db
          .getPool()
          .execute("SELECT id FROM contactos WHERE numero LIKE ?", [
            `%${numero}%`,
          ]);

        if (rows.length > 0) {
          await db.registrarMensaje(
            rows[0].id,
            msg.body,
            "entrante",
            "recibido"
          );
        }
      } catch (error) {
        console.error("Error guardando mensaje entrante:", error);
      }

      // Aquí se procesará el bot IA en Fase 3
    });

    // Desconexión
    this.client.on("disconnected", async (reason) => {
      console.log("❌ WhatsApp desconectado:", reason);
      this.isReady = false;
      this.messageHandler = null;
      await db.updateWhatsAppStatus("desconectado");
    });

    // Error de autenticación
    this.client.on("auth_failure", async (msg) => {
      console.error("❌ Error de autenticación:", msg);
      await db.updateWhatsAppStatus("error_auth");
    });

    // Cambio de estado
    this.client.on("change_state", (state) => {
      console.log("🔄 Estado WhatsApp:", state);
    });
  }

  // Métodos de envío usando MessageHandler
  async sendMessage(numero, mensaje, opciones = {}) {
    if (!this.isReady || !this.messageHandler) {
      throw new Error("WhatsApp no está conectado");
    }

    return await this.messageHandler.sendTextMessage(numero, mensaje);
  }

  async sendImage(numero, imagePath, caption = "") {
    if (!this.isReady || !this.messageHandler) {
      throw new Error("WhatsApp no está conectado");
    }

    return await this.messageHandler.sendImageMessage(
      numero,
      imagePath,
      caption
    );
  }

  async sendDocument(numero, docPath, caption = "") {
    if (!this.isReady || !this.messageHandler) {
      throw new Error("WhatsApp no está conectado");
    }

    return await this.messageHandler.sendDocumentMessage(
      numero,
      docPath,
      caption
    );
  }

  // Envío masivo
  async sendBulk(contactos, mensaje, opciones = {}) {
    if (!this.isReady || !this.messageHandler) {
      throw new Error("WhatsApp no está conectado");
    }

    return await this.messageHandler.sendBulkMessages(
      contactos,
      mensaje,
      opciones
    );
  }

  // Procesador de cola de mensajes
  async startQueueProcessor() {
    if (this.isProcessingQueue) return;

    this.isProcessingQueue = true;
    console.log("🔄 Iniciando procesador de cola de mensajes");

    while (this.isReady) {
      try {
        // Obtener mensajes pendientes de la BD
        const mensajes = await db.obtenerMensajesPendientes();

        if (mensajes.length > 0) {
          console.log(
            `📦 Procesando ${mensajes.length} mensajes de la cola...`
          );

          for (const mensaje of mensajes) {
            try {
              // Marcar como enviando
              await db.actualizarEstadoMensaje(mensaje.id, "enviando");

              // Enviar usando MessageHandler
              const result = await this.messageHandler.processQueueMessage(
                mensaje
              );

              if (result.success) {
                // Marcar como enviado
                await db.actualizarEstadoMensaje(mensaje.id, "enviado");

                // Registrar en historial
                await db.registrarMensaje(
                  mensaje.contacto_id,
                  mensaje.mensaje,
                  "saliente",
                  "enviado"
                );
              }
            } catch (error) {
              console.error(`Error enviando mensaje ${mensaje.id}:`, error);

              // Incrementar intentos
              const intentos = mensaje.intentos + 1;

              if (intentos >= 3) {
                // Marcar como error después de 3 intentos
                await db.actualizarEstadoMensaje(
                  mensaje.id,
                  "error",
                  error.message
                );
              } else {
                // Volver a pendiente para reintentar
                await db
                  .getPool()
                  .execute(
                    'UPDATE cola_mensajes SET estado = "pendiente", intentos = ? WHERE id = ?',
                    [intentos, mensaje.id]
                  );
              }
            }

            // Delay entre mensajes (anti-spam)
            const delay = this.messageHandler.calculateDelay(
              mensajes.indexOf(mensaje),
              mensajes.length
            );
            await new Promise((resolve) => setTimeout(resolve, delay));
          }
        }

        // Esperar antes de verificar nuevamente la cola
        await new Promise((resolve) => setTimeout(resolve, 10000)); // 10 segundos
      } catch (error) {
        console.error("Error en procesador de cola:", error);
        await new Promise((resolve) => setTimeout(resolve, 30000)); // 30 segundos en caso de error
      }
    }

    this.isProcessingQueue = false;
    console.log("❌ Procesador de cola detenido");
  }

  // Obtener estado
  getStatus() {
    return {
      connected: this.isReady,
      info:
        this.isReady && this.client.info
          ? {
              pushname: this.client.info.pushname,
              number: this.client.info.wid.user,
              platform: this.client.info.platform,
            }
          : null,
    };
  }

  // Obtener QR actual
  async getQR() {
    try {
      const [rows] = await db
        .getPool()
        .execute("SELECT qr_code FROM whatsapp_sesion WHERE id = 1");
      return rows[0]?.qr_code || null;
    } catch (error) {
      console.error("Error obteniendo QR:", error);
      return null;
    }
  }

  // Desconectar
  async disconnect() {
    console.log("🔄 Desconectando WhatsApp...");

    if (this.client) {
      await this.client.destroy();
      this.isReady = false;
      this.messageHandler = null;
      await db.updateWhatsAppStatus("desconectado");
    }
  }

  // Obtener contactos de WhatsApp
  async getWhatsAppContacts() {
    if (!this.isReady) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      const contacts = await this.client.getContacts();
      return contacts
        .filter((contact) => contact.isMyContact && !contact.isGroup)
        .map((contact) => ({
          id: contact.id._serialized,
          numero: contact.number,
          nombre: contact.pushname || contact.name || "Sin nombre",
          isBusinessContact: contact.isBusiness,
        }));
    } catch (error) {
      console.error("Error obteniendo contactos:", error);
      return [];
    }
  }

  // Verificar si un número está registrado en WhatsApp
  async isRegisteredUser(numero) {
    if (!this.isReady) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      const formattedNumber = this.messageHandler.formatNumber(numero);
      const isRegistered = await this.client.isRegisteredUser(formattedNumber);
      return isRegistered;
    } catch (error) {
      console.error("Error verificando número:", error);
      return false;
    }
  }

  // Obtener info de perfil
  async getProfilePicUrl(numero) {
    if (!this.isReady) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      const formattedNumber = this.messageHandler.formatNumber(numero);
      const url = await this.client.getProfilePicUrl(formattedNumber);
      return url;
    } catch (error) {
      console.error("Error obteniendo foto de perfil:", error);
      return null;
    }
  }
}

module.exports = WhatsAppClient;
