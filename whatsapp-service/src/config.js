// whatsapp-service/src/config.js (nuevo archivo)
const config = {
    development: {
        host: '0.0.0.0',  // Escuchar en todas las interfaces
        corsOrigin: ['http://localhost', 'http://localhost:*'],
        logLevel: 'debug'
    },
    production: {
        host: '127.0.0.1', // Solo localhost (Nginx hará proxy)
        corsOrigin: process.env.CORS_ORIGIN ? process.env.CORS_ORIGIN.split(',') : ['https://tudominio.com'],
        logLevel: 'info'
    }
};

const environment = process.env.NODE_ENV || 'development';

module.exports = {
    ...config[environment],
    environment,
    isDevelopment: environment === 'development',
    isProduction: environment === 'production'
};