const db = require("./database");
const fs = require("fs").promises;
const path = require("path");

class MessageHandler {
  constructor(whatsappClient) {
    this.whatsappClient = whatsappClient; // Guardar referencia al WhatsAppClient
    this.retryAttempts = 3;
    this.supportedImageTypes = [".jpg", ".jpeg", ".png", ".gif"];
    this.supportedDocTypes = [".pdf", ".doc", ".docx", ".xls", ".xlsx"];
    this.maxFileSize = 16 * 1024 * 1024; // 16MB límite WhatsApp
  }

  // Formatear número a formato WhatsApp
  formatNumber(numero) {
    let cleaned = numero.replace(/\D/g, "");

    if (cleaned.length === 9 && cleaned.startsWith("9")) {
      cleaned = "51" + cleaned;
    }

    return cleaned + "@c.us";
  }

  // Validar mensaje de texto
  validateMessage(mensaje) {
    if (!mensaje || mensaje.trim().length === 0) {
      throw new Error("Mensaje vacío");
    }

    if (mensaje.length > 5000) {
      throw new Error("Mensaje demasiado largo (máximo 5000 caracteres)");
    }

    return true;
  }

  // Validar archivo
  async validateFile(filePath) {
    try {
      const stats = await fs.stat(filePath);

      if (!stats.isFile()) {
        throw new Error("No es un archivo válido");
      }

      if (stats.size > this.maxFileSize) {
        throw new Error("Archivo demasiado grande (máximo 16MB)");
      }

      const ext = path.extname(filePath).toLowerCase();
      const isImage = this.supportedImageTypes.includes(ext);
      const isDocument = this.supportedDocTypes.includes(ext);

      if (!isImage && !isDocument) {
        throw new Error("Tipo de archivo no soportado");
      }

      return { isImage, isDocument, size: stats.size };
    } catch (error) {
      throw new Error(`Error validando archivo: ${error.message}`);
    }
  }

  // Aplicar variables a plantilla
  applyTemplate(template, variables = {}) {
    let mensaje = template;

    // Reemplazar variables {{nombre}}, {{precio}}, etc
    Object.keys(variables).forEach((key) => {
      const regex = new RegExp(`{{${key}}}`, "g");
      mensaje = mensaje.replace(regex, variables[key] || "");
    });

    // Agregar fecha/hora actuales si se usan
    const ahora = new Date();
    mensaje = mensaje.replace(/{{fecha}}/g, ahora.toLocaleDateString("es-PE"));
    mensaje = mensaje.replace(
      /{{hora}}/g,
      ahora.toLocaleTimeString("es-PE", { hour: "2-digit", minute: "2-digit" })
    );

    return mensaje;
  }

  async applyTemplateWithWhatsApp(template, contacto) {
    let mensaje = template;

    try {
      // Obtener información de WhatsApp si es posible
      let nombreWhatsApp = contacto.nombre; // Por defecto usar el nombre de la BD

      if (this.whatsappClient && this.whatsappClient.getContactInfo) {
        const waInfo = await this.whatsappClient.getContactInfo(
          contacto.numero
        );
        if (waInfo && waInfo.pushname) {
          nombreWhatsApp = waInfo.pushname;
          console.log(`📱 Nombre WhatsApp obtenido: ${nombreWhatsApp}`);
        }
      }

      // Variables disponibles
      const variables = {
        nombre: contacto.nombre, // Nombre de la BD
        nombreWhatsApp: nombreWhatsApp, // Nombre de WhatsApp
        whatsapp: nombreWhatsApp, // Alias corto
        categoria: contacto.categoria_nombre || "",
        precio: contacto.precio || "",
        telefono: contacto.numero,
      };

      // Reemplazar variables
      Object.keys(variables).forEach((key) => {
        const regex = new RegExp(`{{${key}}}`, "g");
        mensaje = mensaje.replace(regex, variables[key] || "");
      });

      // Agregar fecha/hora actuales
      const ahora = new Date();
      mensaje = mensaje.replace(
        /{{fecha}}/g,
        ahora.toLocaleDateString("es-PE")
      );
      mensaje = mensaje.replace(
        /{{hora}}/g,
        ahora.toLocaleTimeString("es-PE", {
          hour: "2-digit",
          minute: "2-digit",
        })
      );

      return mensaje;
    } catch (error) {
      console.error("Error aplicando plantilla:", error);
      // Si hay error, usar el método original sin WhatsApp
      return this.applyTemplate(template, {
        nombre: contacto.nombre,
        categoria: contacto.categoria_nombre || "",
        precio: contacto.precio || "",
        telefono: contacto.numero,
      });
    }
  }

