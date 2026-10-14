<?php

namespace OPNsense\UserRegister;

use OPNsense\Auth\User;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/** Uses the native model: no direct XML edits, no shell privileges, no password resets. */
class UserImporter
{
    public function existing(): array
    {
        $names = [];
        foreach ((new User())->user->iterateItems() as $node) {
            $names[(string)$node->name] = true;
        }
        return $names;
    }

    public function createBatch(array $records, string $expires): array
    {
        ImportPlanner::validateExpiry($expires);
        $config = Config::getInstance();
        $created = [];
        $skipped = 0;
        $config->lock();
        try {
            // Construct AFTER lock(): another admin may have changed config since preview.
            $model = new User();
            $existing = [];
            foreach ($model->user->iterateItems() as $node) {
                $existing[(string)$node->name] = true;
            }
            foreach ($records as $record) {
                if (isset($existing[$record['username']])) {
                    $skipped++;
                    continue;
                }
                $password = 'Aa1!' . bin2hex(random_bytes(10));
                $hash = $model->generatePasswordHash($password);
                if (!is_string($hash) || substr($hash, 0, 1) !== '$') {
                    throw new \RuntimeException('Não foi possível gerar o hash da senha.');
                }
                $node = $model->user->Add();
                $node->name = $record['username'];
                $node->descr = $record['name'];
                $node->comment = 'UserRegister | ' . $record['course'];
                $node->password = $hash;
                $node->pwd_changed_at = (string)microtime(true);
                $node->disabled = '0';
                $node->scope = 'user';
                $node->shell = '';
                $node->priv = '';
                $node->expires = $expires;
                $existing[$record['username']] = true;
                $created[] = array_merge($record, ['password' => $password, 'expires' => $expires]);
            }
            if ($created) {
                foreach ($model->performValidation() as $validationMessage) {
                    throw new \RuntimeException('O modelo nativo rejeitou o lote. Verifique validade e limites de usuários.');
                }
                $model->serializeToConfig();
                $config->save('UserRegister: ' . count($created) . ' contas de alunos criadas');
            }
        } finally {
            $config->unlock();
        }
        return ['credentials' => $created, 'skipped' => $skipped];
    }

    public function sync(array $usernames): array
    {
        $failed = [];
        foreach ($usernames as $username) {
            try {
                $response = (new Backend())->configdpRun('auth sync user', [$username], false, 2, 1);
                $result = json_decode(trim($response), true);
                if (($result['status'] ?? '') !== 'updated') {
                    $failed[] = $username;
                }
            } catch (\Throwable $e) {
                $failed[] = $username;
            }
        }
        return $failed;
    }
}
