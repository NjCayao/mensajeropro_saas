// whatsapp-service/src/bots/ventas/intentRouter.js
const db = require('../../database');

class VentasIntentRouter {
    constructor(salesBot) {
        this.salesBot = salesBot; // Referencia al salesBot existente
    }

    /**
     * Ejecuta acción según intención ML (alta confianza)
     */
    async ejecutarAccion(intencion, mensaje, numero, empresaId, contexto) {
        console.log(`🎯 [VentasRouter] Ejecutando: ${intencion}`);

        try {
            switch (intencion) {
                // ===== CONVERSACIONALES =====
                case 'saludo':
                    return await this.manejarSaludo(numero, empresaId);
                
                case 'despedida':
                    return await this.manejarDespedida();
                
                case 'agradecimiento':
                    return await this.manejarAgradecimiento();

                // ===== CONSULTAS =====
                case 'consultar_precio':
                    return await this.consultarPrecio(mensaje, empresaId);
                
                case 'consultar_catalogo':
                    return await this.consultarCatalogo(empresaId);
                
                case 'consultar_disponibilidad':
                    return await this.consultarDisponibilidad(mensaje, empresaId);
                
                case 'consultar_pago':
                    return await this.consultarMetodosPago(empresaId);
                
                case 'consultar_delivery':
                    return await this.consultarDelivery(empresaId);
                
                case 'consultar_promociones':
                    return await this.consultarPromociones(empresaId);

                // ===== CARRITO =====
                case 'agregar_producto':
                    return await this.delegarASalesBot('agregar', mensaje, numero);
                
                case 'ver_carrito':
                    return await this.delegarASalesBot('ver_carrito', mensaje, numero);
                
                case 'modificar_cantidad':
                    return await this.delegarASalesBot('modificar', mensaje, numero);
                
                case 'vaciar_carrito':
                    return await this.delegarASalesBot('vaciar', mensaje, numero);

                // ===== PEDIDO =====
                case 'confirmar_pedido':
                    return await this.delegarASalesBot('confirmar_pedido', mensaje, numero);
                
                case 'cancelar_pedido':
                    return await this.delegarASalesBot('cancelar_pedido', mensaje, numero);
                
                case 'confirmar_pago':
                    return await this.delegarASalesBot('confirmar_pago', mensaje, numero);

                // ===== ESCALAMIENTO =====
                case 'solicitar_humano':
                    return await this.escalarAHumano(numero, empresaId, mensaje);

                case 'queja_reclamo':
                    return await this.manejarQueja(numero, empresaId, mensaje);
                
                case 'problema_pago':
                    return await this.manejarProblemaPago(numero, empresaId, mensaje);

                default:
                    console.log(`⚠️ Intención no implementada: ${intencion}`);
                    return { procesado: false, respuesta: null };
            }
        } catch (error) {
            console.error(`❌ Error ejecutando ${intencion}:`, error);
            return { procesado: false, respuesta: null, error: error.message };
        }
    }

    // ===== CONVERSACIONALES =====
    async manejarSaludo(numero, empresaId) {
        const [config] = await db.getPool().execute(
            'SELECT nombre_negocio FROM configuracion_negocio WHERE empresa_id = ?',
            [empresaId]
        );

        const nombreNegocio = config[0]?.nombre_negocio || 'nuestro negocio';
        
        return {
            procesado: true,
            respuesta: `¡Hola! 👋 Bienvenido a *${nombreNegocio}*. Soy tu asistente virtual de ventas.\n\n¿Te gustaría ver nuestro catálogo o tienes alguna pregunta? 😊`
        };
    }

    async manejarDespedida() {
        return {
            procesado: true,
            respuesta: '¡Hasta pronto! 👋 Gracias por contactarnos. Espero haberte ayudado. ¡Vuelve cuando quieras! 😊'
        };
    }

    async manejarAgradecimiento() {
        return {
            procesado: true,
            respuesta: '¡Con gusto! 😊 Fue un placer ayudarte. ¿Necesitas algo más?'
        };
    }

