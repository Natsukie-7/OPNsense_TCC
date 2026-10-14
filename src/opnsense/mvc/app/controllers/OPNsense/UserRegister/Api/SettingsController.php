<?php

namespace OPNsense\UserRegister\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\UserRegister\ImportPlanner;
use OPNsense\UserRegister\SpreadsheetReader;
use OPNsense\UserRegister\UserImporter;

class SettingsController extends ApiControllerBase
{
    private const SESSION_KEY = 'userregister_import';
    private const TTL = 1800;
    private const BATCH_SIZE = 10;

    public function getAction()
    {
        return $this->guard(false, function () {
            return [
                'status' => 'ok', 'version' => '2.0.0',
                'job' => isset($_SESSION[self::SESSION_KEY]) ? $this->publicJob() : null,
                'limits' => ['bytes' => SpreadsheetReader::MAX_BYTES, 'rows' => SpreadsheetReader::MAX_ROWS],
            ];
        });
    }

    public function previewAction()
    {
        return $this->guard(true, function () {
            if (!empty($_SESSION[self::SESSION_KEY]['credentials'])) {
                throw new \InvalidArgumentException('Baixe as credenciais e encerre a importação anterior primeiro.');
            }
            // The current OPNsense Request is not Phalcon and has no getUploadedFiles().
            $file = $_FILES['file'] ?? null;
            if (count($_FILES) !== 1 || !is_array($file)
                || ($file['error'] ?? null) !== UPLOAD_ERR_OK
                || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
                || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx'
                || !is_uploaded_file($file['tmp_name'])) {
                throw new \InvalidArgumentException('Envie um arquivo .xlsx válido no campo file (máximo 5 MiB e limite do PHP).');
            }
            $expiry = $this->request->getPost('expires', null, '');
            if (!is_string($expiry)) {
                throw new \InvalidArgumentException('Validade deve ser uma data textual ou ficar em branco.');
            }
            $expiry = ImportPlanner::validateExpiry($expiry);
            $data = (new SpreadsheetReader())->read($file['tmp_name']);
            $plan = ImportPlanner::plan($data['records'], (new UserImporter())->existing());
            $_SESSION[self::SESSION_KEY] = array_merge($plan, [
                'token' => bin2hex(random_bytes(32)), 'deadline' => time() + self::TTL,
                'sheets' => $data['sheets'], 'expires' => $expiry, 'offset' => 0,
                'credentials' => [], 'skipped' => 0, 'sync_pending' => [],
            ]);
            return ['status' => 'ok', 'job' => $this->publicJob()];
        });
    }

    public function importAction()
    {
        return $this->guard(true, function () {
            $this->checkToken();
            $job = &$_SESSION[self::SESSION_KEY];
            if ($job['summary']['error'] > 0 || $this->request->getPost('confirm') !== true) {
                throw new \InvalidArgumentException('Corrija as linhas inválidas e confirme explicitamente a criação.');
            }
            // A retry of the previous request returns progress, never creates/reset accounts twice.
            $offset = $this->request->getPost('offset');
            if (!is_int($offset) || $offset < 0 || $offset > $job['offset']) {
                throw new \InvalidArgumentException('Posição de importação inválida; recarregue a prévia.');
            }
            if ($offset === $job['offset']) {
                $candidates = array_values(array_filter($job['records'], static function ($row) {
                    return $row['status'] === 'new';
                }));
                $batch = array_slice($candidates, $job['offset'], self::BATCH_SIZE);
                if ($batch) {
                    $result = (new UserImporter())->createBatch($batch, $job['expires']);
                    $job['credentials'] = array_merge($job['credentials'], $result['credentials']);
                    $job['sync_pending'] = array_merge($job['sync_pending'], array_column($result['credentials'], 'username'));
                    $job['offset'] += count($batch);
                    $job['skipped'] += $result['skipped'];
                    $job['deadline'] = time() + self::TTL;
                    $this->getLogger('audit')->info('UserRegister: lote concluído; criados=' . count($result['credentials']));
                }
            }
            return ['status' => 'ok', 'job' => $this->publicJob(false)];
        });
    }

