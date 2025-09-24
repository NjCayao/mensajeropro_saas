require("dotenv").config();
const WhatsAppClient = require("./whatsapp-wppconnect");
const MessageHandler = require("./messageHandler");
const BotHandler = require('./botHandler');
const Scheduler = require("./scheduler");
const createAPI = require("./api");
const db = require("./database");

async function main() {
  console.log("🚀 Iniciando MensajeroPro WhatsApp Service...");

  try {
    // Inicializar base de datos
    await db.initDatabase();

    // Crear instancia del bot
    const botHandler = new BotHandler();

    // Crear cliente WhatsApp con el bot
    const whatsappClient = new WhatsAppClient(botHandler);

    // Crear el manejador de mensajes
    const messageHandler = new MessageHandler(whatsappClient);

    // IMPORTANTE: Primero iniciar el servidor HTTP
    const app = createAPI(whatsappClient);
    const PORT = process.env.API_PORT || 3001;

    app.listen(PORT, "0.0.0.0", () => {
      console.log(`🌐 API REST corriendo en http://localhost:${PORT}`);
      console.log("📱 Iniciando WhatsApp en segundo plano...");
    });

    // Luego inicializar WhatsApp SIN await (en segundo plano)
    whatsappClient
      .initialize()
      .then(() => {
        console.log("✅ WhatsApp inicializado completamente");

        // Iniciar el scheduler DESPUÉS de que WhatsApp esté listo
        const scheduler = new Scheduler(messageHandler);
        scheduler.start();
        console.log("📅 Scheduler de mensajes programados activado");

        console.log("🔍 Verificando si el scheduler realmente funciona...");

        // Forzar una verificación inmediata
        setTimeout(() => {
          console.log("🔥 Forzando verificación manual...");
          scheduler.checkScheduledMessages();
        }, 5000);
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

// Ejecutar
main();