const wppconnect = require("@wppconnect-team/wppconnect");
const db = require("./database");
const MessageHandler = require("./messageHandler");

class WhatsAppClient {
  constructor(botHandler = null) {
    this.client = null;
    this.isReady = false;
    this.messageHandler = null;
    this.sessionInfo = null;
    this.botHandler = botHandler;
    this.qrGenerationCount = 0; // Contador de QR generados
    this.maxQrAttempts = 1; // Solo 1 intento de QR
    this.qrTimeout = null; // Timer para timeout
    this.qrExpirationTime = 60000; // 60 segundos para escanear
    this.firstQrGenerated = false; // Flag para controlar el primer QR
  }

  async initialize() {
    console.log("🔄 Inicializando WhatsApp con WPPConnect...");
    const empresaId = global.EMPRESA_ID || 1;

    try {
      await db.updateWhatsAppStatus("iniciando");

      this.client = await wppconnect.create({
        session: `empresa-${empresaId}`,
        catchQR: async (base64Qr, asciiQr) => {
          // Solo contar el primer QR, ignorar regeneraciones automáticas
          if (!this.firstQrGenerated) {
            this.qrGenerationCount++;
            this.firstQrGenerated = true;

            console.log(`📱 QR Code generado`);
            console.log(asciiQr);

            // Guardar QR en BD
            await db.updateWhatsAppStatus("qr_pendiente", base64Qr);

            // Iniciar timeout de 60 segundos
            this.startQrTimeout();
          } else {
            // Actualizar QR en BD sin incrementar contador
            console.log(`🔄 QR Code actualizado`);
            await db.updateWhatsAppStatus("qr_pendiente", base64Qr);
          }
        },
        statusFind: (statusSession, session) => {
          console.log("🔄 Estado:", statusSession);

          // Si se conectó, cancelar el timeout
          if (statusSession === "inChat" || statusSession === "successChat") {
            this.cancelQrTimeout();
          }
        },
        headless: true,
        devtools: false,
        useChrome: true,
        debug: false,
        logQR: false,
        browserArgs: [
          "--no-sandbox",
          "--disable-setuid-sandbox",
          "--disable-dev-shm-usage",
          "--disable-accelerated-2d-canvas",
          "--no-first-run",
          "--disable-gpu",

          "--aggressive-cache-discard",
          "--disable-cache",
          "--disable-application-cache",
          "--disable-offline-load-stale-cache",
          "--disk-cache-size=0",
          "--disable-background-networking",
          "--disable-default-apps",
          "--disable-extensions",
          "--disable-sync",
          "--disable-translate",
          "--hide-scrollbars",
          "--metrics-recording-only",
          "--mute-audio",
          "--safebrowsing-disable-auto-update",
          "--disable-web-security",
          "--no-zygote",
          "--single-process",
        ],

        refreshQR: 15000, // Regenerar QR cada 15 segundos
        autoClose: 60000, // Cerrar después de 60 segundos sin escanear
        disableSpins: true,
        puppeteerOptions: {
          args: [
            "--disable-logging",
            "--log-level=3",
            "--max-old-space-size=350",
            "--js-flags=--max-old-space-size=350",
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-accelerated-2d-canvas",
            "--no-first-run",
            "--no-zygote",
            "--disable-gpu",
          ],
        },
      });

      // El cliente ya está listo aquí
      console.log("✅ Cliente WPPConnect creado y conectado");
      this.isReady = true;

      // Cancelar timeout si llegamos aquí
      this.cancelQrTimeout();

      // Esperar un poco más para que cargue completamente
      await new Promise((resolve) => setTimeout(resolve, 5000));

      try {
        const contacts = await this.client.getAllContacts();
        console.log("Total contactos:", contacts.length);
      } catch (e) {
        console.log("No se pudieron obtener contactos");
      }

      // Obtener información del usuario
      try {
        // Esperar un poco más para asegurar que todo esté cargado
        await new Promise((resolve) => setTimeout(resolve, 3000));

        let numeroConectado = "No identificado";
        let nombreConectado = "Sin nombre";

        // Método 1: getHostDevice (más confiable en wppconnect)
        try {
          const hostDevice = await this.client.getHostDevice();
          console.log("Host device info:", hostDevice);

          if (hostDevice) {
            numeroConectado =
              hostDevice.id?.user ||
              hostDevice.wid?.user ||
              hostDevice.me?.user ||
              numeroConectado;
            nombreConectado =
              hostDevice.pushname || hostDevice.name || nombreConectado;
          }
        } catch (e) {
          console.log("Error con getHostDevice:", e.message);
        }

        // Método 2: getAllChats y buscar el propio
        if (numeroConectado === "No identificado") {
          try {
            const chats = await this.client.getAllChats();
            const myChat = chats.find((chat) => chat.isMe);

            if (myChat) {
              numeroConectado = myChat.id?.user || numeroConectado;
              nombreConectado = myChat.name || nombreConectado;
            }
          } catch (e) {
            console.log("Error con getAllChats:", e.message);
          }
        }

        // Método 3: getMe (si existe)
        if (numeroConectado === "No identificado" && this.client.getMe) {
          try {
            const me = await this.client.getMe();
            numeroConectado =
              me?.id?._serialized || me?.id?.user || numeroConectado;
            nombreConectado = me?.pushname || me?.name || nombreConectado;
          } catch (e) {
            console.log("Error con getMe:", e.message);
          }
        }

        this.sessionInfo = {
          number: numeroConectado,
          pushname: nombreConectado,
          platform: "WPPConnect",
        };

        await db.updateWhatsAppStatus("conectado", null, numeroConectado);

        console.log("✅ WhatsApp conectado exitosamente!");
        console.log(`📱 Número: ${numeroConectado}`);
        console.log(`👤 Nombre: ${nombreConectado}`);
      } catch (error) {
        console.log("⚠️ No se pudo obtener información completa del usuario");
        console.error("Error:", error);

        // Aún así marcar como conectado
        this.sessionInfo = {
          number: "No identificado",
          pushname: "Usuario",
          platform: "WPPConnect",
        };

        await db.updateWhatsAppStatus("conectado", null, null);
      }

      // Inicializar MessageHandler
      const adapter = new MessageHandlerAdapter(this);
      this.messageHandler = adapter;
      // Hacer que el whatsappClient sea accesible desde el messageHandler
      this.messageHandler.whatsappClient = this;

      // Configurar event handlers
      this.setupEventHandlers();

      // Iniciar procesamiento de cola
      this.startQueueProcessor();
    } catch (error) {
      console.error("❌ Error inicializando:", error);

      // Si el error es por timeout o usuario no escaneó
      if (
        error.message &&
        (error.message.includes("QR Code not scanned") ||
          error.message.includes("Failed to authenticate"))
      ) {
        console.log("⏱️ Timeout: QR no fue escaneado");
        await db.updateWhatsAppStatus("desconectado");
      } else {
        await db.updateWhatsAppStatus("desconectado");
      }

      // Limpiar recursos
      this.cleanup();

      setTimeout(() => {
        console.log("👋 Cerrando proceso por error");
        process.exit(1);
      }, 2000);
    }
  }

