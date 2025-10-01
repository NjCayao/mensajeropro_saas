// whatsapp-service/src/salesBot.js - CON FUNCTION CALLING
const db = require("./database");
const path = require("path");

class SalesBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.catalogoPdf = null;
    this.moneda = { codigo: 'PEN', simbolo: 'S/' };
    this.nombreNegocio = 'nuestro negocio';
    this.descripcionNegocio = '';
    this.telefonoNegocio = '';
    this.direccionNegocio = '';
    this.carrito = new Map();
    this.botHandler = botHandler;
    this.maxTokens = 100;
    
    // Limpiar carritos cada 5 minutos
    setInterval(() => this.limpiarCarritosInactivos(), 5 * 60 * 1000);
  }

  // ============================================
  // FUNCIONES QUE LA IA PUEDE LLAMAR
  // ============================================
  
  getFunctions() {
    return [
      {
        name: "agregar_al_carrito",
        description: "Agrega productos al carrito del cliente cuando expresan intención de compra",
        parameters: {
          type: "object",
          properties: {
            productos: {
              type: "array",
              items: { type: "string" },
              description: "Nombres exactos de los productos a agregar"
            },
            cantidades: {
              type: "array",
              items: { type: "integer" },
              description: "Cantidad de cada producto (mismo orden)"
            }
          },
          required: ["productos", "cantidades"]
        }
      },
      {
        name: "ver_carrito",
        description: "Muestra el contenido actual del carrito del cliente"
      },
      {
        name: "confirmar_pedido",
        description: "Cliente confirma que quiere proceder con el pedido actual"
      },
      {
        name: "cancelar_carrito",
        description: "Cliente cancela el pedido completo"
      },
      {
        name: "enviar_catalogo",
        description: "Cliente solicita ver el catálogo o menú completo"
      }
    ];
  }

  // ============================================
  // PROCESAMIENTO PRINCIPAL
  // ============================================

  async procesarMensajeVenta(mensaje, numero) {
    const carrito = this.carrito.get(numero);
    
    // Actualizar última actividad
    if (carrito) {
      carrito.ultimaActividad = Date.now();
    }

    // Manejar estados estructurados (pago, entrega, dirección)
    if (carrito?.estado === 'esperando_pago') {
      return await this.manejarPago(mensaje, numero, carrito);
    }
    if (carrito?.estado === 'esperando_entrega') {
      return await this.manejarEntrega(mensaje, numero, carrito);
    }
    if (carrito?.estado === 'esperando_direccion') {
      return await this.manejarDireccion(mensaje, numero, carrito);
    }

    // Usar IA con Function Calling
    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  async respuestaConFunctionCalling(mensaje, numero) {
    if (!this.botHandler) {
      return { respuesta: '¿En qué puedo ayudarte?', tipo: 'bot' };
    }

    try {
      const contexto = await this.botHandler.getContexto(numero);
      const carrito = this.carrito.get(numero);
      
      // Verificar si recién finalizó una compra (últimos 2 mensajes)
      const ultimasRespuestas = contexto.slice(-2).map(c => c.respuesta_bot).join(' ');
      const acabaDeComprar = ultimasRespuestas.includes('Pedido #') && ultimasRespuestas.includes('confirmado');
      
      // Si acaba de comprar y dice algo simple (ok, gracias, etc), no llamar funciones
      if (acabaDeComprar && mensaje.toLowerCase().match(/^(ok|gracias|thank|bien|perfecto|listo|vale)$/)) {
        return {
          respuesta: '¡Gracias a ti! Si necesitas algo más, aquí estoy.😊',
          tipo: 'despedida_post_venta'
        };
      }
      
      // Construir descripción del negocio
      const descripcionNegocio = this.descripcionNegocio || 
        `Somos ${this.nombreNegocio}, tu mejor opción para ${this.catalogo?.productos[0]?.categoria || 'productos de calidad'}.`;
      
      // Construir prompt dinámico
      let systemPrompt = `Eres un asistente de ventas conversacional de ${this.nombreNegocio}.

SOBRE EL NEGOCIO:
${descripcionNegocio}
${this.direccionNegocio ? `Ubicación: ${this.direccionNegocio}` : ''}
${this.telefonoNegocio ? `Teléfono: ${this.telefonoNegocio}` : ''}

PRODUCTOS DISPONIBLES:
${this.generarListaProductos()}

CARRITO ACTUAL:
${carrito ? carrito.productos.map(p => `- ${p.producto} x${p.cantidad}`).join('\n') : 'Vacío'}

${acabaDeComprar ? '\n⚠️ IMPORTANTE: El cliente ACABA DE COMPLETAR Y PAGAR una compra. NO menciones pago ni confirmes nada más. Solo responde amablemente y ofrece ayuda para nuevo pedido si lo desea.\n' : ''}

REGLAS:
- Sé natural y conversacional (no robótico)
- Si el cliente quiere comprar, usa la función agregar_al_carrito
- Si confirma el pedido (dice "sí", "confirmo", "listo"), usa confirmar_pedido
- Si pide el catálogo, usa enviar_catalogo
- Si acaba de comprar, NO uses funciones relacionadas a pago o confirmación
- Máximo ${this.maxTokens} caracteres en respuestas`;

      const messages = [
        { role: "system", content: systemPrompt }
      ];

      // Agregar contexto (solo últimos 3 para no sobrecargar)
      contexto.slice(-3).forEach(c => {
        messages.push({ role: "user", content: c.mensaje_cliente });
        if (c.respuesta_bot) {
          messages.push({ role: "assistant", content: c.respuesta_bot });
        }
      });

      // Mensaje actual
      messages.push({ role: "user", content: mensaje });

      // Llamar a OpenAI con functions
      const response = await this.callOpenAIWithFunctions(messages);

      // Si llamó una función, ejecutarla
      if (response.function_call) {
        const result = await this.ejecutarFuncion(
          response.function_call.name,
          JSON.parse(response.function_call.arguments),
          numero
        );

        // Guardar en conversación
        await this.botHandler.saveConversation(numero, mensaje, {
          content: result.respuesta,
          tokens: response.usage?.total_tokens || 0,
          tiempo: 0
        });

        return result;
      }

      // Si no llamó función, solo respondió
      await this.botHandler.saveConversation(numero, mensaje, {
        content: response.content,
        tokens: response.usage?.total_tokens || 0,
        tiempo: 0
      });

      return {
        respuesta: response.content,
        tipo: 'bot'
      };

    } catch (error) {
      console.error('Error en Function Calling:', error);
      return { respuesta: '¿Qué necesitas?', tipo: 'error' };
    }
  }

  async callOpenAIWithFunctions(messages) {
    const axios = require('axios');
    
    const response = await axios.post(
      'https://api.openai.com/v1/chat/completions',
      {
        model: this.botHandler.globalConfig.openai_modelo || 'gpt-4',
        messages: messages,
        functions: this.getFunctions(),
        function_call: "auto",
        temperature: 0.7,
        max_tokens: this.maxTokens
      },
      {
        headers: {
          'Authorization': `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
          'Content-Type': 'application/json'
        }
      }
    );

    const choice = response.data.choices[0];
    
    return {
      content: choice.message?.content,
      function_call: choice.message?.function_call,
      usage: response.data.usage
    };
  }

  // ============================================
  // EJECUTAR FUNCIONES
  // ============================================

  async ejecutarFuncion(nombre, args, numero) {
    console.log(`🔧 Ejecutando función: ${nombre}`, args);

    switch(nombre) {
      case 'agregar_al_carrito':
        return await this.funcionAgregarCarrito(numero, args.productos, args.cantidades);
      
      case 'ver_carrito':
        return await this.funcionVerCarrito(numero);
      
      case 'confirmar_pedido':
        return await this.funcionConfirmarPedido(numero);
      
      case 'cancelar_carrito':
        return await this.funcionCancelarCarrito(numero);
      
      case 'enviar_catalogo':
        return await this.funcionEnviarCatalogo(numero);
      
      default:
        return { respuesta: '¿En qué puedo ayudarte?', tipo: 'error' };
    }
  }

  async funcionAgregarCarrito(numero, productos, cantidades) {
    let carrito = this.carrito.get(numero) || {
      productos: [],
      total: 0,
      estado: 'agregando',
      ultimaActividad: Date.now()
    };

    // Buscar productos en catálogo
    const productosAgregados = [];
    
    for (let i = 0; i < productos.length; i++) {
      const nombreBuscado = productos[i].toLowerCase();
      const producto = this.catalogo.productos.find(p => 
        p.producto.toLowerCase().includes(nombreBuscado) ||
        nombreBuscado.includes(p.producto.toLowerCase())
      );

      if (producto) {
        const existe = carrito.productos.find(x => x.producto === producto.producto);
        if (existe) {
          existe.cantidad += cantidades[i];
        } else {
          carrito.productos.push({
            producto: producto.producto,
            precio: producto.precio,
            cantidad: cantidades[i]
          });
        }
        productosAgregados.push(`${producto.producto} x${cantidades[i]}`);
      }
    }

    carrito.total = carrito.productos.reduce((sum, p) => 
      sum + (p.precio * p.cantidad), 0
    );
    
    this.carrito.set(numero, carrito);

    let msg = `✅ Agregado: ${productosAgregados.join(', ')}\n\n`;
    msg += `💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}\n\n`;
    msg += `¿Algo más o confirmamos?`;

    return { respuesta: msg, tipo: 'producto_agregado' };
  }

  async funcionVerCarrito(numero) {
    const carrito = this.carrito.get(numero);
    
    if (!carrito || carrito.productos.length === 0) {
      return { respuesta: 'Tu carrito está vacío. ¿Qué te gustaría?', tipo: 'carrito_vacio' };
    }

    let msg = '🛒 *Tu carrito:*\n\n';
    carrito.productos.forEach(p => {
      msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(p.precio * p.cantidad).toFixed(2)}\n`;
    });
    msg += `\n💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}`;

    return { respuesta: msg, tipo: 'ver_carrito' };
  }

  async funcionConfirmarPedido(numero) {
    const carrito = this.carrito.get(numero);
    
    if (!carrito || carrito.productos.length === 0) {
      return { respuesta: 'No tienes nada en el carrito. ¿Qué te gustaría?', tipo: 'carrito_vacio' };
    }

    // IMPORTANTE: Recalcular el total antes de confirmar
    carrito.total = carrito.productos.reduce((sum, p) => 
      sum + (p.precio * p.cantidad), 0
    );
    
    // Actualizar el carrito con el total correcto
    this.carrito.set(numero, carrito);

    console.log(`🛒 Confirmando pedido con ${carrito.productos.length} productos, total: ${carrito.total}`);

    return await this.solicitarPago(numero, carrito);
  }

  async funcionCancelarCarrito(numero) {
    this.carrito.delete(numero);
    return { respuesta: 'Ok, carrito cancelado. ¿Algo más?', tipo: 'cancelado' };
  }

  async funcionEnviarCatalogo(numero) {
    if (this.catalogoPdf) {
      let pdfPath = this.catalogoPdf;
      
      if (!path.isAbsolute(pdfPath)) {
        const projectRoot = path.resolve(__dirname, '../..');
        pdfPath = path.join(projectRoot, pdfPath);
      }
      
      const fs = require('fs');
      if (fs.existsSync(pdfPath)) {
        return {
          respuesta: "📋 Aquí está nuestro catálogo. ¿Qué te gustaría?",
          tipo: 'catalogo_pdf',
          archivo: pdfPath
        };
      }
    }
    
    return { 
      respuesta: await this.generarMenuTexto(), 
      tipo: 'menu' 
    };
  }

  // ============================================
  // FLUJO DE PAGO Y ENTREGA (sin cambios)
  // ============================================

  async solicitarPago(numero, carrito) {
    try {
      // Obtener carrito actualizado para asegurar que tenemos todos los productos
      const carritoActual = this.carrito.get(numero) || carrito;
      
      console.log(`💳 Generando resumen de pago para ${carritoActual.productos.length} productos`);
      carritoActual.productos.forEach(p => {
        console.log(`   - ${p.producto} x${p.cantidad} = ${(p.precio * p.cantidad).toFixed(2)}`);
      });
      
      let msg = '✅ *RESUMEN*\n\n';
      
      carritoActual.productos.forEach(p => {
        msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(p.precio * p.cantidad).toFixed(2)}\n`;
      });
      
      msg += `\n💰 *Total: ${this.moneda.simbolo}${carritoActual.total.toFixed(2)}*\n\n`;
      msg += '💳 *PAGAR POR:*\n\n';

      const [config] = await db.getPool().execute(
        "SELECT cuentas_pago FROM configuracion_negocio WHERE empresa_id = ?",
        [this.empresaId]
      );

      if (config[0]?.cuentas_pago) {
        const datos = JSON.parse(config[0].cuentas_pago);
        
        if (datos.metodos && datos.metodos.length > 0) {
          datos.metodos.forEach(m => {
            msg += `📱 *${m.tipo}*: ${m.dato}\n`;
            if (m.instruccion) msg += `   _${m.instruccion}_\n`;
          });
        }
      }

      msg += '\n💬 Avísame cuando pagues';

      carritoActual.estado = 'esperando_pago';
      this.carrito.set(numero, carritoActual);

      return { respuesta: msg, tipo: 'metodos_pago' };
    } catch (error) {
      console.error('Error en pago:', error);
      return { respuesta: 'Avísame cuando pagues', tipo: 'pago_simple' };
    }
  }

  async manejarPago(mensaje, numero, carrito) {
    const msgLower = mensaje.toLowerCase();

    if (msgLower.match(/\b(pagu[eé]|list[oa]|ya|hecho|transfer)\b/)) {
      carrito.estado = 'esperando_entrega';
      this.carrito.set(numero, carrito);
      
      return {
        respuesta: '¿Cómo lo prefieres?\n\n1️⃣ Delivery\n2️⃣ Recoger',
        tipo: 'tipo_entrega'
      };
    }

    return { respuesta: 'Avísame cuando pagues', tipo: 'esperando_pago' };
  }

  async manejarEntrega(mensaje, numero, carrito) {
    const msgLower = mensaje.toLowerCase();

    if (msgLower.match(/\b(delivery|envio|1)\b/)) {
      carrito.tipo_entrega = 'delivery';
      carrito.estado = 'esperando_direccion';
      this.carrito.set(numero, carrito);
      
      return { respuesta: '📍 ¿Tu dirección?', tipo: 'solicitar_direccion' };
    }

    if (msgLower.match(/\b(recog|tienda|2)\b/)) {
      carrito.tipo_entrega = 'tienda';
      return await this.finalizarVenta(numero, carrito);
    }

    return { respuesta: '1 = Delivery, 2 = Recoger', tipo: 'entrega' };
  }

  async manejarDireccion(mensaje, numero, carrito) {
    carrito.direccion = mensaje;
    return await this.finalizarVenta(numero, carrito);
  }

  async finalizarVenta(numero, carrito) {
    try {
      const [result] = await db.getPool().execute(
        `INSERT INTO ventas_bot 
         (empresa_id, numero_cliente, productos_cotizados, total_cotizado, 
          estado, tipo_entrega, direccion_entrega)
         VALUES (?, ?, ?, ?, 'confirmado', ?, ?)`,
        [
          this.empresaId,
          numero,
          JSON.stringify(carrito.productos),
          carrito.total,
          carrito.tipo_entrega,
          carrito.direccion || null
        ]
      );

      const ventaId = result.insertId;
      await this.notificarVenta(ventaId, carrito, numero);
      
      // Limpiar carrito
      this.carrito.delete(numero);

      let msg = `✅ *¡Pedido #${ventaId} confirmado!*\n\n`;
      msg += `💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}\n`;
      msg += `🕐 Estimado: 30-45 min\n`;
      
      if (carrito.tipo_entrega === 'delivery') {
        msg += `📍 Envío: ${carrito.direccion}\n`;
      } else {
        msg += `📍 Recoger en tienda\n`;
      }

      msg += `\n¡Gracias por tu compra! 😊`;

      return { respuesta: msg, tipo: 'venta_finalizada', ventaId };
    } catch (error) {
      console.error('Error finalizando venta:', error);
      return { respuesta: 'Error. Contáctanos.', tipo: 'error' };
    }
  }

  // ============================================
  // HELPERS
  // ============================================

  async loadCatalog() {
    try {
      const [rows] = await db.getPool().execute(
        "SELECT * FROM catalogo_bot WHERE empresa_id = ?", 
        [this.empresaId]
      );

      if (rows.length > 0) {
        if (rows[0].datos_json) {
          this.catalogo = JSON.parse(rows[0].datos_json);
        }
        this.catalogoPdf = rows[0].archivo_pdf;
      }

      // Cargar configuración del negocio
      const [negocio] = await db.getPool().execute(
        "SELECT nombre_negocio, descripcion, telefono, direccion, cuentas_pago FROM configuracion_negocio WHERE empresa_id = ?",
        [this.empresaId]
      );

      if (negocio[0]) {
        this.nombreNegocio = negocio[0].nombre_negocio || 'nuestro negocio';
        this.descripcionNegocio = negocio[0].descripcion || '';
        this.telefonoNegocio = negocio[0].telefono || '';
        this.direccionNegocio = negocio[0].direccion || '';
        
        if (negocio[0].cuentas_pago) {
          const datos = JSON.parse(negocio[0].cuentas_pago);
          if (datos.moneda && datos.simbolo) {
            this.moneda = { codigo: datos.moneda, simbolo: datos.simbolo };
          }
        }
      }

      const [tokenConfig] = await db.getPool().execute(
        "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_max_tokens'"
      );
      
      if (tokenConfig[0]?.valor) {
        this.maxTokens = parseInt(tokenConfig[0].valor);
      }

    } catch (error) {
      console.error("Error cargando catálogo:", error);
    }
  }

  limpiarCarritosInactivos() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000; // 10 minutos

    for (const [numero, carrito] of this.carrito.entries()) {
      if (ahora - carrito.ultimaActividad > timeout) {
        this.carrito.delete(numero);
        console.log(`🧹 Carrito de ${numero} limpiado`);
      }
    }
  }

  generarListaProductos() {
    if (!this.catalogo) return 'Sin productos';
    
    let lista = '';
    this.catalogo.productos.forEach(p => {
      lista += `- ${p.producto}: ${this.moneda.simbolo}${p.precio.toFixed(2)}\n`;
    });
    
    return lista;
  }

  async generarMenuTexto() {
    if (!this.catalogo) return '📋 Menú no disponible';

    let msg = '📋 *MENÚ*\n\n';
    
    const categorias = {};
    this.catalogo.productos.forEach(p => {
      if (!categorias[p.categoria]) categorias[p.categoria] = [];
      categorias[p.categoria].push(p);
    });

    for (const [cat, prods] of Object.entries(categorias)) {
      msg += `*${cat}*\n`;
      prods.forEach(p => {
        msg += `• ${p.producto} - ${this.moneda.simbolo}${p.precio.toFixed(2)}\n`;
      });
      msg += '\n';
    }

    return msg;
  }

  async notificarVenta(ventaId, carrito, numero) {
    try {
      console.log('📢 === INICIO NOTIFICACIÓN ===');
      
      const config = this.botHandler?.config;
      console.log('Config bot:', {
        existe: !!config,
        notificar: config?.notificar_ventas,
        numeros: config?.numeros_notificacion
      });
      
      if (!config?.notificar_ventas) {
        console.log('📵 Notificaciones desactivadas en configuracion_bot');
        return;
      }
      
      if (!config.numeros_notificacion) {
        console.log('📵 No hay numeros_notificacion configurados');
        return;
      }

      let numeros;
      try {
        numeros = JSON.parse(config.numeros_notificacion);
      } catch (e) {
        console.error('❌ Error parseando numeros_notificacion:', e);
        return;
      }

      if (!Array.isArray(numeros) || numeros.length === 0) {
        console.log('📵 Lista de notificación vacía o inválida:', numeros);
        return;
      }

      console.log(`📱 Números a notificar:`, numeros);

      let msg = `🎉 *VENTA #${ventaId}*\n\n`;
      msg += `📱 Cliente: ${numero.replace('@c.us', '')}\n`;
      msg += `💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}\n\n`;
      msg += `📦 Productos:\n`;
      carrito.productos.forEach(p => {
        msg += `• ${p.producto} x${p.cantidad}\n`;
      });
      
      if (carrito.tipo_entrega === 'delivery') {
        msg += `\n📍 Delivery: ${carrito.direccion}`;
      } else {
        msg += `\n📍 Recoger en tienda`;
      }

      msg += `\n\n💬 https://wa.me/${numero.replace('@c.us', '')}`;

      console.log('📝 Mensaje preparado');

      // Debug estructura disponible
      console.log('🔍 Estructura disponible:', {
        botHandler: !!this.botHandler,
        whatsappClient: !!this.botHandler?.whatsappClient,
        client: !!this.botHandler?.whatsappClient?.client,
        sendText: typeof this.botHandler?.whatsappClient?.client?.sendText
      });

      for (const num of numeros) {
        try {
          // Limpiar el número (quitar + si tiene)
          let numeroLimpio = num.replace(/\+/g, '');
          
          // Si no tiene @c.us, agregarlo
          if (!numeroLimpio.includes('@')) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }
          
          console.log(`📤 Enviando a: ${numeroLimpio}`);
          
          // Acceder al cliente correcto según los logs
          const whatsappClient = this.botHandler.whatsappClient;
          
          if (whatsappClient && whatsappClient.client && whatsappClient.client.sendText) {
            await whatsappClient.client.sendText(numeroLimpio, msg);
            console.log(`✅ Notificación enviada a ${num}`);
          } else {
            console.error(`❌ No se encontró sendText. Disponible:`, Object.keys(whatsappClient || {}));
          }
          
        } catch (error) {
          console.error(`❌ Error enviando a ${num}:`, error.message);
        }
      }
      
      console.log('📢 === FIN NOTIFICACIÓN ===');
    } catch (error) {
      console.error('❌ Error general en notificarVenta:', error.message);
    }
  }
}

module.exports = SalesBot;