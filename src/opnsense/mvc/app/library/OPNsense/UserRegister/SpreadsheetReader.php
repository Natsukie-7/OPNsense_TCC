<?php

namespace OPNsense\UserRegister;

use InvalidArgumentException;
use Shuchkin\SimpleXLSX;
use ZipArchive;

/** Bounded, read-only XLSX import. Never extract archives or evaluate formulas. */
class SpreadsheetReader
{
    public const MAX_BYTES = 5242880;
    public const MAX_ROWS = 5000;

    public function read(string $path): array
    {
        $this->inspectArchive($path);
        require_once __DIR__ . '/vendor/autoload.php';
        $xlsx = SimpleXLSX::parseFile($path);
        if (!$xlsx) {
            throw new InvalidArgumentException('Planilha XLSX inválida ou protegida por senha.');
        }
        if (count($xlsx->sheetNames()) > 30) {
            throw new InvalidArgumentException('Limite de 30 abas excedido.');
        }
        $records = [];
        $sheets = [];
        $scanned = 0;
        foreach ($xlsx->sheetNames() as $index => $course) {
            $sheets[] = $course;
            foreach ($xlsx->worksheet($index)->sheetData->row as $row) {
                if (++$scanned > self::MAX_ROWS) {
                    throw new InvalidArgumentException('Limite de 5.000 linhas por arquivo excedido.');
                }
                $values = ['', ''];
                $error = '';
                $position = 0;
                foreach ($row->c as $cell) {
                    $reference = (string)$cell['r'];
                    $column = $reference !== '' ? $xlsx->getIndex($reference)[0] : $position;
                    $position++;
                    if ($column < 0 || $column > 1) {
                        continue;
                    }
                    if (isset($cell->f)) {
                        $error = 'Fórmulas não são aceitas nas colunas A e B; cole somente valores.';
                    }
                    $type = (string)$cell['t'];
                    if ($type === '' || $type === 'n') {
                        // Raw digits avoid float conversion and preserve a text RA's leading zeroes.
                        $value = (string)$cell->v;
                        if ($column === 0 && (!preg_match('/^[0-9]+$/D', $value) || strlen($value) > 15)) {
                            $error = 'Matrícula numérica imprecisa; formate a célula como Texto e redigite o RA.';
                        }
                    } else {
                        $value = (string)$xlsx->value($cell);
                        if (!in_array($type, ['s', 'inlineStr', 'str'], true)) {
                            $error = 'Tipo de célula não suportado; utilize Texto.';
                        }
                    }
                    $values[$column] = trim($value);
                }
                if ($values === ['', ''] && $error === '') {
                    continue;
                }
                $header = strtolower(strtr($values[0], ['í' => 'i', 'Í' => 'i']));
                if (in_array($header, ['ra', 'matricula', 'registro academico'], true)
                    && preg_match('/^nome( completo| do aluno)?$/iu', $values[1])) {
                    continue;
                }
                if (strlen($values[0]) > 100 || strlen($values[1]) > 1024 || strlen($course) > 128) {
                    throw new InvalidArgumentException('Célula ou nome de aba maior que o limite permitido.');
                }
                $records[] = [
                    'username' => $values[0], 'name' => $values[1], 'course' => $course,
                    'line' => (int)$row['r'], 'error' => $error,
                ];
            }
        }
        if (!$records) {
            throw new InvalidArgumentException('Nenhum aluno encontrado nas colunas A (matrícula) e B (nome).');
        }
        return ['records' => $records, 'sheets' => $sheets];
    }

    private function inspectArchive(string $path): void
    {
        if (!is_file($path) || filesize($path) < 1 || filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Envie um arquivo XLSX de até 5 MiB.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('O arquivo não é uma planilha XLSX válida.');
        }
        try {
            if ($zip->numFiles > 250 || $zip->locateName('xl/workbook.xml') === false) {
                throw new InvalidArgumentException('Estrutura XLSX inválida ou excessivamente complexa.');
            }
            $total = 0;
            $xmlTags = 0;
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $total += $entry['size'];
                if ($total > 26214400 || $entry['size'] > 8388608 || isset($names[$name])
                    || preg_match('~(^/|(^|/)\.\.(/|$)|\\\\|vbaProject|externalLinks)~i', $name)
                    || !empty($entry['encryption_method'])) {
                    throw new InvalidArgumentException('Arquivo excede os limites ou contém conteúdo não permitido.');
                }
                $names[$name] = true;
                if (preg_match('/\.(xml|rels)$/i', $name)) {
                    $xml = $zip->getFromIndex($i);
                    if ($xml === false || strpos($xml, "\0") !== false
                        || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
                        throw new InvalidArgumentException('XML com entidades ou codificação não permitida.');
                    }
                    // Bound tree allocations before handing XML to SimpleXML or the XLSX library.
                    $xmlTags += substr_count($xml, '<');
                    if ($xmlTags > 100000) {
                        throw new InvalidArgumentException('Planilha contém elementos XML demais; remova formatação e células extras.');
                    }
                    $previous = libxml_use_internal_errors(true);
                    try {
                        $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
                        if ($document === false) {
                            throw new InvalidArgumentException('XML inválido dentro da planilha.');
                        }
                        if (substr($name, -5) === '.rels') {
                            foreach ($document->children() as $relationship) {
                                if (strtolower((string)$relationship['TargetMode']) === 'external'
                                    || preg_match('~(^/|^[a-z]+:|\\\\)~i', (string)$relationship['Target'])) {
                                    throw new InvalidArgumentException('Links externos não são aceitos na planilha.');
                                }
                            }
                        }
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }
}
