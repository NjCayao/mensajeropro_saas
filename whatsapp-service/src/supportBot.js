// whatsapp-service/src/supportBot.js - PARTE 1
const db = require("./database");
const axios = require("axios");
const moment = require("moment");

moment.locale("es");

class SupportBot {
  constructor(empresaId, botHandler = null) {
    this.empresaId = empresaId;
    this.botHandler = botHandler;

    // Configuración
    this.infoBot = {};
    this.infoNegocio = {};
    this.planes = [];
    this.servicios = [];
    this.horarios = [];
    this.moneda = null; // Se cargará desde BD
    this.notificaciones = {};
    this.zonasCobertura = [];

    // Estados de procesos activos (UN SOLO Map para todo)
    this.procesosActivos = new Map();
    this.ventasCompletadas = new Map();

    // Configuración IA
    this.maxTokens = 150;
    this.temperature = 0.7;

    // Limpieza automática
    setInterval(() => this.limpiarProcesosInactivos(), 5 * 60 * 1000);
    setInterval(() => this.limpiarVentasCompletadas(), 5 * 60 * 1000);
  }

  async loadConfig() {
    try {
      // 1. Configuración del bot
      const [botConfig] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (botConfig[0]) {
        this.infoBot = botConfig[0];
        
        if (botConfig[0].business_info) {
          this.zonasCobertura = this.extraerZonasCobertura(botConfig[0].business_info);
        }
      }

      // 2. Información del negocio
      const [negocio] = await db
        .getPool()
        .execute("SELECT * FROM configuracion_negocio WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (negocio[0]) {
        this.infoNegocio = negocio[0];

        if (negocio[0].cuentas_pago) {
          try {
            const cuentas = JSON.parse(negocio[0].cuentas_pago);
            
            // Configurar moneda desde BD
            if (cuentas.moneda && cuentas.simbolo) {
              this.moneda = {
                codigo: cuentas.moneda,
                simbolo: cuentas.simbolo
              };
              console.log(`✅ Moneda configurada desde BD: ${this.moneda.codigo} (${this.moneda.simbolo})`);
            } else {
              // Valor por defecto SOLO si no existe en BD
              this.moneda = { codigo: "USD", simbolo: "$" };
              console.log(`⚠️ Usando moneda por defecto: ${this.moneda.codigo}`);
            }
            
            this.infoNegocio.metodos_pago_array = cuentas.metodos || [];
          } catch (e) {
            console.error("Error parseando cuentas_pago:", e);
            // Valor por defecto si hay error
            this.moneda = { codigo: "USD", simbolo: "$" };
          }
        } else {
          // Si no hay cuentas_pago en BD
          this.moneda = { codigo: "USD", simbolo: "$" };
          console.log(`⚠️ No hay cuentas_pago configuradas, usando: ${this.moneda.codigo}`);
        }
      } else {
        // Si no hay configuración de negocio
        this.moneda = { codigo: "USD", simbolo: "$" };
        console.log(`⚠️ No hay configuracion_negocio, usando moneda por defecto`);
      }

      // 3. Planes desde catálogo
      const [catalogoRows] = await db
        .getPool()
        .execute("SELECT * FROM catalogo_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (catalogoRows[0] && catalogoRows[0].datos_json) {
        const datos = JSON.parse(catalogoRows[0].datos_json);
        this.planes = datos.productos || [];
      }

      // 4. Servicios técnicos
      const [serviciosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM servicios_disponibles WHERE empresa_id = ? AND activo = 1 ORDER BY id",
          [this.empresaId]
        );
      this.servicios = serviciosRows;

      // 5. Horarios de atención
      const [horariosRows] = await db
        .getPool()
        .execute(
          "SELECT * FROM horarios_atencion WHERE empresa_id = ? AND activo = 1 ORDER BY dia_semana",
          [this.empresaId]
        );
      this.horarios = horariosRows;

      // 6. Notificaciones
      const [notifRows] = await db
        .getPool()
        .execute("SELECT * FROM notificaciones_bot WHERE empresa_id = ?", [
          this.empresaId,
        ]);

      if (notifRows[0]) {
        this.notificaciones = notifRows[0];
      }

      // 7. Configuración de tokens
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

      console.log("✅ SupportBot configurado correctamente");
    } catch (error) {
      console.error("❌ Error cargando configuración de soporte:", error);
    }
  }

  extraerZonasCobertura(businessInfo) {
    const regex = /zonas?\s+de\s+cobertura\s*:\s*([^\n]+)/i;
    const match = businessInfo.match(regex);
    
    if (match && match[1]) {
      return match[1]
        .split(',')
        .map(z => z.trim().toLowerCase())
        .filter(z => z.length > 0);
    }
    
    return [];
  }

  // ============================================
  // MÉTODO PRINCIPAL - PROCESAMIENTO DE MENSAJES
  // ============================================

  async procesarMensajeSoporte(mensaje, numero) {
    console.log(`🤖 SupportBot procesando: "${mensaje.substring(0, 50)}..."`);

    // 1. VERIFICAR DESPEDIDAS POST-VENTA
    if (this.ventasCompletadas.has(numero)) {
      const ventaInfo = this.ventasCompletadas.get(numero);
      const tiempoTranscurrido = Date.now() - ventaInfo.timestamp;

      if (tiempoTranscurrido < 3 * 60 * 1000) {
        const respuesta = await this.generarRespuestaConIA(
          "DESPEDIDA_POST_VENTA",
          {},
          numero,
          mensaje
        );

        await this.botHandler.saveConversation(numero, mensaje, {
          content: respuesta,
          tokens: 0,
          tiempo: 0,
        });

        return { respuesta, tipo: "despedida_post_venta" };
      } else {
        this.ventasCompletadas.delete(numero);
      }
    }

    // 2. OBTENER O CREAR PROCESO
    let proceso = this.procesosActivos.get(numero);
    
    if (proceso) {
      proceso.ultimaActividad = Date.now();
    }

    // 3. DETECTAR INTENCIÓN DEL MENSAJE (técnico)
    const intencion = await this.detectarIntencion(mensaje, numero, proceso);
    
    console.log(`🎯 Intención detectada: ${intencion.accion}`);

    // 4. EJECUTAR ACCIÓN TÉCNICA BASADA EN INTENCIÓN
    const resultado = await this.ejecutarAccionTecnica(intencion, numero, mensaje, proceso);

    // 5. GUARDAR CONVERSACIÓN Y RETORNAR
    await this.botHandler.saveConversation(numero, mensaje, {
      content: resultado.respuesta,
      tokens: resultado.tokens || 0,
      tiempo: 0,
    });

    return resultado;
  }

  // ============================================
  // DETECTOR DE INTENCIÓN (LÓGICA TÉCNICA)
  // ============================================

