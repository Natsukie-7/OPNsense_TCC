#!/bin/bash
set -euo pipefail

# Configuracoes (podem ser sobrescritas por variaveis de ambiente)
OPNSENSE_IP="${OPNSENSE_IP:-192.168.56.10}"
OPNSENSE_USER="${OPNSENSE_USER:-root}"
PLUGIN_NAME="${PLUGIN_NAME:-userregister}"
PLUGIN_DIR="${PLUGIN_DIR:-/usr/plugins/devel/${PLUGIN_NAME}}"

echo "Iniciando build e deploy automatico no OPNsense (${OPNSENSE_USER}@${OPNSENSE_IP})..."

for cmd in ssh scp make; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "Erro: comando obrigatorio nao encontrado: $cmd"
        exit 1
    fi
done

# 1. Garante que o diretorio de destino existe
ssh -T "${OPNSENSE_USER}@${OPNSENSE_IP}" "mkdir -p '${PLUGIN_DIR}'"

# 1.1 Limpa fontes antigas para evitar empacotar arquivos removidos localmente
ssh -T "${OPNSENSE_USER}@${OPNSENSE_IP}" "rm -rf '${PLUGIN_DIR}/src' '${PLUGIN_DIR}/Makefile'"

# 2. Envia os arquivos atualizados
echo "Transferindo src/ e Makefile..."
scp -r ./src ./Makefile "${OPNSENSE_USER}@${OPNSENSE_IP}:${PLUGIN_DIR}/"

# 3. Roda os comandos de compilacao e instalacao remotamente
echo "Compilando, instalando e reiniciando a interface remotamente..."
ssh -T "${OPNSENSE_USER}@${OPNSENSE_IP}" /bin/sh -s << EOF
    cd '${PLUGIN_DIR}'

    echo "--> Removendo build anterior para evitar conflitos..."
    make clean > /dev/null 2>&1 || true

    echo "--> Compilando o pacote..."
    make package

    echo "--> Instalando a nova versao..."
    pkg install -y work/pkg/*.pkg

    echo "--> Reiniciando o WebGUI..."
    rm -f /tmp/opnsense_menu_cache.xml
    configctl webgui restart
EOF

echo "Build e deploy concluidos com sucesso no OPNsense (${OPNSENSE_USER}@${OPNSENSE_IP})."