    // ===== CONSULTAS =====
    async consultarPrecio(mensaje, empresaId) {
        // Extraer posible nombre de producto
        const palabrasClave = mensaje.toLowerCase()
            .replace(/cuánto|cuesta|precio|vale|cuanto/gi, '')
            .trim();

        const catalogo = await this.obtenerCatalogo(empresaId);
        if (!catalogo) return { procesado: false, respuesta: null };

        // Buscar producto
        const producto = catalogo.productos?.find(p => 
            p.nombre.toLowerCase().includes(palabrasClave) ||
            palabrasClave.includes(p.nombre.toLowerCase().substring(0, 5))
        );

        if (producto) {
            return {
                procesado: true,
                respuesta: `📦 *${producto.nombre}*\n💰 Precio: S/ ${producto.precio}\n\n¿Te gustaría agregarlo al carrito? Responde "agregar ${producto.nombre}" 🛒`
            };
        }

        return { procesado: false, respuesta: null };
    }

    async consultarCatalogo(empresaId) {
        const catalogo = await this.obtenerCatalogo(empresaId);
        
        if (!catalogo || !catalogo.productos || catalogo.productos.length === 0) {
            return {
                procesado: true,
                respuesta: 'Lo siento, aún no tenemos catálogo digital disponible. ¿Te gustaría hablar con un asesor? 📞'
            };
        }

        let respuesta = '📋 *Nuestro Catálogo:*\n\n';
        
        catalogo.productos.slice(0, 5).forEach((prod, i) => {
            respuesta += `${i + 1}. *${prod.nombre}* - S/ ${prod.precio}\n`;
            if (prod.descripcion) {
                respuesta += `   _${prod.descripcion}_\n`;
            }
            respuesta += '\n';
        });

        if (catalogo.productos.length > 5) {
            respuesta += `_...y ${catalogo.productos.length - 5} productos más_\n\n`;
        }

        respuesta += '💬 Pregúntame por el precio de algún producto o escribe "agregar [nombre]" para comprar. 😊';

        return { procesado: true, respuesta };
    }

    async consultarDisponibilidad(mensaje, empresaId) {
        // Similar a consultarPrecio pero verifica stock
        const catalogo = await this.obtenerCatalogo(empresaId);
        if (!catalogo) return { procesado: false, respuesta: null };

        return {
            procesado: true,
            respuesta: 'Todos nuestros productos están disponibles. ¿Cuál te interesa específicamente? 📦'
        };
    }

    async consultarMetodosPago(empresaId) {
        const [config] = await db.getPool().execute(
            'SELECT cuentas_pago FROM configuracion_negocio WHERE empresa_id = ?',
            [empresaId]
        );

        if (!config[0]?.cuentas_pago) {
            return { procesado: false, respuesta: null };
        }

        const cuentas = JSON.parse(config[0].cuentas_pago);
        let respuesta = '💳 *Métodos de Pago:*\n\n';

        if (cuentas.yape) respuesta += `📱 *Yape:* ${cuentas.yape}\n`;
        if (cuentas.plin) respuesta += `📱 *Plin:* ${cuentas.plin}\n`;
        if (cuentas.banco) {
            respuesta += `🏦 *Transferencia:*\n`;
            respuesta += `   ${cuentas.banco.nombre}\n`;
            respuesta += `   Cta: ${cuentas.banco.numero}\n`;
            if (cuentas.banco.cci) respuesta += `   CCI: ${cuentas.banco.cci}\n`;
        }
        if (cuentas.efectivo) respuesta += `💵 *Efectivo:* Contraentrega\n`;

        respuesta += '\n¿Con cuál prefieres pagar? 😊';

        return { procesado: true, respuesta };
    }

