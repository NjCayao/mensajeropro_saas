// whatsapp-service/src/cleanupComprobantes.js
const fs = require("fs").promises;
const path = require("path");

async function limpiarComprobantesAntiguos() {
  try {
    const directorioComprobantes = path.join(__dirname, '../uploads/comprobantes');
    
    // Verificar si existe el directorio
    try {
      await fs.access(directorioComprobantes);
    } catch {
      console.log("No hay directorio de comprobantes");
      return;
    }

    const archivos = await fs.readdir(directorioComprobantes);
    const ahora = Date.now();
    const limite48h = 48 * 60 * 60 * 1000;
    
    let eliminados = 0;

    for (const archivo of archivos) {
      const rutaArchivo = path.join(directorioComprobantes, archivo);
      const stats = await fs.stat(rutaArchivo);
      const edad = ahora - stats.mtimeMs;

      if (edad > limite48h) {
        await fs.unlink(rutaArchivo);
        eliminados++;
        console.log(`🗑️ Eliminado: ${archivo} (${Math.round(edad / 3600000)}h de antigüedad)`);
      }
    }

    if (eliminados > 0) {
      console.log(`✅ Limpieza completada: ${eliminados} comprobantes eliminados`);
    } else {
      console.log("✅ No hay comprobantes antiguos para eliminar");
    }

  } catch (error) {
    console.error("❌ Error en limpieza de comprobantes:", error);
  }
}

// Ejecutar cada 6 horas
setInterval(limpiarComprobantesAntiguos, 6 * 60 * 60 * 1000);

// Ejecutar inmediatamente al iniciar
limpiarComprobantesAntiguos();

module.exports = { limpiarComprobantesAntiguos };