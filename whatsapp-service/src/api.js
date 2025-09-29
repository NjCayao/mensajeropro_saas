const express = require("express");
const cors = require("cors");
const config = require("./config");
const multer = require("multer");
const path = require("path");
const fs = require("fs").promises;
const db = require("./database");

// Configurar multer para archivos
const storage = multer.diskStorage({
  destination: async (req, file, cb) => {
    const projectRoot = path.resolve(__dirname, "../..");
    const uploadPath = path.join(projectRoot, "uploads", "mensajes");

    console.log("Ruta de uploads:", uploadPath); // Para debug
    await fs.mkdir(uploadPath, { recursive: true });
    cb(null, uploadPath);
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + "-" + Math.round(Math.random() * 1e9);
    cb(null, uniqueSuffix + path.extname(file.originalname));
  },
});

const upload = multer({
  storage: storage,
  limits: { fileSize: 16 * 1024 * 1024 }, // 16MB
  fileFilter: (req, file, cb) => {
    const allowedTypes = /jpeg|jpg|png|gif|pdf|doc|docx|xls|xlsx/;
    const extname = allowedTypes.test(
      path.extname(file.originalname).toLowerCase()
    );
    const mimetype = allowedTypes.test(file.mimetype);

    if (mimetype && extname) {
      return cb(null, true);
    } else {
      cb(new Error("Tipo de archivo no permitido"));
    }
  },
});

