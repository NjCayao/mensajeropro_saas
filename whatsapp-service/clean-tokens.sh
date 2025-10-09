#!/bin/bash
EMPRESA_ID=$1
TOKEN_PATH="/var/www/mensajeropro/whatsapp-service/tokens/empresa-$EMPRESA_ID"

if [ -d "$TOKEN_PATH" ]; then
    rm -rf "$TOKEN_PATH"
    echo "Tokens eliminados: $TOKEN_PATH"
else
    echo "No hay tokens previos para empresa $EMPRESA_ID"
fi