  // Enviar mensaje de texto con reintentos
  async sendTextMessage(numero, mensaje, intentos = 0) {
    try {
      this.validateMessage(mensaje);

      // Intentar personalizar el mensaje con el nombre de WhatsApp si contiene variables
      if (
        mensaje.includes("{{nombreWhatsApp}}") ||
        mensaje.includes("{{whatsapp}}")
      ) {
        console.log("Detectadas variables de WhatsApp, obteniendo nombre...");

        let nombreWhatsApp = null;

        // Intentar obtener el nombre de WhatsApp
        if (this.whatsappClient && this.whatsappClient.getContactInfo) {
          try {
            const waInfo = await this.whatsappClient.getContactInfo(numero);
            if (waInfo && waInfo.pushname) {
              nombreWhatsApp = waInfo.pushname;
              console.log(`Nombre WhatsApp obtenido: ${nombreWhatsApp}`);
            }
          } catch (e) {
            console.log("No se pudo obtener nombre de WhatsApp");
          }
        }

        // Si encontramos el nombre, reemplazar las variables
        if (nombreWhatsApp) {
          mensaje = mensaje.replace(/{{nombreWhatsApp}}/g, nombreWhatsApp);
          mensaje = mensaje.replace(/{{whatsapp}}/g, nombreWhatsApp);
        } else {
          // Si no encontramos nombre, quitar las variables o poner un valor por defecto
          mensaje = mensaje.replace(/{{nombreWhatsApp}}/g, "Estimado/a");
          mensaje = mensaje.replace(/{{whatsapp}}/g, "Estimado/a");
        }
      }

      // Usar el método sendMessage del WhatsAppClient
      const result = await this.whatsappClient.sendMessage(numero, mensaje);
      console.log(`✅ Mensaje enviado a ${numero}`);
      return result;
    } catch (error) {
      if (intentos < this.retryAttempts) {
        console.log(
          `⚠️ Reintentando envío a ${numero} (${intentos + 1}/${
            this.retryAttempts
          })...`
        );
        await new Promise((resolve) =>
          setTimeout(resolve, 5000 * (intentos + 1))
        );
        return await this.sendTextMessage(numero, mensaje, intentos + 1);
      }
      throw error;
    }
  }

  // Enviar imagen con caption
  async sendImageMessage(numero, imagePath, caption = "", intentos = 0) {
    try {
      console.log("Intentando enviar imagen:", { numero, imagePath, caption });

      // Verificar que el archivo existe
      const fileExists = await fs
        .access(imagePath)
        .then(() => true)
        .catch(() => false);
      if (!fileExists) {
        throw new Error(`Archivo no encontrado: ${imagePath}`);
      }

      await this.validateFile(imagePath);

      // Usar el método sendImage del WhatsAppClient
      const result = await this.whatsappClient.sendImage(
        numero,
        imagePath,
        caption
      );

      console.log(`✅ Imagen enviada a ${numero}`);
      return result;
    } catch (error) {
      console.error("Error enviando imagen:", error);
      if (intentos < this.retryAttempts) {
        console.log(
          `⚠️ Reintentando envío de imagen a ${numero} (${intentos + 1}/${
            this.retryAttempts
          })...`
        );
        await new Promise((resolve) =>
          setTimeout(resolve, 5000 * (intentos + 1))
        );
        return await this.sendImageMessage(
          numero,
          imagePath,
          caption,
          intentos + 1
        );
      }
      throw error;
    }
  }