function createAPI(whatsappClient) {
  const app = express();

  // Configuración CORS dinámica
  const corsOptions = {
    origin: function (origin, callback) {
      // Permitir requests sin origin (mismo servidor)
      if (!origin) {
        callback(null, true);
        return;
      }

      // Lista de orígenes permitidos
      const allowedOrigins = [
        "http://localhost",
        "http://localhost:80",
        "http://localhost:3000",
        "http://127.0.0.1",
        "http://127.0.0.1:80",
      ];

      // En producción, agregar tu dominio
      if (process.env.NODE_ENV === "production") {
        allowedOrigins.push("https://tudominio.com");
        allowedOrigins.push("https://www.tudominio.com");
      }

      // Verificar si el origen está permitido
      const isAllowed = allowedOrigins.some((allowed) =>
        origin.startsWith(allowed)
      );

      if (isAllowed) {
        callback(null, true);
      } else {
        console.log("CORS bloqueado para origen:", origin);
        callback(new Error("Not allowed by CORS"));
      }
    },
    credentials: true,
    methods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allowedHeaders: ["Content-Type", "X-API-Key", "Authorization"],
    preflightContinue: false,
    optionsSuccessStatus: 204,
  };

  // Middlewares
  app.use(cors(corsOptions));
  app.use(express.json());

  // Logging middleware en desarrollo
  if (config.isDevelopment) {
    app.use((req, res, next) => {
      console.log(`${new Date().toISOString()} ${req.method} ${req.path}`);
      next();
    });
  }

  // Middleware de autenticación simple
  app.use((req, res, next) => {
    const apiKey = req.headers["x-api-key"];
    if (apiKey !== process.env.API_KEY) {
      return res
        .status(401)
        .json({ success: false, error: "API key inválida" });
    }
    next();
  });

  // Rutas

  // Estado de WhatsApp
  app.get("/api/status", (req, res) => {
    const status = whatsappClient.getStatus();
    res.json({ success: true, data: status });
  });

  // Obtener QR
  app.get("/api/qr", async (req, res) => {
    try {
      const qr = await whatsappClient.getQR();
      res.json({ success: true, qr: qr });
    } catch (error) {
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Verificar si número está en WhatsApp
  app.post("/api/check-number", async (req, res) => {
    try {
      const { numero } = req.body;
      const isRegistered = await whatsappClient.isRegisteredUser(numero);
      res.json({ success: true, isRegistered });
    } catch (error) {
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Enviar mensaje individual
  app.post("/api/send", async (req, res) => {
    try {
      const { numero, mensaje, contacto_id, tiene_variables } = req.body;

      if (!numero || !mensaje) {
        return res.status(400).json({
          success: false,
          error: "Número y mensaje son requeridos",
        });
      }

      let mensajeFinal = mensaje;

      // Si el mensaje tiene variables y especialmente {{nombreWhatsApp}} o {{whatsapp}}
      if (
        tiene_variables &&
        (mensaje.includes("{{nombreWhatsApp}}") ||
          mensaje.includes("{{whatsapp}}"))
      ) {
        console.log("Procesando variables de WhatsApp...");

        try {
          // Obtener información del contacto de WhatsApp
          const waInfo = await whatsappClient.getContactInfo(numero);

          if (waInfo && waInfo.pushname) {
            console.log(`Nombre WhatsApp obtenido: ${waInfo.pushname}`);
            mensajeFinal = mensajeFinal.replace(
              /{{nombreWhatsApp}}/g,
              waInfo.pushname
            );
            mensajeFinal = mensajeFinal.replace(
              /{{whatsapp}}/g,
              waInfo.pushname
            );
          } else {
            console.log(
              "No se pudo obtener nombre de WhatsApp, usando fallback"
            );

            // Si tenemos contacto_id, buscar el nombre en la BD como fallback
            if (contacto_id) {
              try {
                const [contacto] = await db
                  .getPool()
                  .execute("SELECT nombre FROM contactos WHERE id = ?", [
                    contacto_id,
                  ]);

                if (contacto.length > 0) {
                  mensajeFinal = mensajeFinal.replace(
                    /{{nombreWhatsApp}}/g,
                    contacto[0].nombre
                  );
                  mensajeFinal = mensajeFinal.replace(
                    /{{whatsapp}}/g,
                    contacto[0].nombre
                  );
                  mensajeFinal = mensajeFinal.replace(
                    /{{nombre}}/g,
                    contacto[0].nombre
                  );
                }
              } catch (e) {
                console.error("Error obteniendo contacto de BD:", e);
              }
            }

            // Si aún quedan variables sin reemplazar, usar un valor genérico
            mensajeFinal = mensajeFinal.replace(
              /{{nombreWhatsApp}}/g,
              "Estimado/a"
            );
            mensajeFinal = mensajeFinal.replace(/{{whatsapp}}/g, "Estimado/a");
          }

          // Si el mensaje también tiene {{nombre}} y no fue reemplazado en el cliente
          if (mensaje.includes("{{nombre}}") && contacto_id) {
            try {
              const [contacto] = await db
                .getPool()
                .execute("SELECT nombre FROM contactos WHERE id = ?", [
                  contacto_id,
                ]);

              if (contacto.length > 0) {
                mensajeFinal = mensajeFinal.replace(
                  /{{nombre}}/g,
                  contacto[0].nombre
                );
              }
            } catch (e) {
              console.error("Error obteniendo nombre de BD:", e);
            }
          }
        } catch (error) {
          console.error("Error procesando variables:", error);
          // En caso de error, usar el mensaje original o con fallbacks
          mensajeFinal = mensajeFinal.replace(
            /{{nombreWhatsApp}}/g,
            "Estimado/a"
          );
          mensajeFinal = mensajeFinal.replace(/{{whatsapp}}/g, "Estimado/a");
        }
      }

      // Enviar el mensaje con las variables ya procesadas
      const result = await whatsappClient.sendMessage(numero, mensajeFinal);
      res.json({ success: true, ...result, mensaje_enviado: mensajeFinal });
    } catch (error) {
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Enviar mensaje con archivo
  app.post("/api/send-media", upload.single("archivo"), async (req, res) => {
    try {
      const { numero, mensaje, tipo, contacto_id, tiene_variables } = req.body;
      const file = req.file;

      if (!numero || !file) {
        return res.status(400).json({
          success: false,
          error: "Número y archivo son requeridos",
        });
      }

      let mensajeFinal = mensaje || "";

      // Procesar variables si es necesario
      if (
        tiene_variables &&
        mensajeFinal &&
        (mensajeFinal.includes("{{nombreWhatsApp}}") ||
          mensajeFinal.includes("{{whatsapp}}"))
      ) {
        try {
          const waInfo = await whatsappClient.getContactInfo(numero);

          if (waInfo && waInfo.pushname) {
            mensajeFinal = mensajeFinal.replace(
              /{{nombreWhatsApp}}/g,
              waInfo.pushname
            );
            mensajeFinal = mensajeFinal.replace(
              /{{whatsapp}}/g,
              waInfo.pushname
            );
          } else if (contacto_id) {
            // Fallback a nombre de BD
            const [contacto] = await db
              .getPool()
              .execute("SELECT nombre FROM contactos WHERE id = ?", [
                contacto_id,
              ]);

            if (contacto.length > 0) {
              mensajeFinal = mensajeFinal.replace(
                /{{nombreWhatsApp}}/g,
                contacto[0].nombre
              );
              mensajeFinal = mensajeFinal.replace(
                /{{whatsapp}}/g,
                contacto[0].nombre
              );
            }
          }
        } catch (error) {
          console.error("Error procesando variables:", error);
        }
      }

      let result;
      if (tipo === "documento") {
        result = await whatsappClient.sendDocument(
          numero,
          file.path,
          mensajeFinal
        );
      } else {
        result = await whatsappClient.sendImage(
          numero,
          file.path,
          mensajeFinal
        );
      }

      // Eliminar archivo después de enviar
      setTimeout(async () => {
        try {
          await fs.unlink(file.path);
        } catch (err) {
          console.error("Error eliminando archivo:", err);
        }
      }, 5000);

      res.json({ success: true, ...result });
    } catch (error) {
      console.error("Error en send-media:", error);
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Agregar mensajes a la cola
  app.post("/api/queue/add", async (req, res) => {
    try {
      const { contacto_id, mensaje, tipo = "texto", imagen_path } = req.body;

      const [result] = await db.getPool().execute(
        `INSERT INTO cola_mensajes (contacto_id, mensaje, tipo, imagen_path) 
                 VALUES (?, ?, ?, ?)`,
        [contacto_id, mensaje, tipo, imagen_path]
      );

      res.json({
        success: true,
        message: "Mensaje agregado a la cola",
        id: result.insertId,
      });
    } catch (error) {
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Envío masivo
  const processFileUpload = (req, res, next) => {
    const contentType = req.headers["content-type"] || "";

    if (contentType.includes("multipart/form-data")) {
      upload.single("archivo")(req, res, (err) => {
        if (err instanceof multer.MulterError) {
          return res.status(400).json({
            success: false,
            error: `Error de archivo: ${err.message}`,
          });
        } else if (err) {
          return res.status(400).json({
            success: false,
            error: "Error procesando archivo",
          });
        }
        next();
      });
    } else {
      next();
    }
  };

  // Envío masivo con manejo robusto
  app.post("/api/send/bulk", processFileUpload, async (req, res) => {
    try {
      // Validar entrada
      const { categoria_id, mensaje, tipo = "texto" } = req.body;
      const file = req.file;

      // Log para debugging
      console.log("[BULK] Request recibida:", {
        categoria_id,
        mensaje: mensaje ? mensaje.substring(0, 50) + "..." : "null",
        tipo,
        hasFile: !!file,
        fileName: file?.filename,
      });

      // Validaciones
      if (!mensaje || mensaje.trim() === "") {
        return res.status(400).json({
          success: false,
          error: "El mensaje es obligatorio",
        });
      }

      if ((tipo === "imagen" || tipo === "documento") && !file) {
        return res.status(400).json({
          success: false,
          error: `Se requiere un archivo para tipo ${tipo}`,
        });
      }

      // Detectar si el mensaje tiene variables
      const tieneVariables = mensaje.includes("{{") && mensaje.includes("}}");
      const tieneNombreWhatsApp =
        mensaje.includes("{{nombreWhatsApp}}") ||
        mensaje.includes("{{whatsapp}}");

      // Obtener contactos con validación
      const contactos = await db.getContactosPorCategoria(categoria_id);

      if (!contactos || contactos.length === 0) {
        // Si hay archivo, eliminarlo
        if (file) {
          await fs
            .unlink(file.path)
            .catch((err) => console.error("Error eliminando archivo:", err));
        }

        return res.status(404).json({
          success: false,
          error:
            "No se encontraron contactos activos en la categoría seleccionada",
        });
      }

      // Preparar datos para la cola
      const imagen_path = file ? file.filename : null;
      const ahora = new Date();

      // Insertar en la cola usando transacción
      const pool = db.getPool();
      const connection = await pool.getConnection();

      try {
        await connection.beginTransaction();

        let agregados = 0;
        let errores = [];

        for (const contacto of contactos) {
          try {
            let mensajePersonalizado = mensaje;

            // Si tiene variables, personalizarlas para cada contacto
            if (tieneVariables) {
              // Reemplazar variables básicas
              mensajePersonalizado = mensajePersonalizado.replace(
                /{{nombre}}/g,
                contacto.nombre
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

              // Si tiene categoría
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

              // Si tiene {{nombreWhatsApp}}, intentar obtenerlo
              if (
                tieneNombreWhatsApp &&
                whatsappClient &&
                whatsappClient.getContactInfo
              ) {
                try {
                  console.log(
                    `[BULK] Obteniendo nombre WhatsApp para ${contacto.numero}`
                  );
                  const waInfo = await whatsappClient.getContactInfo(
                    contacto.numero
                  );

                  if (waInfo && waInfo.pushname) {
                    console.log(
                      `[BULK] Nombre WhatsApp obtenido: ${waInfo.pushname}`
                    );
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
                    console.log(
                      `[BULK] No se obtuvo nombre WhatsApp, usando BD: ${contacto.nombre}`
                    );
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
                  console.error(
                    `[BULK] Error obteniendo nombre WhatsApp:`,
                    e.message
                  );
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
            }

            // Insertar en la cola con el mensaje personalizado
            await connection.execute(
              `INSERT INTO cola_mensajes 
                      (contacto_id, mensaje, tipo, imagen_path, estado, fecha_creacion) 
                      VALUES (?, ?, ?, ?, 'pendiente', ?)`,
              [contacto.id, mensajePersonalizado, tipo, imagen_path, ahora]
            );
            agregados++;

            // Pequeño delay entre contactos para no saturar WhatsApp API
            if (tieneNombreWhatsApp && agregados % 5 === 0) {
              await new Promise((resolve) => setTimeout(resolve, 1000));
            }
          } catch (error) {
            console.error(`Error agregando contacto ${contacto.id}:`, error);
            errores.push({
              contacto_id: contacto.id,
              error: error.message,
            });
          }
        }

        await connection.commit();

        // Respuesta detallada
        const response = {
          success: true,
          message: `${agregados} mensajes agregados a la cola de envío`,
          data: {
            total_contactos: contactos.length,
            agregados: agregados,
            fallidos: errores.length,
            tipo_mensaje: tipo,
            tiene_archivo: !!file,
            variables_procesadas: tieneVariables,
          },
        };

        if (errores.length > 0) {
          response.data.errores = errores;
        }

        res.json(response);
      } catch (error) {
        await connection.rollback();
        throw error;
      } finally {
        connection.release();
      }
    } catch (error) {
      console.error("[BULK] Error:", error);

      // Si hay archivo y hubo error, intentar eliminarlo
      if (req.file) {
        await fs
          .unlink(req.file.path)
          .catch((err) => console.error("Error eliminando archivo:", err));
      }

      res.status(500).json({
        success: false,
        error: "Error procesando envío masivo",
        details: error.message,
      });
    }
  });

  // Desconectar WhatsApp
  app.post("/api/disconnect", async (req, res) => {
    try {
      await whatsappClient.disconnect();
      res.json({ success: true, message: "WhatsApp desconectado" });
    } catch (error) {
      res.status(500).json({ success: false, error: error.message });
    }
  });

  // Health check
  app.get("/health", (req, res) => {
    res.json({
      status: "ok",
      whatsapp: whatsappClient.getStatus(),
      timestamp: new Date().toISOString(),
    });
  });

  return app;
}

module.exports = createAPI;

