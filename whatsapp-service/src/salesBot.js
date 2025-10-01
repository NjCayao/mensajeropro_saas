// whatsapp-service/src/salesBot.js - VERSIÓN GENÉRICA PARA TODOS LOS NEGOCIOS
const db = require("./database");
const path = require("path");

class SalesBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.catalogoPdf = null;
    this.infoCatalogo = {}; // promociones, delivery, etc
    this.infoNegocio = {}; // Todo de configuracion_negocio
    this.infoBot = {}; // Todo de configuracion_bot
    this.moneda = { codigo: "PEN", simbolo: "S/" };
    this.carrito = new Map();
    this.botHandler = botHandler;
    this.maxTokens = 150;

    setInterval(() => this.limpiarCarritosInactivos(), 5 * 60 * 1000);
  }

  // ============================================
  // CARGAR TODO DE BD - GENÉRICO
  // ============================================

  async loadCatalog() {
    try {
      console.log("📦 Cargando configuración del negocio...");

      // 1. CARGAR CATÁLOGO COMPLETO
      const [catalogoRows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (catalogoRows.length > 0) {
        if (catalogoRows[0].datos_json) {
          const datos = JSON.parse(catalogoRows[0].datos_json);

          // Guardar productos
          this.catalogo = { productos: datos.productos || [] };

          // Guardar TODO lo demás sin asumir estructura
          this.infoCatalogo = {
            promociones: datos.promociones || [],
            delivery: datos.delivery || null,
            // Cualquier otro campo que venga en el JSON
            ...datos,
          };

          console.log(
            `   ✅ ${this.catalogo.productos.length} productos cargados`
          );
        }
        this.catalogoPdf = catalogoRows[0].archivo_pdf;
      }

      // 2. CARGAR INFO DEL NEGOCIO (TODO)
      const [negocio] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (negocio[0]) {
        this.infoNegocio = negocio[0];

        // Procesar métodos de pago
        if (negocio[0].cuentas_pago) {
          try {
            const cuentas = JSON.parse(negocio[0].cuentas_pago);

            if (cuentas.moneda && cuentas.simbolo) {
              this.moneda = {
                codigo: cuentas.moneda,
                simbolo: cuentas.simbolo,
              };
            }

            this.infoNegocio.metodos_pago_array = cuentas.metodos || [];
          } catch (e) {
            console.error("Error parseando cuentas_pago:", e);
          }
        }

        console.log(
          `   ✅ Negocio: ${this.infoNegocio.nombre_negocio || "Sin nombre"}`
        );
      }

      // 3. CARGAR CONFIG DEL BOT
      const [botConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (botConfig[0]) {
        this.infoBot = botConfig[0];
      }

      // 4. CARGAR MAX TOKENS
      const [tokenConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_max_tokens'"
        );

      if (tokenConfig[0]?.valor) {
        this.maxTokens = parseInt(tokenConfig[0].valor);
      }

      // 5. CARGAR TEMPERATURE (SIN DEFAULT HARDCODED)
      cconst[tempConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_temperatura'"
        );

      if (tempConfig[0]?.valor) {
        this.temperature = parseFloat(tempConfig[0].valor);
      }

      console.log(`   ✅ Temperature: ${this.temperature || "no configurada"}`);
    } catch (error) {
      console.error("❌ Error cargando configuración:", error);
    }
  }

  // ============================================
  // FUNCIONES GENÉRICAS (sin cambios)
  // ============================================

  getFunctions() {
    return [
      {
        name: "agregar_al_carrito",
        description: "Agrega productos al carrito",
        parameters: {
          type: "object",
          properties: {
            productos: {
              type: "array",
              items: { type: "string" },
              description: "Nombres de productos",
            },
            cantidades: {
              type: "array",
              items: { type: "integer" },
              description: "Cantidades",
            },
          },
          required: ["productos", "cantidades"],
        },
      },
      {
        name: "ver_carrito",
        description: "Muestra el carrito actual",
      },
      // NUEVA FUNCIÓN
      {
        name: "vaciar_carrito",
        description:
          "Elimina TODOS los productos del carrito cuando el cliente dice que no quiere nada o que está mal el pedido",
      },
      // NUEVA FUNCIÓN
      {
        name: "actualizar_cantidad",
        description:
          "Cambia la cantidad de un producto específico en el carrito",
        parameters: {
          type: "object",
          properties: {
            producto: {
              type: "string",
              description: "Nombre del producto a modificar",
            },
            nueva_cantidad: {
              type: "integer",
              description: "Nueva cantidad (0 para eliminar)",
            },
          },
          required: ["producto", "nueva_cantidad"],
        },
      },
      {
        name: "confirmar_pedido",
        description:
          "⚠️ OBLIGATORIO llamar cuando cliente confirma pedido con palabras: 'confirmo', 'listo', 'eso nomás', 'no más', 'ok' (después de agregar productos). NO manejes confirmación con texto conversacional, esta función existe específicamente para eso.",
      },
      {
        name: "cancelar_carrito",
        description: "Cliente cancela TODO",
      },
      {
        name: "enviar_catalogo",
        description: "Cliente solicita ver catálogo/menú",
      },
    ];
  }

  // ============================================
  // PROCESAMIENTO (sin cambios)
  // ============================================

  async procesarMensajeVenta(mensaje, numero) {
    const carrito = this.carrito.get(numero);

    if (carrito) {
      carrito.ultimaActividad = Date.now();
    }

    // Estados estructurados (siempre tienen prioridad)
    if (carrito?.estado === "esperando_pago") {
      return await this.manejarPago(mensaje, numero, carrito);
    }
    if (carrito?.estado === "esperando_entrega") {
      return await this.manejarEntrega(mensaje, numero, carrito);
    }
    if (carrito?.estado === "esperando_direccion") {
      return await this.manejarDireccion(mensaje, numero, carrito);
    }

    // DETECCIÓN INTELIGENTE DE INTENCIÓN para estado "agregando"
    if (
      carrito &&
      carrito.productos.length > 0 &&
      carrito.estado === "agregando"
    ) {
      const contexto = await this.botHandler.getContexto(numero);
      const ultimoMensajeBot =
        contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";

      const intencion = await this.detectarIntencion(mensaje, ultimoMensajeBot);
      console.log(`🎯 Intención detectada: ${intencion}`);

      switch (intencion) {
        case "CONFIRMAR_PEDIDO":
          return await this.funcionConfirmarPedido(numero);

        case "VACIAR_CARRITO":
          return await this.funcionVaciarCarrito(numero);

        case "VER_CARRITO":
          return await this.funcionVerCarrito(numero);

        default:
          break;
      }
    }

    if (
      carrito &&
      carrito.productos.length > 0 &&
      carrito.estado === "agregando"
    ) {
      const contexto = await this.botHandler.getContexto(numero);
      const ultimoMensajeBot =
        contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";

      const botPregunto =
        ultimoMensajeBot.toLowerCase().includes("confirmamos") ||
        ultimoMensajeBot.toLowerCase().includes("algo más");

      const mensajeCorto = mensaje.trim().length <= 20;

      if (botPregunto && mensajeCorto) {
        const intencion = await this.detectarIntencion(
          mensaje,
          ultimoMensajeBot
        );
        console.log(`🎯 Intención detectada: ${intencion}`);

        if (intencion === "CONFIRMAR_PEDIDO") {
          return await this.funcionConfirmarPedido(numero);
        }
      }
    }

    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  async respuestaConFunctionCalling(mensaje, numero) {
    if (!this.botHandler) {
      return { respuesta: "¿En qué puedo ayudarte?", tipo: "bot" };
    }

    try {
      const contexto = await this.botHandler.getContexto(numero);
      const carrito = this.carrito.get(numero);

      const ultimasRespuestas = contexto
        .slice(-2)
        .map((c) => c.respuesta_bot)
        .join(" ");
      const acabaDeComprar =
        ultimasRespuestas.includes("Pedido #") &&
        ultimasRespuestas.includes("confirmado");

      // NUEVA DETECCIÓN: Si acaba de comprar y dice algo corto, despedida simple
      const mensajeCorto = mensaje.toLowerCase().trim();
      const esDespedidaSimple = mensajeCorto.match(
        /^(ok|gracias|thank|bien|perfecto|listo|vale|bueno|si|sí|no)$/
      );

      if (acabaDeComprar && esDespedidaSimple) {
        const respuestas = [
          "¡Que disfrutes! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "¡Buen provecho! 😊",
          "¡Excelente día! 😊",
        ];

        const respuesta =
          respuestas[Math.floor(Math.random() * respuestas.length)];

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_venta" };
      }

      const yaEnvioCatalogo = contexto
        .slice(-5)
        .some(
          (c) =>
            c.respuesta_bot?.includes("catálogo") ||
            c.respuesta_bot?.includes("📋") ||
            c.respuesta_bot?.includes("MENÚ")
        );

      let systemPrompt = this.construirPromptGenerico(
        carrito,
        acabaDeComprar,
        yaEnvioCatalogo
      );

      const messages = [{ role: "system", content: systemPrompt }];

      contexto.slice(-4).forEach((c) => {
        messages.push({ role: "user", content: c.mensaje_cliente });
        if (c.respuesta_bot) {
          messages.push({ role: "assistant", content: c.respuesta_bot });
        }
      });

      messages.push({ role: "user", content: mensaje });

      const response = await this.callOpenAIWithFunctions(messages);

      if (response.function_call) {
        const result = await this.ejecutarFuncion(
          response.function_call.name,
          JSON.parse(response.function_call.arguments),
          numero
        );

        await this.botHandler.saveConversation(numero, mensaje, {
          content: result.respuesta,
          tokens: response.usage?.total_tokens || 0,
          tiempo: 0,
        });

        return result;
      }

      await this.botHandler.saveConversation(numero, mensaje, {
        content: response.content,
        tokens: response.usage?.total_tokens || 0,
        tiempo: 0,
      });

      return {
        respuesta: response.content,
        tipo: "bot",
      };
    } catch (error) {
      console.error("❌ Error en Function Calling:", error);
      return {
        respuesta: "Disculpa, tuve un problema. ¿Me repites?",
        tipo: "error",
      };
    }
  }

  // ============================================
  // PROMPT TOTALMENTE GENÉRICO
  // ============================================

  construirPromptGenerico(carrito, acabaDeComprar, yaEnvioCatalogo) {
    let prompt = "";

    // PERSONALIDAD Y PROMPTS PERSONALIZADOS
    if (this.infoBot.system_prompt) {
      prompt += `${this.infoBot.system_prompt}\n\n`;
    }

    if (this.infoBot.prompt_ventas) {
      prompt += `${this.infoBot.prompt_ventas}\n\n`;
    }

    if (this.infoBot.business_info) {
      prompt += `INFORMACIÓN DEL NEGOCIO:\n${this.infoBot.business_info}\n\n`;
    }

    // INFO BÁSICA DEL NEGOCIO (solo lo que existe)
    prompt += `📍 DATOS DE CONTACTO:\n`;
    if (this.infoNegocio.nombre_negocio) {
      prompt += `• Nombre: ${this.infoNegocio.nombre_negocio}\n`;
    }
    if (this.infoNegocio.telefono) {
      prompt += `• Teléfono: ${this.infoNegocio.telefono}\n`;
    }
    if (this.infoNegocio.direccion) {
      prompt += `• Dirección: ${this.infoNegocio.direccion}\n`;
    }
    if (
      this.infoNegocio.metodos_pago_array &&
      this.infoNegocio.metodos_pago_array.length > 0
    ) {
      const metodos = this.infoNegocio.metodos_pago_array
        .map((m) => m.tipo)
        .join(", ");
      prompt += `• Métodos de pago: ${metodos}\n`;
    }
    prompt += "\n";

    // PRODUCTOS
    prompt += `📦 PRODUCTOS/SERVICIOS DISPONIBLES:\n`;
    prompt += this.generarListaProductos();
    prompt += "\n";

    // PROMOCIONES/COMBOS (solo mostrar activos HOY)
    if (
      this.infoCatalogo.promociones &&
      this.infoCatalogo.promociones.length > 0
    ) {
      const diasSemana = [
        "domingo",
        "lunes",
        "martes",
        "miércoles",
        "jueves",
        "viernes",
        "sábado",
      ];
      const hoy = diasSemana[new Date().getDay()];

      const promocionesActivas = this.infoCatalogo.promociones.filter((p) => {
        if (!p.descripcion) return true;

        const descripcionLower = p.descripcion.toLowerCase();
        const tieneDias = descripcionLower.match(
          /(lunes|martes|miércoles|jueves|viernes|sábado|domingo)/gi
        );

        if (!tieneDias) return true;

        const diasPromo = tieneDias.map((d) => d.toLowerCase());
        return diasPromo.includes(hoy);
      });

      if (promocionesActivas.length > 0) {
        prompt += `🎁 OFERTAS ESPECIALES HOY (${hoy}):\n`;
        promocionesActivas.forEach((p) => {
          prompt += `  - ${p.producto}: ${this.moneda.simbolo}${p.precio_promo}`;
          if (p.descripcion) prompt += ` (${p.descripcion})`;
          prompt += "\n";
        });
        prompt += "\n";
      }
    }

    // DELIVERY (si existe)
    if (this.infoCatalogo.delivery) {
      prompt += `🚚 INFORMACIÓN DE ENTREGA:\n`;

      if (
        this.infoCatalogo.delivery.zonas &&
        this.infoCatalogo.delivery.zonas.length > 0
      ) {
        this.infoCatalogo.delivery.zonas.forEach((z) => {
          prompt += `• ${z.zona || "Zona"}: ${this.moneda.simbolo}${
            z.costo || 0
          }`;
          if (z.tiempo) prompt += ` (${z.tiempo})`;
          prompt += "\n";
        });
      }

      if (this.infoCatalogo.delivery.gratis_desde) {
        prompt += `• Entrega GRATIS desde ${this.moneda.simbolo}${this.infoCatalogo.delivery.gratis_desde}\n`;
      }

      prompt += "\n";
    }

    // CARRITO ACTUAL
    prompt += `🛒 CARRITO DEL CLIENTE:\n`;
    if (carrito && carrito.productos.length > 0) {
      carrito.productos.forEach((p) => {
        prompt += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
          p.precio * p.cantidad
        ).toFixed(2)}\n`;
      });
      prompt += `Total actual: ${this.moneda.simbolo}${carrito.total.toFixed(
        2
      )}\n`;
      prompt += `\n⚠️ CRÍTICO: Si cliente confirma este pedido, DEBES llamar confirmar_pedido. NO lo confirmes con texto.\n`;
    } else {
      prompt += "Vacío\n";
    }
    prompt += "\n";

    // ALERTAS ESPECIALES
    if (acabaDeComprar) {
      prompt += `⚠️ El cliente ACABA DE COMPLETAR su compra. NO menciones confirmación ni pago. Solo responde amablemente.\n\n`;
    }

    if (yaEnvioCatalogo) {
      prompt += `📋 Ya enviaste el catálogo recientemente. NO uses enviar_catalogo a menos que lo pida explícitamente de nuevo.\n\n`;
    }

    // REGLAS GENERALES
    prompt += `🎯 REGLAS:
1. Sé natural y conversacional
2. NUNCA inventes información que no esté arriba
3. Si no sabes algo, di "déjame verificar"
4. Máximo ${this.maxTokens} caracteres por respuesta

⚠️ IMPORTANTE SOBRE FUNCIONES:
Las funciones estructuran el proceso de venta. Úsalas cuando detectes estas intenciones:

- agregar_al_carrito: Cliente quiere comprar productos
- confirmar_pedido: Cliente está listo para finalizar su pedido
- actualizar_cantidad: Cliente quiere modificar cantidades
- vaciar_carrito: Cliente quiere reiniciar su pedido
- ver_carrito: Cliente pregunta qué tiene en su pedido
- enviar_catalogo: Cliente quiere ver todos los productos disponibles

❌ NO HAGAS:
- Confirmar pedidos con texto (usa la función confirmar_pedido)
- Manejar pago/entrega conversacionalmente (las funciones lo hacen)

✅ HAZ:
- Usa las funciones cuando identifiques la intención del cliente
- Las funciones manejarán el flujo automáticamente`;

    return prompt;
  }

  // ============================================
  // RESTO DE FUNCIONES (sin cambios hardcodeados)
  // ============================================

  async callOpenAIWithFunctions(messages) {
    const axios = require("axios");

    const response = await axios.post(
      "https://api.openai.com/v1/chat/completions",
      {
        model: this.botHandler.globalConfig.openai_modelo || "gpt-3.5-turbo",
        messages: messages,
        functions: this.getFunctions(),
        function_call: "auto",
        temperature: this.temperature,
        max_tokens: this.maxTokens,
      },
      {
        headers: {
          Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
          "Content-Type": "application/json",
        },
      }
    );

    const choice = response.data.choices[0];

    return {
      content: choice.message?.content,
      function_call: choice.message?.function_call,
      usage: response.data.usage,
    };
  }

  async detectarIntencion(mensaje, contextoBot = "") {
    try {
      const axios = require("axios");

      const prompt = `Analiza el mensaje del cliente y detecta su intención principal.

CONTEXTO: El bot preguntó: "${contextoBot}"
MENSAJE CLIENTE: "${mensaje}"

Responde SOLO con una de estas opciones:
- CONFIRMAR_PEDIDO (cliente quiere confirmar/finalizar su pedido)
- AGREGAR_PRODUCTO (cliente quiere comprar algo)
- VER_CARRITO (cliente pregunta qué tiene en su pedido)
- MODIFICAR_CANTIDAD (cliente quiere cambiar cantidad de algo)
- VACIAR_CARRITO (cliente quiere borrar todo)
- CANCELAR (cliente quiere cancelar)
- VER_CATALOGO (cliente pide ver productos/menú)
- CONVERSACION (solo está conversando, sin acción específica)

Responde solo la palabra clave, nada más.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.1,
          max_tokens: 20,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      return response.data.choices[0].message.content.trim().toUpperCase();
    } catch (error) {
      console.error("Error detectando intención:", error);
      return "CONVERSACION";
    }
  }

  async detectarIntencionPago(mensaje) {
    try {
      const axios = require("axios");

      const prompt = `¿El cliente está confirmando que ya realizó el pago?

Mensaje: "${mensaje}"

Ejemplos de confirmación de pago:
- Ya pagué
- Listo, hice el yape
- Transferí
- Ya deposité
- Hecho
- Pagado
- etc.

Responde SOLO "SI" o "NO".`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.1,
          max_tokens: 5,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const respuesta = response.data.choices[0].message.content
        .trim()
        .toUpperCase();
      return respuesta === "SI" || respuesta === "SÍ" || respuesta === "YES";
    } catch (error) {
      console.error("Error detectando pago:", error);
      return false;
    }
  }

  async esIntencionConfirmar(mensaje) {
    try {
      const axios = require("axios");

      const prompt = `Analiza si el siguiente mensaje es una confirmación o respuesta afirmativa.
Responde SOLO "SI" o "NO".

Mensaje: "${mensaje}"

Ejemplos de confirmación: sí, ok, vale, listo, confirmo, eso nomás, ya, bien, perfecto, adelante, dale, está bien, etc.
También mensajes con errores de tipeo como: "si", "oka", "list", "cofirmo", etc.

¿Es una confirmación?`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.1,
          max_tokens: 5,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const respuesta = response.data.choices[0].message.content
        .trim()
        .toUpperCase();
      return respuesta === "SI" || respuesta === "SÍ" || respuesta === "YES";
    } catch (error) {
      console.error("Error detectando intención:", error);
      return false;
    }
  }

  async ejecutarFuncion(nombre, args, numero) {
    console.log(`🔧 Ejecutando: ${nombre}`, args);

    if (nombre === "enviar_catalogo") {
      const contexto = await this.botHandler.getContexto(numero);
      const yaEnvio = contexto
        .slice(-5)
        .some(
          (c) =>
            c.respuesta_bot?.includes("catálogo") ||
            c.respuesta_bot?.includes("📋") ||
            c.respuesta_bot?.includes("MENÚ")
        );

      if (yaEnvio) {
        return {
          respuesta:
            "Ya te envié el catálogo hace un momento. ¿Te ayudo con algo?",
          tipo: "catalogo_repetido",
        };
      }
    }

    switch (nombre) {
      case "agregar_al_carrito":
        return await this.funcionAgregarCarrito(
          numero,
          args.productos,
          args.cantidades
        );

      case "ver_carrito":
        return await this.funcionVerCarrito(numero);

      // NUEVA FUNCIÓN
      case "vaciar_carrito":
        return await this.funcionVaciarCarrito(numero);

      // NUEVA FUNCIÓN
      case "actualizar_cantidad":
        return await this.funcionActualizarCantidad(
          numero,
          args.producto,
          args.nueva_cantidad
        );

      case "confirmar_pedido":
        return await this.funcionConfirmarPedido(numero);

      case "cancelar_carrito":
        return await this.funcionCancelarCarrito(numero);

      case "enviar_catalogo":
        return await this.funcionEnviarCatalogo(numero);

      default:
        return { respuesta: "¿En qué puedo ayudarte?", tipo: "error" };
    }
  }

  async funcionVaciarCarrito(numero) {
    this.carrito.delete(numero);
    return {
      respuesta: "Listo, carrito vacío. ¿Qué te gustaría pedir?",
      tipo: "carrito_vaciado",
    };
  }

  async funcionActualizarCantidad(numero, producto, nuevaCantidad) {
    const carrito = this.carrito.get(numero);

    if (!carrito) {
      return {
        respuesta: "No tienes productos en el carrito.",
        tipo: "carrito_vacio",
      };
    }

    const productoEnCarrito = carrito.productos.find((p) =>
      p.producto.toLowerCase().includes(producto.toLowerCase())
    );

    if (!productoEnCarrito) {
      return {
        respuesta: `No encontré "${producto}" en tu carrito.`,
        tipo: "producto_no_encontrado",
      };
    }

    if (nuevaCantidad === 0) {
      // Eliminar producto
      carrito.productos = carrito.productos.filter(
        (p) => p !== productoEnCarrito
      );

      if (carrito.productos.length === 0) {
        this.carrito.delete(numero);
        return {
          respuesta: "Producto eliminado. Tu carrito está vacío.",
          tipo: "carrito_vacio",
        };
      }
    } else {
      // Actualizar cantidad
      productoEnCarrito.cantidad = nuevaCantidad;
    }

    // Recalcular total
    carrito.total = carrito.productos.reduce(
      (sum, p) => sum + p.precio * p.cantidad,
      0
    );

    this.carrito.set(numero, carrito);

    let msg = "✅ Carrito actualizado:\n\n";
    carrito.productos.forEach((p) => {
      msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
        p.precio * p.cantidad
      ).toFixed(2)}\n`;
    });
    msg += `\n💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}`;

    return { respuesta: msg, tipo: "carrito_actualizado" };
  }

  async funcionAgregarCarrito(numero, productos, cantidades) {
    let carrito = this.carrito.get(numero) || {
      productos: [],
      total: 0,
      estado: "agregando",
      ultimaActividad: Date.now(),
    };

    const productosAgregados = [];

    for (let i = 0; i < productos.length; i++) {
      const nombreBuscado = productos[i].toLowerCase();

      // PRIMERO: Buscar en promociones/combos
      let productoEncontrado = null;
      if (this.infoCatalogo.promociones) {
        productoEncontrado = this.infoCatalogo.promociones.find(
          (p) =>
            p.producto.toLowerCase().includes(nombreBuscado) ||
            nombreBuscado.includes(p.producto.toLowerCase())
        );

        if (productoEncontrado && productoEncontrado.precio_promo) {
          console.log(`✅ Combo encontrado: ${productoEncontrado.producto}`);

          const existe = carrito.productos.find(
            (x) => x.producto === productoEncontrado.producto
          );

          if (existe) {
            console.log(`⚠️ Combo ya existe, incrementando cantidad`);
            existe.cantidad += cantidades[i];
          } else {
            console.log(`➕ Agregando nuevo combo`);
            carrito.productos.push({
              producto: productoEncontrado.producto,
              precio: productoEncontrado.precio_promo,
              cantidad: cantidades[i],
              es_combo: true,
            });
          }
          productosAgregados.push(
            `${productoEncontrado.producto} x${cantidades[i]}`
          );
          continue;
        }
      }

      // SEGUNDO: Buscar en productos normales
      const producto = this.catalogo.productos.find(
        (p) =>
          p.producto.toLowerCase().includes(nombreBuscado) ||
          nombreBuscado.includes(p.producto.toLowerCase())
      );

      if (producto) {
        const existe = carrito.productos.find(
          (x) => x.producto === producto.producto
        );
        if (existe) {
          existe.cantidad += cantidades[i];
        } else {
          carrito.productos.push({
            producto: producto.producto,
            precio: producto.precio,
            cantidad: cantidades[i],
          });
        }
        productosAgregados.push(`${producto.producto} x${cantidades[i]}`);
      }
    }

    carrito.total = carrito.productos.reduce(
      (sum, p) => sum + p.precio * p.cantidad,
      0
    );

    this.carrito.set(numero, carrito);

    let msg = `✅ Agregado:\n`;
    productosAgregados.forEach((p) => (msg += `• ${p}\n`));
    msg += `\n💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}\n\n`;
    msg += `¿Algo más o confirmamos?`;

    return { respuesta: msg, tipo: "producto_agregado" };
  }

  async funcionVerCarrito(numero) {
    const carrito = this.carrito.get(numero);

    if (!carrito || carrito.productos.length === 0) {
      return {
        respuesta: "Tu carrito está vacío. ¿Qué necesitas?",
        tipo: "carrito_vacio",
      };
    }

    let msg = "🛒 Tu pedido:\n\n";
    carrito.productos.forEach((p) => {
      msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
        p.precio * p.cantidad
      ).toFixed(2)}\n`;
    });
    msg += `\n💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}`;

    return { respuesta: msg, tipo: "ver_carrito" };
  }

  async funcionConfirmarPedido(numero) {
    const carrito = this.carrito.get(numero);

    if (!carrito || carrito.productos.length === 0) {
      return {
        respuesta: "No hay productos en el carrito. ¿Qué necesitas?",
        tipo: "carrito_vacio",
      };
    }

    // Recalcular subtotal (sin delivery aún)
    carrito.subtotal = carrito.productos.reduce(
      (sum, p) => sum + p.precio * p.cantidad,
      0
    );
    carrito.total = carrito.subtotal; // Por ahora igual

    // Cambiar estado a "esperando_entrega" (primero preguntamos tipo de entrega)
    carrito.estado = "esperando_entrega";
    this.carrito.set(numero, carrito);

    return {
      respuesta:
        "¿Cómo prefieres recibirlo?\n\n1️⃣ Delivery/Envío\n2️⃣ Recoger en tienda",
      tipo: "tipo_entrega",
    };
  }

  async funcionCancelarCarrito(numero) {
    this.carrito.delete(numero);
    return {
      respuesta: "Pedido cancelado. ¿Te ayudo con algo más?",
      tipo: "cancelado",
    };
  }

  async funcionEnviarCatalogo(numero) {
    if (this.catalogoPdf) {
      let pdfPath = this.catalogoPdf;

      if (!path.isAbsolute(pdfPath)) {
        const projectRoot = path.resolve(__dirname, "../..");
        pdfPath = path.join(projectRoot, pdfPath);
      }

      const fs = require("fs");
      if (fs.existsSync(pdfPath)) {
        return {
          respuesta: "📋 Aquí está nuestro catálogo. ¿Qué te interesa?",
          tipo: "catalogo_pdf",
          archivo: pdfPath,
        };
      }
    }

    return {
      respuesta: await this.generarMenuTexto(),
      tipo: "menu",
    };
  }

  // Resto de funciones de pago/entrega (sin cambios, ya son genéricas)
  async solicitarPago(numero, carrito) {
    try {
      const carritoActual = this.carrito.get(numero) || carrito;

      let msg = "✅ RESUMEN DE TU PEDIDO:\n\n";

      // Productos
      carritoActual.productos.forEach((p) => {
        msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
          p.precio * p.cantidad
        ).toFixed(2)}\n`;
      });

      // Subtotal
      msg += `\nSubtotal: ${
        this.moneda.simbolo
      }${carritoActual.subtotal.toFixed(2)}\n`;

      // Delivery (si aplica)
      if (carritoActual.tipo_entrega === "delivery") {
        if (carritoActual.costo_delivery > 0) {
          msg += `Delivery: ${
            this.moneda.simbolo
          }${carritoActual.costo_delivery.toFixed(2)}\n`;
        } else {
          msg += `Delivery: GRATIS ✅\n`;
        }
        if (carritoActual.tiempo_estimado) {
          msg += `Tiempo estimado: ${carritoActual.tiempo_estimado}\n`;
        }
      }

      // Total final
      msg += `\n💰 *TOTAL A PAGAR: ${
        this.moneda.simbolo
      }${carritoActual.total.toFixed(2)}*\n\n`;

      msg += "💳 MÉTODOS DE PAGO:\n\n";

      if (
        this.infoNegocio.metodos_pago_array &&
        this.infoNegocio.metodos_pago_array.length > 0
      ) {
        this.infoNegocio.metodos_pago_array.forEach((m) => {
          msg += `📱 ${m.tipo}: ${m.dato}\n`;
          if (m.instruccion) msg += `   ${m.instruccion}\n`;
        });
      }

      msg += "\n💬 Avísame cuando hayas pagado";

      carritoActual.estado = "esperando_pago";
      this.carrito.set(numero, carritoActual);

      return { respuesta: msg, tipo: "metodos_pago" };
    } catch (error) {
      console.error("Error en pago:", error);
      return { respuesta: "Avísame cuando hayas pagado", tipo: "pago_simple" };
    }
  }

  async manejarPago(mensaje, numero, carrito) {
    // Usar GPT para detectar si confirmó el pago
    const intencionPago = await this.detectarIntencionPago(mensaje);

    if (intencionPago) {
      console.log("💰 Pago confirmado por IA, cambiando estado");

      carrito.estado = "esperando_entrega";
      this.carrito.set(numero, carrito);

      return {
        respuesta:
          "¿Cómo prefieres recibirlo?\n\n1️⃣ Delivery/Envío\n2️⃣ Recoger en tienda",
        tipo: "tipo_entrega",
      };
    }

    return {
      respuesta: "Avísame cuando hayas realizado el pago para continuar 😊",
      tipo: "esperando_pago",
    };
  }

  async manejarEntrega(mensaje, numero, carrito) {
    const msgLower = mensaje.toLowerCase();

    if (msgLower.match(/\b(delivery|envio|envío|1)\b/)) {
      carrito.tipo_entrega = "delivery";
      carrito.estado = "esperando_direccion";
      this.carrito.set(numero, carrito);

      return {
        respuesta: "📍 ¿Cuál es tu dirección completa?",
        tipo: "solicitar_direccion",
      };
    }

    if (msgLower.match(/\b(recog|tienda|local|2)\b/)) {
      carrito.tipo_entrega = "tienda";
      carrito.costo_delivery = 0;
      carrito.total = carrito.subtotal; // Sin delivery

      // Ir directo a mostrar resumen de pago
      return await this.solicitarPago(numero, carrito);
    }

    return {
      respuesta: "Elige: 1 para Delivery o 2 para Recoger",
      tipo: "entrega",
    };
  }

  async manejarDireccion(mensaje, numero, carrito) {
    carrito.direccion = mensaje;

    // CALCULAR COSTO DE DELIVERY
    let costoDelivery = 0;
    let tiempoEstimado = "30-45 min";

    if (this.infoCatalogo.delivery && this.infoCatalogo.delivery.zonas) {
      const direccionLower = mensaje.toLowerCase();
      const zonaEncontrada = this.infoCatalogo.delivery.zonas.find((z) =>
        direccionLower.includes(z.zona.toLowerCase())
      );

      if (zonaEncontrada) {
        costoDelivery = zonaEncontrada.costo || 0;
        tiempoEstimado = zonaEncontrada.tiempo || tiempoEstimado;
      } else {
        costoDelivery = this.infoCatalogo.delivery.zonas[0]?.costo || 0;
      }
    }

    // Delivery gratis si supera monto
    if (
      this.infoCatalogo.delivery?.gratis_desde &&
      carrito.subtotal >= this.infoCatalogo.delivery.gratis_desde
    ) {
      costoDelivery = 0;
    }

    carrito.costo_delivery = costoDelivery;
    carrito.tiempo_estimado = tiempoEstimado;
    carrito.total = carrito.subtotal + costoDelivery;
    carrito.estado = "esperando_pago"; // IMPORTANTE: Cambia a esperando_pago

    this.carrito.set(numero, carrito);

    return await this.solicitarPago(numero, carrito);
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
          carrito.direccion || null,
        ]
      );

      const ventaId = result.insertId;
      await this.notificarVenta(ventaId, carrito, numero);

      this.carrito.delete(numero);

      let msg = `✅ ¡Pedido #${ventaId} confirmado!\n\n`;
      msg += `💰 Total: ${this.moneda.simbolo}${carrito.total.toFixed(2)}\n`;

      if (carrito.tipo_entrega === "delivery") {
        msg += `📍 Envío a: ${carrito.direccion}\n`;
      } else {
        msg += `📍 Recoger en tienda\n`;
        if (this.infoNegocio.direccion) {
          msg += `   ${this.infoNegocio.direccion}\n`;
        }
      }

      msg += `\n¡Gracias!`;

      return { respuesta: msg, tipo: "venta_finalizada", ventaId };
    } catch (error) {
      console.error("Error finalizando venta:", error);
      return { respuesta: "Hubo un problema. Contáctanos.", tipo: "error" };
    }
  }

  async notificarVenta(ventaId, carrito, numero) {
    try {
      console.log("📢 Intentando notificar venta #" + ventaId);

      const [notifRows] = await db
        .getPool()
        .execute("SELECT * FROM notificaciones_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (!notifRows[0]) {
        console.log("❌ No hay configuración de notificaciones");
        return;
      }

      if (!notifRows[0].notificar_ventas) {
        console.log("📵 Notificaciones de ventas desactivadas");
        return;
      }

      let numeros;
      try {
        numeros = JSON.parse(notifRows[0].numeros_notificacion || "[]");
        console.log("📱 Números para notificar:", numeros);
      } catch (e) {
        console.error("Error parseando números:", e);
        return;
      }

      if (!Array.isArray(numeros) || numeros.length === 0) {
        console.log("📵 No hay números configurados");
        return;
      }

      let msg = notifRows[0].mensaje_ventas || `Nueva venta #${ventaId}`;

      const productosTexto = carrito.productos
        .map((p) => `${p.producto} x${p.cantidad}`)
        .join(", ");

      msg = msg
        .replace("{nombre_cliente}", numero.replace("@c.us", ""))
        .replace("{productos}", productosTexto)
        .replace("{total}", `${this.moneda.simbolo}${carrito.total.toFixed(2)}`)
        .replace("{fecha_hora}", new Date().toLocaleString("es-PE"));

      console.log("📝 Mensaje preparado:", msg.substring(0, 100));

      // VERIFICAR ESTRUCTURA
      console.log("🔍 Verificando botHandler:", {
        existe: !!this.botHandler,
        whatsappClient: !!this.botHandler?.whatsappClient,
        client: !!this.botHandler?.whatsappClient?.client,
        sendText: typeof this.botHandler?.whatsappClient?.client?.sendText,
      });

      for (const num of numeros) {
        try {
          let numeroLimpio = num.replace(/[^\d]/g, ""); // Solo dígitos

          if (!numeroLimpio.includes("@")) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }

          console.log(`📤 Enviando a: ${numeroLimpio}`);

          // ACCEDER AL CLIENTE CORRECTO
          const whatsappClient = this.botHandler?.whatsappClient;

          if (
            whatsappClient &&
            whatsappClient.client &&
            typeof whatsappClient.client.sendText === "function"
          ) {
            await whatsappClient.client.sendText(numeroLimpio, msg);
            console.log(`✅ Notificación enviada a ${num}`);
          } else {
            console.error(
              `❌ sendText no disponible. Estructura:`,
              Object.keys(whatsappClient?.client || {})
            );
          }
        } catch (error) {
          console.error(`❌ Error enviando a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("❌ Error general en notificarVenta:", error);
    }
  }

  limpiarCarritosInactivos() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000;

    for (const [numero, carrito] of this.carrito.entries()) {
      if (ahora - carrito.ultimaActividad > timeout) {
        this.carrito.delete(numero);
      }
    }
  }

  generarListaProductos() {
    if (!this.catalogo || !this.catalogo.productos) return "Sin productos";

    let lista = "";
    const categorias = {};

    this.catalogo.productos.forEach((p) => {
      if (!categorias[p.categoria]) categorias[p.categoria] = [];
      categorias[p.categoria].push(p);
    });

    for (const [cat, prods] of Object.entries(categorias)) {
      lista += `\n${cat}:\n`;
      prods.forEach((p) => {
        lista += `  - ${p.producto}: ${this.moneda.simbolo}${p.precio.toFixed(
          2
        )}\n`;
      });
    }

    return lista;
  }

  async generarMenuTexto() {
    if (!this.catalogo) return "Catálogo no disponible";

    const nombreNegocio = this.infoNegocio.nombre_negocio || "Nuestro negocio";
    let msg = `📋 CATÁLOGO DE ${nombreNegocio.toUpperCase()}\n\n`;

    const categorias = {};
    this.catalogo.productos.forEach((p) => {
      if (!categorias[p.categoria]) categorias[p.categoria] = [];
      categorias[p.categoria].push(p);
    });

    for (const [cat, prods] of Object.entries(categorias)) {
      msg += `${cat}\n`;
      prods.forEach((p) => {
        msg += `• ${p.producto} - ${this.moneda.simbolo}${p.precio.toFixed(
          2
        )}\n`;
      });
      msg += "\n";
    }

    if (
      this.infoCatalogo.promociones &&
      this.infoCatalogo.promociones.length > 0
    ) {
      msg += "PROMOCIONES\n";
      this.infoCatalogo.promociones.forEach((p) => {
        msg += `• ${p.producto}: ${p.descripcion}\n`;
      });
    }

    return msg;
  }
}

module.exports = SalesBot;
