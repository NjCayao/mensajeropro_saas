import os
import pymysql
from dotenv import load_dotenv

load_dotenv()

class Database:
    def __init__(self):
        self.host = os.getenv('DB_HOST', 'localhost')
        self.user = os.getenv('DB_USER', 'root')
        self.password = os.getenv('DB_PASS', '')
        self.database = os.getenv('DB_NAME', 'mensajeropro_saas')
        self.charset = os.getenv('DB_CHARSET', 'utf8mb4')
        self.connection = None
    
    def connect(self):
        """Conectar a la base de datos"""
        try:
            self.connection = pymysql.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                charset=self.charset,
                cursorclass=pymysql.cursors.DictCursor
            )
            return self.connection
        except Exception as e:
            print(f"❌ Error conectando a BD: {e}")
            return None
    
    def close(self):
        """Cerrar conexión"""
        if self.connection:
            self.connection.close()
    
    def execute_query(self, query, params=None):
        """Ejecutar query SELECT"""
        try:
            if not self.connection or not self.connection.open:
                self.connect()
            
            with self.connection.cursor() as cursor:
                cursor.execute(query, params or ())
                result = cursor.fetchall()
                return result
        except Exception as e:
            print(f"❌ Error en query: {e}")
            return []
    
    def execute_insert(self, query, params=None):
        """Ejecutar INSERT/UPDATE"""
        try:
            if not self.connection or not self.connection.open:
                self.connect()
            
            with self.connection.cursor() as cursor:
                cursor.execute(query, params or ())
                self.connection.commit()
                return cursor.lastrowid
        except Exception as e:
            print(f"❌ Error en insert: {e}")
            self.connection.rollback()
            return None
    
    def get_training_samples(self, limit=None):
        """Obtener ejemplos de entrenamiento"""
        query = """
            SELECT texto_usuario, 
                   COALESCE(intencion_confirmada, intencion_detectada) as intencion
            FROM training_samples 
            WHERE estado = 'confirmado' 
              AND usado_entrenamiento = 0
        """
        
        if limit:
            query += f" LIMIT {limit}"
        
        return self.execute_query(query)
    
    def mark_samples_as_used(self, sample_ids):
        """Marcar ejemplos como usados en entrenamiento"""
        if not sample_ids:
            return
        
        ids_str = ','.join(map(str, sample_ids))
        query = f"""
            UPDATE training_samples 
            SET usado_entrenamiento = 1 
            WHERE id IN ({ids_str})
        """
        return self.execute_insert(query)
    
    def save_model_metrics(self, version, metrics):
        """Guardar métricas del modelo"""
        query = """
            INSERT INTO metricas_modelo 
            (version_modelo, accuracy, precision_avg, recall_avg, f1_score, 
             ejemplos_entrenamiento, fecha_entrenamiento, duracion_segundos, 
             archivo_modelo, estado)
            VALUES (%s, %s, %s, %s, %s, %s, NOW(), %s, %s, 'activo')
        """
        
        params = (
            version,
            metrics['accuracy'],
            metrics['precision'],
            metrics['recall'],
            metrics['f1_score'],
            metrics['total_samples'],
            metrics['duration'],
            metrics['model_file']
        )
        
        result = self.execute_insert(query, params)
        
        # Marcar versiones anteriores como inactivas
        if result:
            update_query = """
                UPDATE metricas_modelo 
                SET estado = 'inactivo' 
                WHERE version_modelo < %s
            """
            self.execute_insert(update_query, (version,))
        
        return result
    
    def get_latest_model_version(self):
        """Obtener versión del último modelo"""
        query = """
            SELECT MAX(version_modelo) as version 
            FROM metricas_modelo
        """
        result = self.execute_query(query)
        
        if result and result[0]['version']:
            return result[0]['version']
        return 0
    
    def get_openai_config(self):
        """Obtener configuración de OpenAI desde BD"""
        query = """
            SELECT clave, valor 
            FROM configuracion_plataforma 
            WHERE clave IN ('openai_api_key', 'openai_modelo', 
                           'openai_max_tokens', 'openai_temperatura')
        """
        result = self.execute_query(query)
        
        config = {}
        for row in result:
            config[row['clave']] = row['valor']
        
        return config