  startQrTimeout() {
    // Timeout de 60 segundos
    this.qrTimeout = setTimeout(async () => {
      console.log("⏱️ Timeout de QR alcanzado (60 segundos)");
      await this.stopQrGeneration();
    }, this.qrExpirationTime); // 60 segundos
  }

  cancelQrTimeout() {
    if (this.qrTimeout) {
      clearTimeout(this.qrTimeout);
      this.qrTimeout = null;
    }
  }

  async stopQrGeneration() {
    try {
      console.log("🛑 Deteniendo generación de QR...");

      // Cancelar timeout
      this.cancelQrTimeout();

      // Actualizar estado en BD
      await db.updateWhatsAppStatus("desconectado", null, null);

      // Intentar cerrar el cliente si existe
      if (this.client) {
        try {
          await this.client.close();
        } catch (e) {
          console.log("Error cerrando cliente:", e.message);
        }
      }

      // Terminar el proceso después de un pequeño delay
      setTimeout(() => {
        console.log("👋 Cerrando proceso por timeout de QR");
        process.exit(0);
      }, 2000);
    } catch (error) {
      console.error("Error en stopQrGeneration:", error);
      process.exit(1);
    }
  }

  cleanup() {
    this.cancelQrTimeout();
    this.qrGenerationCount = 0;
    this.isReady = false;
  }

