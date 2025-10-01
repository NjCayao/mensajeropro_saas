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
    this.botHandler = botHandler;
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
    let venta = this.ventas.get(numero);

    // Si hay venta en proceso, continuar con el flujo
    if (venta) {
      return await this.continuarFlujoVenta(mensaje, numero, venta);
    }

    // NUEVO: Analizar intención con IA
    const intencion = await this.analizarIntencion(mensaje);

    switch (intencion.tipo) {
      case "COMPRAR":
        return await this.iniciarVenta(mensaje, numero, intencion);
      case "MENU":
        return await this.mostrarMenu(numero);
      case "PRECIO":
        return await this.consultarPrecio(mensaje, numero);
      case "DELIVERY":
        return await this.infoDelivery(numero);
      default:
        return await this.respuestaIA(mensaje, numero);
    }
  }

  async analizarIntencion(mensaje) {
    try {
      const prompt = `Analiza este mensaje y responde con un JSON:
      {
        "tipo": "COMPRAR|MENU|PRECIO|DELIVERY|OTRO",
        "productos": [] // si tipo es COMPRAR, lista los productos mencionados
      }
      
      Mensaje: "${mensaje}"
      
      IMPORTANTE: 
      - COMPRAR: si menciona productos específicos para ordenar
      - MENU: si pide ver carta/menú/opciones
      - PRECIO: si pregunta cuánto cuesta algo
      - DELIVERY: si pregunta sobre envío/zonas
      - OTRO: cualquier otra cosa`;

      const respuesta = await this.botHandler.generateResponse(prompt, []);

      try {
        return JSON.parse(respuesta.content);
      } catch {
        return { tipo: "OTRO", productos: [] };
      }
    } catch (error) {
      return { tipo: "OTRO", productos: [] };
    }
  }

  async iniciarVenta(mensaje, numero, intencion) {
    // Buscar productos en el catálogo usando IA
    const productosEncontrados = await this.buscarProductosIA(
      intencion.productos
    );

    if (productosEncontrados.length === 0) {
      return {
        respuesta: "No encontré esos productos. ¿Qué te gustaría ordenar?",
        tipo: "productos_no_encontrados",
      };
    }

    // Crear venta
    const venta = {
      productos: productosEncontrados,
      total: this.calcularTotal(productosEncontrados),
      estado: "esperando_confirmacion",
    };
    this.ventas.set(numero, venta);

    // Respuesta corta y natural
    let respuesta = "📦 ";
    productosEncontrados.forEach((p) => {
      respuesta += `${p.producto} `;
    });
    respuesta += `\n💰 S/${venta.total}\n¿Confirmas?`;

    return {
      respuesta: respuesta,
      tipo: "confirmacion_pedido",
    };
  }

  async buscarProductosIA(productosMencionados) {
    if (!this.catalogo) return [];

    const encontrados = [];

    for (const productoMencionado of productosMencionados) {
      // Usar IA para hacer match fuzzy
      const prompt = `Del siguiente catálogo, encuentra el producto que mejor coincida con "${productoMencionado}":
      ${JSON.stringify(this.catalogo.productos.map((p) => p.producto))}
      Responde SOLO con el nombre exacto del producto o "NO_ENCONTRADO"`;

      const respuesta = await this.botHandler.generateResponse(prompt, []);
      const productoExacto = respuesta.content.trim();

      if (productoExacto !== "NO_ENCONTRADO") {
        const producto = this.catalogo.productos.find(
          (p) => p.producto === productoExacto
        );
        if (producto) {
          encontrados.push({
            producto: producto.producto,
            precio: producto.precio,
            cantidad: 1,
          });
        }
      }
    }

    return encontrados;
  }

  async continuarFlujoVenta(mensaje, numero, venta) {
    const mensajeLower = mensaje.toLowerCase();

    switch (venta.estado) {
      case "esperando_confirmacion":
        if (
          mensajeLower.includes("si") ||
          mensajeLower.includes("sí") ||
          mensajeLower.includes("ok") ||
          mensajeLower.includes("dale")
        ) {
          return await this.mostrarMetodosPago(numero, venta);
        } else if (mensajeLower.includes("no")) {
          this.ventas.delete(numero);
          return {
            respuesta: "Cancelado. ¿Qué más necesitas?",
            tipo: "pedido_cancelado",
          };
        }
        break;

      case "esperando_pago":
        if (
          mensajeLower.includes("pag") ||
          mensajeLower.includes("listo") ||
          mensajeLower.includes("ya")
        ) {
          return await this.confirmarPedido(numero, venta);
        }
        break;

      case "esperando_entrega":
        if (mensajeLower.includes("delivery") || mensajeLower.includes("1")) {
          venta.tipo_entrega = "delivery";
          venta.estado = "esperando_direccion";
          this.ventas.set(numero, venta);
          return {
            respuesta: "📍 Tu dirección:",
            tipo: "solicitar_direccion",
          };
        } else if (
          mensajeLower.includes("recog") ||
          mensajeLower.includes("2")
        ) {
          venta.tipo_entrega = "tienda";
          return await this.finalizarPedido(numero, venta);
        }
        break;

      case "esperando_direccion":
        venta.direccion_entrega = mensaje;
        venta.costo_delivery = 5; // Simplificado
        venta.total_con_delivery = venta.total + 5;
        return await this.finalizarPedido(numero, venta);
    }

    // Si no entendimos, preguntar de nuevo
    return {
      respuesta: "No entendí. ¿Puedes repetir?",
      tipo: "no_entendido",
    };
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

    const [config] = await db
      .getPool()
      .execute(
        "SELECT cuentas_pago FROM configuracion_negocio WHERE empresa_id = ?",
        [this.empresaId]
      );

    let respuesta = "💳 PAGAR:\n";

    if (config[0]?.cuentas_pago) {
      const datos = JSON.parse(config[0].cuentas_pago);
      // Mostrar solo los primeros 2 métodos para ser breve
      datos.metodos.slice(0, 2).forEach((m) => {
        respuesta += `${m.tipo}: ${m.dato}\n`;
      });
    }

    respuesta += `Total: S/${venta.total}\nAvísame cuando pagues`;

    return { respuesta, tipo: "metodos_pago" };
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
      respuesta: "¿Delivery(1) o Recoges(2)?",
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
    const [result] = await db.getPool().execute(
      `INSERT INTO ventas_bot 
       (empresa_id, numero_cliente, productos_cotizados, total_cotizado, 
        estado, tipo_entrega, direccion_entrega)
       VALUES (?, ?, ?, ?, 'confirmado', ?, ?)`,
      [
        this.empresaId,
        numero,
        JSON.stringify(venta.productos),
        venta.total_con_delivery || venta.total,
        venta.tipo_entrega,
        venta.direccion_entrega || null,
      ]
    );

    this.ventas.delete(numero);

    const total = venta.total_con_delivery || venta.total;
    return {
      respuesta: `✅ Pedido #${result.insertId}\nTotal: S/${total}\nTiempo: 30min`,
      tipo: "pedido_confirmado",
    };
  }

  async mostrarMenu(numero) {
    if (!this.catalogo) {
      return {
        respuesta: "Un momento, cargando menú...",
        tipo: "menu_cargando",
      };
    }

    // Agrupar por categorías y mostrar breve
    let respuesta = "🍽️ MENÚ:\n";
    const categorias = {};

    this.catalogo.productos.slice(0, 5).forEach((p) => {
      if (!categorias[p.categoria]) {
        categorias[p.categoria] = [];
      }
      categorias[p.categoria].push(`${p.producto} S/${p.precio}`);
    });

    for (const [cat, prods] of Object.entries(categorias)) {
      respuesta += `\n${cat}:\n`;
      prods.forEach((p) => (respuesta += `• ${p}\n`));
    }

    respuesta += "\n¿Qué te gustaría?";

    return { respuesta, tipo: "menu" };
  }


  async respuestaIA(mensaje, numero) {
    // Respuesta general con IA pero corta
    const contexto = await this.botHandler.getContexto(numero);
    
    // Agregar instrucción de brevedad al prompt
    const promptOriginal = this.botHandler.config.system_prompt;
    this.botHandler.config.system_prompt = promptOriginal + 
      "\nIMPORTANTE: Responde en máximo 2 líneas. Sé breve y directo.";
    
    const respuestaIA = await this.botHandler.generateResponse(mensaje, contexto);
    
    // Restaurar prompt original
    this.botHandler.config.system_prompt = promptOriginal;
    
    await this.botHandler.saveConversation(numero, mensaje, respuestaIA);
    
    return {
      respuesta: respuestaIA.content,
      tipo: "bot"
    };
  }

  calcularTotal(productos) {
    return productos.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
  }
}

  // Método auxiliar para notificar venta
  async notificarVenta(ventaId, venta, numero, simbolo) {
    try {
      const config = this.botHandler.config;
      const numeros = JSON.parse(config.numeros_notificacion || "[]");

      if (numeros.length === 0) return;

      let notificacion = `🎉 *NUEVA VENTA #${ventaId}*\n\n`;
      notificacion += `📱 Cliente: ${numero.replace("@c.us", "")}\n`;
      notificacion += `💳 Método pago: ${
        venta.metodo_pago || "Por confirmar"
      }\n\n`;

      notificacion += `📦 *PRODUCTOS:*\n`;
      venta.productos.forEach((item) => {
        notificacion += `• ${item.producto} x${item.cantidad}\n`;
      });

      notificacion += `\n💰 *TOTAL: ${simbolo} ${(
        venta.total_con_delivery || venta.total
      ).toFixed(2)}*\n`;

      if (venta.tipo_entrega === "delivery") {
        notificacion += `📍 Delivery a: ${venta.direccion_entrega}\n`;
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

      // Enviar a cada número configurado
      for (const numeroNotificar of numeros) {
        await this.botHandler.whatsappClient.client.sendText(
          numeroNotificar.includes("@")
            ? numeroNotificar
            : `${numeroNotificar}@c.us`,
          notificacion
        );
        console.log(`📢 Notificación de venta enviada a ${numeroNotificar}`);
      }
    } catch (error) {
      console.error("Error enviando notificación de venta:", error);
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