  async detectarIntencion(mensaje, numero, proceso) {
    const contexto = await this.botHandler.getContexto(numero);
    const msgTrim = mensaje.trim().toLowerCase();

    // CASO 1: Primera interacción SIN proceso → Crear proceso y mostrar menú
    if (!proceso) {
      // Crear proceso en estado inicial
      this.procesosActivos.set(numero, {
        estado: "menu_inicial",
        ultimaActividad: Date.now()
      });
      return { accion: "MOSTRAR_MENU", datos: {} };
    }

    // CASO 2: Cliente está en menú inicial y responde
    if (proceso.estado === "menu_inicial") {
      if (msgTrim === "1") {
        return { accion: "INICIAR_VENTAS", datos: {} };
      }
      if (msgTrim === "2") {
        return { accion: "INICIAR_SOPORTE", datos: {} };
      }
      if (msgTrim === "3") {
        return { accion: "INICIAR_PAGOS", datos: {} };
      }
      // Si responde algo que no es 1, 2 o 3 → es conversación general
      return { accion: "CONVERSACION_GENERAL", datos: { mensaje } };
    }

    // CASO 3: Comprobante recibido
    if (proceso?.estado === "esperando_comprobante_imagen") {
      return { accion: "ESPERANDO_IMAGEN", datos: {} };
    }

    // CASO 4: Esperando datos de pago
    if (proceso?.estado === "esperando_datos_pago") {
      return { accion: "PROCESAR_DATOS_PAGO", datos: { mensaje } };
    }

    // CASO 5: Esperando selección de día
    if (proceso?.esperando_seleccion_dia) {
      return { accion: "SELECCIONAR_DIA", datos: { opcion: msgTrim } };
    }

    // CASO 6: Esperando selección de hora
    if (proceso?.esperando_seleccion_hora) {
      return { accion: "SELECCIONAR_HORA", datos: { opcion: msgTrim } };
    }

    // CASO 7: Esperando confirmación final
    if (proceso?.esperando_confirmacion_final) {
      return { accion: "CONFIRMAR_DATOS", datos: { respuesta: msgTrim } };
    }

    // CASO 8: Cliente en flujo de VENTAS
    if (proceso?.flujo === "ventas") {
      // Sub-caso: Está en estado de selección de plan y NO tiene plan
      if (proceso.estado === "seleccionando_plan" && !proceso.plan) {
        // Detectar selección de plan
        const planDetectado = await this.detectarPlanSeleccionado(mensaje, msgTrim);
        
        if (planDetectado) {
          return { 
            accion: "PLAN_SELECCIONADO", 
            datos: { plan: planDetectado } 
          };
        }
      }

      // Sub-caso: Ya tiene plan pero NO tiene zona
      if (proceso.plan && !proceso.zona) {
        // Intentar extraer zona y dirección del mensaje
        const datosExtraidos = await this.extraerZonaYDireccion(mensaje);
        
        if (datosExtraidos.zona) {
          return {
            accion: "VERIFICAR_COBERTURA",
            datos: { mensaje, zona: datosExtraidos.zona, direccion: datosExtraidos.direccion }
          };
        }
        
        // Si no detectamos zona, es una pregunta contextual
        return {
          accion: "PREGUNTA_CONTEXTUAL",
          datos: { contexto: "necesita_zona" }
        };
      }

      // Sub-caso: Tiene plan Y zona, falta datos personales
      if (proceso.plan && proceso.zona && (!proceso.nombre || !proceso.dni || !proceso.direccion)) {
        // Intentar extraer datos del mensaje
        const datosExtraidos = await this.extraerDatosCompletosContratacion(mensaje);
        
        if (datosExtraidos.nombre || datosExtraidos.dni || datosExtraidos.direccion) {
          return {
            accion: "DATOS_EXTRAIDOS",
            datos: datosExtraidos
          };
        }
        
        // Si no hay datos, es conversación
        return {
          accion: "PREGUNTA_CONTEXTUAL",
          datos: { contexto: "necesita_datos" }
        };
      }
    }

    // CASO 9: Cliente en flujo de SOPORTE
    if (proceso?.flujo === "soporte") {
      // Si ya tiene nombre y documento, escalar directamente
      if (proceso.nombre_escalamiento && proceso.documento_escalamiento) {
        return {
          accion: "ESCALAR_CONSULTA",
          datos: { 
            motivo: mensaje,
            nombre: proceso.nombre_escalamiento,
            documento: proceso.documento_escalamiento
          }
        };
      }
      
      // Si no tiene datos, pedirlos primero
      if (!proceso.solicitando_datos_escalamiento) {
        return {
          accion: "SOLICITAR_DATOS_ESCALAMIENTO",
          datos: {}
        };
      }
      
      // Si ya solicitó datos, procesarlos
      return {
        accion: "PROCESAR_DATOS_ESCALAMIENTO",
        datos: { mensaje }
      };
    }

    // CASO 10: Cliente en flujo de PAGOS
    if (proceso?.flujo === "pagos") {
      // Detectar si dice "ya pagué"
      if (msgTrim.match(/ya\s+(pag|yap|transfer)/)) {
        return { accion: "SOLICITAR_COMPROBANTE", datos: {} };
      }
    }

    // CASO DEFAULT: Conversación general
    return {
      accion: "CONVERSACION_GENERAL",
      datos: { mensaje }
    };
  }

  // ============================================
  // EJECUTOR DE ACCIONES TÉCNICAS
  // ============================================

  async ejecutarAccionTecnica(intencion, numero, mensaje, proceso) {
    switch (intencion.accion) {
      case "MOSTRAR_MENU":
        return await this.accionMostrarMenu(numero);

      case "INICIAR_VENTAS":
        return await this.accionIniciarVentas(numero);

      case "INICIAR_SOPORTE":
        return await this.accionIniciarSoporte(numero);

      case "INICIAR_PAGOS":
        return await this.accionIniciarPagos(numero);

      case "PLAN_SELECCIONADO":
        return await this.accionPlanSeleccionado(numero, intencion.datos.plan);

      case "VERIFICAR_COBERTURA":
        return await this.accionVerificarCobertura(numero, intencion.datos.zona, intencion.datos.direccion, intencion.datos.mensaje);

      case "DATOS_EXTRAIDOS":
        return await this.accionProcesarDatos(numero, intencion.datos);

      case "SELECCIONAR_DIA":
        return await this.accionSeleccionarDia(numero, intencion.datos.opcion, proceso);

      case "SELECCIONAR_HORA":
        return await this.accionSeleccionarHora(numero, intencion.datos.opcion, proceso);

      case "CONFIRMAR_DATOS":
        return await this.accionConfirmarDatos(numero, intencion.datos.respuesta, proceso);

      case "SOLICITAR_DATOS_ESCALAMIENTO":
        return await this.accionSolicitarDatosEscalamiento(numero);

      case "PROCESAR_DATOS_ESCALAMIENTO":
        return await this.accionProcesarDatosEscalamiento(numero, intencion.datos.mensaje);

      case "ESCALAR_CONSULTA":
        return await this.accionEscalarConsulta(numero, intencion.datos.motivo, intencion.datos.nombre, intencion.datos.documento);

      case "SOLICITAR_COMPROBANTE":
        return await this.accionSolicitarComprobante(numero);

      case "PROCESAR_DATOS_PAGO":
        return await this.accionProcesarDatosPago(numero, intencion.datos.mensaje, proceso);

      case "ESPERANDO_IMAGEN":
        return {
          respuesta: "Esperando que envíes la imagen del comprobante...",
          tipo: "esperando_imagen"
        };

      case "PREGUNTA_CONTEXTUAL":
        return await this.accionPreguntaContextual(numero, mensaje, intencion.datos.contexto);

      case "CONVERSACION_GENERAL":
        return await this.accionConversacionGeneral(numero, mensaje);

      default:
        return {
          respuesta: "¿En qué puedo ayudarte?",
          tipo: "error"
        };
    }
  }

