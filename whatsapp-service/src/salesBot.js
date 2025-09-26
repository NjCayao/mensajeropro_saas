// whatsapp-service/src/salesBot.js
const db = require("./database");
const path = require("path");
const fs = require("fs").promises;

class SalesBot {
  constructor(empresaId) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.ventas = new Map(); // Almacenar ventas en proceso
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
      } else {
        console.log("❌ No hay catálogo cargado para esta empresa");
      }
    } catch (error) {
      console.error("Error cargando catálogo:", error);
    }
  }

  async procesarMensajeVenta(mensaje, numero) {
    if (!this.catalogo) {
      return {
        respuesta: "Lo siento, no tengo información de productos disponible en este momento. Por favor, contacta directamente al local.",
        tipo: "sin_catalogo"
      };
    }

    // Obtener o crear sesión de venta
    let venta = this.ventas.get(numero) || {
      productos: [],
      total: 0,
      estado: "inicial"
    };

    const mensajeLower = mensaje.toLowerCase();

    // Detectar intención
    if (mensajeLower.includes("catálogo") || mensajeLower.includes("catalogo") || 
        mensajeLower.includes("carta") || mensajeLower.includes("menu")) {
      return await this.enviarCatalogo(numero);
    }

    if (mensajeLower.includes("precio") || mensajeLower.includes("cuánto") || 
        mensajeLower.includes("cuanto") || mensajeLower.includes("cuesta")) {
      return await this.consultarPrecio(mensaje);
    }

    if (mensajeLower.includes("promocion") || mensajeLower.includes("oferta") || 
        mensajeLower.includes("descuento")) {
      return this.mostrarPromociones();
    }

    if (mensajeLower.includes("delivery") || mensajeLower.includes("entrega") || 
        mensajeLower.includes("envío") || mensajeLower.includes("envio")) {
      return this.informarDelivery();
    }

    if (mensajeLower.includes("quiero") || mensajeLower.includes("pedir") || 
        mensajeLower.includes("ordenar") || mensajeLower.includes("comprar")) {
      return await this.procesarPedido(mensaje, numero, venta);
    }

    if (venta.estado === "esperando_confirmacion" && 
        (mensajeLower.includes("sí") || mensajeLower.includes("si") || 
         mensajeLower.includes("confirmo") || mensajeLower.includes("correcto"))) {
      return await this.confirmarPedido(numero, venta);
    }

    if (venta.estado === "esperando_entrega" && 
        (mensajeLower.includes("delivery") || mensajeLower.includes("tienda") || 
         mensajeLower.includes("recoger"))) {
      return await this.procesarTipoEntrega(mensaje, numero, venta);
    }

    if (venta.estado === "esperando_direccion") {
      return await this.procesarDireccion(mensaje, numero, venta);
    }

    // Si no entendemos la intención, sugerir opciones
    return {
      respuesta: `🤖 Soy el asistente de ventas. Puedo ayudarte con:\n\n` +
                 `📋 Ver el *catálogo*\n` +
                 `💰 Consultar *precios*\n` +
                 `🏷️ Ver *promociones*\n` +
                 `🚚 Información de *delivery*\n` +
                 `🛒 *Realizar un pedido*\n\n` +
                 `¿Qué deseas hacer?`,
      tipo: "menu_opciones"
    };
  }

  async enviarCatalogo(numero) {
    if (this.catalogoPdf && await this.fileExists(this.catalogoPdf)) {
      return {
        respuesta: "📋 Te envío nuestro catálogo completo:",
        tipo: "catalogo_pdf",
        archivo: this.catalogoPdf
      };
    } else {
      // Enviar lista de productos en texto
      let respuesta = "📋 *NUESTRO CATÁLOGO*\n\n";
      
      // Agrupar por categoría
      const categorias = {};
      this.catalogo.productos.forEach(prod => {
        if (!categorias[prod.categoria]) {
          categorias[prod.categoria] = [];
        }
        categorias[prod.categoria].push(prod);
      });

      for (const [categoria, productos] of Object.entries(categorias)) {
        respuesta += `*${categoria.toUpperCase()}*\n`;
        productos.forEach(prod => {
          respuesta += `• ${prod.producto} - S/ ${prod.precio.toFixed(2)}\n`;
        });
        respuesta += "\n";
      }

      return {
        respuesta: respuesta,
        tipo: "catalogo_texto"
      };
    }
  }

  async consultarPrecio(mensaje) {
    // Buscar producto mencionado
    const palabras = mensaje.toLowerCase().split(' ');
    let productoEncontrado = null;
    let mejorCoincidencia = 0;

    this.catalogo.productos.forEach(prod => {
      const nombreLower = prod.producto.toLowerCase();
      let coincidencias = 0;
      
      palabras.forEach(palabra => {
        if (palabra.length > 3 && nombreLower.includes(palabra)) {
          coincidencias++;
        }
      });

      if (coincidencias > mejorCoincidencia) {
        mejorCoincidencia = coincidencias;
        productoEncontrado = prod;
      }
    });

    if (productoEncontrado) {
      // Verificar si tiene promoción
      const promo = this.catalogo.promociones.find(p => 
        p.producto.toLowerCase() === productoEncontrado.producto.toLowerCase()
      );

      let respuesta = `💰 *${productoEncontrado.producto}*\n`;
      respuesta += `Precio: S/ ${productoEncontrado.precio.toFixed(2)}`;

      if (promo) {
        respuesta += `\n\n🏷️ *¡En promoción!*\n`;
        respuesta += `${promo.tipo}: ${promo.descripcion}\n`;
        respuesta += `Precio promo: S/ ${promo.precio_promo.toFixed(2)}`;
      }

      return {
        respuesta: respuesta,
        tipo: "precio_producto"
      };
    }

    return {
      respuesta: "No encontré ese producto. ¿Podrías ser más específico o pedirme el catálogo completo?",
      tipo: "producto_no_encontrado"
    };
  }

  mostrarPromociones() {
    if (!this.catalogo.promociones || this.catalogo.promociones.length === 0) {
      return {
        respuesta: "Por el momento no tenemos promociones activas.",
        tipo: "sin_promociones"
      };
    }

    let respuesta = "🏷️ *PROMOCIONES ACTIVAS*\n\n";
    
    this.catalogo.promociones.forEach(promo => {
      respuesta += `🎉 *${promo.producto}*\n`;
      respuesta += `${promo.tipo}: ${promo.descripcion}\n`;
      respuesta += `Precio especial: S/ ${promo.precio_promo.toFixed(2)}\n\n`;
    });

    return {
      respuesta: respuesta,
      tipo: "lista_promociones"
    };
  }

  informarDelivery() {
    if (!this.catalogo.delivery || !this.catalogo.delivery.zonas) {
      return {
        respuesta: "Por favor consulta las zonas de delivery llamando al local.",
        tipo: "sin_delivery"
      };
    }

    let respuesta = "🚚 *INFORMACIÓN DE DELIVERY*\n\n";
    
    this.catalogo.delivery.zonas.forEach(zona => {
      respuesta += `📍 *${zona.zona}*\n`;
      respuesta += `   Costo: S/ ${zona.costo.toFixed(2)}\n`;
      respuesta += `   Tiempo: ${zona.tiempo}\n\n`;
    });

    if (this.catalogo.delivery.gratis_desde) {
      respuesta += `✅ *Delivery GRATIS en compras desde S/ ${this.catalogo.delivery.gratis_desde.toFixed(2)}*`;
    }

    return {
      respuesta: respuesta,
      tipo: "info_delivery"
    };
  }

  async procesarPedido(mensaje, numero, venta) {
    // Buscar productos mencionados
    const productosEncontrados = [];
    const mensajeLower = mensaje.toLowerCase();

    this.catalogo.productos.forEach(prod => {
      if (mensajeLower.includes(prod.producto.toLowerCase()) || 
          mensajeLower.includes(prod.categoria.toLowerCase())) {
        productosEncontrados.push(prod);
      }
    });

    if (productosEncontrados.length === 0) {
      return {
        respuesta: "No pude identificar qué productos deseas. Por favor, sé más específico o pide el catálogo.",
        tipo: "productos_no_identificados"
      };
    }

    // Agregar productos a la venta
    productosEncontrados.forEach(prod => {
      venta.productos.push({
        ...prod,
        cantidad: 1 // Por defecto 1 unidad
      });
      venta.total += prod.precio;
    });

    venta.estado = "esperando_confirmacion";
    this.ventas.set(numero, venta);

    // Generar resumen
    let respuesta = "🛒 *RESUMEN DE TU PEDIDO*\n\n";
    venta.productos.forEach(item => {
      respuesta += `• ${item.producto} x${item.cantidad} - S/ ${(item.precio * item.cantidad).toFixed(2)}\n`;
    });
    respuesta += `\n*TOTAL: S/ ${venta.total.toFixed(2)}*\n\n`;
    respuesta += `¿Es correcto? Responde *SÍ* para continuar o dime qué cambiar.`;

    return {
      respuesta: respuesta,
      tipo: "resumen_pedido"
    };
  }

  async confirmarPedido(numero, venta) {
    venta.estado = "esperando_entrega";
    this.ventas.set(numero, venta);

    return {
      respuesta: `✅ Perfecto. ¿Cómo prefieres recibir tu pedido?\n\n` +
                 `1️⃣ *Delivery* a domicilio\n` +
                 `2️⃣ *Recoger* en tienda\n\n` +
                 `Por favor, elige una opción.`,
      tipo: "tipo_entrega"
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
        tipo: "solicitar_direccion"
      };
    } else if (mensajeLower.includes("tienda") || mensajeLower.includes("recoger") || mensajeLower.includes("2")) {
      venta.tipo_entrega = "tienda";
      return await this.finalizarPedido(numero, venta);
    }

    return {
      respuesta: "Por favor, elige:\n1️⃣ Delivery\n2️⃣ Recoger en tienda",
      tipo: "tipo_entrega_invalido"
    };
  }

  async procesarDireccion(mensaje, numero, venta) {
    venta.direccion_entrega = mensaje;
    
    // Buscar zona de delivery
    let costoDelivery = 5; // Por defecto
    const direccionLower = mensaje.toLowerCase();
    
    if (this.catalogo.delivery && this.catalogo.delivery.zonas) {
      this.catalogo.delivery.zonas.forEach(zona => {
        if (direccionLower.includes(zona.zona.toLowerCase())) {
          costoDelivery = zona.costo;
        }
      });
    }

    // Verificar si aplica delivery gratis
    if (this.catalogo.delivery.gratis_desde && venta.total >= this.catalogo.delivery.gratis_desde) {
      costoDelivery = 0;
    }

    venta.costo_delivery = costoDelivery;
    venta.total_con_delivery = venta.total + costoDelivery;

    return await this.finalizarPedido(numero, venta);
  }

  async finalizarPedido(numero, venta) {
    // Guardar en base de datos
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
          venta.direccion_entrega || null
        ]
      );

      const ventaId = result.insertId;

      // Generar mensaje final
      let respuesta = `✅ *PEDIDO CONFIRMADO*\n`;
      respuesta += `Número de pedido: #${ventaId}\n\n`;
      
      respuesta += `📦 *Productos:*\n`;
      venta.productos.forEach(item => {
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

      respuesta += `\n\n💳 *Métodos de pago disponibles:*\n`;
      respuesta += `• Efectivo\n• Yape\n• Plin\n• Tarjeta\n\n`;
      respuesta += `⏱️ Tiempo estimado: 30-45 minutos\n\n`;
      respuesta += `¡Gracias por tu pedido! Te contactaremos pronto para confirmar.`;

      // Limpiar sesión de venta
      this.ventas.delete(numero);

      return {
        respuesta: respuesta,
        tipo: "pedido_confirmado",
        ventaId: ventaId
      };

    } catch (error) {
      console.error("Error guardando venta:", error);
      return {
        respuesta: "Hubo un error procesando tu pedido. Por favor, intenta nuevamente.",
        tipo: "error_pedido"
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