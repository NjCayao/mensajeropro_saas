// whatsapp-service/src/salesBot.js
const db = require("./database");
const path = require("path");
const fs = require("fs").promises;

class SalesBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.catalogoPdf = null;
    this.ventas = new Map();
    this.botHandler = botHandler; // Recibir botHandler como parámetro
    // this.loadCatalog();
  }

  async loadCatalog() {
    try {
      const [rows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (rows.length > 0 && rows[0].datos_json) {
        this.catalogo = JSON.parse(rows[0].datos_json);
        this.catalogoPdf = rows[0].archivo_pdf;
        // console.log(
        //   `✅ Catálogo cargado: ${this.catalogo.productos.length} productos`
        // );
      }
    } catch (error) {
      console.error("Error cargando catálogo:", error);
    }
  }

  async procesarMensajeVenta(mensaje, numero) {
    const mensajeLower = mensaje.toLowerCase();

    // Verificar si hay una venta en proceso
    let venta = this.ventas.get(numero);

    // Si hay venta en proceso, manejar con flujo estructurado
    if (venta) {
      // Estados del flujo estructurado
      switch (venta.estado) {
        case "esperando_confirmacion":
          if (
            mensajeLower.includes("sí") ||
            mensajeLower.includes("si") ||
            mensajeLower.includes("confirmo") ||
            mensajeLower.includes("correcto")
          ) {
            return await this.mostrarMetodosPago(numero, venta);
          } else if (
            mensajeLower.includes("no") ||
            mensajeLower.includes("cancelar")
          ) {
            this.ventas.delete(numero);
            return {
              respuesta: "❌ Pedido cancelado. ¿En qué más puedo ayudarte?",
              tipo: "pedido_cancelado",
            };
          }
          break;

        case "esperando_pago":
          if (
            mensajeLower.includes("pagué") ||
            mensajeLower.includes("pague") ||
            mensajeLower.includes("yapeé") ||
            mensajeLower.includes("yapee") ||
            mensajeLower.includes("transferí")
          ) {
            return await this.confirmarPedido(numero, venta);
          }
          break;

        case "esperando_entrega":
          return await this.procesarTipoEntrega(mensaje, numero, venta);

        case "esperando_direccion":
          return await this.procesarDireccion(mensaje, numero, venta);
      }
    }

    // Detectar si el mensaje menciona productos del catálogo
    const productosDetectados = await this.detectarProductos(mensaje);

    if (productosDetectados.length > 0) {
      // Crear nueva venta
      venta = {
        productos: productosDetectados,
        total: this.calcularTotal(productosDetectados),
        estado: "esperando_confirmacion",
      };
      this.ventas.set(numero, venta);

      // Responder con confirmación
      let respuesta = "🛒 *RESUMEN DE TU PEDIDO:*\n\n";
      productosDetectados.forEach((item) => {
        respuesta += `• ${item.producto} x${item.cantidad} - S/ ${(
          item.precio * item.cantidad
        ).toFixed(2)}\n`;
      });
      respuesta += `\n💰 *TOTAL: S/ ${venta.total.toFixed(2)}*\n\n`;
      respuesta +=
        "¿Confirmas tu pedido? Responde *SÍ* para continuar o *NO* para cancelar.";

      return {
        respuesta: respuesta,
        tipo: "confirmacion_pedido",
      };
    }

    // Si no hay productos detectados, usar IA
    return await this.usarOpenAI(mensaje, numero);
  }

  // Nuevo método para detectar productos
  async detectarProductos(mensaje) {
    if (!this.catalogo) return [];

    const productos = [];
    const mensajeLower = mensaje.toLowerCase();

    // Buscar productos mencionados
    this.catalogo.productos.forEach((prod) => {
      if (
        mensajeLower.includes(prod.producto.toLowerCase()) ||
        mensajeLower.includes(prod.categoria.toLowerCase())
      ) {
        // Detectar cantidad (buscar números antes del producto)
        const regex = new RegExp(`(\\d+)\\s*${prod.producto}`, "i");
        const match = mensaje.match(regex);
        const cantidad = match ? parseInt(match[1]) : 1;

        productos.push({
          producto: prod.producto,
          precio: prod.precio,
          cantidad: cantidad,
        });
      }
    });

    // Buscar promociones
    if (this.catalogo.promociones) {
      this.catalogo.promociones.forEach((promo) => {
        if (mensajeLower.includes(promo.producto.toLowerCase())) {
          productos.push({
            producto: `${promo.producto} (${promo.tipo})`,
            precio: promo.precio_promo,
            cantidad: 1,
          });
        }
      });
    }

    return productos;
  }

  // Calcular total
  calcularTotal(productos) {
    return productos.reduce(
      (sum, item) => sum + item.precio * item.cantidad,
      0
    );
  }

  // Nuevo método para mostrar métodos de pago
  async mostrarMetodosPago(numero, venta) {
    venta.estado = "esperando_pago";
    this.ventas.set(numero, venta);

    // Obtener métodos de pago de configuración_negocio
    const [config] = await db
      .getPool()
      .execute(
        "SELECT cuentas_pago FROM configuracion_negocio WHERE empresa_id = ?",
        [this.empresaId]
      );

    let respuesta = "💳 *MÉTODOS DE PAGO DISPONIBLES:*\n\n";

    if (config[0] && config[0].cuentas_pago) {
      const cuentas = JSON.parse(config[0].cuentas_pago);
      if (cuentas.yape) respuesta += `📱 *YAPE:* ${cuentas.yape}\n`;
      if (cuentas.plin) respuesta += `📱 *PLIN:* ${cuentas.plin}\n`;
      if (cuentas.bcp) respuesta += `🏦 *BCP:* ${cuentas.bcp}\n`;
      if (cuentas.interbank)
        respuesta += `🏦 *INTERBANK:* ${cuentas.interbank}\n`;
    } else {
      // Valores por defecto si no hay configuración
      respuesta += `📱 *YAPE/PLIN:* 912345678\n`;
      respuesta += `🏦 *BCP:* 123-456789-0-12\n`;
    }

    respuesta += `\n💰 *Total a pagar: S/ ${venta.total.toFixed(2)}*\n`;
    respuesta += `\nPor favor realiza el pago y luego escribe *"ya pagué"* o *"listo"* para confirmar.`;

    return {
      respuesta: respuesta,
      tipo: "metodos_pago",
    };
  }

  async usarOpenAI(mensaje, numero) {
    // Usar el generateResponse directamente, NO processMessage
    if (!this.botHandler) {
      console.error("❌ BotHandler no disponible en SalesBot");
      return {
        respuesta: "Lo siento, tuve un problema al procesar tu mensaje.",
        tipo: "error",
      };
    }

    try {
      // Obtener contexto
      const contexto = await this.botHandler.getContexto(numero);

      // Agregar info del catálogo al contexto si existe
      let businessInfoOriginal = this.botHandler.config.business_info;
      if (this.catalogo) {
        const catalogoResumen = this.generarResumenCatalogo();
        this.botHandler.config.business_info += `\n\n📋 CATÁLOGO ACTUALIZADO:\n${catalogoResumen}`;
      }

      // Generar respuesta con IA directamente (sin pasar por processMessage)
      const respuestaIA = await this.botHandler.generateResponse(
        mensaje,
        contexto
      );

      // Restaurar business_info original
      this.botHandler.config.business_info = businessInfoOriginal;

      // Guardar conversación
      await this.botHandler.saveConversation(numero, mensaje, respuestaIA);

      return {
        respuesta: respuestaIA.content,
        tipo: "bot",
        tokens: respuestaIA.tokens,
        tiempo: respuestaIA.tiempo,
      };
    } catch (error) {
      console.error("Error en usarOpenAI:", error);
      return {
        respuesta: "Lo siento, tuve un problema al procesar tu mensaje.",
        tipo: "error",
      };
    }
  }

  generarResumenCatalogo() {
    if (!this.catalogo) return "";

    let resumen = "\n🛍️ PRODUCTOS DISPONIBLES:\n\n";

    // Agrupar por categoría
    const categorias = {};
    this.catalogo.productos.forEach((prod) => {
      if (!categorias[prod.categoria]) {
        categorias[prod.categoria] = [];
      }
      categorias[prod.categoria].push(prod);
    });

    for (const [categoria, productos] of Object.entries(categorias)) {
      resumen += `**${categoria.toUpperCase()}**\n`;
      productos.forEach((prod) => {
        const disponible = prod.disponible ? "✅" : "❌";
        resumen += `${disponible} ${prod.producto} - S/ ${prod.precio.toFixed(
          2
        )}\n`;
      });
      resumen += "\n";
    }

    // Agregar promociones si existen
    if (this.catalogo.promociones && this.catalogo.promociones.length > 0) {
      resumen += "\n🏷️ PROMOCIONES ACTIVAS:\n";
      this.catalogo.promociones.forEach((promo) => {
        resumen += `🎉 ${promo.producto} - ${promo.tipo}: ${promo.descripcion}\n`;
        resumen += `   Precio especial: S/ ${promo.precio_promo.toFixed(2)}\n`;
      });
      resumen += "\n";
    }

    // Agregar info de delivery si existe
    if (this.catalogo.delivery && this.catalogo.delivery.zonas) {
      resumen += "\n🚚 DELIVERY:\n";
      this.catalogo.delivery.zonas.forEach((zona) => {
        resumen += `📍 ${zona.zona}: S/ ${zona.costo.toFixed(2)} (${
          zona.tiempo
        })\n`;
      });

      if (this.catalogo.delivery.gratis_desde) {
        resumen += `✅ GRATIS desde S/ ${this.catalogo.delivery.gratis_desde.toFixed(
          2
        )}\n`;
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
      respuesta:
        `✅ Perfecto. ¿Cómo prefieres recibir tu pedido?\n\n` +
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
        respuesta:
          "🏠 Por favor, escribe tu dirección completa para el delivery:",
        tipo: "solicitar_direccion",
      };
    } else if (
      mensajeLower.includes("tienda") ||
      mensajeLower.includes("recoger") ||
      mensajeLower.includes("2")
    ) {
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

    if (
      this.catalogo &&
      this.catalogo.delivery &&
      this.catalogo.delivery.zonas
    ) {
      this.catalogo.delivery.zonas.forEach((zona) => {
        if (direccionLower.includes(zona.zona.toLowerCase())) {
          costoDelivery = zona.costo;
        }
      });
    }

    if (
      this.catalogo &&
      this.catalogo.delivery.gratis_desde &&
      venta.total >= this.catalogo.delivery.gratis_desde
    ) {
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

      // Notificar venta si está configurado
      const config = this.botHandler?.config;
      if (
        config?.notificar_ventas &&
        config?.numeros_notificacion &&
        this.botHandler?.whatsappClient
      ) {
        try {
          const numeros = JSON.parse(config.numeros_notificacion);

          let notificacion = `🎉 *NUEVA VENTA #${ventaId}*\n\n`;
          notificacion += `📱 Cliente: ${numero.replace("@c.us", "")}\n`;
          notificacion += `📦 *Productos:*\n`;
          venta.productos.forEach((item) => {
            notificacion += `  • ${item.producto} x${item.cantidad}\n`;
          });
          notificacion += `\n💰 *TOTAL: S/ ${(
            venta.total_con_delivery || venta.total
          ).toFixed(2)}*\n`;

          if (venta.tipo_entrega === "delivery") {
            notificacion += `📍 Delivery: ${venta.direccion_entrega}\n`;
          } else {
            notificacion += `📍 Recoger en tienda\n`;
          }

          notificacion += `\n⏰ ${new Date().toLocaleTimeString("es-PE", {
            hour: "2-digit",
            minute: "2-digit",
          })}`;
          notificacion += `\n\n💬 Contactar: https://wa.me/${numero.replace(
            "@c.us",
            ""
          )}`;

          for (const numeroNotificar of numeros) {
            await this.botHandler.whatsappClient.client.sendText(
              numeroNotificar.includes("@")
                ? numeroNotificar
                : `${numeroNotificar}@c.us`,
              notificacion
            );
            console.log(
              `📢 Notificación de venta enviada a ${numeroNotificar}`
            );
          }
        } catch (error) {
          console.error("Error enviando notificación de venta:", error);
        }
      }

      // Respuesta al cliente
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
        respuesta:
          "Hubo un error procesando tu pedido. Por favor, intenta nuevamente.",
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
