// src/index.js - MODIFICADO PARA MULTI-PUERTO
require("dotenv").config();
const WhatsAppClient = require("./whatsapp-wppconnect");
const MessageHandler = require("./messageHandler");
const BotHandler = require("./botHandler");
const Scheduler = require("./scheduler");
const createAPI = require("./api");
const db = require("./database");

// OBTENER PARÁMETROS DE LÍNEA DE COMANDO
const args = process.argv.slice(2);
const PUERTO = args[0] || process.env.API_PORT || "3001";
const EMPRESA_ID = args[1] || "1";

// Guardar empresa_id globalmente
global.EMPRESA_ID = EMPRESA_ID;

const checkPort = (port) => {
  return new Promise((resolve) => {
    const server = require("net").createServer();
    server.once("error", () => resolve(false));
    server.once("listening", () => {
      server.close();
      resolve(true);
    });
    server.listen(port);
  });
};

async function main() {
  console.log(`🚀 Iniciando MensajeroPro WhatsApp Service...`);
  console.log(`🏢 Empresa ID: ${EMPRESA_ID}`);
  console.log(`🔌 Puerto: ${PUERTO}`);

  try {
    // Inicializar base de datos
    await db.initDatabase();

    const portAvailable = await checkPort(PUERTO);
    if (!portAvailable) {
      console.error(`❌ Puerto ${PUERTO} ya está en uso`);
      console.log("Intenta ejecutar: taskkill /F /IM node.exe");
      process.exit(1);
    }

    // Crear instancia del bot
    const botHandler = new BotHandler();

    // Crear cliente WhatsApp con el bot
    const whatsappClient = new WhatsAppClient(botHandler);

    // Crear el manejador de mensajes
    const messageHandler = new MessageHandler(whatsappClient);

    // Iniciar servidor en el puerto especificado
    const app = createAPI(whatsappClient);

    const HOST = process.env.NODE_ENV === "production" ? "127.0.0.1" : "0.0.0.0";

    // AQUÍ USAS LA IMPLEMENTACIÓN SIMPLE (sin startServer)
    app.listen(parseInt(PUERTO), HOST, () => {
      console.log(`🌐 API REST corriendo en http://${HOST}:${PUERTO}`);
      console.log("📱 Iniciando WhatsApp en segundo plano...");
    }).on("error", (err) => {
      if (err.code === "EADDRINUSE") {
        console.error(`❌ Puerto ${PUERTO} ya está en uso`);
        console.log("💡 Ejecuta el servicio desde el panel web para limpieza automática");
        process.exit(1);
      } else {
        console.error("Error iniciando servidor:", err);
        process.exit(1);
      }
    });

    // Inicializar WhatsApp
    whatsappClient
      .initialize()
      .then(() => {
        console.log("✅ WhatsApp inicializado completamente");

        const scheduler = new Scheduler(messageHandler);
        scheduler.start();
        console.log("📅 Scheduler de mensajes programados activado");
      })
      .catch((error) => {
        console.error("❌ Error inicializando WhatsApp:", error);
      });

    // Manejo de cierre graceful
    process.on("SIGINT", async () => {
      console.log("\n🛑 Cerrando aplicación...");
      await whatsappClient.disconnect();
      process.exit(0);
    });
  } catch (error) {
    console.error("❌ Error fatal:", error);
    process.exit(1);
  }
}

main();