  // Segunda Parte

  // ============================================
  // ACCIONES TÉCNICAS ESPECÍFICAS
  // ============================================

  async accionMostrarMenu(numero) {
    // NO crear proceso aquí, ya se creó en detectarIntencion
    
    const nombreNegocio = this.infoNegocio.nombre_negocio || 'nuestro negocio';
    
    const contexto = {
      nombre_negocio: nombreNegocio,
      tiene_planes: this.planes.length > 0,
      tiene_metodos_pago: this.infoNegocio.metodos_pago_array?.length > 0
    };

    const respuesta = await this.generarRespuestaConIA(
      "MENU_INICIAL",
      contexto,
      numero,
      ""
    );

    return { respuesta, tipo: "menu_inicial" };
  }

  async accionIniciarVentas(numero) {
    let proceso = this.procesosActivos.get(numero);
    
    // Actualizar estado del proceso existente
    proceso.flujo = "ventas";
    proceso.estado = "seleccionando_plan";
    proceso.ultimaActividad = Date.now();
    
    this.procesosActivos.set(numero, proceso);

    if (this.planes.length === 0) {
      return {
        respuesta: "No tenemos planes disponibles en este momento. Contáctanos directamente.",
        tipo: "sin_planes"
      };
    }

    const respuesta = await this.generarRespuestaConIA(
      "MOSTRAR_PLANES",
      { planes: this.planes, moneda: this.moneda },
      numero,
      ""
    );

    return { respuesta, tipo: "lista_planes" };
  }

  async accionIniciarSoporte(numero) {
    let proceso = this.procesosActivos.get(numero);
    
    // Actualizar estado del proceso existente
    proceso.flujo = "soporte";
    proceso.estado = "describiendo_problema";
    proceso.ultimaActividad = Date.now();
    
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "INICIAR_SOPORTE",
      {},
      numero,
      ""
    );