  // Enviar documento
  async sendDocumentMessage(numero, docPath, caption = "", intentos = 0) {
    try {
      await this.validateFile(docPath);

      // Usar el método sendDocument del WhatsAppClient
      const result = await this.whatsappClient.sendDocument(
        numero,
        docPath,
        caption
      );

      console.log(`✅ Documento enviado a ${numero}`);
      return result;
    } catch (error) {
      if (intentos < this.retryAttempts) {
        console.log(
          `⚠️ Reintentando envío de documento a ${numero} (${intentos + 1}/${
            this.retryAttempts
          })...`
        );
        await new Promise((resolve) =>
          setTimeout(resolve, 5000 * (intentos + 1))
        );
        return await this.sendDocumentMessage(
          numero,
          docPath,
          caption,
          intentos + 1
        );
      }
      throw error;
    }
  }

  // Enviar mensaje con plantilla
  async sendTemplateMessage(contacto, plantillaId) {
    try {
      // Obtener plantilla de BD
      const [rows] = await db
        .getPool()
        .execute("SELECT * FROM plantillas_mensajes WHERE id = ?", [
          plantillaId,
        ]);

      if (rows.length === 0) {
        throw new Error("Plantilla no encontrada");
      }

      const plantilla = rows[0];

      // Obtener datos adicionales del contacto si es necesario
      const [contactoData] = await db.getPool().execute(
        `
              SELECT c.*, cat.nombre as categoria_nombre, cat.precio 
              FROM contactos c
              LEFT JOIN categorias cat ON c.categoria_id = cat.id
              WHERE c.id = ?
          `,
        [contacto.id]
      );

      const contactoCompleto = contactoData[0];

      // Usar el nuevo método que obtiene el nombre de WhatsApp
      const mensaje = await this.applyTemplateWithWhatsApp(
        plantilla.mensaje,
        contactoCompleto
      );

      return await this.sendTextMessage(contactoCompleto.numero, mensaje);
    } catch (error) {
      console.error("Error enviando mensaje con plantilla:", error);
      throw error;
    }
  }

  // Procesar mensaje desde la cola
  async processQueueMessage(mensajeCola) {
    console.log("[QUEUE] Procesando mensaje:", {
      id: mensajeCola.id,
      tipo: mensajeCola.tipo,
      numero: mensajeCola.numero,
      tiene_archivo: !!mensajeCola.imagen_path,
    });

    try {
      let result;

      switch (mensajeCola.tipo) {
        case "texto":
          result = await this.sendTextMessage(
            mensajeCola.numero,
            mensajeCola.mensaje
          );
          break;

        case "imagen":
        case "documento":
          if (!mensajeCola.imagen_path) {
            throw new Error(
              `No se especificó archivo para mensaje tipo ${mensajeCola.tipo}`
            );
          }

          const filePath = path.join(
            __dirname,
            "..",
            "..",
            "uploads",
            "mensajes",
            mensajeCola.imagen_path
          );

          // Verificar que el archivo existe
          try {
            await fs.access(filePath);
          } catch (error) {
            throw new Error(
              `Archivo no encontrado: ${mensajeCola.imagen_path}`
            );
          }

          if (mensajeCola.tipo === "imagen") {
            result = await this.sendImageMessage(
              mensajeCola.numero,
              filePath,
              mensajeCola.mensaje || ""
            );
          } else {
            result = await this.sendDocumentMessage(
              mensajeCola.numero,
              filePath,
              mensajeCola.mensaje || ""
            );
          }
          break;

        default:
          throw new Error(`Tipo de mensaje no soportado: ${mensajeCola.tipo}`);
      }

      console.log("[QUEUE] Mensaje procesado exitosamente:", mensajeCola.id);
      return result;
    } catch (error) {
      console.error("[QUEUE] Error procesando mensaje:", mensajeCola.id, error);
      throw error;
    }
  }

