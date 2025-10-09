// whatsapp-service/src/config.js
const environment = process.env.NODE_ENV || 'development';

const config = {
    development: {
        host: '0.0.0.0',
        corsOrigin: ['http://localhost', 'http://127.0.0.1'],
        logLevel: 'debug'
    },
    production: {
        host: '127.0.0.1',
        corsOrigin: ['https://mensajeropro.com', 'https://www.mensajeropro.com'],
        logLevel: 'info'
    }
};

// Verificar que existe la configuración
if (!config[environment]) {
    console.error(`No existe configuración para el entorno: ${environment}`);
    // Usar development por defecto
    module.exports = config.development;
} else {
    module.exports = {
        ...config[environment],
        environment,
        isDevelopment: environment === 'development',
        isProduction: environment === 'production'
    };
}