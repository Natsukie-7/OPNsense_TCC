#!/bin/bash
set -euo pipefail

# --package-only: gera bundle local. --check: verifica SSH/VM, sem instalar.
# Sem argumentos: testa, empacota e instala via SSH. Senhas somente no prompt do SSH.
cd "$(dirname "$0")"
OPNSENSE_IP="${OPNSENSE_IP:-192.168.56.10}"
OPNSENSE_USER="${OPNSENSE_USER:-root}"
OPNSENSE_PORT="${OPNSENSE_PORT:-22}"
MODE="${1:-deploy}"
case "$MODE" in deploy|--package-only|--check) ;; *) echo "Uso: $0 [--package-only|--check]" >&2; exit 2 ;; esac
[[ "$OPNSENSE_IP" =~ ^[a-zA-Z0-9][a-zA-Z0-9._-]*$ ]] || { echo 'Host/alias SSH invalido.' >&2; exit 2; }
[[ "$OPNSENSE_USER" =~ ^[a-zA-Z_][a-zA-Z0-9_-]*$ ]] || { echo 'Usuario SSH invalido.' >&2; exit 2; }
[[ "$OPNSENSE_PORT" =~ ^[0-9]{1,5}$ ]] && ((10#$OPNSENSE_PORT > 0 && 10#$OPNSENSE_PORT <= 65535)) || { echo 'Porta invalida.' >&2; exit 2; }
for cmd in php composer tar python3; do
    command -v "$cmd" >/dev/null || { echo "Dependencia local ausente: $cmd" >&2; exit 1; }
done

library=src/opnsense/mvc/app/library/OPNsense/UserRegister
composer install --working-dir="$library" --no-dev --prefer-dist --no-interaction --no-plugins --no-scripts
bash tests/run.sh
composer audit --working-dir="$library" --no-dev
mkdir -p dist
version=$(awk '/^PLUGIN_VERSION=/{print $2}' Makefile)
bundle="dist/userregister-${version}-sources.tar.gz"
tar --exclude='*/.git' --exclude='*/vendor/bin' -czf "$bundle" src Makefile pkg-descr scripts tests README.md docs
sha256sum "$bundle" > "$bundle.sha256"
if [[ "$MODE" == '--package-only' ]]; then
    echo "Bundle gerado: $bundle (dependencias incluidas; nenhum dado de alunos)."
    exit 0
fi

for cmd in ssh scp; do command -v "$cmd" >/dev/null || exit 1; done
target="${OPNSENSE_USER}@${OPNSENSE_IP}"
control_dir=$(mktemp -d)
chmod 700 "$control_dir"
ssh_opts=(-p "$OPNSENSE_PORT" -o ConnectTimeout=10 -o ServerAliveInterval=15 -o ServerAliveCountMax=3
    -o ControlMaster=auto -o ControlPersist=300 -o "ControlPath=$control_dir/ssh")
if [[ -n "${OPNSENSE_IDENTITY:-}" ]]; then
    [[ -f "$OPNSENSE_IDENTITY" ]] || { echo 'Arquivo de chave nao encontrado.' >&2; exit 1; }
    ssh_opts+=(-i "$OPNSENSE_IDENTITY" -o IdentitiesOnly=yes)
fi
cleanup() {
    ssh "${ssh_opts[@]}" -O exit "$target" >/dev/null 2>&1 || true
    rm -rf -- "$control_dir"
}
trap cleanup EXIT

echo "Conectando a $target na porta $OPNSENSE_PORT. A chave do host sera verificada normalmente."
ssh "${ssh_opts[@]}" -T "$target" 'set -e; test "$(uname -s)" = FreeBSD; test "$(id -u)" = 0; opnsense-version; php -v; test -f /usr/local/opnsense/mvc/app/models/OPNsense/Auth/User.php; test -f /conf/config.xml; echo PRECHECK_OK'
if [[ "$MODE" == '--check' ]]; then
    echo 'Conexao e requisitos basicos verificados; nada foi instalado.'
    exit 0
fi
remote_dir=$(ssh "${ssh_opts[@]}" -T "$target" 'mktemp -d /tmp/userregister.XXXXXXXX')
[[ "$remote_dir" =~ ^/tmp/userregister\.[a-zA-Z0-9]+$ ]] || { echo 'Diretorio remoto inesperado.' >&2; exit 1; }
scp_opts=(-P "$OPNSENSE_PORT" -o "ControlPath=$control_dir/ssh")
scp "${scp_opts[@]}" "$bundle" "$target:$remote_dir/source.tar.gz"
expected=$(sha256sum "$bundle" | cut -d' ' -f1)
ssh "${ssh_opts[@]}" -T "$target" "set -e; test \"\$(sha256 -q '$remote_dir/source.tar.gz')\" = '$expected'; tar -xzf '$remote_dir/source.tar.gz' -C '$remote_dir'; /bin/sh '$remote_dir/scripts/install_remote.sh' '$remote_dir'"
scp "${scp_opts[@]}" "$target:$remote_dir/packages/*.pkg" dist/
ssh "${ssh_opts[@]}" -T "$target" "rm -rf '$remote_dir'"
echo "Instalacao concluida. Pacote FreeBSD copiado para dist/. Abra Lobby > Cadastro de alunos."