  async detectarIntervencionOperador(message) {
    try {
      const empresaId = global.EMPRESA_ID || 1;

      // Obtener config de escalamiento
      const [configRows] = await db
        .getPool()
        .execute(
          "SELECT escalamiento_config FROM configuracion_bot WHERE empresa_id = ?",
          [empresaId]
        );

      if (!configRows[0] || !configRows[0].escalamiento_config) {
        return; // No hay config
      }

      const config = JSON.parse(configRows[0].escalamiento_config);

      if (!config.detectar_intervencion_humana) {
        return; // Función desactivada
      }

      const numerosOperadores = config.numeros_operadores || [];
      if (numerosOperadores.length === 0) {
        return; // No hay operadores configurados
      }

      // Obtener el autor real del mensaje (quién lo envió)
      const author = message.author || message.from;
      const numeroCliente = message.from;

      // Si el autor ES el cliente, no es intervención
      if (author === numeroCliente) {
        return; // Es el cliente escribiendo
      }

      // Verificar si el autor es un operador
      const esOperador = numerosOperadores.some((op) => {
        const opLimpio = op.replace(/\D/g, "");
        const authorLimpio = author.replace(/\D/g, "");
        return (
          authorLimpio.includes(opLimpio) || opLimpio.includes(authorLimpio)
        );
      });

      if (!esOperador) {
        return; // No es un operador conocido
      }

      console.log(
        `👤 [INTERVENCIÓN] Operador ${author} intervino con ${numeroCliente}`
      );

      // Calcular timestamp de timeout
      const timeoutSegundos = config.timeout_intervencion_humana || 120;
      const timestampTimeout = new Date(Date.now() + timeoutSegundos * 1000);

      // Registrar o actualizar intervención
      await db.getPool().execute(
        `INSERT INTO intervencion_humana 
      (empresa_id, numero_cliente, numero_operador, estado, timestamp_ultima_intervencion, timestamp_timeout)
      VALUES (?, ?, ?, 'humano_interviniendo', NOW(), ?)
      ON DUPLICATE KEY UPDATE
        numero_operador = VALUES(numero_operador),
        estado = 'humano_interviniendo',
        timestamp_ultima_intervencion = NOW(),
        timestamp_timeout = VALUES(timestamp_timeout),
        contador_timeout = 0`,
        [empresaId, numeroCliente, author, timestampTimeout]
      );

      console.log(
        `✅ Bot pausado para ${numeroCliente}. Reactivación en ${timeoutSegundos}s`
      );
    } catch (error) {
      console.error("❌ Error detectando intervención:", error);
    }
  }

