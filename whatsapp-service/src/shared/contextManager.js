// whatsapp-service/src/shared/contextManager.js
const db = require("../database");

class ContextManager {
  constructor() {
    this.cache = new Map();
  }

  /**
   * Obtiene últimos 5 mensajes de conversación
   */
  async obtenerContexto(numero, empresaId) {
    const cacheKey = `${empresaId}_${numero}`;

    if (this.cache.has(cacheKey)) {
      const cached = this.cache.get(cacheKey);
      if (Date.now() - cached.timestamp < 120000) {
        // 2 min
        return cached.contexto;
      }
    }

    try {
      const [rows] = await db.getPool().execute(
        `SELECT 
                    mensaje_cliente,
                    respuesta_bot,
                    fecha_hora,
                    categoria_detectada,
                    confianza_respuesta
                FROM conversaciones_bot 
                WHERE numero_cliente = ? 
                AND empresa_id = ?
                ORDER BY fecha_hora DESC 
                LIMIT 5`,
        [numero, empresaId]
      );

      const contexto = rows.reverse();

      this.cache.set(cacheKey, {
        contexto,
        timestamp: Date.now(),
      });

      return contexto;
    } catch (error) {
      console.error("❌ Error obteniendo contexto:", error);
      return [];
    }
  }

  /**
   * Formatea contexto para ML Engine
   */
  formatearParaML(contexto) {
    const mensajes = [];
    contexto.forEach((msg) => {
      if (msg.mensaje_cliente) {
        mensajes.push(`Usuario: ${msg.mensaje_cliente}`);
      }
      if (msg.respuesta_bot) {
        mensajes.push(`Bot: ${msg.respuesta_bot}`);
      }
    });
    return mensajes;
  }

  /**
   * Formatea contexto para GPT (mensajes array)
   */
  formatearParaGPT(contexto) {
    const mensajes = [];

    contexto.forEach((conv) => {
      mensajes.push({
        role: "user",
        content: conv.mensaje_cliente,
      });

      if (conv.respuesta_bot) {
        mensajes.push({
          role: "assistant",
          content: conv.respuesta_bot,
        });
      }
    });

    return mensajes;
  }

  /**
   * Guarda conversación en BD
   */
  async guardarConversacion(datos) {
    const {
      empresaId,
      numero,
      mensajeCliente,
      respuestaBot,
      intencionDetectada,
      confianza,
      tokensUsados,
      tiempoRespuesta,
      contexto,
    } = datos;

    try {
      const esRegistrado = await this.esClienteRegistrado(numero, empresaId);

      await db.getPool().execute(
        `INSERT INTO conversaciones_bot 
                (empresa_id, numero_cliente, mensaje_cliente, respuesta_bot,
                 categoria_detectada, confianza_respuesta, tokens_usados,
                 tiempo_respuesta, contexto_conversacion, es_cliente_registrado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          empresaId,
          numero,
          mensajeCliente,
          respuestaBot,
          intencionDetectada,
          confianza,
          tokensUsados || 0,
          tiempoRespuesta || 0,
          JSON.stringify(contexto || []),
          esRegistrado,
        ]
      );

      // Invalidar cache
      this.cache.delete(`${empresaId}_${numero}`);
    } catch (error) {
      console.error("❌ Error guardando conversación:", error);
    }
  }

  /**
   * Verifica si número está registrado
   */
  async esClienteRegistrado(numero, empresaId) {
    try {
      const [rows] = await db
        .getPool()
        .execute(
          "SELECT id FROM contactos WHERE numero = ? AND empresa_id = ? AND activo = 1",
          [numero.replace("@c.us", ""), empresaId]
        );
      return rows.length > 0 ? 1 : 0;
    } catch (error) {
      return 0;
    }
  }

  /**
   * Limpia cache antiguo (>5 min)
   */
  limpiarCache() {
    const ahora = Date.now();
    for (const [key, value] of this.cache.entries()) {
      if (ahora - value.timestamp > 300000) {
        this.cache.delete(key);
      }
    }
  }
}

module.exports = ContextManager;
