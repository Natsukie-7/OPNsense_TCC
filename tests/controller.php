<?php

/** Exercise session job protocol with fake HTTP/native-user adapters, not a replacement for VM testing. */
namespace UserRegister\Tests {
    class FakeApiControllerBase
    {
        public $request;
        public $response;
        public static bool $external = false;
        protected function getUserName() { return 'test-admin'; }
        protected function isExternalClient() { return self::$external; }
        protected function getLogger($name) { return new class {
            public function info($message) {}
            public function error($message) {}
        }; }
    }
}
namespace UserRegister\Tests {
    class FakeUserImporter
    {
        public static int $calls = 0;
        public function createBatch($records, $expires) {
            self::$calls++;
            return ['skipped' => 0, 'credentials' => array_map(fn($r) => array_merge($r, ['password' => 'Synthetic-only!', 'expires' => $expires]), $records)];
        }
        public function sync($users) { return []; }
    }
}
namespace {
    class_alias(\UserRegister\Tests\FakeApiControllerBase::class, 'OPNsense\Base\ApiControllerBase');
    class_alias(\UserRegister\Tests\FakeUserImporter::class, 'OPNsense\UserRegister\UserImporter');
    $base = dirname(__DIR__) . '/src/opnsense/mvc/app/';
    require $base . 'library/OPNsense/UserRegister/ImportPlanner.php';
    require $base . 'library/OPNsense/UserRegister/SpreadsheetReader.php';
    require $base . 'controllers/OPNsense/UserRegister/Api/SettingsController.php';
    $temp = sys_get_temp_dir() . '/ur-session-' . bin2hex(random_bytes(8));
    mkdir($temp, 0700);
    session_save_path($temp);
    session_id('synthetic-' . bin2hex(random_bytes(8)));
    session_start();
    $records = [];
    for ($i = 0; $i < 12; $i++) {
        $records[] = ['username' => '12345' . $i, 'name' => 'Pessoa Fictícia', 'course' => 'Teste', 'line' => $i + 1, 'error' => '', 'status' => 'new'];
    }
    $_SESSION = ['Username' => 'test-admin', 'userregister_import' => [
        'records' => $records, 'summary' => ['total' => 12, 'new' => 12, 'error' => 0, 'existing' => 0, 'duplicate' => 0],
        'token' => 'test-token', 'deadline' => time() + 1800, 'sheets' => ['Teste'], 'expires' => '',
        'offset' => 0, 'credentials' => [], 'skipped' => 0, 'sync_pending' => [],
    ]];
    session_write_close();
    $controller = new \OPNsense\UserRegister\Api\SettingsController();
    $request = new class {
        public string $method = 'POST';
        public array $data = [];
        public function isPost() { return $this->method === 'POST'; }
        public function isGet() { return $this->method === 'GET'; }
        public function getPost($key, $filter = null, $default = null) { return $this->data[$key] ?? $default; }
    };
    $response = new class {
        public int $status = 200;
        public function setHeader($key, $value) {}
        public function setStatusCode($code, $reason) { $this->status = $code; }
    };
    // Reflection injects isolated adapters without claiming they are real MVC Request/Response instances.
    (new \ReflectionProperty($controller, 'request'))->setValue($controller, $request);
    (new \ReflectionProperty($controller, 'response'))->setValue($controller, $response);
    $checks = 0;
    $check = function ($ok, $message) use (&$checks) { $checks++; if (!$ok) { throw new \RuntimeException($message); } };
    try {
        $request->data = ['token' => 'wrong', 'offset' => 0, 'confirm' => true];
        $check($controller->importAction()['status'] === 'error', 'Rejeitar token de outra prévia');
        $request->data = ['token' => 'test-token', 'offset' => 0, 'confirm' => false];
        $check($controller->importAction()['status'] === 'error', 'Exigir confirmação');
        $request->data['confirm'] = true;
        $result = $controller->importAction();
        $check($result['job']['offset'] === 10 && $result['job']['created'] === 10, 'Processar no máximo dez por lote');
        $check(!isset($result['job']['credentials']) && !str_contains(json_encode($result), 'Synthetic-only!'), 'Não expor senhas na prévia');
        $retry = $controller->importAction();
        $check(\UserRegister\Tests\FakeUserImporter::$calls === 1 && $retry['job']['offset'] === 10, 'Requisição repetida não recria lote');
        $request->data['offset'] = 999;
        $check($controller->importAction()['status'] === 'error', 'Rejeitar salto de offset');
        $request->data['offset'] = 10;
        $result = $controller->importAction();
        $check($result['job']['done'] && $result['job']['created'] === 12, 'Concluir último lote parcial');
        $result = $controller->syncAction();
        $check($result['job']['sync_pending'] === 2, 'Sincronizar no máximo dez contas');
        $check($controller->syncAction()['job']['sync_pending'] === 0, 'Concluir sincronização');
        $check(str_contains($controller->downloadAction()['csv'], 'Synthetic-only!'), 'Exportar credenciais somente pela ação explícita');
        $request->method = 'GET';
        $check($controller->downloadAction()['status'] === 'error' && $response->status === 405, 'Bloquear GET de credenciais');
        $check($controller->getAction()['job']['created'] === 12, 'Recuperar progresso após nova requisição');
        \UserRegister\Tests\FakeApiControllerBase::$external = true;
        $check($controller->getAction()['status'] === 'error' && $response->status === 403, 'Bloquear API key em fluxo de sessão');
        \UserRegister\Tests\FakeApiControllerBase::$external = false;
        $request->method = 'POST';
        $check($controller->clearAction()['status'] === 'ok', 'Limpar dados temporários');
        $check($controller->downloadAction()['status'] === 'error', 'Não recuperar credenciais depois de limpar');
        session_start();
        $_SESSION['userregister_import'] = ['deadline' => time() - 1];
        session_write_close();
        $request->method = 'GET';
        $check($controller->getAction()['job'] === null, 'Expirar sessão de importação');
        session_start();
        $_SESSION['Username'] = 'other-admin';
        session_write_close();
        $check($controller->getAction()['status'] === 'error', 'Revalidar proprietário autenticado da sessão');
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        foreach (glob($temp . '/*') as $file) { unlink($file); }
        rmdir($temp);
    }
    echo "OK: $checks verificações do protocolo de sessão e lotes (adapters simulados).\n";
}