  setupEventHandlers() {
    // Mensajes entrantes
    this.client.onMessage(async (message) => {
      // Ignorar estados de WhatsApp
      if (message.from === "status@broadcast" || message.isStatusV3) {
        return;
      }

      // Ignorar mensajes de grupos
      if (message.isGroupMsg || message.from.includes("@g.us")) {
        return;
      }

      console.log(
        "📩 Mensaje recibido:",
        message.from,
        message.type,
        message.body?.substring(0, 50) || "[MULTIMEDIA]"
      );

      try {
        // ✅ NUEVO: Detectar intervención humana PRIMERO
        await this.detectarIntervencionOperador(message);

        // Crear BotHandler si no existe
        if (this.messageHandler && this.messageHandler.botHandler) {
          // MANEJO DE IMÁGENES
          if (message.type === "image") {
            console.log("📸 Imagen recibida");

            const botResponse =
              await this.messageHandler.botHandler.handleIncomingMessage(
                message.from,
                message.body || "",
                false,
                "image",
                message
              );

            if (botResponse) {
              await this.client.sendText(message.from, botResponse.respuesta);
              console.log("🤖 Bot respondió a imagen");
            }
            return;
          }

          // IGNORAR OTROS TIPOS DE MULTIMEDIA
          if (
            message.type === "document" ||
            message.type === "video" ||
            message.type === "audio" ||
            message.type === "ptt" ||
            message.type === "sticker"
          ) {
            console.log(`📷 Multimedia tipo ${message.type} ignorado`);
            return;
          }

          // MENSAJES DE TEXTO
          if (!message.body || message.body.trim() === "") {
            console.log("📵 Mensaje vacío, ignorando");
            return;
          }

          const botResponse =
            await this.messageHandler.botHandler.handleIncomingMessage(
              message.from,
              message.body,
              false
            );

          if (botResponse) {
            // Enviar respuesta de texto
            await this.client.sendText(message.from, botResponse.respuesta);
            console.log("🤖 Bot respondió automáticamente");

            // Si hay archivo PDF, enviarlo
            if (botResponse.tipo === "catalogo_pdf" && botResponse.archivo) {
              try {
                console.log(`📎 Enviando PDF: ${botResponse.archivo}`);
                await this.client.sendFile(
                  message.from,
                  botResponse.archivo,
                  "catalogo.pdf",
                  "Aquí está nuestro catálogo completo"
                );
                console.log("✅ PDF enviado exitosamente");
              } catch (error) {
                console.error("❌ Error enviando PDF:", error);
                await this.client.sendText(
                  message.from,
                  "Hubo un problema enviando el PDF. Por favor solicítalo nuevamente más tarde."
                );
              }
            }
          }
        }

        // Guardar el mensaje en el historial
        const numero = message.from.replace("@c.us", "");
        const [rows] = await db
          .getPool()
          .execute("SELECT id FROM contactos WHERE numero LIKE ?", [
            `%${numero}%`,
          ]);

        if (rows.length > 0) {
          await db.registrarMensaje(
            rows[0].id,
            message.body || "[MULTIMEDIA]",
            "entrante",
            "recibido"
          );
        }
      } catch (error) {
        console.error("Error procesando mensaje entrante:", error);
      }
    });

    // Cambio de estado (mantener como estaba)
    this.client.onStateChange(async (state) => {
      // ← IMPORTANTE: async
      console.log("🔄 Estado cambió a:", state);

      if (state === "CONFLICT" || state === "UNLAUNCHED") {
        console.log("⚠️ Sesión cerrada en otro dispositivo, reconectando...");
        try {
          await this.client.useHere();
        } catch (e) {
          console.error("Error en useHere():", e.message);
        }
        return;
      }

      // DESCONEXIÓN TEMPORAL (NO CERRAR EL SERVICIO)
      if (state === "DISCONNECTED") {
        console.log(
          "⚠️ WhatsApp desconectado temporalmente (pérdida de señal)"
        );
        this.isReady = false;

        // Actualizar BD CON await para garantizar escritura
        try {
          await db.updateWhatsAppStatus("desconectado");
          console.log("✅ BD actualizada a 'desconectado'");
        } catch (e) {
          console.error("❌ Error actualizando BD:", e.message);
        }

        console.log("🔄 Esperando reconexión automática...");
        console.log(
          "ℹ️ El servicio seguirá corriendo y reconectará cuando haya señal"
        );

        // NO ejecutar process.exit() aquí
        // WPPConnect intentará reconectar automáticamente
        return;
      }

      // SESIÓN ELIMINADA PERMANENTEMENTE (SÍ CERRAR)
      if (state === "UNPAIRED") {
        console.log("❌ Sesión eliminada del celular (usuario desvinculó)");
        this.isReady = false;

        // Actualizar BD CON await
        try {
          await db.updateWhatsAppStatus("desconectado");
          console.log("✅ BD actualizada a 'desconectado'");
        } catch (e) {
          console.error("❌ Error actualizando BD:", e.message);
        }

        // Verificar si suscripción está vencida
        const empresaId = global.EMPRESA_ID || 1;
        try {
          const [suscripcion] = await db
            .getPool()
            .execute(
              "SELECT estado, fecha_fin FROM suscripciones WHERE empresa_id = ? AND estado = 'activa' LIMIT 1",
              [empresaId]
            );

          if (suscripcion.length > 0) {
            const fechaFin = new Date(suscripcion[0].fecha_fin);
            const ahora = new Date();

            if (fechaFin < ahora) {
              console.log("⏰ Suscripción vencida detectada");
            } else {
              console.log(
                "✅ Suscripción activa - Usuario eliminó sesión manualmente"
              );
            }
          } else {
            console.log("⚠️ No hay suscripción activa");
          }
        } catch (e) {
          console.error("Error verificando suscripción:", e.message);
        }

        // Cerrar servicio después de 5 segundos
        setTimeout(() => {
          console.log("👋 Cerrando proceso por sesión eliminada (UNPAIRED)");
          process.exit(0);
        }, 5000);

        return;
      }

      // ERRORES DE QR/AUTENTICACIÓN
      if (
        state === "qrReadError" ||
        state === "autocloseCalled" ||
        state === "browserClose"
      ) {
        console.log("❌ Error de autenticación/timeout, cerrando servicio...");
        this.cleanup();

        setTimeout(() => {
          console.log("🛑 Cerrando proceso por error de QR");
          process.exit(1);
        }, 2000);
      }
    });
  }

