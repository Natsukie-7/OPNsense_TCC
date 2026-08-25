#!/bin/bash

set -euo pipefail

# IP OPNsense
OPNSENSE_IP="192.168.56.10"
REMOTE_USER="root"
REMOTE_HOST="$REMOTE_USER@$OPNSENSE_IP"

echo "Enviando arquivos do plugin para o OPNsense ($OPNSENSE_IP)..."

if [[ ! -d "./src" ]]; then
	echo "Erro: diretório ./src não encontrado."
	exit 1
fi

if ! command -v ssh >/dev/null 2>&1; then
	echo "Erro: ssh não encontrado no sistema local."
	exit 1
fi

if ssh -o ConnectTimeout=6 "$REMOTE_HOST" "which rsync" >/dev/null 2>&1; then
	# OPNsense espelha o conteúdo de 'src/' diretamente em /usr/local/
	rsync -avz --progress ./src/ "$REMOTE_HOST:/usr/local/"
else
	echo "Aviso: rsync não encontrado no host remoto. Usando fallback com tar+ssh."
	tar -C ./src -cf - . | ssh "$REMOTE_HOST" "tar -C /usr/local -xf -"
fi

echo "Deploy concluído com sucesso!"