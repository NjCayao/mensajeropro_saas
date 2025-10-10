// whatsapp-service/src/salesBot.js
const db = require("../../database");
const path = require("path");

class SalesBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.catalogo = null;
    this.catalogoPdf = null;
    this.infoCatalogo = {};
    this.infoNegocio = {};
    this.infoBot = {};
    this.moneda = { codigo: "PEN", simbolo: "S/" };
    this.carrito = new Map();
    this.ventasCompletadas = new Map();
    this.botHandler = botHandler;
    this.maxTokens = 150;
    this.temperature = 0.7;

    setInterval(() => this.limpiarCarritosInactivos(), 5 * 60 * 1000);
    setInterval(() => this.limpiarVentasCompletadas(), 5 * 60 * 1000);
  }

  async loadCatalog() {
    try {
      // console.log("📦 Cargando configuración del negocio...");

      const [catalogoRows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (catalogoRows.length > 0) {
        if (catalogoRows[0].datos_json) {
          const datos = JSON.parse(catalogoRows[0].datos_json);
          this.catalogo = { productos: datos.productos || [] };
          this.infoCatalogo = {
            promociones: datos.promociones || [],
            delivery: datos.delivery || null,
            ...datos,
          };
          // console.log(
          //   `   ✅ ${this.catalogo.productos.length} productos cargados`
          // );
        }
        this.catalogoPdf = catalogoRows[0].archivo_pdf;
      }

      const [negocio] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (negocio[0]) {
        this.infoNegocio = negocio[0];

        if (negocio[0].cuentas_pago) {
          const cuentas = JSON.parse(negocio[0].cuentas_pago);
          if (cuentas.moneda && cuentas.simbolo) {
            this.moneda = {
              codigo: cuentas.moneda,
              simbolo: cuentas.simbolo,
            };
          }          
        }
      }
      const [botConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (botConfig[0]) {
        this.infoBot = botConfig[0];
      }

      const [tokenConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_max_tokens'"
        );

      if (tokenConfig[0]?.valor) {
        this.maxTokens = parseInt(tokenConfig[0].valor);
      }

      const [tempConfig] = await db
        .getPool()
        .execute(
          "SELECT valor FROM configuracion_plataforma WHERE clave = 'openai_temperatura'"
        );

      if (tempConfig[0]?.valor) {
        this.temperature = parseFloat(tempConfig[0].valor);
      }

      // console.log(`   ✅ Temperature: ${this.temperature}`);
    } catch (error) {
      console.error("❌ Error cargando configuración:", error);
    }
  }

  getFunctions() {
    return [
      {
        name: "agregar_al_carrito",
        description:
          "Agrega productos específicos al carrito. SOLO usa esta función cuando el cliente pida productos NUEVOS que NO están en el carrito.",
        parameters: {
          type: "object",
          properties: {
            productos: {
              type: "array",
              items: { type: "string" },
              description: "Nombres exactos de productos NUEVOS a agregar",
            },
            cantidades: {
              type: "array",
              items: { type: "integer" },
              description: "Cantidades correspondientes",
            },
          },
          required: ["productos", "cantidades"],
        },
      },
      {
        name: "ver_carrito",
        description: "Muestra el resumen actual del carrito",
      },
      {
        name: "vaciar_carrito",
        description:
          "Elimina TODOS los productos del carrito cuando el cliente dice que no quiere nada o que está mal el pedido",
      },
      {
        name: "actualizar_cantidad",
        description:
          "Cambia la cantidad de un producto YA existente en el carrito",
        parameters: {
          type: "object",
          properties: {
            producto: {
              type: "string",
              description: "Nombre del producto existente a modificar",
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
          "OBLIGATORIO: Llama esta función cuando el cliente confirme su pedido con palabras como: confirmo, listo, ok, sí, adelante, dale, está bien",
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

  async procesarMensajeVenta(mensaje, numero) {
    if (this.ventasCompletadas.has(numero)) {
      const ventaInfo = this.ventasCompletadas.get(numero);
      const tiempoTranscurrido = Date.now() - ventaInfo.timestamp;

      console.log(
        `🔍 Venta completada detectada. Tiempo: ${Math.floor(
          tiempoTranscurrido / 1000
        )}s`
      );

      if (tiempoTranscurrido < 3 * 60 * 1000) {
        const respuestas = [
          "¡Que disfrutes! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "¡Buen provecho! 😊",
          "Tu pedido ya está confirmado y en proceso. espera 3 minutos para volver hacer un nuevo pedido😊",
        ];
        const respuesta =
          respuestas[Math.floor(Math.random() * respuestas.length)];

        console.log("✅ Respondiendo con despedida post-venta");

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_venta" };
      } else {
        console.log("⏰ Timeout de venta completada alcanzado, limpiando...");
        this.ventasCompletadas.delete(numero);
      }
    }

    const carrito = this.carrito.get(numero);

    if (carrito) {
      carrito.ultimaActividad = Date.now();
    }

    if (carrito?.estado === "esperando_pago") {
      return await this.manejarPago(mensaje, numero, carrito);
    }
    if (carrito?.estado === "esperando_entrega") {
      return await this.manejarEntrega(mensaje, numero, carrito);
    }
    if (carrito?.estado === "esperando_direccion") {
      return await this.manejarDireccion(mensaje, numero, carrito);
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

      if (botPregunto) {
        const mensajeLower = mensaje.toLowerCase();
        const mencionaPagoEntrega = mensajeLower.match(
          /\b(yape|pago|delivery|envio|envío|recog|tienda|transfer|efectivo|tarjeta|paypal)\b/
        );
        const esConfirmacionExplicita = mensajeLower.match(
          /^(si|sí|yes|ok|listo|confirmo|dale|está bien|vale|adelante|perfecto)$/
        );

        if (mencionaPagoEntrega || esConfirmacionExplicita) {
          console.log(
            "🎯 Confirmación detectada después de 'confirmamos' → Auto-confirmar pedido"
          );
          return await this.funcionConfirmarPedido(numero);
        }
      }

      const intencion = await this.detectarIntencionMejorada(
        mensaje,
        ultimoMensajeBot,
        carrito
      );
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

    return await this.respuestaConFunctionCalling(mensaje, numero);
  }

  async respuestaConFunctionCalling(mensaje, numero) {
    if (!this.botHandler) {
      return { respuesta: "¿En qué puedo ayudarte?", tipo: "bot" };
    }

    try {
      const contexto = await this.botHandler.getContexto(numero);
      const carrito = this.carrito.get(numero);

      const ultimoMensaje =
        contexto.length > 0 ? contexto[contexto.length - 1].respuesta_bot : "";
      const acabaDeComprar =
        ultimoMensaje.includes("Pedido #") &&
        ultimoMensaje.includes("confirmado");

      if (acabaDeComprar) {
        console.log("✅ Post-venta detectado - NO usar function calling");

        const respuestas = [
          "¡Que disfrutes! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "Tu pedido ya está confirmado y en proceso 😊",
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

      const mensajeCorto = mensaje.toLowerCase().trim();
      const esDespedidaSimple = mensajeCorto.match(
        /^(ok|gracias|thank|bien|perfecto|listo|vale|bueno|si|sí|no)$/
      );

      const ultimasRespuestas = contexto
        .slice(-2)
        .map((c) => c.respuesta_bot)
        .join(" ");

      if (ultimasRespuestas.includes("confirmado") && esDespedidaSimple) {
        const respuestas = [
          "¡Que disfrutes! 😊",
          "¡Hasta pronto! 😊",
          "¡Gracias a ti! 😊",
          "¡Buen provecho! 😊",
          "¡Excelente! 😊",
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

  construirPromptGenerico(carrito, acabaDeComprar, yaEnvioCatalogo) {
    let prompt = "";

    if (this.infoBot.system_prompt) {
      prompt += `${this.infoBot.system_prompt}\n\n`;
    }

    if (this.infoBot.prompt_ventas) {
      prompt += `${this.infoBot.prompt_ventas}\n\n`;
    }

    if (this.infoBot.business_info) {
      prompt += `INFORMACIÓN DEL NEGOCIO:\n${this.infoBot.business_info}\n\n`;
    }

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

    prompt += `📦 PRODUCTOS/SERVICIOS DISPONIBLES:\n`;
    prompt += this.generarListaProductos();
    prompt += "\n";

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

    prompt += `🛒 CARRITO DEL CLIENTE:\n`;
    if (carrito && carrito.productos.length > 0) {
      carrito.productos.forEach((p) => {
        prompt += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
          p.precio * p.cantidad
        ).toFixed(2)}\n`;
      });
      prompt += `Total actual: ${this.moneda.simbolo}${carrito.total.toFixed(
        2
      )}\n\n`;
      prompt += `⚠️ IMPORTANTE: Este carrito tiene productos agregados. Si el cliente confirma, DEBES llamar confirmar_pedido.\n`;
    } else {
      prompt += "Vacío\n";
    }
    prompt += "\n";

    if (acabaDeComprar) {
      prompt += `✅ El cliente YA completó su compra (Pedido confirmado). 
⚠️ CRÍTICO: NO preguntes nada sobre pago ni delivery. Solo responde cordialmente a cualquier despedida o agradecimiento.
Si el cliente dice "ya pagué" de nuevo, responde: "Tu pedido ya está confirmado y en proceso 😊".\n\n`;
    }

    if (yaEnvioCatalogo) {
      prompt += `📋 Ya enviaste el catálogo recientemente. NO uses enviar_catalogo a menos que lo pida explícitamente.\n\n`;
    }

    prompt += `🎯 REGLAS PARA USO DE FUNCIONES:

⚠️ CRÍTICO: Cuando uses una función, NO generes texto conversacional adicional. La función ya retorna el mensaje apropiado.

1. **agregar_al_carrito**: 
   - SOLO cuando el cliente pida productos/servicios NUEVOS que NO están en el carrito
   - Si el cliente pide "otro producto más" y YA tiene productos en el carrito, agregar SOLO el nuevo item
   - NO agregues texto como "perfecto, agrego eso". La función ya responde.

2. **confirmar_pedido**:
   - OBLIGATORIO cuando el cliente diga: "confirmo", "listo", "ok", "sí", "dale", "está bien"
   - TAMBIÉN cuando mencione método de pago o tipo de entrega (implica confirmación)
   - DESPUÉS de que se agregaron productos al carrito
   - NO generes texto conversacional, la función maneja el flujo

3. **actualizar_cantidad**:
   - Cuando el cliente quiera cambiar cantidad de algo YA en el carrito
   - NO agregues texto explicativo, la función responde

4. **ver_carrito**:
   - Cuando el cliente pregunte qué tiene en su pedido
   - La función muestra el resumen completo

5. **vaciar_carrito**:
   - Cuando el cliente diga "borra todo", "empezar de nuevo", "está mal"
   - La función confirma la acción

6. **enviar_catalogo**:
   - SOLO si el cliente pide ver productos/servicios/menú y NO lo enviaste recientemente

❌ NO HAGAS:
- Agregar productos duplicados (verifica el carrito primero)
- Generar texto conversacional cuando llamas una función
- Preguntar sobre pago/entrega antes de confirmar pedido
- Ignorar cuando el cliente confirma

✅ HAZ:
- Llama las funciones directamente sin texto adicional
- Las funciones ya contienen los mensajes apropiados
- Máximo ${this.maxTokens} caracteres solo cuando NO uses funciones`;

    return prompt;
  }

  async detectarIntencionMejorada(mensaje, contextoBot, carrito) {
    try {
      const axios = require("axios");

      let contextCarrito = "Carrito actual:\n";
      carrito.productos.forEach((p) => {
        contextCarrito += `- ${p.producto} x${p.cantidad}\n`;
      });
      contextCarrito += `Total: ${this.moneda.simbolo}${carrito.total.toFixed(
        2
      )}`;

      const prompt = `Eres un asistente que detecta la intención del cliente en una conversación de ventas.

CONTEXTO DEL CARRITO:
${contextCarrito}

ÚLTIMO MENSAJE DEL BOT:
"${contextoBot}"

MENSAJE DEL CLIENTE:
"${mensaje}"

Analiza el mensaje del cliente y detecta su intención principal. Responde SOLO con una de estas opciones:

- CONFIRMAR_PEDIDO: El cliente está de acuerdo y quiere finalizar/confirmar su pedido actual
- AGREGAR_PRODUCTO: El cliente quiere comprar algo NUEVO
- VER_CARRITO: El cliente pregunta qué tiene en su pedido
- MODIFICAR_CANTIDAD: El cliente quiere cambiar cantidad de algo YA agregado
- VACIAR_CARRITO: El cliente quiere borrar todo y empezar de nuevo
- CANCELAR: El cliente quiere cancelar la compra
- VER_CATALOGO: El cliente pide ver productos/menú
- CONVERSACION: Solo está conversando, sin acción específica

IMPORTANTE:
- Si el bot preguntó "¿confirmamos?" y el cliente dice "sí/ok/listo/confirmo/dale", la intención es CONFIRMAR_PEDIDO
- Si el cliente reclama o dice que algo está mal, NO es confirmación
- Si el cliente pide "otra bebida" y YA tiene bebidas, es AGREGAR_PRODUCTO

Responde SOLO la palabra clave, nada más.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 20,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const intencion = response.data.choices[0].message.content
        .trim()
        .toUpperCase();
      console.log(`🧠 GPT detectó: ${intencion}`);
      return intencion;
    } catch (error) {
      console.error("Error detectando intención:", error);
      return "CONVERSACION";
    }
  }

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

  async detectarIntencionPago(mensaje) {
    try {
      const axios = require("axios");

      const prompt = `¿El cliente está confirmando que ya realizó el pago?

Mensaje: "${mensaje}"

Ejemplos de confirmación de pago:
- Ya pagué / Ya pague
- Listo, hice el yape / yapee
- Transferí / Transferi
- Ya deposité
- Hecho
- Pagado
- Listo con el pago

Responde SOLO "SI" o "NO".`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
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
      case "vaciar_carrito":
        return await this.funcionVaciarCarrito(numero);
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
      productoEnCarrito.cantidad = nuevaCantidad;
    }

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

      const existeEnCarrito = carrito.productos.find(
        (p) =>
          p.producto.toLowerCase().includes(nombreBuscado) ||
          nombreBuscado.includes(p.producto.toLowerCase())
      );

      if (existeEnCarrito) {
        console.log(`⚠️ Producto ya existe en carrito, incrementando cantidad`);
        existeEnCarrito.cantidad += cantidades[i];
        productosAgregados.push(
          `${existeEnCarrito.producto} x${cantidades[i]}`
        );
        continue;
      }

      let productoEncontrado = null;
      if (this.infoCatalogo.promociones) {
        productoEncontrado = this.infoCatalogo.promociones.find(
          (p) =>
            p.producto.toLowerCase().includes(nombreBuscado) ||
            nombreBuscado.includes(p.producto.toLowerCase())
        );

        if (productoEncontrado && productoEncontrado.precio_promo) {
          console.log(`✅ Combo encontrado: ${productoEncontrado.producto}`);
          carrito.productos.push({
            producto: productoEncontrado.producto,
            precio: productoEncontrado.precio_promo,
            cantidad: cantidades[i],
            es_combo: true,
          });
          productosAgregados.push(
            `${productoEncontrado.producto} x${cantidades[i]}`
          );
          continue;
        }
      }

      const producto = this.catalogo.productos.find(
        (p) =>
          p.producto.toLowerCase().includes(nombreBuscado) ||
          nombreBuscado.includes(p.producto.toLowerCase())
      );

      if (producto) {
        carrito.productos.push({
          producto: producto.producto,
          precio: producto.precio,
          cantidad: cantidades[i],
        });
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

    carrito.subtotal = carrito.productos.reduce(
      (sum, p) => sum + p.precio * p.cantidad,
      0
    );
    carrito.total = carrito.subtotal;
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

  async solicitarPago(numero, carrito) {
    try {
      const carritoActual = this.carrito.get(numero) || carrito;

      let msg = "✅ RESUMEN DE TU PEDIDO:\n\n";

      carritoActual.productos.forEach((p) => {
        msg += `• ${p.producto} x${p.cantidad} - ${this.moneda.simbolo}${(
          p.precio * p.cantidad
        ).toFixed(2)}\n`;
      });

      msg += `\nSubtotal: ${
        this.moneda.simbolo
      }${carritoActual.subtotal.toFixed(2)}\n`;

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
    const intencionPago = await this.detectarIntencionPago(mensaje);

    if (intencionPago) {
      console.log("💰 Pago confirmado por IA, finalizando venta");
      return await this.finalizarVenta(numero, carrito);
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
        respuesta:
          "📍 Por favor indica tu *dirección y sector*\n\nEjemplo: Jr Comercio 304 / La Molina",
        tipo: "solicitar_direccion",
      };
    }

    if (msgLower.match(/\b(recog|tienda|local|2)\b/)) {
      carrito.tipo_entrega = "tienda";
      carrito.costo_delivery = 0;
      carrito.total = carrito.subtotal;

      return await this.solicitarPago(numero, carrito);
    }

    return {
      respuesta: "Elige: 1 para Delivery o 2 para Recoger",
      tipo: "entrega",
    };
  }

  async manejarDireccion(mensaje, numero, carrito) {
    carrito.direccion = mensaje;

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

    if (
      this.infoCatalogo.delivery?.gratis_desde &&
      carrito.subtotal >= this.infoCatalogo.delivery.gratis_desde
    ) {
      costoDelivery = 0;
    }

    carrito.costo_delivery = costoDelivery;
    carrito.tiempo_estimado = tiempoEstimado;
    carrito.total = carrito.subtotal + costoDelivery;
    carrito.estado = "esperando_pago";

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

      this.ventasCompletadas.set(numero, {
        ventaId: ventaId,
        timestamp: Date.now(),
      });
      console.log(
        `✅ Venta #${ventaId} marcada como completada para ${numero}`
      );

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
        console.log("❌ No hay configuración de notificaciones en BD");
        return;
      }

      console.log("✅ Configuración encontrada:", {
        notificar_ventas: notifRows[0].notificar_ventas,
        tiene_numeros: !!notifRows[0].numeros_notificacion,
      });

      if (!notifRows[0].notificar_ventas) {
        console.log("📵 Notificaciones de ventas desactivadas");
        return;
      }

      let numeros;
      try {
        numeros = JSON.parse(notifRows[0].numeros_notificacion || "[]");
        console.log("📱 Números para notificar:", numeros);
      } catch (e) {
        console.error("❌ Error parseando números:", e);
        return;
      }

      if (!Array.isArray(numeros) || numeros.length === 0) {
        console.log("📵 No hay números configurados para notificar");
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

      for (const num of numeros) {
        try {
          let numeroLimpio = num.replace(/[^\d]/g, "");
          if (!numeroLimpio.includes("@")) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }

          console.log(`📤 Enviando notificación a: ${numeroLimpio}`);

          let enviado = false;

          if (this.botHandler.whatsappClient?.client?.client?.sendText) {
            await this.botHandler.whatsappClient.client.client.sendText(
              numeroLimpio,
              msg
            );
            enviado = true;
            console.log(`✅ Enviado vía client.client.sendText`);
          } else if (this.botHandler.whatsappClient?.client?.sendText) {
            await this.botHandler.whatsappClient.client.sendText(
              numeroLimpio,
              msg
            );
            enviado = true;
            console.log(`✅ Enviado vía client.sendText`);
          } else if (this.botHandler.whatsappClient?.sendMessage) {
            await this.botHandler.whatsappClient.sendMessage(numeroLimpio, msg);
            enviado = true;
            console.log(`✅ Enviado vía sendMessage`);
          }

          if (!enviado) {
            console.error("❌ No se encontró método de envío disponible");
            console.log(
              "Estructura:",
              JSON.stringify(
                {
                  hasWhatsappClient: !!this.botHandler.whatsappClient,
                  hasClient: !!this.botHandler.whatsappClient?.client,
                  hasClientClient:
                    !!this.botHandler.whatsappClient?.client?.client,
                  clientClientKeys: this.botHandler.whatsappClient?.client
                    ?.client
                    ? Object.keys(
                        this.botHandler.whatsappClient.client.client
                      ).slice(0, 10)
                    : [],
                },
                null,
                2
              )
            );
          } else {
            console.log(`✅ Notificación enviada exitosamente a ${num}`);
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
        console.log(`🧹 Carrito limpiado por inactividad: ${numero}`);
      }
    }
  }

  limpiarVentasCompletadas() {
    const ahora = Date.now();
    const timeout = 3 * 60 * 1000;

    for (const [numero, ventaInfo] of this.ventasCompletadas.entries()) {
      if (ahora - ventaInfo.timestamp > timeout) {
        this.ventasCompletadas.delete(numero);
        console.log(
          `🧹 Venta completada limpiada: ${numero} (Venta #${ventaInfo.ventaId})`
        );
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