  // Métodos de envío
  async sendMessage(numero, mensaje) {
    if (!this.isReady || !this.client) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      // Limpiar el número de caracteres no deseados
      let formattedNumber = numero.replace(/\D/g, ""); // Quitar todo lo que no sea dígito

      // Si el número no empieza con código de país, agregar 51 (Perú)
      if (formattedNumber.length === 9 && formattedNumber.startsWith("9")) {
        formattedNumber = "51" + formattedNumber;
      }

      // Agregar @c.us al final
      formattedNumber = formattedNumber + "@c.us";

      console.log(
        `📤 Enviando mensaje a: ${formattedNumber} (original: ${numero})`
      );

      const result = await this.client.sendText(formattedNumber, mensaje);
      console.log(`✅ Mensaje enviado exitosamente`);

      return { success: true, messageId: result.id };
    } catch (error) {
      console.error(`❌ Error enviando mensaje:`, error);
      throw error;
    }
  }

  async sendImage(numero, imagePath, caption = "") {
    if (!this.isReady || !this.client) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      // Limpiar el número de caracteres no deseados
      let formattedNumber = numero.replace(/\D/g, ""); // Quitar todo lo que no sea dígito

      // Si el número no empieza con código de país, agregar 51 (Perú)
      if (formattedNumber.length === 9 && formattedNumber.startsWith("9")) {
        formattedNumber = "51" + formattedNumber;
      }

      // Agregar @c.us al final
      formattedNumber = formattedNumber + "@c.us";

      console.log(
        `📤 Enviando imagen a: ${formattedNumber} (original: ${numero})`
      );

      const result = await this.client.sendImage(
        formattedNumber,
        imagePath,
        "image",
        caption
      );

      console.log(`✅ Imagen enviada exitosamente`);
      return { success: true, messageId: result.id };
    } catch (error) {
      console.error(`❌ Error enviando imagen:`, error);
      throw error;
    }
  }

  async sendDocument(numero, docPath, caption = "") {
    if (!this.isReady || !this.client) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      // Limpiar el número de caracteres no deseados
      let formattedNumber = numero.replace(/\D/g, ""); // Quitar todo lo que no sea dígito

      // Si el número no empieza con código de país, agregar 51 (Perú)
      if (formattedNumber.length === 9 && formattedNumber.startsWith("9")) {
        formattedNumber = "51" + formattedNumber;
      }

      // Agregar @c.us al final
      formattedNumber = formattedNumber + "@c.us";

      const filename = require("path").basename(docPath);
      console.log(
        `📤 Enviando documento a: ${formattedNumber} (original: ${numero})`
      );

      const result = await this.client.sendFile(
        formattedNumber,
        docPath,
        filename,
        caption
      );

      console.log(`✅ Documento enviado exitosamente`);
      return { success: true, messageId: result.id };
    } catch (error) {
      console.error(`❌ Error enviando documento:`, error);
      throw error;
    }
  }

  // MÉTODO PARA OBTENER INFO DEL CONTACTO
  async getContactInfo(numero) {
    if (!this.isReady || !this.client) {
      console.log("WhatsApp no está listo para obtener info de contacto");
      return null;
    }

    try {
      // Formatear número
      let formattedNumber = numero.replace(/\D/g, "");
      if (formattedNumber.length === 9 && formattedNumber.startsWith("9")) {
        formattedNumber = "51" + formattedNumber;
      }
      formattedNumber = formattedNumber + "@c.us";

      console.log(`🔍 Obteniendo info de contacto: ${formattedNumber}`);

      // Para wppconnect, intentar varios métodos
      try {
        // Método 1: getContact
        const contact = await this.client.getContact(formattedNumber);
        console.log(
          "Contact desde getContact:",
          JSON.stringify(contact, null, 2)
        );

        if (contact) {
          // En wppconnect, el pushname puede estar en diferentes lugares
          const pushname =
            contact.pushname ||
            contact.name ||
            contact.verifiedName ||
            contact.formattedName ||
            contact.displayName ||
            contact.notifyName ||
            null;

          return {
            pushname: pushname,
            isMyContact: contact.isMyContact || false,
            isBusiness: contact.isBusiness || false,
          };
        }
      } catch (e) {
        console.log("getContact falló, intentando alternativas...");
      }

      // Método 2: getAllContacts y buscar
      try {
        const allContacts = await this.client.getAllContacts();
        const foundContact = allContacts.find((c) => {
          const contactId = c.id?._serialized || c.id?.user || c.id;
          return (
            contactId === formattedNumber ||
            contactId === formattedNumber.replace("@c.us", "")
          );
        });

        if (foundContact) {
          console.log(
            "Contact desde getAllContacts:",
            JSON.stringify(foundContact, null, 2)
          );
          return {
            pushname:
              foundContact.pushname ||
              foundContact.name ||
              foundContact.verifiedName ||
              null,
            isMyContact: foundContact.isMyContact || false,
            isBusiness: foundContact.isBusiness || false,
          };
        }
      } catch (e) {
        console.log("getAllContacts falló:", e.message);
      }

      // Método 3: getNumberProfile
      try {
        const profile = await this.client.getNumberProfile(formattedNumber);
        console.log("Profile desde getNumberProfile:", profile);

        if (profile) {
          return {
            pushname: profile.pushname || profile.name || null,
            isMyContact: false,
            isBusiness: profile.isBusiness || false,
          };
        }
      } catch (e) {
        console.log("getNumberProfile falló:", e.message);
      }

      // Si no encontramos nada
      console.log("No se pudo obtener información del contacto");
      return null;
    } catch (error) {
      console.error(`❌ Error obteniendo info del contacto:`, error);
      return null;
    }
  }

  // Procesador de cola
  async startQueueProcessor() {
    console.log("🔄 Iniciando procesador de cola de mensajes");

    while (true) {
      try {
        if (this.isReady && this.messageHandler) {
          const mensajes = await db.obtenerMensajesPendientes();

          if (mensajes.length > 0) {
            console.log(`📦 Procesando ${mensajes.length} mensajes de la cola`);
          }

          for (const mensaje of mensajes) {
            try {
              await db.actualizarEstadoMensaje(mensaje.id, "enviando");

              const result = await this.messageHandler.processQueueMessage(
                mensaje
              );

              if (result.success) {
                await db.actualizarEstadoMensaje(mensaje.id, "enviado");
                await db.registrarMensaje(
                  mensaje.contacto_id,
                  mensaje.mensaje,
                  "saliente",
                  "enviado"
                );
              }
            } catch (error) {
              console.error(`Error enviando mensaje ${mensaje.id}:`, error);
              await db.actualizarEstadoMensaje(
                mensaje.id,
                "error",
                error.message
              );
            }

            // Delay anti-spam
            const delay = this.messageHandler.calculateDelay();
            await new Promise((resolve) => setTimeout(resolve, delay));
          }
        }

        // Esperar 10 segundos antes de verificar nuevamente
        await new Promise((resolve) => setTimeout(resolve, 10000));
      } catch (error) {
        console.error("Error en procesador de cola:", error);
        await new Promise((resolve) => setTimeout(resolve, 30000));
      }
    }
  }

  async disconnect() {
    console.log("🔄 Desconectando WhatsApp...");

    this.cleanup();

    if (this.client) {
      await this.client.close();
      this.isReady = false;
      this.messageHandler = null;
      await db.updateWhatsAppStatus("desconectado");
    }
  }

  getStatus() {
    return {
      connected: this.isReady,
      info: this.sessionInfo,
    };
  }

  async getQR() {
    const [rows] = await db
      .getPool()
      .execute("SELECT qr_code FROM whatsapp_sesion WHERE id = 1");
    return rows[0]?.qr_code || null;
  }

  async isRegisteredUser(numero) {
    if (!this.isReady) {
      throw new Error("WhatsApp no está conectado");
    }

    try {
      const formattedNumber = numero.includes("@") ? numero : `${numero}@c.us`;
      const result = await this.client.checkNumberStatus(formattedNumber);
      return result?.canReceiveMessage || true;
    } catch {
      return true;
    }
  }
}

