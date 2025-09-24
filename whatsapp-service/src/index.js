// src/index.js - MODIFICADO PARA MULTI-PUERTO
require("dotenv").config();
const WhatsAppClient = require("./whatsapp-wppconnect");
const MessageHandler = require("./messageHandler");
const BotHandler = require('./botHandler');
const Scheduler = require("./scheduler");
const createAPI = require("./api");
const db = require("./database");

// OBTENER PARÁMETROS DE LÍNEA DE COMANDO
const args = process.argv.slice(2);
const PUERTO = args[0] || process.env.API_PORT || '3001';
const EMPRESA_ID = args[1] || '1';

// Guardar empresa_id globalmente
global.EMPRESA_ID = EMPRESA_ID;

async function main() {
  console.log(`🚀 Iniciando MensajeroPro WhatsApp Service...`);
  console.log(`🏢 Empresa ID: ${EMPRESA_ID}`);
  console.log(`🔌 Puerto: ${PUERTO}`);

  try {
    // Inicializar base de datos
    await db.initDatabase();

    // Crear instancia del bot
    const botHandler = new BotHandler();

    // Crear cliente WhatsApp con el bot
    const whatsappClient = new WhatsAppClient(botHandler);

    // Crear el manejador de mensajes
    const messageHandler = new MessageHandler(whatsappClient);

    // Iniciar servidor en el puerto especificado
    const app = createAPI(whatsappClient);

    app.listen(parseInt(PUERTO), "0.0.0.0", () => {
      console.log(`🌐 API REST corriendo en http://localhost:${PUERTO}`);
      console.log("📱 Iniciando WhatsApp en segundo plano...");
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