    return { respuesta, tipo: "iniciar_soporte" };
  }

  async accionSolicitarDatosEscalamiento(numero) {
    let proceso = this.procesosActivos.get(numero);
    proceso.solicitando_datos_escalamiento = true;
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "SOLICITAR_DATOS_ESCALAMIENTO",
      {},
      numero,
      ""
    );

    return { respuesta, tipo: "solicitar_datos_escalamiento" };
  }

  async accionProcesarDatosEscalamiento(numero, mensaje) {
    let proceso = this.procesosActivos.get(numero);

    const datosExtraidos = await this.extraerDatosPersonales(mensaje);

    if (!datosExtraidos.nombre || !datosExtraidos.dni) {
      return {
        respuesta: "No pude identificar tu nombre o documento. Por favor intenta así:\n\nJuan Pérez, DNI 12345678",
        tipo: "datos_invalidos"
      };
    }

    proceso.nombre_escalamiento = datosExtraidos.nombre;
    proceso.documento_escalamiento = datosExtraidos.dni;
    proceso.tipo_documento = datosExtraidos.tipo_documento || "Documento";
    this.procesosActivos.set(numero, proceso);

    // Escalar automáticamente después de obtener datos
    return await this.accionEscalarConsulta(
      numero, 
      "Problema técnico reportado", 
      datosExtraidos.nombre,
      datosExtraidos.dni
    );
  }

  async accionEscalarConsulta(numero, motivo, nombreCliente = null, documentoCliente = null) {
    const numeroLimpio = numero.replace("@c.us", "");

    // Preparar notas con los datos del cliente si existen
    let notasEscalamiento = motivo;
    if (nombreCliente && documentoCliente) {
      notasEscalamiento = `Nombre: ${nombreCliente} | Documento: ${documentoCliente} | Motivo: ${motivo}`;
    }

    await db.getPool().execute(
      `INSERT INTO estados_conversacion 
       (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado, notas)
       VALUES (?, ?, 'escalado_humano', NOW(), ?, ?)
       ON DUPLICATE KEY UPDATE 
         estado = 'escalado_humano',
         fecha_escalado = NOW(),
         motivo_escalado = ?,
         notas = ?`,
      [this.empresaId, numero, motivo, notasEscalamiento, motivo, notasEscalamiento]
    );

    await this.notificarEscalamiento(numeroLimpio, motivo, nombreCliente, documentoCliente);

    this.procesosActivos.delete(numero);

    const respuesta = await this.generarRespuestaConIA(
      "ESCALADO",
      {},
      numero,
      ""
    );

    return { respuesta, tipo: "escalado" };
  }

  async accionIniciarPagos(numero) {
    let proceso = this.procesosActivos.get(numero);
    
    // Actualizar estado del proceso existente
    proceso.flujo = "pagos";
    proceso.estado = "consultando_metodos";
    proceso.ultimaActividad = Date.now();
    
    this.procesosActivos.set(numero, proceso);

    if (!this.infoNegocio.metodos_pago_array || this.infoNegocio.metodos_pago_array.length === 0) {
      return await this.accionEscalarConsulta(numero, "Solicita métodos de pago");
    }

    const respuesta = await this.generarRespuestaConIA(
      "MOSTRAR_METODOS_PAGO",
      { metodos_pago: this.infoNegocio.metodos_pago_array },
      numero,
      ""
    );

    return { respuesta, tipo: "metodos_pago" };
  }

  async accionPlanSeleccionado(numero, plan) {
    let proceso = this.procesosActivos.get(numero);
    proceso.plan = plan;
    proceso.estado = "solicitando_zona"; // Cambiar estado
    this.procesosActivos.set(numero, proceso);

    console.log(`✅ Plan guardado: ${plan.producto}, Estado: solicitando_zona`);

    const respuesta = await this.generarRespuestaConIA(
      "SOLICITAR_ZONA",
      { plan: plan.producto },
      numero,
      ""
    );

    return { respuesta, tipo: "solicitar_zona" };
  }

  async accionVerificarCobertura(numero, zona, direccion, mensaje) {
    let proceso = this.procesosActivos.get(numero);
    
    // Si no se pasó zona, intentar extraer del mensaje
    if (!zona && mensaje) {
      const datosExtraidos = await this.extraerZonaYDireccion(mensaje);
      zona = datosExtraidos.zona;
      direccion = datosExtraidos.direccion;
    }
    
    if (!zona) {
      return {
        respuesta: "No pude identificar tu zona. Por favor indica tu dirección y zona.\n\nEjemplo: Jr.comercio 304, Ventanilla",
        tipo: "zona_invalida"
      };
    }
    
    proceso.zona = zona;
    proceso.direccion = direccion || null;
    this.procesosActivos.set(numero, proceso);

    const hayCobertura = this.verificarCobertura(zona);

    if (!hayCobertura) {
      const respuesta = await this.generarRespuestaConIA(
        "SIN_COBERTURA",
        { zona, zonas_disponibles: this.zonasCobertura },
        numero,
        ""
      );

      return { respuesta, tipo: "sin_cobertura" };
    }

    // Cambiar estado a solicitando datos
    proceso.estado = "solicitando_datos";
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "CON_COBERTURA_SOLICITAR_DATOS",
      { zona, plan: proceso.plan?.producto, tiene_direccion: !!direccion },
      numero,
      ""
    );

    return { respuesta, tipo: "solicitar_datos_completos" };
  }

  async accionProcesarDatos(numero, datosExtraidos) {
    let proceso = this.procesosActivos.get(numero);

    // Actualizar datos del proceso
    if (datosExtraidos.nombre) proceso.nombre = datosExtraidos.nombre;
    if (datosExtraidos.dni) proceso.dni = datosExtraidos.dni;
    if (datosExtraidos.direccion && !proceso.direccion) {
      proceso.direccion = datosExtraidos.direccion;
    }

    this.procesosActivos.set(numero, proceso);

    // Verificar qué datos faltan
    const datosFaltantes = [];
    if (!proceso.nombre) datosFaltantes.push("nombre");
    if (!proceso.dni) datosFaltantes.push("documento");
    if (!proceso.direccion) datosFaltantes.push("dirección");

    if (datosFaltantes.length > 0) {
      const respuesta = await this.generarRespuestaConIA(
        "DATOS_INCOMPLETOS",
        { 
          datos_recibidos: datosExtraidos,
          datos_faltantes: datosFaltantes
        },
        numero,
        ""
      );

      return { respuesta, tipo: "solicitar_datos_faltantes" };
    }

    // Todos los datos completos → Mostrar resumen
    return await this.mostrarResumenContratacion(numero, proceso);
  }

  async mostrarResumenContratacion(numero, proceso) {
    proceso.esperando_confirmacion_final = true;
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "RESUMEN_CONTRATACION",
      {
        plan: proceso.plan,
        nombre: proceso.nombre,
        dni: proceso.dni,
        direccion: proceso.direccion,
        moneda: this.moneda
      },
      numero,
      ""
    );

    return { respuesta, tipo: "confirmar_datos" };
  }

  async accionConfirmarDatos(numero, respuesta, proceso) {
    if (respuesta.match(/^(si|sí|yes|ok|confirmo|correcto|dale|listo)$/)) {
      return await this.iniciarAgendamientoDias(numero, proceso);
    }

    if (respuesta.match(/^(no|cancelar)$/)) {
      this.procesosActivos.delete(numero);
      
      const msg = await this.generarRespuestaConIA(
        "CANCELACION",
        {},
        numero,
        ""
      );

      return { respuesta: msg, tipo: "cancelado" };
    }

    return {
      respuesta: "Responde SÍ para confirmar o NO para cancelar.",
      tipo: "respuesta_invalida"
    };
  }

  async iniciarAgendamientoDias(numero, proceso) {
    const diasDisponibles = await this.obtenerDiasDisponibles();
    
    if (diasDisponibles.length === 0) {
      return {
        respuesta: "No hay disponibilidad en este momento. Contáctanos directamente.",
        tipo: "sin_disponibilidad"
      };
    }

    proceso.diasDisponibles = diasDisponibles;
    proceso.esperando_seleccion_dia = true;
    proceso.esperando_confirmacion_final = false;
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "MOSTRAR_DIAS_DISPONIBLES",
      { dias: diasDisponibles },
      numero,
      ""
    );

    return { respuesta, tipo: "seleccion_fecha" };
  }

  async accionSeleccionarDia(numero, opcion, proceso) {
    const num = parseInt(opcion);

    if (isNaN(num) || num < 1 || num > proceso.diasDisponibles.length) {
      return {
        respuesta: `Escribe el número (1 al ${proceso.diasDisponibles.length})`,
        tipo: "seleccion_invalida"
      };
    }

    const diaSeleccionado = proceso.diasDisponibles[num - 1];
    proceso.fecha = diaSeleccionado.fecha;
    proceso.diaSemana = diaSeleccionado.diaSemana;
    proceso.esperando_seleccion_dia = false;
    proceso.esperando_seleccion_hora = true;
    
    this.procesosActivos.set(numero, proceso);

    return await this.mostrarHorariosDisponibles(numero, proceso);
  }

  async mostrarHorariosDisponibles(numero, proceso) {
    const horarioDelDia = this.horarios.find(h => h.dia_semana === proceso.diaSemana);

    if (!horarioDelDia) {
      return {
        respuesta: "No hay horario configurado para este día.",
        tipo: "error_horario"
      };
    }

    const slotsDisponibles = await this.generarSlotsDisponibles(
      proceso.fecha,
      horarioDelDia,
      30
    );

    if (slotsDisponibles.length === 0) {
      return {
        respuesta: "No hay horarios disponibles para este día.",
        tipo: "sin_horarios"
      };
    }

    proceso.slotsDisponibles = slotsDisponibles;
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "MOSTRAR_HORARIOS_DISPONIBLES",
      { 
        fecha: moment(proceso.fecha).format("dddd D [de] MMMM"),
        horarios: slotsDisponibles 
      },
      numero,
      ""
    );

    return { respuesta, tipo: "seleccion_hora" };
  }

  async accionSeleccionarHora(numero, opcion, proceso) {
    const num = parseInt(opcion);

    if (isNaN(num) || num < 1 || num > proceso.slotsDisponibles.length) {
      return {
        respuesta: `Escribe el número (1 al ${proceso.slotsDisponibles.length})`,
        tipo: "hora_invalida"
      };
    }

    proceso.hora = proceso.slotsDisponibles[num - 1];
    
    const citaId = await this.guardarCitaBD(numero, proceso);
    
    await this.notificarInstalacion(citaId, proceso, numero);

    this.ventasCompletadas.set(numero, {
      timestamp: Date.now(),
      citaId: citaId
    });

    this.procesosActivos.delete(numero);

    const respuesta = await this.generarRespuestaConIA(
      "CITA_CONFIRMADA",
      {
        citaId,
        plan: proceso.plan.producto,
        fecha: moment(proceso.fecha).format("dddd D [de] MMMM"),
        hora: proceso.hora
      },
      numero,
      ""
    );

    return { respuesta, tipo: "cita_confirmada", citaId };
  }

  async accionSolicitarComprobante(numero) {
    let proceso = this.procesosActivos.get(numero) || {
      flujo: "pagos",
      ultimaActividad: Date.now()
    };

    proceso.estado = "esperando_comprobante_imagen";
    this.procesosActivos.set(numero, proceso);

    const respuesta = await this.generarRespuestaConIA(
      "SOLICITAR_COMPROBANTE",
      {},
      numero,
      ""
    );

    return { respuesta, tipo: "solicitar_comprobante" };
  }

  async accionProcesarDatosPago(numero, mensaje, proceso) {
    const datosExtraidos = await this.extraerDatosPersonales(mensaje);

    if (!datosExtraidos.nombre || !datosExtraidos.dni) {
      return {
        respuesta: "No pude identificar tu nombre o documento. Intenta así:\n\nJuan Pérez, DNI 12345678",
        tipo: "datos_invalidos"
      };
    }

    proceso.nombre_pago = datosExtraidos.nombre;
    proceso.dni_pago = datosExtraidos.dni;
    proceso.tipo_documento = datosExtraidos.tipo_documento || "Documento";

    await this.escalarYNotificarPago(numero, proceso);

    this.procesosActivos.delete(numero);

    const respuesta = await this.generarRespuestaConIA(
      "PAGO_RECIBIDO",
      {},
      numero,
      ""
    );

    return { respuesta, tipo: "pago_escalado" };
  }

  async accionPreguntaContextual(numero, mensaje, contexto) {
    const proceso = this.procesosActivos.get(numero);

    const respuesta = await this.generarRespuestaConIA(
      "PREGUNTA_CONTEXTUAL",
      {
        pregunta: mensaje,
        contexto_flujo: contexto,
        proceso: proceso
      },
      numero,
      mensaje
    );

    return { 
      respuesta, 
      tipo: "pregunta_contextual",
      tokens: 50 
    };
  }

  async accionConversacionGeneral(numero, mensaje) {
    const respuesta = await this.generarRespuestaConIA(
      "CONVERSACION_GENERAL",
      { mensaje },
      numero,
      mensaje
    );

    return { 
      respuesta, 
      tipo: "conversacion_general",
      tokens: 50 
    };
  }

  // ============================================
  // GENERADOR DE RESPUESTAS CON IA
  // ============================================

  async generarRespuestaConIA(tipoRespuesta, contexto, numero, mensajeCliente) {
    try {
      let prompt = this.construirPromptParaTipoRespuesta(tipoRespuesta, contexto);

      // Si es conversación, agregar historial
      if (tipoRespuesta === "PREGUNTA_CONTEXTUAL" || tipoRespuesta === "CONVERSACION_GENERAL") {
        const historial = await this.botHandler.getContexto(numero);
        
        const messages = [{ role: "system", content: prompt }];

        historial.slice(-3).forEach((c) => {
          messages.push({ role: "user", content: c.mensaje_cliente });
          if (c.respuesta_bot) {
            messages.push({ role: "assistant", content: c.respuesta_bot });
          }
        });

        messages.push({ role: "user", content: mensajeCliente });

        const response = await axios.post(
          "https://api.openai.com/v1/chat/completions",
          {
            model: this.botHandler.globalConfig.openai_modelo || "gpt-3.5-turbo",
            messages: messages,
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

        return response.data.choices[0].message.content.trim();
      }

      // Para respuestas estructuradas, generar directamente
      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: this.botHandler.globalConfig.openai_modelo || "gpt-3.5-turbo",
          messages: [
            { role: "system", content: prompt },
            { role: "user", content: "Genera la respuesta" }
          ],
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

      return response.data.choices[0].message.content.trim();
    } catch (error) {
      console.error("Error generando respuesta con IA:", error);
      return this.getRespuestaFallback(tipoRespuesta, contexto);
    }
  }

  construirPromptParaTipoRespuesta(tipo, contexto) {
    const nombreNegocio = this.infoNegocio.nombre_negocio || "nuestro negocio";

    switch (tipo) {
      case "MENU_INICIAL":
        return `Genera un saludo amigable y muestra el menú de opciones.

Nombre del negocio: ${nombreNegocio}

Opciones disponibles:
${contexto.tiene_planes ? "1️⃣ Planes y servicios" : ""}
2️⃣ Soporte técnico
${contexto.tiene_metodos_pago ? "3️⃣ Consultar pagos" : ""}

Incluye: Saludo + "¿En qué puedo ayudarte?" + Opciones numeradas + "Escribe el número"

Máximo 100 palabras, tono amigable.`;

      case "MOSTRAR_PLANES":
        let planes = "";
        contexto.planes.forEach((plan, index) => {
          planes += `${index + 1}. ${plan.producto} - ${contexto.moneda.simbolo}${plan.precio}/mes`;
          if (plan.descripcion) planes += ` (${plan.descripcion})`;
          planes += "\n";
        });

        return `Presenta los planes disponibles de forma atractiva.

PLANES:
${planes}

FORMATO OBLIGATORIO:
- Título llamativo
- Lista numerada de planes
- "Escribe el *número* del plan que te interesa" (DEBE estar al final)

Tono: Vendedor amigable, máximo 120 palabras.`;

      case "SOLICITAR_ZONA":
        return `El cliente eligió el plan: ${contexto.plan}

Genera una respuesta que:
1. Confirme su elección brevemente
2. Pregunte dirección y zona juntas

Ejemplo: "Perfecto, el plan de [velocidad]. ¿cual es la dirección y zona en la que te encuentras?\n\nEjemplo: Comercio 304, Ventanilla"

Tono natural, máximo 40 palabras.`;

      case "CON_COBERTURA_SOLICITAR_DATOS":
        let msgDatos = `Confirmamos cobertura en ${contexto.zona} para el plan ${contexto.plan}.

Genera respuesta que:`;
        
        if (contexto.tiene_direccion) {
          msgDatos += `
1. Confirme que hay cobertura
2. Solicite SOLO: nombre completo y DNI

Ejemplo: "Tenemos cobertura en ${contexto.zona}. Para continuar necesito tu nombre completo y DNI, cédula o documento de identidad."`;
        } else {
          msgDatos += `
1. Confirme que hay cobertura
2. Solicite: dirección completa, nombre y DNI

Ejemplo: "Tenemos cobertura en ${contexto.zona}. Para continuar necesito tu dirección completa, nombre y DNI. Puedes enviarlo todo junto."`;
        }
        
        msgDatos += `\n\nMáximo 50 palabras.`;
        return msgDatos;

      case "SIN_COBERTURA":
        let zonasDisp = "";
        if (contexto.zonas_disponibles.length > 0) {
          zonasDisp = `\n\nAtendemos en: ${contexto.zonas_disponibles.join(", ")}`;
        }

        return `No hay cobertura en ${contexto.zona}.${zonasDisp}

Genera disculpa empática + zonas disponibles si existen.

Máximo 60 palabras.`;

      case "DATOS_INCOMPLETOS":
        let faltantes = contexto.datos_faltantes.join(", ");
        
        return `Cliente envió datos pero faltan: ${faltantes}

Genera respuesta natural que:
1. Confirme lo recibido
2. Solicite lo faltante

Ejemplo: "Perfecto, ya tengo [datos recibidos]. Solo me falta tu [datos faltantes]."

Máximo 40 palabras.`;

      case "RESUMEN_CONTRATACION":
        return `Genera resumen de contratación para confirmación.

Plan: ${contexto.plan.producto}
Precio: ${contexto.moneda.simbolo}${contexto.plan.precio}/mes
Nombre: ${contexto.nombre}
DNI: ${contexto.dni}
Dirección: ${contexto.direccion}

Incluye:
- Título "RESUMEN"
- Datos listados
- "¿Todo correcto? Responde SÍ para agendar instalación"

Máximo 150 palabras.`;

      case "MOSTRAR_DIAS_DISPONIBLES":
        let dias = "";
        contexto.dias.forEach((dia, index) => {
          dias += `${index + 1}. ${dia.display}\n`;
        });

        return `Muestra días disponibles para instalación.

DÍAS:
${dias}

FORMATO OBLIGATORIO:
"📅 Días disponibles:\n\n[lista]\n\nEscribe el *número* del día que prefieres"

Máximo 150 palabras.`;

      case "MOSTRAR_HORARIOS_DISPONIBLES":
        let horarios = "";
        contexto.horarios.forEach((h, index) => {
          horarios += `${index + 1}. ${h}\n`;
        });

        return `Muestra horarios para ${contexto.fecha}.

HORARIOS:
${horarios}

FORMATO OBLIGATORIO:
"🕐 Horarios disponibles - [fecha]:\n\n[lista]\n\nEscribe el *número* de tu horario preferido"

Máximo 100 palabras.`;

      case "CITA_CONFIRMADA":
        return `Instalación agendada exitosamente.

Cita #${contexto.citaId}
Plan: ${contexto.plan}
Fecha: ${contexto.fecha}
Hora: ${contexto.hora}

Genera mensaje de confirmación entusiasta + "Te esperamos"

Máximo 100 palabras.`;

      case "INICIAR_SOPORTE":
        return `Cliente eligió soporte técnico.

Genera pregunta natural: "¿Qué problema técnico tienes con tu servicio?"

Tono empático, máximo 20 palabras.`;

      case "SOLICITAR_DATOS_ESCALAMIENTO":
        return `Cliente necesita soporte de un asesor humano.

Genera respuesta que:
1. Informe que lo conectará con un asesor
2. Solicite nombre completo y documento de identidad

Ejemplo: "Entiendo. Te conectaré con un asesor. Por favor indícame tu nombre completo y número de documento.\n\nEjemplo: Juan Pérez, DNI 12345678"

Tono empático, máximo 40 palabras.`;

      case "MOSTRAR_METODOS_PAGO":
        let metodos = "";
        contexto.metodos_pago.forEach(m => {
          metodos += `${m.tipo}: ${m.dato}\n`;
          if (m.instruccion) metodos += `${m.instruccion}\n`;
        });

        return `Muestra métodos de pago disponibles.

MÉTODOS:
${metodos}

Incluye: Título + Lista + "Una vez que pagues, escribe 'ya pagué' y envía tu comprobante"

Máximo 120 palabras.`;

      case "SOLICITAR_COMPROBANTE":
        return `Cliente dice que ya pagó.

Genera: "Perfecto. Envíame la foto o captura de tu comprobante de pago."

Máximo 20 palabras.`;

      case "PAGO_RECIBIDO":
        return `Comprobante recibido para validación.

Genera: "Comprobante recibido. Validaremos tu pago y te confirmaremos pronto."

Máximo 20 palabras.`;

      case "ESCALADO":
        return `Consulta escalada a asesor humano.

Genera: "Un asesor te atenderá en breve."

Máximo 15 palabras.`;

      case "CANCELACION":
        return `Cliente canceló proceso.

Genera: "Solicitud cancelada. ¿Te ayudo con algo más?"

Máximo 15 palabras.`;

      case "DESPEDIDA_POST_VENTA":
        return `Cliente ya completó su compra/contratación.

Genera despedida amigable aleatoria: "Nos vemos", "Hasta pronto", "Gracias"

Máximo 10 palabras.`;

      case "PREGUNTA_CONTEXTUAL":
        return `Eres asistente de ${nombreNegocio}.

Cliente está en proceso de: ${contexto.contexto_flujo}

CONTEXTO ACTUAL:
${JSON.stringify(contexto.proceso, null, 2)}

Responde la pregunta del cliente de forma natural, considerando el contexto del flujo.

Tono amigable, máximo ${this.maxTokens} palabras.`;

      case "CONVERSACION_GENERAL":
        return `Eres asistente de ${nombreNegocio}.

Responde de forma natural y útil.

Tono amigable, máximo ${this.maxTokens} palabras.`;

      default:
        return "Genera una respuesta amigable y útil.";
    }
  }

  getRespuestaFallback(tipo, contexto) {
    switch (tipo) {
      case "MENU_INICIAL":
        return `Hola, soy el asistente de ${this.infoNegocio.nombre_negocio || "nuestro negocio"}\n\n¿En qué puedo ayudarte?\n\n1️⃣ Planes y servicios\n2️⃣ Soporte técnico\n3️⃣ Consultar pagos\n\nEscribe el número.`;
      
      case "SOLICITAR_ZONA":
        return "¿En qué zona estás?";
      
      case "ESCALADO":
        return "Un asesor te atenderá en breve.";
      
      default:
        return "¿En qué puedo ayudarte?";
    }
  }

  // tercera parte

  // ============================================
  // MÉTODOS AUXILIARES DE DETECCIÓN
  // ============================================

  async detectarPlanSeleccionado(mensaje, msgTrim) {
    // Detectar por número directo (1, 2)
    const num = parseInt(msgTrim);
    if (!isNaN(num) && num > 0 && num <= this.planes.length) {
      return this.planes[num - 1];
    }

    // Detectar por velocidad (50 mbps, 20 mbps)
    const matchVelocidad = mensaje.toLowerCase().match(/(\d+)\s*mbps/);
    if (matchVelocidad) {
      const velocidad = matchVelocidad[1];
      const plan = this.planes.find(p => p.producto.toLowerCase().includes(velocidad));
      if (plan) return plan;
    }

    // Detectar "el 1", "el 2", "plan 1"
    const matchOpcion = mensaje.toLowerCase().match(/(?:el\s+)?(?:plan\s+)?([12])/);
    if (matchOpcion) {
      const numeroPlan = parseInt(matchOpcion[1]);
      if (numeroPlan > 0 && numeroPlan <= this.planes.length) {
        return this.planes[numeroPlan - 1];
      }
    }

    return null;
  }

  async extraerZonaYDireccion(mensaje) {
    try {
      const mensajeLower = mensaje.toLowerCase();
      
      // Buscar zona conocida en las zonas de cobertura
      let zonaEncontrada = null;
      for (const zona of this.zonasCobertura) {
        if (mensajeLower.includes(zona)) {
          zonaEncontrada = zona;
          break;
        }
      }

      // Extraer dirección (todo lo que tenga Jr, Av, Calle, Mz, Lt, Psje, etc)
      const matchDireccion = mensaje.match(/(jr\.?|av\.?|avenida|calle|psje\.?|pasaje|mz\.?|lt\.?|lote|manzana).+/i);
      const direccion = matchDireccion ? matchDireccion[0].trim() : null;

      // Si no encontró zona directamente, usar GPT para extraerla
      if (!zonaEncontrada) {
        const prompt = `Extrae SOLO la zona o distrito del mensaje. Responde una palabra en minúsculas o "desconocido".

Mensaje: "${mensaje}"

Ejemplos:
"Ventanilla, Jr. Comercio 304" → ventanilla
"estoy en callao" → callao
"mi perú" → mi perú

Responde solo la zona:`;

        const response = await axios.post(
          "https://api.openai.com/v1/chat/completions",
          {
            model: "gpt-3.5-turbo",
            messages: [{ role: "user", content: prompt }],
            temperature: 0.0,
            max_tokens: 10,
          },
          {
            headers: {
              Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
              "Content-Type": "application/json",
            },
          }
        );

        zonaEncontrada = response.data.choices[0].message.content.trim().toLowerCase();
      }

      return {
        zona: zonaEncontrada !== "desconocido" ? zonaEncontrada : null,
        direccion: direccion
      };
    } catch (error) {
      console.error("Error extrayendo dirección y zona:", error);
      return { zona: null, direccion: null };
    }
  }

  verificarCobertura(zona) {
    if (this.zonasCobertura.length === 0) {
      return true;
    }

    const zonaLower = zona.toLowerCase();
    return this.zonasCobertura.some(z => zonaLower.includes(z) || z.includes(zonaLower));
  }

  async extraerDatosCompletosContratacion(mensaje) {
    try {
      const prompt = `Extrae datos del mensaje. Responde SOLO JSON válido.

Mensaje: "${mensaje}"

JSON:
{
  "nombre": "string o null",
  "dni": "string o null",
  "direccion": "string o null"
}

REGLAS:
- Dirección: Contiene "Jr", "Av", "Calle" o números de calle
- DNI: 8 dígitos consecutivos
- Nombre: Texto sin números ni direcciones

Responde SOLO JSON.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 150,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const contenido = response.data.choices[0].message.content.trim();
      return JSON.parse(contenido);
    } catch (error) {
      console.error("Error extrayendo datos:", error);
      return { nombre: null, dni: null, direccion: null };
    }
  }

  async extraerDatosPersonales(mensaje) {
    try {
      const prompt = `Extrae nombre y documento.

Mensaje: "${mensaje}"

JSON:
{
  "nombre": "string o null",
  "dni": "string o null",
  "tipo_documento": "DNI|Cédula|Pasaporte"
}

Responde SOLO JSON.`;

      const response = await axios.post(
        "https://api.openai.com/v1/chat/completions",
        {
          model: "gpt-3.5-turbo",
          messages: [{ role: "user", content: prompt }],
          temperature: 0.0,
          max_tokens: 100,
        },
        {
          headers: {
            Authorization: `Bearer ${this.botHandler.globalConfig.openai_api_key}`,
            "Content-Type": "application/json",
          },
        }
      );

      const contenido = response.data.choices[0].message.content.trim();
      return JSON.parse(contenido);
    } catch (error) {
      console.error("Error extrayendo datos:", error);
      return { nombre: null, dni: null, tipo_documento: null };
    }
  }

  // ============================================
  // MÉTODOS AUXILIARES DE BASE DE DATOS
  // ============================================

  async obtenerDiasDisponibles() {
    const dias = [];
    for (let i = 1; i <= 30; i++) {
      const fecha = moment().add(i, "days");
      const diaSemana = fecha.isoWeekday();

      const horarioDelDia = this.horarios.find(h => h.dia_semana === diaSemana);

      if (horarioDelDia) {
        dias.push({
          fecha: fecha.format("YYYY-MM-DD"),
          display: fecha.format("dddd D [de] MMMM"),
          diaSemana: diaSemana,
        });
      }

      if (dias.length >= 7) break;
    }

    return dias;
  }

  async generarSlotsDisponibles(fecha, horario, duracionServicio) {
    const slots = [];
    const horaInicio = moment(fecha + " " + horario.hora_inicio);
    const horaFin = moment(fecha + " " + horario.hora_fin);
    const duracionSlot = horario.duracion_cita;

    const [citasExistentes] = await db.getPool().execute(
      `SELECT hora_cita FROM citas_bot 
       WHERE empresa_id = ? AND fecha_cita = ? 
       AND estado IN ('agendada', 'confirmada')
       ORDER BY hora_cita`,
      [this.empresaId, fecha]
    );

    const horasOcupadas = citasExistentes.map(c => c.hora_cita.substring(0, 5));

    let horaActual = horaInicio.clone();

    while (horaActual.isBefore(horaFin)) {
      const horaFormato = horaActual.format("HH:mm");

      if (!horasOcupadas.includes(horaFormato)) {
        const tiempoRestante = horaFin.diff(horaActual, "minutes");
        if (tiempoRestante >= duracionServicio) {
          slots.push(horaFormato);
        }
      }

      horaActual.add(duracionSlot, "minutes");
    }

    return slots;
  }

  async guardarCitaBD(numero, proceso) {
    const [result] = await db.getPool().execute(
      `INSERT INTO citas_bot 
       (empresa_id, numero_cliente, nombre_cliente, dni_cedula, fecha_cita, hora_cita, 
        tipo_servicio, estado, direccion_completa)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'agendada', ?)`,
      [
        this.empresaId,
        numero,
        proceso.nombre,
        proceso.dni,
        proceso.fecha,
        proceso.hora + ":00",
        `Instalación ${proceso.plan.producto}`,
        proceso.direccion,
      ]
    );

    return result.insertId;
  }

  // ============================================
  // NOTIFICACIONES
  // ============================================

  async notificarInstalacion(citaId, proceso, numero) {
    try {
      if (!this.notificaciones.notificar_citas) return;

      let numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
      if (!Array.isArray(numeros) || numeros.length === 0) return;

      // Construir dirección completa (dirección + zona)
      let direccionCompleta = proceso.direccion || "";
      if (proceso.zona) {
        direccionCompleta += (direccionCompleta ? ", " : "") + proceso.zona.charAt(0).toUpperCase() + proceso.zona.slice(1);
      }

      // USAR PLANTILLA DE LA BD con placeholders
      let msg = this.notificaciones.mensaje_citas || "📅 Nueva cita agendada";

      // Reemplazar todos los placeholders
      msg = msg
        .replace(/{nombre_cliente}/g, proceso.nombre || "N/A")
        .replace(/{descripcion_problema}/g, `Instalación ${proceso.plan?.producto || "servicio"}`)
        .replace(/{fecha_cita}/g, moment(proceso.fecha).format("DD/MM/YYYY"))
        .replace(/{hora_cita}/g, proceso.hora)
        .replace(/{direccion}/g, direccionCompleta)
        .replace(/{numero_ticket}/g, citaId)
        .replace(/{telefono}/g, numero.replace("@c.us", ""))
        .replace(/{fecha_hora}/g, new Date().toLocaleString("es-PE"))
        .replace(/{plan_contratado}/g, proceso.plan?.producto || "N/A")
        .replace(/{velocidad}/g, proceso.plan?.producto || "N/A")
        .replace(/{precio}/g, proceso.plan?.precio ? `${this.moneda.simbolo}${proceso.plan.precio}` : "N/A")
        .replace(/{fecha_instalacion}/g, moment(proceso.fecha).format("DD/MM/YYYY"))
        .replace(/{numero_cliente_empresa}/g, "N/A");

      for (const num of numeros) {
        try {
          let numeroLimpio = num.replace(/[^\d]/g, "");
          if (!numeroLimpio.includes("@")) {
            numeroLimpio = `${numeroLimpio}@c.us`;
          }

          if (this.botHandler.whatsappClient?.client?.client?.sendText) {
            await this.botHandler.whatsappClient.client.client.sendText(numeroLimpio, msg);
          } else if (this.botHandler.whatsappClient?.client?.sendText) {
            await this.botHandler.whatsappClient.client.sendText(numeroLimpio, msg);
          }

          console.log(`✅ Notificación de instalación enviada a ${num}`);
        } catch (error) {
          console.error(`Error notificando instalación a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("Error en notificarInstalacion:", error);
    }
  }

  async notificarEscalamiento(numeroCliente, motivo, nombreCliente = null, documentoCliente = null) {
    try {
      if (!this.notificaciones.notificar_escalamiento) return;

      let numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
      if (!Array.isArray(numeros) || numeros.length === 0) return;

      // USAR PLANTILLA DE LA BD
      let msg = this.notificaciones.mensaje_escalamiento || "🔔 Consulta escalada";

      // Si no tiene nombre del cliente, buscar en contactos
      if (!nombreCliente) {
        try {
          const [contacto] = await db.getPool().execute(
            "SELECT nombre FROM contactos WHERE numero = ? AND empresa_id = ? LIMIT 1",
            [numeroCliente, this.empresaId]
          );
          if (contacto[0]) {
            nombreCliente = contacto[0].nombre;
          }
        } catch (e) {
          console.error("Error buscando contacto:", e);
        }
      }

      // Reemplazar placeholders
      msg = msg
        .replace(/{nombre_cliente}/g, nombreCliente || numeroCliente)
        .replace(/{numero_cliente_empresa}/g, "N/A")
        .replace(/{motivo_escalamiento}/g, motivo)
        .replace(/{telefono}/g, numeroCliente)
        .replace(/{fecha_hora}/g, new Date().toLocaleString("es-PE"));

      for (const num of numeros) {
        try {
          let numeroNotificar = num.replace(/[^\d]/g, "");
          if (!numeroNotificar.includes("@")) {
            numeroNotificar = `${numeroNotificar}@c.us`;
          }

          if (this.botHandler.whatsappClient?.client?.client?.sendText) {
            await this.botHandler.whatsappClient.client.client.sendText(numeroNotificar, msg);
          } else if (this.botHandler.whatsappClient?.client?.sendText) {
            await this.botHandler.whatsappClient.client.sendText(numeroNotificar, msg);
          }

          console.log(`✅ Notificación de escalamiento enviada a ${num}`);
        } catch (error) {
          console.error(`Error notificando escalamiento a ${num}:`, error.message);
        }
      }
    } catch (error) {
      console.error("Error en notificarEscalamiento:", error);
    }
  }

  async escalarYNotificarPago(numero, proceso) {
    const numeroLimpio = numero.replace("@c.us", "");

    const notasEscalamiento = `Nombre: ${proceso.nombre_pago} | ${proceso.tipo_documento}: ${proceso.dni_pago} | Comprobante: ${proceso.comprobante_path}`;
    
    await db.getPool().execute(
      `INSERT INTO estados_conversacion 
       (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado, notas)
       VALUES (?, ?, 'escalado_humano', NOW(), 'Validación de pago', ?)
       ON DUPLICATE KEY UPDATE 
         estado = 'escalado_humano',
         fecha_escalado = NOW(),
         motivo_escalado = 'Validación de pago',
         notas = ?`,
      [this.empresaId, numero, notasEscalamiento, notasEscalamiento]
    );

    if (!this.notificaciones.notificar_escalamiento) return;

    let numeros = JSON.parse(this.notificaciones.numeros_notificacion || "[]");
    if (!Array.isArray(numeros) || numeros.length === 0) return;

    // USAR PLANTILLA DE LA BD
    let msg = this.notificaciones.mensaje_escalamiento || "🔔 Validación de pago";

    // Reemplazar placeholders
    msg = msg
      .replace(/{nombre_cliente}/g, proceso.nombre_pago || "N/A")
      .replace(/{numero_cliente_empresa}/g, "N/A")
      .replace(/{motivo_escalamiento}/g, "Validación de pago")
      .replace(/{telefono}/g, numeroLimpio)
      .replace(/{fecha_hora}/g, new Date().toLocaleString("es-PE"));

    for (const num of numeros) {
      try {
        let numeroNotificar = num.replace(/[^\d]/g, "");
        if (!numeroNotificar.includes("@")) {
          numeroNotificar = `${numeroNotificar}@c.us`;
        }

        if (this.botHandler.whatsappClient?.client?.client?.sendText) {
          await this.botHandler.whatsappClient.client.client.sendText(numeroNotificar, msg);
        } else if (this.botHandler.whatsappClient?.client?.sendText) {
          await this.botHandler.whatsappClient.client.sendText(numeroNotificar, msg);
        }

        // Enviar comprobante como imagen si existe
        if (proceso.comprobante_path) {
          if (this.botHandler.whatsappClient?.client?.client?.sendImage) {
            await this.botHandler.whatsappClient.client.client.sendImage(
              numeroNotificar, 
              proceso.comprobante_path,
              'comprobante',
              'Comprobante de pago'
            );
          }
        }

        console.log(`✅ Notificación de validación de pago enviada a ${num}`);
      } catch (error) {
        console.error(`Error notificando pago a ${num}:`, error.message);
      }
    }
  }

  // ============================================
  // MANEJO DE IMÁGENES (COMPROBANTES)
  // ============================================

  async manejarComprobanteRecibido(numero, mediaPath) {
    console.log(`📸 Comprobante recibido de ${numero}`);
    
    const proceso = this.procesosActivos.get(numero);
    
    if (!proceso || proceso.estado !== "esperando_comprobante_imagen") {
      return {
        respuesta: "No estaba esperando un comprobante. ¿En qué puedo ayudarte?",
        tipo: "comprobante_inesperado"
      };
    }

    proceso.comprobante_path = mediaPath;
    proceso.estado = "esperando_datos_pago";
    this.procesosActivos.set(numero, proceso);

    return {
      respuesta: "Para validar tu pago, necesito:\n\n• Tu nombre completo\n• Tu documento de identidad\n\nEjemplo: Juan Pérez, DNI 12345678",
      tipo: "solicitar_datos_pago"
    };
  }

  // ============================================
  // LIMPIEZA AUTOMÁTICA
  // ============================================

  limpiarProcesosInactivos() {
    const ahora = Date.now();
    const timeout = 10 * 60 * 1000;

    for (const [numero, proceso] of this.procesosActivos.entries()) {
      if (ahora - proceso.ultimaActividad > timeout) {
        this.procesosActivos.delete(numero);
        console.log(`🧹 Proceso limpiado por inactividad: ${numero}`);
      }
    }
  }

  limpiarVentasCompletadas() {
    const ahora = Date.now();
    const timeout = 3 * 60 * 1000;

    for (const [numero, ventaInfo] of this.ventasCompletadas.entries()) {
      if (ahora - ventaInfo.timestamp > timeout) {
        this.ventasCompletadas.delete(numero);
      }
    }
  }
}

module.exports = SupportBot;
