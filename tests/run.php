<?php

// Self-contained tests: no real OPNsense configuration or student data is modified.
$library = dirname(__DIR__) . '/src/opnsense/mvc/app/library/OPNsense/UserRegister/';
require $library . 'SpreadsheetReader.php';
require $library . 'ImportPlanner.php';

use OPNsense\UserRegister\ImportPlanner;
use OPNsense\UserRegister\SpreadsheetReader;

$checks = 0;
function check($condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $e) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

function workbook(array $sheets, array $extra = []): string
{
    $file = tempnam(sys_get_temp_dir(), 'ur-test-');
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $rels = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
    $zip->addFromString('_rels/.rels', '<Relationships xmlns="' . $rels . '"><Relationship Id="rId1" Type="' . $type . 'officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $book = '<workbook xmlns="' . $ns . '" xmlns:r="' . $type . '"><sheets>';
    $links = '<Relationships xmlns="' . $rels . '">';
    foreach ($sheets as $i => $rows) {
        $id = $i + 1;
        $book .= '<sheet name="Curso ' . $id . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        $links .= '<Relationship Id="rId' . $id . '" Type="' . $type . 'worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $id . '.xml', '<worksheet xmlns="' . $ns . '"><sheetData>' . $rows . '</sheetData></worksheet>');
    }
    $zip->addFromString('xl/workbook.xml', $book . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $links . '</Relationships>');
    foreach ($extra as $path => $data) {
        $zip->addFromString($path, $data);
    }
    $zip->close();
    return $file;
}

function row(int $n, string $ra, string $name, bool $numeric = false): string
{
    $a = $numeric ? '<c r="A' . $n . '"><v>' . htmlspecialchars($ra, ENT_XML1) . '</v></c>' :
        '<c r="A' . $n . '" t="inlineStr"><is><t>' . htmlspecialchars($ra, ENT_XML1) . '</t></is></c>';
    return '<row r="' . $n . '">' . $a . '<c r="B' . $n . '" t="inlineStr"><is><t>' .
        htmlspecialchars($name, ENT_XML1) . '</t></is></c></row>';
}

$files = [];
try {
    $files[] = $file = workbook([
        row(1, '0012345678901', 'Aluno Fictício') . row(2, '1570000000001', 'Pessoa Teste', true),
        row(1, 'Matrícula', 'Nome completo') . row(2, '1570000000002', 'Outra Pessoa') . '<row r="3"/>',
    ]);
    $data = (new SpreadsheetReader())->read($file);
    check(count($data['sheets']) === 2, 'Ler todas as abas');
    check(count($data['records']) === 3, 'Não descartar primeira linha sem cabeçalho; ignorar cabeçalho e linha vazia');
    check($data['records'][0]['username'] === '0012345678901', 'Preservar zero inicial');
    check($data['records'][1]['username'] === '1570000000001', 'Preservar RA numérico de 13 dígitos');
    check($data['records'][2]['line'] === 2, 'Preservar linha original');
    $records = $data['records'];
    $records[] = $records[0];
    $plan = ImportPlanner::plan($records, ['1570000000001' => true]);
    check($plan['summary'] === ['total' => 4, 'new' => 2, 'existing' => 1, 'duplicate' => 1, 'error' => 0], 'Resumo e duplicação');
    $records[3]['name'] = 'Nome Conflitante';
    $plan = ImportPlanner::plan($records, []);
    check($plan['summary']['error'] === 2, 'Bloquear ambas as matrículas conflitantes');
    foreach (['root', '=1+2', '', '1234', str_repeat('1', 21)] as $invalid) {
        $records = [$data['records'][0]]; $records[0]['username'] = $invalid;
        check(ImportPlanner::plan($records, [])['summary']['error'] === 1, 'Rejeitar RA inválido');
    }
    $records = [$data['records'][0]]; $records[0]['name'] = '<script>alert(1)</script>';
    check(ImportPlanner::plan($records, [])['summary']['error'] === 1, 'Rejeitar nome com HTML');
    $files[] = $formula = workbook(['<row r="1"><c r="A1"><f>1+1</f><v>12345</v></c><c r="B1" t="inlineStr"><is><t>Aluno</t></is></c></row>']);
    check(ImportPlanner::plan((new SpreadsheetReader())->read($formula)['records'], [])['summary']['error'] === 1, 'Rejeitar fórmula');
    $files[] = $imprecise = workbook([row(1, '1570000000000001', 'Pessoa', true)]);
    check((new SpreadsheetReader())->read($imprecise)['records'][0]['error'] !== '', 'Rejeitar número Excel acima de 15 dígitos');
    $files[] = $scientific = workbook([row(1, '1.57E+12', 'Pessoa', true)]);
    check((new SpreadsheetReader())->read($scientific)['records'][0]['error'] !== '', 'Rejeitar notação científica');
    $files[] = $empty = workbook(['']);
    rejects(fn() => (new SpreadsheetReader())->read($empty), 'Rejeitar planilha vazia');
    $files[] = $xxe = workbook([row(1, '12345', 'Pessoa')], ['evil.xml' => '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>']);
    rejects(fn() => (new SpreadsheetReader())->read($xxe), 'Bloquear XXE/DTD');
    $files[] = $traversal = workbook([row(1, '12345', 'Pessoa')], ['../outside.xml' => '<x/>']);
    rejects(fn() => (new SpreadsheetReader())->read($traversal), 'Bloquear path traversal');
    $files[] = $bomb = workbook([row(1, '12345', 'Pessoa')], ['large.bin' => str_repeat('A', 8388609)]);
    rejects(fn() => (new SpreadsheetReader())->read($bomb), 'Bloquear ZIP de expansão excessiva');
    $files[] = $nodes = workbook([row(1, '12345', 'Pessoa')], ['nodes.xml' => '<root>' . str_repeat('<node/>', 100001) . '</root>']);
    rejects(fn() => (new SpreadsheetReader())->read($nodes), 'Limitar alocação de árvores XML antes do parse');
    $files[] = $external = workbook([row(1, '12345', 'Pessoa')], ['external.rels' => '<Relationships><Relationship TargetMode="External" Target="https://example.com"/></Relationships>']);
    rejects(fn() => (new SpreadsheetReader())->read($external), 'Bloquear link externo');
    $files[] = $malformed = workbook([row(1, '12345', 'Pessoa')], ['bad.xml' => '<broken>']);
    rejects(fn() => (new SpreadsheetReader())->read($malformed), 'Bloquear XML inválido');
    check(ImportPlanner::validateExpiry('') === '', 'Permitir sem expiração');
    $future = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
    check(ImportPlanner::validateExpiry($future) === $future, 'Aceitar validade futura');
    rejects(fn() => ImportPlanner::validateExpiry('2000-01-01'), 'Rejeitar data passada');
    rejects(fn() => ImportPlanner::validateExpiry('2099-02-31'), 'Rejeitar data impossível');
    rejects(fn() => ImportPlanner::validateExpiry('tomorrow'), 'Rejeitar formato de data livre');
    $csv = ImportPlanner::credentialsCsv([['username' => '0012345', 'name' => '=HYPERLINK("evil")', 'course' => '@SUM(1)', 'password' => 'Aa1!ficticia', 'expires' => '']]);
    check(str_contains($csv, "'0012345"), 'CSV preserva matrícula textual');
    check(str_contains($csv, "'=HYPERLINK") && str_contains($csv, "'@SUM"), 'CSV neutraliza fórmulas');
    if (isset($argv[1])) {
        $real = (new SpreadsheetReader())->read($argv[1]);
        $summary = ImportPlanner::plan($real['records'], [])['summary'];
        echo 'Planilha fornecida (somente totais): ' . json_encode(['abas' => count($real['sheets']), 'resumo' => $summary]) . PHP_EOL;
    }
    echo "OK: $checks verificações de leitura, validação e segurança.\n";
} finally {
    foreach ($files as $file) { unlink($file); }
}
