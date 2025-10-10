import os
import time
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional
from intent_classifier import IntentClassifier
from model_trainer import ModelTrainer
from database import Database

app = FastAPI(
    title="MensajeroPro ML Engine",
    description="Motor de Machine Learning para detección de intenciones",
    version="1.0.0"
)

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inicializar clasificador
classifier = IntentClassifier()
db = Database()

# Modelos Pydantic
class ClassifyRequest(BaseModel):
    texto: str
    contexto: Optional[List[str]] = []

class ClassifyResponse(BaseModel):
    success: bool
    intencion: Optional[str] = None
    confianza: Optional[float] = None
    usar_gpt: Optional[bool] = None
    modelo_version: Optional[int] = None
    tiempo_ms: Optional[int] = None
    error: Optional[str] = None

class TrainResponse(BaseModel):
    success: bool
    version: Optional[int] = None
    accuracy: Optional[float] = None
    mensaje: Optional[str] = None
    error: Optional[str] = None

# Endpoints
@app.get("/")
def root():
    return {
        "message": "MensajeroPro ML Engine",
        "status": "online",
        "version": "1.0.0"
    }

@app.get("/health")
def health_check():
    """Check de salud del servicio"""
    model_loaded = classifier.model is not None
    
    return {
        "status": "healthy" if model_loaded else "degraded",
        "model_loaded": model_loaded,
        "model_info": classifier.get_model_info() if model_loaded else None
    }

@app.post("/classify", response_model=ClassifyResponse)
def classify_intent(request: ClassifyRequest):
    """Clasificar intención de un mensaje"""
    start_time = time.time()
    
    if not request.texto or len(request.texto.strip()) == 0:
        raise HTTPException(status_code=400, detail="Texto vacío")
    
    # Clasificar
    result = classifier.classify(request.texto, request.contexto)
    
    if not result['success']:
        raise HTTPException(status_code=500, detail=result.get('error', 'Error desconocido'))
    
    # Calcular tiempo
    tiempo_ms = int((time.time() - start_time) * 1000)
    
    return ClassifyResponse(
        success=True,
        intencion=result['intencion'],
        confianza=result['confianza'],
        usar_gpt=result['usar_gpt'],
        modelo_version=result['modelo_version'],
        tiempo_ms=tiempo_ms
    )

@app.post("/train", response_model=TrainResponse)
def train_model():
    """Entrenar/reentrenar el modelo"""
    try:
        trainer = ModelTrainer()
        result = trainer.train_model(use_new_samples=True)
        
        if not result:
            raise HTTPException(status_code=500, detail="Error en el entrenamiento")
        
        # Recargar clasificador con nuevo modelo
        classifier.load_latest_model()
        
        return TrainResponse(
            success=True,
            version=result['version'],
            accuracy=result['metrics']['accuracy'],
            mensaje=f"Modelo v{result['version']} entrenado exitosamente"
        )
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/metrics")
def get_metrics():
    """Obtener métricas del modelo actual"""
    info = classifier.get_model_info()
    
    if 'error' in info:
        raise HTTPException(status_code=404, detail="Modelo no encontrado")
    
    return info

@app.get("/intents")
def get_intents():
    """Listar todas las intenciones disponibles"""
    info = classifier.get_model_info()
    
    if 'error' in info:
        return {"intents": []}
    
    return {
        "intents": info['intents'],
        "total": len(info['intents'])
    }

if __name__ == "__main__":
    import uvicorn
    
    host = os.getenv('API_HOST', '0.0.0.0')
    port = int(os.getenv('API_PORT', 5000))
    reload = os.getenv('API_RELOAD', 'True').lower() == 'true'
    
    print(f"\n🚀 Iniciando ML Engine en {host}:{port}\n")
    
    uvicorn.run(
        "api_server:app",
        host=host,
        port=port,
        reload=reload,
        log_level="info"
    )