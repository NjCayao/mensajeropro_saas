import os
import json
import time
import joblib
import numpy as np
from datetime import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, precision_recall_fscore_support
from database import Database

class ModelTrainer:
    def __init__(self):
        self.db = Database()
        self.db.connect()
        self.models_path = os.getenv('MODELS_PATH', './models')
        self.training_path = os.getenv('TRAINING_PATH', './training')
        
        # Crear carpetas si no existen
        os.makedirs(self.models_path, exist_ok=True)
        os.makedirs(self.training_path, exist_ok=True)
    
    def load_base_dataset(self):
        """Cargar dataset base desde JSON"""
        base_file = os.path.join(self.training_path, 'base_intents.json')
        
        if not os.path.exists(base_file):
            print(f"❌ No se encontró {base_file}")
            return [], []
        
        with open(base_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        textos = [item['texto'] for item in data]
        intenciones = [item['intencion'] for item in data]
        
        print(f"✅ Dataset base cargado: {len(textos)} ejemplos")
        return textos, intenciones
    
    def load_new_samples(self):
        """Cargar nuevos ejemplos desde BD"""
        samples = self.db.get_training_samples()
        
        if not samples:
            print("⚠️ No hay ejemplos nuevos en BD")
            return [], [], []
        
        textos = [s['texto_usuario'] for s in samples]
        intenciones = [s['intencion'] for s in samples]
        ids = [s['id'] for s in samples]
        
        print(f"✅ Ejemplos nuevos cargados: {len(textos)}")
        return textos, intenciones, ids
    
    def train_model(self, use_new_samples=True):
        """Entrenar modelo completo"""
        start_time = time.time()
        
        print("\n🚀 Iniciando entrenamiento del modelo...")
        
        # 1. Cargar dataset base
        base_textos, base_intenciones = self.load_base_dataset()
        
        if not base_textos:
            return None
        
        # 2. Cargar ejemplos nuevos de BD
        sample_ids = []
        if use_new_samples:
            new_textos, new_intenciones, sample_ids = self.load_new_samples()
            
            if new_textos:
                base_textos.extend(new_textos)
                base_intenciones.extend(new_intenciones)
                print(f"✅ Total con nuevos ejemplos: {len(base_textos)}")
        
        # 3. Dividir datos para evaluación
        X_train, X_test, y_train, y_test = train_test_split(
            base_textos, 
            base_intenciones, 
            test_size=0.2, 
            random_state=42,
            stratify=base_intenciones
        )
        
        print(f"📊 Train: {len(X_train)} | Test: {len(X_test)}")
        
        # 4. Crear vectorizador TF-IDF
        print("🔧 Creando vectorizador TF-IDF...")
        vectorizer = TfidfVectorizer(
            max_features=1000,
            ngram_range=(1, 2),
            lowercase=True,
            strip_accents='unicode'
        )
        
        X_train_vec = vectorizer.fit_transform(X_train)
        X_test_vec = vectorizer.transform(X_test)
        
        # 5. Entrenar modelo Logistic Regression
        print("🧠 Entrenando Logistic Regression...")
        model = LogisticRegression(
            max_iter=1000,
            C=1.0,
            random_state=42,
            solver='lbfgs',
            multi_class='multinomial'
        )
        
        model.fit(X_train_vec, y_train)
        
        # 6. Evaluar modelo
        print("📈 Evaluando modelo...")
        y_pred = model.predict(X_test_vec)
        
        accuracy = accuracy_score(y_test, y_pred)
        precision, recall, f1, _ = precision_recall_fscore_support(
            y_test, 
            y_pred, 
            average='weighted',
            zero_division=0
        )
        
        # 7. Calcular nueva versión
        current_version = self.db.get_latest_model_version()
        new_version = current_version + 1
        
        # 8. Guardar modelo
        model_filename = f'sales_bot_v{new_version}.pkl'
        vectorizer_filename = f'vectorizer_v{new_version}.pkl'
        
        model_path = os.path.join(self.models_path, model_filename)
        vectorizer_path = os.path.join(self.models_path, vectorizer_filename)
        
        joblib.dump(model, model_path)
        joblib.dump(vectorizer, vectorizer_path)
        
        print(f"💾 Modelo guardado: {model_filename}")
        print(f"💾 Vectorizer guardado: {vectorizer_filename}")
        
        # 9. Guardar metadata
        metadata = {
            'version': new_version,
            'accuracy': float(accuracy),
            'precision': float(precision),
            'recall': float(recall),
            'f1_score': float(f1),
            'total_samples': len(base_textos),
            'train_samples': len(X_train),
            'test_samples': len(X_test),
            'intents': list(set(base_intenciones)),
            'trained_at': datetime.now().isoformat()
        }
        
        metadata_path = os.path.join(self.models_path, 'metadata.json')
        with open(metadata_path, 'w', encoding='utf-8') as f:
            json.dump(metadata, f, indent=2, ensure_ascii=False)
        
        print(f"💾 Metadata guardada")
        
        # 10. Guardar métricas en BD
        duration = int(time.time() - start_time)
        
        metrics = {
            'accuracy': accuracy,
            'precision': precision,
            'recall': recall,
            'f1_score': f1,
            'total_samples': len(base_textos),
            'duration': duration,
            'model_file': model_filename
        }
        
        self.db.save_model_metrics(new_version, metrics)
        
        # 11. Marcar ejemplos como usados
        if sample_ids:
            self.db.mark_samples_as_used(sample_ids)
            print(f"✅ {len(sample_ids)} ejemplos marcados como usados")
        
        # 12. Eliminar modelos antiguos (mantener últimos 3)
        self._cleanup_old_models(new_version)
        
        end_time = time.time()
        
        print(f"\n✅ Entrenamiento completado en {duration}s")
        print(f"📊 Métricas:")
        print(f"   - Accuracy: {accuracy:.4f} ({accuracy*100:.2f}%)")
        print(f"   - Precision: {precision:.4f}")
        print(f"   - Recall: {recall:.4f}")
        print(f"   - F1-Score: {f1:.4f}")
        print(f"   - Versión: v{new_version}")
        
        return {
            'success': True,
            'version': new_version,
            'metrics': metadata
        }
    
    def _cleanup_old_models(self, current_version, keep=3):
        """Eliminar modelos antiguos, mantener últimos N"""
        try:
            # Listar todos los archivos .pkl
            model_files = [f for f in os.listdir(self.models_path) if f.endswith('.pkl')]
            
            # Filtrar solo sales_bot y vectorizer
            sales_files = [f for f in model_files if 'sales_bot_v' in f]
            vec_files = [f for f in model_files if 'vectorizer_v' in f]
            
            # Ordenar por versión (extraer número)
            def get_version(filename):
                try:
                    return int(filename.split('_v')[1].split('.')[0])
                except:
                    return 0
            
            sales_files.sort(key=get_version, reverse=True)
            vec_files.sort(key=get_version, reverse=True)
            
            # Eliminar los que sobran
            for filename in sales_files[keep:]:
                file_path = os.path.join(self.models_path, filename)
                os.remove(file_path)
                print(f"🗑️ Eliminado: {filename}")
            
            for filename in vec_files[keep:]:
                file_path = os.path.join(self.models_path, filename)
                os.remove(file_path)
                print(f"🗑️ Eliminado: {filename}")
        
        except Exception as e:
            print(f"⚠️ Error limpiando modelos antiguos: {e}")

# Script de prueba
if __name__ == "__main__":
    trainer = ModelTrainer()
    result = trainer.train_model(use_new_samples=False)
    
    if result:
        print("\n✅ Modelo entrenado exitosamente")
    else:
        print("\n❌ Error en el entrenamiento")