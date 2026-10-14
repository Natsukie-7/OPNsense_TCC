#!/bin/sh
# Runs only on the target OPNsense as root; no framework checkout is required.
set -eu
root=$1
case "$root" in /tmp/userregister.*) ;; *) echo 'Diretorio de staging invalido.' >&2; exit 1 ;; esac
cd "$root"
test "$(uname -s)" = FreeBSD
test "$(id -u)" = 0
test -f /conf/config.xml
test -f /usr/local/opnsense/mvc/app/models/OPNsense/Auth/User.php
echo 'Validando extensoes e APIs do OPNsense...'
php -r 'foreach (["zip", "simplexml", "mbstring", "dom"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Extensao ausente: $ext. Instale o pacote PHP correspondente antes de continuar.\n"); exit(1); } }'
php -r 'require "/usr/local/opnsense/mvc/script/load_phalcon.php"; foreach (["OPNsense\\Auth\\User" => ["generatePasswordHash", "serializeToConfig"], "OPNsense\\Core\\Config" => ["lock", "save"], "OPNsense\\Core\\Backend" => ["configdpRun"]] as $class => $methods) { foreach ($methods as $method) { if (!method_exists($class, $method)) { fwrite(STDERR, "API incompativel: $class::$method\n"); exit(1); } } }'
find src -name '*.php' -print0 | xargs -0 -n1 php -l > "$root/lint.log"
php tests/run.php
php tests/controller.php
php tests/native.php /usr/local

version=$(awk '/^PLUGIN_VERSION=/{print $2}' Makefile)
plugin=$(awk '/^PLUGIN_NAME=/{print $2}' Makefile)
package="os-${plugin}-devel"
backup="/conf/backup/userregister-$(date +%Y%m%d-%H%M%S)"
umask 077
mkdir -p "$backup" "$root/packages" "$root/stage/usr/local"
cp -p /conf/config.xml "$backup/config.xml"
chmod 600 "$backup/config.xml"
if pkg info -e "$package"; then
    pkg create -o "$backup" "$package"
fi
echo "Backup local da VM: $backup (contem configuracao sensivel; nao sera copiado para o computador)."
cp -R src/opnsense "$root/stage/usr/local/"
find "$root/stage" -type d -exec chmod 755 {} \;
find "$root/stage" -type f -exec chmod 644 {} \;
php scripts/package_manifest.php "$root" "$root/stage" "$root/manifest.json"
pkg create -M "$root/manifest.json" -r "$root/stage" -o "$root/packages"
set -- "$root/packages/"*.pkg
test -f "$1"
echo 'Instalando pacote e atualizando interface web (breve interrupcao do painel)...'
if ! pkg add -f "$1"; then
    echo "Instalacao falhou. Pacote anterior e config preservados em $backup." >&2
    exit 1
fi
pkg info -e "$package"
pkg check -s "$package"
rm -f /tmp/opnsense_menu_cache.xml
configctl webgui restart
echo "Pacote $package $version instalado. Nenhuma conta de aluno foi criada pelo deploy."
echo "Rollback: reinstale o pacote anterior preservado em $backup, caso exista."
