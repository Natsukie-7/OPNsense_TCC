<?php

namespace OPNsense\UserRegister;

class ImportPlanner
{
    public static function plan(array $records, array $existing): array
    {
        $seen = [];
        foreach ($records as $i => &$record) {
            $record['status'] = 'new';
            if ($record['error'] !== '') {
                $record['status'] = 'error';
            } elseif (!preg_match('/^[0-9]{5,20}$/D', $record['username'])) {
                $record['status'] = 'error';
                $record['error'] = 'A matrícula deve conter entre 5 e 20 dígitos (preserve os zeros à esquerda).';
            } elseif (!preg_match('/\p{L}/u', $record['name']) || strlen($record['name']) > 200
                || preg_match('/[\x00-\x1f\x7f<>]/u', $record['name'])) {
                $record['status'] = 'error';
                $record['error'] = 'Informe um nome válido, com até 200 bytes, sem caracteres de controle ou HTML.';
            } elseif (isset($existing[$record['username']])) {
                $record['status'] = 'existing';
                $record['error'] = 'Conta existente: nome, senha e privilégios serão preservados.';
            }
            $key = $record['username'];
            if (isset($seen[$key])) {
                $first = $seen[$key];
                if ($records[$first]['name'] !== $record['name']) {
                    $record['status'] = $records[$first]['status'] = 'error';
                    $record['error'] = $records[$first]['error'] = 'Matrícula repetida com nomes diferentes; corrija o arquivo.';
                } elseif ($record['status'] === 'new') {
                    $record['status'] = 'duplicate';
                    $record['error'] = 'Matrícula repetida na planilha; somente a primeira ocorrência será criada.';
                }
            } else {
                $seen[$key] = $i;
            }
        }
        unset($record);
        $summary = ['total' => count($records), 'new' => 0, 'existing' => 0, 'duplicate' => 0, 'error' => 0];
        foreach ($records as $record) {
            $summary[$record['status']]++;
        }
        return ['records' => $records, 'summary' => $summary];
    }

    public static function validateExpiry(string $expiry): string
    {
        if ($expiry === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);
        if (!$date || $date->format('Y-m-d') !== $expiry || $date <= new \DateTimeImmutable('today')) {
            throw new \InvalidArgumentException('A validade deve ser uma data futura no formato AAAA-MM-DD.');
        }
        return $expiry;
    }

    public static function credentialsCsv(array $credentials): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['matricula', 'nome', 'curso', 'senha', 'validade'], ';', '"', '');
        foreach ($credentials as $record) {
            $cells = [];
            foreach (['username', 'name', 'course', 'password', 'expires'] as $key) {
                $value = (string)$record[$key];
                // CSV spreadsheet formula injection protection; prefix RAs to retain leading zeroes.
                $cells[] = $key === 'username' || preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value)
                    ? "'" . $value : $value;
            }
            fputcsv($stream, $cells, ';', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
