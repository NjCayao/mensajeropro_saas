import os
import json
import joblib
import numpy as np

class IntentClassifier:
    def __init__(self):
        self.models_path = os.getenv('MODELS_PATH', './models')
        self.model = None
        self.vectorizer = None
        self.metadata = None
        self.min_confidence = float(os.getenv('MIN_CONFIDENCE', 0.80))
        
        self.load_latest_model()
    
    def load_latest_model(self):
        """Cargar el último modelo entrenado"""
        try:
            # Leer metadata para saber qué versión cargar
            metadata_path = os.path.join(self.models_path, 'metadata.json')
            
            if not os.path.exists(metadata_path):
                print("❌ No se encontró metadata.json")
                return False
            
            with open(metadata_path, 'r', encoding='utf-8') as f:
                self.metadata = json.load(f)
            
            version = self.metadata['version']
            
            # Cargar modelo y vectorizer
            model_path = os.path.join(self.models_path, f'sales_bot_v{version}.pkl')
            vectorizer_path = os.path.join(self.models_path, f'vectorizer_v{version}.pkl')
            
            if not os.path.exists(model_path) or not os.path.exists(vectorizer_path):
                print(f"❌ No se encontraron archivos del modelo v{version}")
                return False
            
            self.model = joblib.load(model_path)
            self.vectorizer = joblib.load(vectorizer_path)
            
            print(f"✅ Modelo v{version} cargado (Accuracy: {self.metadata['accuracy']:.2%})")
            return True
        
        except Exception as e:
            print(f"❌ Error cargando modelo: {e}")
            return False
    
    def classify(self, texto, contexto=None):
        """Clasificar intención de un mensaje"""
        if not self.model or not self.vectorizer:
            return {
                'success': False,
                'error': 'Modelo no cargado'
            }
        
        try:
            # Preprocesar texto
            texto_clean = texto.lower().strip()
            
            # Vectorizar
            texto_vec = self.vectorizer.transform([texto_clean])
            
            # Predecir
            intencion = self.model.predict(texto_vec)[0]
            
            # Obtener probabilidades
            probabilidades = self.model.predict_proba(texto_vec)[0]
            confianza = float(np.max(probabilidades))
            
            # Decidir si usar GPT
            usar_gpt = confianza < self.min_confidence
            
            result = {
                'success': True,
                'intencion': intencion,
                'confianza': round(confianza, 4),
                'usar_gpt': usar_gpt,
                'modelo_version': self.metadata['version'],
                'todas_probabilidades': {
                    clase: round(float(prob), 4) 
                    for clase, prob in zip(self.model.classes_, probabilidades)
                }
            }
            
            return result
        
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_model_info(self):
        """Obtener información del modelo actual"""
        if not self.metadata:
            return {'error': 'Modelo no cargado'}
        
        return {
            'version': self.metadata['version'],
            'accuracy': self.metadata['accuracy'],
            'precision': self.metadata['precision'],
            'recall': self.metadata['recall'],
            'f1_score': self.metadata['f1_score'],
            'total_samples': self.metadata['total_samples'],
            'intents': self.metadata['intents'],
            'trained_at': self.metadata['trained_at'],
            'min_confidence': self.min_confidence
        }

# Script de prueba
if __name__ == "__main__":
    classifier = IntentClassifier()
    
    # Pruebas
    tests = [
        "hola como estas",
        "cuanto cuesta la pizza",
        "tienen delivery",
        "quiero comprar",
        "ya pague",
        "necesito hablar con alguien"
    ]
    
    print("\n🧪 Probando clasificador:\n")
    for texto in tests:
        result = classifier.classify(texto)
        if result['success']:
            print(f"'{texto}'")
            print(f"  → {result['intencion']} ({result['confianza']:.2%})")
            print(f"  → GPT: {'Sí' if result['usar_gpt'] else 'No'}\n")