  // Calcular delay anti-spam
  calculateDelay(messageIndex, totalMessages) {
    const minDelay = parseInt(process.env.DELAY_MIN_MS) || 3000;
    const maxDelay = parseInt(process.env.DELAY_MAX_MS) || 8000;

    // Aumentar delay progresivamente
    const progressiveFactor = Math.min(messageIndex / 10, 3);
    const adjustedMin = minDelay * (1 + progressiveFactor * 0.5);
    const adjustedMax = maxDelay * (1 + progressiveFactor * 0.5);

    return Math.random() * (adjustedMax - adjustedMin) + adjustedMin;
  }

  // Envío masivo con control
  async sendBulkMessages(contactos, mensaje, opciones = {}) {
    const {
      tipo = "texto",
      imagePath = null,
      progressCallback = null,
    } = opciones;

    const resultados = {
      exitosos: 0,
      fallidos: 0,
      errores: [],
    };

    for (let i = 0; i < contactos.length; i++) {
      const contacto = contactos[i];

      try {
        // Calcular y aplicar delay
        if (i > 0) {
          const delay = this.calculateDelay(i, contactos.length);
          await new Promise((resolve) => setTimeout(resolve, delay));
        }

        // Personalizar mensaje si tiene variables
        let mensajePersonalizado = mensaje;

        if (
          mensaje.includes("{{nombre}}") ||
          mensaje.includes("{{nombreWhatsApp}}") ||
          mensaje.includes("{{whatsapp}}")
        ) {
          // Aplicar variables básicas
          mensajePersonalizado = mensajePersonalizado.replace(
            /{{nombre}}/g,
            contacto.nombre
          );

          // Intentar obtener nombre de WhatsApp
          if (
            (mensaje.includes("{{nombreWhatsApp}}") ||
              mensaje.includes("{{whatsapp}}")) &&
            this.whatsappClient &&
            this.whatsappClient.getContactInfo
          ) {
            try {
              const waInfo = await this.whatsappClient.getContactInfo(
                contacto.numero
              );
              if (waInfo && waInfo.pushname) {
                mensajePersonalizado = mensajePersonalizado.replace(
                  /{{nombreWhatsApp}}/g,
                  waInfo.pushname
                );
                mensajePersonalizado = mensajePersonalizado.replace(
                  /{{whatsapp}}/g,
                  waInfo.pushname
                );
              } else {
                // Usar nombre de BD como fallback
                mensajePersonalizado = mensajePersonalizado.replace(
                  /{{nombreWhatsApp}}/g,
                  contacto.nombre
                );
                mensajePersonalizado = mensajePersonalizado.replace(
                  /{{whatsapp}}/g,
                  contacto.nombre
                );
              }
            } catch (e) {
              // En caso de error, usar nombre de BD
              mensajePersonalizado = mensajePersonalizado.replace(
                /{{nombreWhatsApp}}/g,
                contacto.nombre
              );
              mensajePersonalizado = mensajePersonalizado.replace(
                /{{whatsapp}}/g,
                contacto.nombre
              );
            }
          }

          // Agregar fecha y hora si están presentes
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
        }

        // Enviar según tipo
        let result;
        switch (tipo) {
          case "texto":
            result = await this.sendTextMessage(
              contacto.numero,
              mensajePersonalizado
            );
            break;
          case "imagen":
            result = await this.sendImageMessage(
              contacto.numero,
              imagePath,
              mensajePersonalizado
            );
            break;
          case "documento":
            result = await this.sendDocumentMessage(
              contacto.numero,
              imagePath,
              mensajePersonalizado
            );
            break;
        }

        if (result.success) {
          resultados.exitosos++;

          // Registrar en historial
          await db.registrarMensaje(
            contacto.id,
            mensajePersonalizado,
            "saliente",
            "enviado"
          );
        }

        // Callback de progreso
        if (progressCallback) {
          progressCallback({
            total: contactos.length,
            enviados: i + 1,
            exitosos: resultados.exitosos,
            porcentaje: Math.round(((i + 1) / contactos.length) * 100),
          });
        }
      } catch (error) {
        console.error(`Error enviando a ${contacto.numero}:`, error);
        resultados.fallidos++;
        resultados.errores.push({
          contacto: contacto.numero,
          error: error.message,
        });
      }
    }

    return resultados;
  }
}

module.exports = MessageHandler;