// Adaptador para MessageHandler
class MessageHandlerAdapter {
  constructor(whatsappClient) {
    this.client = whatsappClient;
    this.whatsappClient = whatsappClient; // IMPORTANTE: Agregar esta línea

    // Inicializar BotHandler aquí, una sola vez
    const BotHandler = require("./botHandler");
    this.botHandler = new BotHandler(this);
  }

  formatNumber(numero) {
    let cleaned = numero.replace(/\D/g, "");
    if (cleaned.length === 9 && cleaned.startsWith("9")) {
      cleaned = "51" + cleaned;
    }
    return cleaned;
  }

  async sendTextMessage(numero, mensaje) {
    return await this.client.sendMessage(numero, mensaje);
  }

  async sendImageMessage(numero, imagePath, caption) {
    return await this.client.sendImage(numero, imagePath, caption);
  }

  async sendDocumentMessage(numero, docPath, caption) {
    return await this.client.sendDocument(numero, docPath, caption);
  }

  async processQueueMessage(mensajeCola) {
    const numero = this.formatNumber(mensajeCola.numero);

    console.log(
      `[QUEUE] Procesando mensaje tipo ${mensajeCola.tipo} para ${numero}`
    );

    switch (mensajeCola.tipo) {
      case "texto":
        return await this.sendTextMessage(numero, mensajeCola.mensaje);

      case "imagen":
        if (!mensajeCola.imagen_path) {
          throw new Error("No se especificó imagen");
        }
        const projectRootImg = require("path").resolve(__dirname, "../..");
        const imagePath = require("path").join(
          projectRootImg,
          "uploads/mensajes/",
          mensajeCola.imagen_path
        );
        return await this.sendImageMessage(
          numero,
          imagePath,
          mensajeCola.mensaje || ""
        );

      case "documento":
        if (!mensajeCola.imagen_path) {
          throw new Error("No se especificó documento");
        }
        const projectRootDoc = require("path").resolve(__dirname, "../..");
        const docPath = require("path").join(
          projectRootDoc,
          "uploads/mensajes/",
          mensajeCola.imagen_path
        );
        return await this.sendDocumentMessage(
          numero,
          docPath,
          mensajeCola.mensaje || ""
        );

      default:
        throw new Error(`Tipo de mensaje no soportado: ${mensajeCola.tipo}`);
    }
  }

  calculateDelay() {
    const min = parseInt(process.env.DELAY_MIN_MS) || 3000;
    const max = parseInt(process.env.DELAY_MAX_MS) || 8000;
    return Math.random() * (max - min) + min;
  }
}

module.exports = WhatsAppClient;
