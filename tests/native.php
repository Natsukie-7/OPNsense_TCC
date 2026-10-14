<?php

/** Real OPNsense models/Config against a synthetic temporary config; only configd is stubbed. */
namespace UserRegister\Tests {
    class FakeBackend
    {
        public static bool $fail = false;
        public function configdRun($event, ...$args) { return '{}'; }
        public function configdpRun($event, ...$args) {
            return self::$fail ? '{}' : '{"status":"updated"}';
        }
    }
}

namespace {
    class_alias(\UserRegister\Tests\FakeBackend::class, 'OPNsense\Core\Backend');
    $core = $argv[1] ?? '';
    $app = rtrim($core, '/') . '/src/opnsense/mvc/app';
    if (!is_file($app . '/config/AppConfig.php')) {
        $app = rtrim($core, '/') . '/opnsense/mvc/app';
    }
    if (!is_file($app . '/config/AppConfig.php')) {
        fwrite(STDERR, "Informe o caminho para um checkout do core OPNsense atual.\n");
        exit(1);
    }
    require $app . '/config/AppConfig.php';
    if (!class_exists('Phalcon\Filter\Validation')) {
        fwrite(STDERR, "SKIP: a extensão Phalcon do OPNsense é necessária para testar os modelos reais.\n");
        exit(77);
    }
    $temp = sys_get_temp_dir() . '/ur-native-' . bin2hex(random_bytes(8));
    mkdir($temp, 0700);
    $rootHash = password_hash('Only-a-synthetic-test!', PASSWORD_BCRYPT);
    file_put_contents($temp . '/config.xml', '<opnsense><system><hostname>test</hostname><domain>invalid</domain>' .
        '<webgui><protocol>https</protocol></webgui><user uuid="00000000-0000-4000-8000-000000000001">' .
        '<uid>0</uid><name>root</name><scope>system</scope><password>' . $rootHash . '</password>' .
        '<descr>Test administrator</descr><priv>page-all</priv></user><group><name>admins</name><gid>1999</gid>' .
        '<member>0</member><priv>page-all</priv></group></system></opnsense>');
    $config = new \OPNsense\Core\AppConfig([
        'application' => ['controllersDir' => $app . '/controllers/', 'modelsDir' => $app . '/models/',
            'libraryDir' => $app . '/library/', 'configDir' => $temp, 'cacheDir' => $temp,
            'tempDir' => $temp, 'configDefault' => '/does/not/exist', 'contribDir' => $core . '/src/opnsense/contrib'],
        'globals' => ['debug' => false, 'simulate_mode' => true, 'owner' => 'wwwonly:wheel'],
    ]);
    require $app . '/config/loader.php';
    $library = dirname(__DIR__) . '/src/opnsense/mvc/app/library/OPNsense/UserRegister/';
    require $library . 'ImportPlanner.php';
    require $library . 'UserImporter.php';
    $checks = 0;
    $assert = function ($condition, $message) use (&$checks) {
        $checks++;
        if (!$condition) { throw new \RuntimeException($message); }
    };
    try {
        $importer = new \OPNsense\UserRegister\UserImporter();
        $records = [
            ['username' => '0012345678901', 'name' => 'Aluno Fictício', 'course' => 'Curso Teste'],
            ['username' => '0012345678902', 'name' => 'Outra Pessoa', 'course' => 'Curso Teste'],
        ];
        $result = $importer->createBatch($records, '');
        $assert(count($result['credentials']) === 2, 'Criar dois usuários com o modelo real');
        $xml = simplexml_load_file($temp . '/config.xml');
        $assert(count($xml->system->user) === 3, 'Persistir sem perder root');
        $assert((string)$xml->system->user[0]->password === $rootHash, 'Preservar senha de root');
        $assert((string)$xml->system->group->member === '0', 'Não adicionar alunos ao grupo admins');
        foreach ($result['credentials'] as $i => $record) {
            $node = $xml->system->user[$i + 1];
            $assert((string)$node->name === $record['username'], 'Preservar zeros na configuração nativa');
            $assert(password_verify($record['password'], (string)$node->password), 'Hash autentica senha emitida');
            $assert((string)$node->shell === '' && (string)$node->priv === '', 'Nenhum shell ou privilégio');
            $assert((string)$node->disabled === '0' && (int)$node->uid >= 2000, 'Conta habilitada com UID regular');
            $assert(!str_contains(file_get_contents($temp . '/config.xml'), $record['password']), 'Não persistir senha em claro na configuração');
        }
        $assert($result['credentials'][0]['password'] !== $result['credentials'][1]['password'], 'Senhas individuais');
        $before = file_get_contents($temp . '/config.xml');
        $retry = $importer->createBatch($records, '');
        $assert($retry['skipped'] === 2 && !$retry['credentials'], 'Revalidar duplicações no momento da gravação');
        $assert(file_get_contents($temp . '/config.xml') === $before, 'Não salvar nem redefinir senhas em repetição');
        $rejected = false;
        try {
            $importer->createBatch([
                ['username' => '0012345678903', 'name' => 'Pessoa Válida', 'course' => 'Teste'],
                ['username' => 'invalid!name', 'name' => 'Pessoa Inválida', 'course' => 'Teste'],
            ], '');
        } catch (\RuntimeException $e) {
            $rejected = true;
        }
        $assert($rejected, 'Modelo deve rejeitar lote inválido');
        $assert(file_get_contents($temp . '/config.xml') === $before, 'Lote inválido não grava parcialmente');
        $assert($importer->sync(['0012345678901']) === [], 'Sincronização aceita retorno do configd');
        \UserRegister\Tests\FakeBackend::$fail = true;
        $assert($importer->sync(['0012345678901']) === ['0012345678901'], 'Falha de sync permanece pendente');
        echo "OK: $checks verificações com User/Config reais do OPNsense (configd simulado).\n";
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($temp);
    }
}
