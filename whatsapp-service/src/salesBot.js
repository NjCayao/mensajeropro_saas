// whatsapp-service/src/salesBot.js
const db = require("./database");
const path = require("path");
const fs = require("fs").promises;

class SalesBot {
  constructor(empresaId) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.catalogoPdf = null;
    this.ventas = new Map();
    this.loadCatalog();
  }

  async loadCatalog() {
    try {
      const [rows] = await db.getPool().execute(
        "SELECT * FROM catalogo_bot WHERE empresa_id = ?",
        [this.empresaId]
      );

      if (rows.length > 0 && rows[0].datos_json) {
        this.catalogo = JSON.parse(rows[0].datos_json);
        this.catalogoPdf = rows[0].archivo_pdf;
        console.log(`✅ Catálogo cargado: ${this.catalogo.productos.length} productos`);
      }
    } catch (error) {
      console.error("Error cargando catálogo:", error);
    }
  }

  async procesarMensajeVenta(mensaje, numero) {
    const mensajeLower = mensaje.toLowerCase();

    // ============================================
    // HARDCODE: Solo para enviar PDF del catálogo
    // ============================================
    if ((mensajeLower.includes('catálogo') || mensajeLower.includes('catalogo') || 
         mensajeLower.includes('pdf') || mensajeLower.includes('carta') || 
         mensajeLower.includes('menu')) && 
        this.catalogoPdf && await this.fileExists(this.catalogoPdf)) {
      
      return {
        respuesta: "📋 Te envío nuestro catálogo completo en PDF:",
        tipo: "catalogo_pdf",
        archivo: this.catalogoPdf
      };
    }

    // ============================================
    // HARDCODE: Gestión de pedidos confirmados
    // ============================================
    let venta = this.ventas.get(numero);
    
    // Si ya está en proceso de pedido
    if (venta) {
      if (venta.estado === 'esperando_confirmacion') {
        if (mensajeLower.includes('sí') || mensajeLower.includes('si') || 
            mensajeLower.includes('confirmo') || mensajeLower.includes('correcto')) {
          return await this.confirmarPedido(numero, venta);
        } else if (mensajeLower.includes('no') || mensajeLower.includes('cancelar')) {
          this.ventas.delete(numero);
          return {
            respuesta: "❌ Pedido cancelado. ¿En qué más puedo ayudarte?",
            tipo: "pedido_cancelado"
          };
        }
      }

      if (venta.estado === 'esperando_entrega') {
        return await this.procesarTipoEntrega(mensaje, numero, venta);
      }

      if (venta.estado === 'esperando_direccion') {
        return await this.procesarDireccion(mensaje, numero, venta);
      }
    }

    // ============================================
    // IA: TODO LO DEMÁS usa OpenAI
    // ============================================
    return await this.usarOpenAI(mensaje, numero);
  }

  async usarOpenAI(mensaje, numero) {
    // Llamar al BotHandler para usar OpenAI
    const BotHandler = require('./botHandler');
    const botHandler = new BotHandler();
    
    // Cargar configuración
    await botHandler.loadConfig();
    
    // Preparar contexto con información del catálogo si existe
    let mensajeConContexto = mensaje;
    
    if (this.catalogo) {
      // Agregar contexto del catálogo al prompt
      const catalogoResumen = this.generarResumenCatalogo();
      
      // El botHandler ya tiene business_info, solo agregamos el catálogo actualizado
      const configActual = botHandler.config;
      if (configActual && configActual.business_info) {
        configActual.business_info += `\n\n📋 CATÁLOGO ACTUALIZADO:\n${catalogoResumen}`;
      }
    }
    
    // Procesar con IA
    const respuesta = await botHandler.processMessage(mensajeConContexto, numero);
    
    return respuesta;
  }

  generarResumenCatalogo() {
    if (!this.catalogo) return '';
    
    let resumen = '\n🛍️ PRODUCTOS DISPONIBLES:\n\n';
    
    // Agrupar por categoría
    const categorias = {};
    this.catalogo.productos.forEach(prod => {
      if (!categorias[prod.categoria]) {
        categorias[prod.categoria] = [];
      }
      categorias[prod.categoria].push(prod);
    });

    for (const [categoria, productos] of Object.entries(categorias)) {
      resumen += `**${categoria.toUpperCase()}**\n`;
      productos.forEach(prod => {
        const disponible = prod.disponible ? '✅' : '❌';
        resumen += `${disponible} ${prod.producto} - S/ ${prod.precio.toFixed(2)}\n`;
      });
      resumen += '\n';
    }

    // Agregar promociones si existen
    if (this.catalogo.promociones && this.catalogo.promociones.length > 0) {
      resumen += '\n🏷️ PROMOCIONES ACTIVAS:\n';
      this.catalogo.promociones.forEach(promo => {
        resumen += `🎉 ${promo.producto} - ${promo.tipo}: ${promo.descripcion}\n`;
        resumen += `   Precio especial: S/ ${promo.precio_promo.toFixed(2)}\n`;
      });
      resumen += '\n';
    }

    // Agregar info de delivery si existe
    if (this.catalogo.delivery && this.catalogo.delivery.zonas) {
      resumen += '\n🚚 DELIVERY:\n';
      this.catalogo.delivery.zonas.forEach(zona => {
        resumen += `📍 ${zona.zona}: S/ ${zona.costo.toFixed(2)} (${zona.tiempo})\n`;
      });
      
      if (this.catalogo.delivery.gratis_desde) {
        resumen += `✅ GRATIS desde S/ ${this.catalogo.delivery.gratis_desde.toFixed(2)}\n`;
      }
    }

    return resumen;
  }

  // ============================================
  // Métodos técnicos (hardcode necesario)
  // ============================================

  async confirmarPedido(numero, venta) {
    venta.estado = "esperando_entrega";
    this.ventas.set(numero, venta);

    return {
      respuesta: `✅ Perfecto. ¿Cómo prefieres recibir tu pedido?\n\n` +
                 `1️⃣ *Delivery* a domicilio\n` +
                 `2️⃣ *Recoger* en tienda\n\n` +
                 `Por favor, elige una opción.`,
      tipo: "tipo_entrega",
    };
  }

  async procesarTipoEntrega(mensaje, numero, venta) {
    const mensajeLower = mensaje.toLowerCase();

    if (mensajeLower.includes("delivery") || mensajeLower.includes("1")) {
      venta.tipo_entrega = "delivery";
      venta.estado = "esperando_direccion";
      this.ventas.set(numero, venta);

      return {
        respuesta: "🏠 Por favor, escribe tu dirección completa para el delivery:",
        tipo: "solicitar_direccion",
      };
    } else if (mensajeLower.includes("tienda") || mensajeLower.includes("recoger") || mensajeLower.includes("2")) {
      venta.tipo_entrega = "tienda";
      return await this.finalizarPedido(numero, venta);
    }

    return {
      respuesta: "Por favor, elige:\n1️⃣ Delivery\n2️⃣ Recoger en tienda",
      tipo: "tipo_entrega_invalido",
    };
  }

  async procesarDireccion(mensaje, numero, venta) {
    venta.direccion_entrega = mensaje;
    
    let costoDelivery = 5;
    const direccionLower = mensaje.toLowerCase();
    
    if (this.catalogo && this.catalogo.delivery && this.catalogo.delivery.zonas) {
      this.catalogo.delivery.zonas.forEach(zona => {
        if (direccionLower.includes(zona.zona.toLowerCase())) {
          costoDelivery = zona.costo;
        }
      });
    }

    if (this.catalogo && this.catalogo.delivery.gratis_desde && venta.total >= this.catalogo.delivery.gratis_desde) {
      costoDelivery = 0;
    }

    venta.costo_delivery = costoDelivery;
    venta.total_con_delivery = venta.total + costoDelivery;

    return await this.finalizarPedido(numero, venta);
  }

  async finalizarPedido(numero, venta) {
    try {
      const [result] = await db.getPool().execute(
        `INSERT INTO ventas_bot 
         (empresa_id, numero_cliente, productos_cotizados, total_cotizado, 
          estado, tipo_entrega, direccion_entrega)
         VALUES (?, ?, ?, ?, 'cotizado', ?, ?)`,
        [
          this.empresaId,
          numero,
          JSON.stringify(venta.productos),
          venta.total_con_delivery || venta.total,
          venta.tipo_entrega,
          venta.direccion_entrega || null,
        ]
      );

      const ventaId = result.insertId;

      let respuesta = `✅ *PEDIDO CONFIRMADO*\n`;
      respuesta += `Número de pedido: #${ventaId}\n\n`;
      respuesta += `📦 *Productos:*\n`;
      venta.productos.forEach((item) => {
        respuesta += `• ${item.producto} x${item.cantidad}\n`;
      });
      respuesta += `\n💰 Subtotal: S/ ${venta.total.toFixed(2)}`;

      if (venta.tipo_entrega === "delivery") {
        if (venta.costo_delivery > 0) {
          respuesta += `\n🚚 Delivery: S/ ${venta.costo_delivery.toFixed(2)}`;
        } else {
          respuesta += `\n🚚 Delivery: GRATIS`;
        }
        respuesta += `\n📍 Dirección: ${venta.direccion_entrega}`;
        respuesta += `\n\n*TOTAL: S/ ${venta.total_con_delivery.toFixed(2)}*`;
      } else {
        respuesta += `\n\n*TOTAL: S/ ${venta.total.toFixed(2)}*`;
        respuesta += `\n\n📍 *Recoger en tienda*`;
      }

      respuesta += `\n\n⏱️ Tiempo estimado: 30-45 minutos\n\n`;
      respuesta += `¡Gracias por tu pedido! Te contactaremos pronto para confirmar.`;

      this.ventas.delete(numero);

      return {
        respuesta: respuesta,
        tipo: "pedido_confirmado",
        ventaId: ventaId,
      };
    } catch (error) {
      console.error("Error guardando venta:", error);
      return {
        respuesta: "Hubo un error procesando tu pedido. Por favor, intenta nuevamente.",
        tipo: "error_pedido",
      };
    }
  }

  async fileExists(filePath) {
    try {
      await fs.access(filePath);
      return true;
    } catch {
      return false;
    }
  }
}

module.exports = SalesBot;