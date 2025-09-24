const mysql = require('mysql2/promise');
require('dotenv').config();

let pool;

async function initDatabase() {
    try {
        pool = mysql.createPool({
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASS || '',
            database: process.env.DB_NAME || 'mensajeropro',
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0,
            // NUEVAS CONFIGURACIONES IMPORTANTES
            multipleStatements: false,
            supportBigNumbers: true,
            bigNumberStrings: true,
            dateStrings: true,
            timezone: '+00:00', // UTC para evitar problemas de zona horaria
            charset: 'utf8mb4'
        });

        // Probar conexión
        const connection = await pool.getConnection();
        console.log('✅ Conexión a base de datos establecida');
        
        // Configurar la zona horaria de la conexión
        await connection.execute("SET time_zone = '-05:00'"); // Perú
        
        connection.release();

        return pool;
    } catch (error) {
        console.error('❌ Error conectando a la base de datos:', error);
        process.exit(1);
    }
}

// NUEVA FUNCIÓN para ejecutar queries con transacciones
async function executeWithCommit(query, params = []) {
    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const [result] = await connection.execute(query, params);
        await connection.commit();
        console.log(`✅ Query ejecutada con éxito: ${query.substring(0, 50)}...`);
        return result;
    } catch (error) {
        await connection.rollback();
        console.error('❌ Error en query, rollback ejecutado:', error);
        throw error;
    } finally {
        connection.release();
    }
}

// Funciones helper actualizadas
async function updateWhatsAppStatus(estado, qrCode = null, numeroConectado = null) {
    try {
        const empresaId = global.EMPRESA_ID || 1;
        
        await executeWithCommit(
            `UPDATE whatsapp_sesiones_empresa 
             SET estado = ?, qr_code = ?, numero_conectado = ?, ultima_actualizacion = NOW() 
             WHERE empresa_id = ?`,
            [estado, qrCode, numeroConectado, empresaId]
        );
        
        console.log('✅ Estado actualizado en BD para empresa:', empresaId);
    } catch (error) {
        console.error('❌ Error actualizando estado WhatsApp:', error);
    }
}

async function getContactosPorCategoria(categoriaId = null) {
    try {
        const empresaId = global.EMPRESA_ID || 1;
        let query = `
            SELECT c.*, cat.nombre as categoria_nombre 
            FROM contactos c 
            LEFT JOIN categorias cat ON c.categoria_id = cat.id 
            WHERE c.activo = 1 AND c.empresa_id = ?
        `;
        
        const params = [empresaId];
        if (categoriaId) {
            query += ' AND c.categoria_id = ?';
            params.push(categoriaId);
        }

        const [rows] = await pool.execute(query, params);
        return rows;
    } catch (error) {
        console.error('Error obteniendo contactos:', error);
        return [];
    }
}

async function registrarMensaje(contactoId, mensaje, tipo = 'saliente', estado = 'enviado', mensajeProgramadoId = null) {
    try {
        await executeWithCommit(
            `INSERT INTO historial_mensajes (contacto_id, mensaje, tipo, estado, mensaje_programado_id, fecha) 
             VALUES (?, ?, ?, ?, ?, NOW())`,
            [contactoId, mensaje, tipo, estado, mensajeProgramadoId]
        );
        console.log('✅ Mensaje registrado en historial');
    } catch (error) {
        console.error('Error registrando mensaje:', error);
    }
}

async function obtenerMensajesPendientes() {
    try {
        const [rows] = await pool.execute(`
            SELECT cm.*, c.numero, c.nombre 
            FROM cola_mensajes cm
            INNER JOIN contactos c ON cm.contacto_id = c.id
            WHERE cm.estado = 'pendiente' 
            ORDER BY cm.fecha_creacion ASC
            LIMIT 10
        `);
        return rows;
    } catch (error) {
        console.error('Error obteniendo mensajes pendientes:', error);
        return [];
    }
}

async function actualizarEstadoMensaje(mensajeId, estado, errorMensaje = null) {
    try {
        await executeWithCommit(
            `UPDATE cola_mensajes 
             SET estado = ?, error_mensaje = ?, fecha_envio = NOW() 
             WHERE id = ?`,
            [estado, errorMensaje, mensajeId]
        );
        console.log(`✅ Estado del mensaje ${mensajeId} actualizado a: ${estado}`);
    } catch (error) {
        console.error('Error actualizando estado mensaje:', error);
    }
}

// NUEVA función específica para mensajes programados
async function actualizarEstadoMensajeProgramado(id, estado, mensajesEnviados = null) {
    try {
        let query = 'UPDATE mensajes_programados SET estado = ?';
        const params = [estado];
        
        if (mensajesEnviados !== null) {
            query += ', mensajes_enviados = ?';
            params.push(mensajesEnviados);
        }
        
        query += ' WHERE id = ?';
        params.push(id);
        
        const result = await executeWithCommit(query, params);
        console.log(`✅ Mensaje programado ${id} actualizado a estado: ${estado}`);
        return result;
    } catch (error) {
        console.error('Error actualizando mensaje programado:', error);
        throw error;
    }
}

module.exports = {
    initDatabase,
    updateWhatsAppStatus,
    getContactosPorCategoria,
    registrarMensaje,
    obtenerMensajesPendientes,
    actualizarEstadoMensaje,
    actualizarEstadoMensajeProgramado,
    executeWithCommit,
    getPool: () => pool
};