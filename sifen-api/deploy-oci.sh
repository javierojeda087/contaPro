#!/usr/bin/env bash
# ============================================================================
# Script de Instalación y Despliegue Automático en Oracle Cloud (OCI)
# Compatible con Ubuntu 22.04/24.04 y Oracle Linux 8/9
# ============================================================================
set -e

echo "=== 1. Instalando OpenJDK 21 y Maven en la instancia OCI ==="
if command -v apt-get >/dev/null 2>&1; then
    sudo apt-get update -y
    sudo apt-get install -y openjdk-21-jdk maven
elif command -v dnf >/dev/null 2>&1; then
    sudo dnf install -y java-21-openjdk-devel maven
fi

echo "=== 2. Compilando sifen-api.jar ==="
mvn clean package -DskipTests

echo "=== 3. Creando servicio systemd (contapro-sifen-api) ==="
APP_DIR=$(pwd)
API_KEY="${SIFEN_API_KEY:-contapro-sifen-secret-2026}"

sudo tee /etc/systemd/system/contapro-sifen-api.service > /dev/null <<EOF
[Unit]
Description=ContaPro SIFEN API Microservice (Java Spring Boot)
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$APP_DIR
Environment="PORT=8085"
Environment="SIFEN_API_KEY=$API_KEY"
ExecStart=/usr/bin/java -Xms128m -Xmx512m -jar $APP_DIR/target/sifen-api.jar
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable contapro-sifen-api
sudo systemctl restart contapro-sifen-api

echo "=== ¡Microservicio desplegado y activo en el puerto 8085! ==="
sudo systemctl status contapro-sifen-api --no-pager