    async consultarDelivery(empresaId) {
        const [config] = await db.getPool().execute(
            'SELECT direccion FROM configuracion_negocio WHERE empresa_id = ?',
            [empresaId]
        );

        return {
            procesado: true,
            respuesta: `🚚 *Delivery Disponible*\n\nSí, hacemos entregas a domicilio. El costo depende de tu zona.\n\n📍 También puedes recoger en:\n${config[0]?.direccion || 'Tienda física'}\n\n¿A dónde necesitas el envío?`
        };
    }

    async consultarPromociones(empresaId) {
        const catalogo = await this.obtenerCatalogo(empresaId);
        
        if (!catalogo?.promociones || catalogo.promociones.length === 0) {
            return {
                procesado: true,
                respuesta: '🎉 Por el momento no hay promociones activas, pero pronto habrán novedades. ¿Quieres ver el catálogo regular?'
            };
        }

        let respuesta = '🎉 *¡Promociones Activas!*\n\n';
        catalogo.promociones.forEach((promo, i) => {
            respuesta += `${i + 1}. *${promo.nombre}*\n`;
            respuesta += `   ${promo.descripcion}\n`;
            respuesta += `   ~S/ ${promo.precio_regular}~ → *S/ ${promo.precio_promocion}*\n\n`;
        });

        respuesta += '😍 ¿Te interesa alguna?';

        return { procesado: true, respuesta };
    }

    // ===== DELEGAR A SALESBOT =====
    async delegarASalesBot(accion, mensaje, numero) {
        if (!this.salesBot) {
            return { procesado: false, respuesta: null };
        }

        // Llamar al método correspondiente del salesBot
        const respuesta = await this.salesBot.procesarMensajeVenta(mensaje, numero);
        
        return {
            procesado: true,
            respuesta: respuesta.respuesta,
            tipo: respuesta.tipo,
            archivo: respuesta.archivo
        };
    }

    // ===== ESCALAMIENTO =====
    async escalarAHumano(numero, empresaId, motivo) {
        await db.getPool().execute(
            `INSERT INTO estados_conversacion 
            (empresa_id, numero_cliente, estado, fecha_escalado, motivo_escalado)
            VALUES (?, ?, 'escalado_humano', NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                estado = 'escalado_humano',
                fecha_escalado = NOW(),
                motivo_escalado = ?`,
            [empresaId, numero, motivo, motivo]
        );

        return {
            procesado: true,
            respuesta: '👤 Perfecto, te conectaré con un asesor humano que te atenderá en breve. ¡Gracias por tu paciencia!',
            escalar: true
        };
    }

    async manejarQueja(numero, empresaId, queja) {
        await db.getPool().execute(
            `INSERT INTO tickets_soporte 
            (empresa_id, numero_cliente, tipo_problema, descripcion, prioridad, estado)
            VALUES (?, ?, 'queja_reclamo', ?, 'alta', 'abierto')`,
            [empresaId, numero, queja]
        );

        return await this.escalarAHumano(numero, empresaId, `Queja: ${queja}`);
    }

    async manejarProblemaPago(numero, empresaId, problema) {
        await db.getPool().execute(
            `INSERT INTO tickets_soporte 
            (empresa_id, numero_cliente, tipo_problema, descripcion, prioridad, estado)
            VALUES (?, ?, 'problema_pago', ?, 'media', 'abierto')`,
            [empresaId, numero, problema]
        );

        return {
            procesado: true,
            respuesta: '😔 Lamento el inconveniente. Estoy registrando tu caso y un asesor te ayudará pronto. ¿Podrías enviarme una captura del problema?',
            escalar: true
        };
    }

    // ===== HELPERS =====
    async obtenerCatalogo(empresaId) {
        try {
            const [rows] = await db.getPool().execute(
                'SELECT datos_json FROM catalogo_bot WHERE empresa_id = ?',
                [empresaId]
            );

            if (rows[0]?.datos_json) {
                return JSON.parse(rows[0].datos_json);
            }
        } catch (error) {
            console.error('Error obteniendo catálogo:', error);
        }
        return null;
    }
}

module.exports = VentasIntentRouter;