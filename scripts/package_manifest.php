<?php

// Run on FreeBSD after staging files. pkg computes checksums from these absolute file names.
[$script, $root, $stage, $output] = $argv;
$makefile = file_get_contents($root . '/Makefile');
preg_match('/^PLUGIN_NAME=\s*(\S+)/m', $makefile, $name);
preg_match('/^PLUGIN_VERSION=\s*(\S+)/m', $makefile, $version);
if (empty($name[1]) || empty($version[1])) {
    throw new RuntimeException('Nome/versão de pacote ausentes.');
}
$packageName = 'os-' . $name[1] . '-devel';
$abi = trim(shell_exec('pkg config ABI') ?? '');
if (!str_starts_with($abi, 'FreeBSD:')) {
    throw new RuntimeException('O pacote deve ser gerado no ambiente FreeBSD de destino.');
}
$metadata = [
    'product_abi' => trim(shell_exec('opnsense-version -a') ?? ''),
    'product_arch' => trim(shell_exec('uname -p') ?? ''),
    'product_conflicts' => 'os-' . $name[1], 'product_email' => 'opnsensedev@gmail.com',
    'product_hash' => hash_file('sha256', $root . '/source.tar.gz'),
    'product_id' => $packageName, 'product_name' => $name[1], 'product_tier' => '4',
    'product_version' => $version[1], 'product_website' => 'https://github.com/Natsukie-7/OPNsense_TCC',
];
$versionDir = $stage . '/usr/local/opnsense/version';
if (!is_dir($versionDir)) { mkdir($versionDir, 0755, true); }
chmod($versionDir, 0755);
file_put_contents($versionDir . '/' . $name[1], json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
chmod($versionDir . '/' . $name[1], 0644);
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Links simbólicos não são aceitos no pacote.');
    }
    if ($file->isFile()) {
        $files[substr($file->getPathname(), strlen($stage))] = '1$' . hash_file('sha256', $file->getPathname());
    }
}
ksort($files);
$dependencies = [];
exec("pkg query '%n %v %o'", $packages, $exit);
if ($exit !== 0) { throw new RuntimeException('Não foi possível consultar o pkg.'); }
foreach ($packages as $package) {
    [$pkg, $pkgVersion, $origin] = explode(' ', $package, 3);
    if (preg_match('/^php[0-9]+-(zip|simplexml)$/', $pkg)) {
        $dependencies[$pkg] = ['version' => $pkgVersion, 'origin' => $origin];
    }
}
$manifest = [
    'name' => $packageName, 'version' => $version[1], 'abi' => $abi, 'arch' => strtolower($abi),
    'origin' => 'opnsense/' . $packageName, 'comment' => 'Cadastro de alunos via XLSX',
    'desc' => 'Importacao de alunos com previa, validacao e contas locais sem privilegios administrativos.',
    'maintainer' => 'opnsensedev@gmail.com', 'www' => 'https://github.com/Natsukie-7/OPNsense_TCC',
    'prefix' => '/usr/local', 'categories' => ['devel'], 'annotations' => $metadata,
    'files' => $files, 'deps' => (object)$dependencies,
    'scripts' => [
        'post-install' => 'if [ -f /usr/local/etc/rc.configure_plugins ]; then /usr/local/etc/rc.configure_plugins POST_INSTALL; fi',
        'post-deinstall' => 'if [ -f /usr/local/etc/rc.configure_plugins ]; then /usr/local/etc/rc.configure_plugins POST_DEINSTALL; fi',
    ],
];
file_put_contents($output, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