    public function syncAction()
    {
        return $this->guard(true, function () {
            $this->checkToken();
            $job = &$_SESSION[self::SESSION_KEY];
            $batch = array_slice($job['sync_pending'], 0, self::BATCH_SIZE);
            $failed = (new UserImporter())->sync($batch);
            $job['sync_pending'] = array_merge($failed, array_slice($job['sync_pending'], count($batch)));
            $job['deadline'] = time() + self::TTL;
            return ['status' => $failed ? 'warning' : 'ok', 'job' => $this->publicJob(false),
                'message' => $failed ? 'Contas salvas, mas há sincronizações pendentes. Verifique o configd e tente novamente.' : ''];
        });
    }

    public function downloadAction()
    {
        return $this->guard(true, function () {
            $this->checkToken();
            return ['status' => 'ok', 'csv' => ImportPlanner::credentialsCsv($_SESSION[self::SESSION_KEY]['credentials'])];
        });
    }

    public function clearAction()
    {
        return $this->guard(true, function () {
            $this->checkToken();
            unset($_SESSION[self::SESSION_KEY]);
            return ['status' => 'ok'];
        });
    }

    private function checkToken(): void
    {
        $token = $this->request->getPost('token');
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($token)
            || !hash_equals($_SESSION[self::SESSION_KEY]['token'], $token)) {
            throw new \InvalidArgumentException('Prévia expirada ou inválida. Envie a planilha novamente.');
        }
    }

    private function publicJob(bool $includeRecords = true): array
    {
        $job = $_SESSION[self::SESSION_KEY];
        $result = array_intersect_key($job, array_flip(['token', 'deadline', 'summary', 'sheets', 'expires', 'offset', 'skipped']));
        $result['created'] = count($job['credentials']);
        $result['sync_pending'] = count($job['sync_pending']);
        $result['done'] = $job['offset'] >= $job['summary']['new'];
        if ($includeRecords) {
            $result['records'] = $job['records'];
        }
        return $result;
    }

    private function guard(bool $post, callable $callback): array
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $this->response->setHeader('Pragma', 'no-cache');
        if ($post ? !$this->request->isPost() : !$this->request->isGet()) {
            $this->response->setStatusCode(405, 'Method Not Allowed');
            return ['status' => 'error', 'message' => 'Método HTTP não permitido.'];
        }
        // Session-bound preview/credentials: API key authentication is intentionally not supported.
        if ($this->isExternalClient()) {
            $this->response->setStatusCode(403, 'Forbidden');
            return ['status' => 'error', 'message' => 'Use a sessão autenticada da interface web.'];
        }
        try {
            $library = dirname((new \ReflectionClass(SpreadsheetReader::class))->getFileName());
            if (!class_exists('ZipArchive') || !extension_loaded('simplexml')
                || !is_file($library . '/vendor/autoload.php')) {
                throw new \RuntimeException('Instale as dependências PHP e Composer do plugin.');
            }
            // OPNsense's session adapter releases locks early. Reopen the native session to serialize batches.
            if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
                throw new \RuntimeException('Não foi possível abrir a sessão.');
            }
            if (($_SESSION['Username'] ?? '') !== $this->getUserName()) {
                $this->response->setStatusCode(403, 'Forbidden');
                return ['status' => 'error', 'message' => 'Sessão inválida. Entre novamente.'];
            }
            if (isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY]['deadline'] < time()) {
                unset($_SESSION[self::SESSION_KEY]);
            }
            return $callback();
        } catch (\InvalidArgumentException $e) {
            $this->response->setStatusCode(400, 'Bad Request');
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            // Do not log names, passwords, XLSX contents, config or validation payloads.
            $this->getLogger('audit')->error('UserRegister: falha interna (' . get_class($e) . ')');
            $this->response->setStatusCode(500, 'Internal Server Error');
            return ['status' => 'error', 'message' => 'Falha ao processar o lote. Recarregue a prévia antes de retomar. Consulte o log de auditoria e os requisitos de instalação.'];
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}