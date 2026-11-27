<?php
declare(strict_types=1);

/**
 * Bookexa Schema Checker
 * Parses every CREATE TABLE block in database/bookexa.sql and fails on any
 * duplicate column name (case-insensitive) or non-InnoDB table engine.
 *
 * Standalone CLI usage: php tests/schema_check.php
 */

function bookexa_validate_schema_sql(string $sqlFilePath): array
{
    $sql = @file_get_contents($sqlFilePath);
    if (!is_string($sql) || trim($sql) === '') {
        return ['ok' => false, 'tables' => 0, 'errors' => ["Unable to read schema file: {$sqlFilePath}"]];
    }

    $errors = [];
    $tables = 0;
    $offset = 0;
    $len = strlen($sql);

    while (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $tableName = $m[1][0];
        $openParenPos = (int) $m[0][1] + strlen($m[0][0]) - 1;

        // Walk balanced parentheses to find the exact end of CREATE TABLE (...)
        $depth = 0;
        $inStr = false;
        $quoteChar = '';
        $bodyStart = $openParenPos + 1;
        $bodyEnd = -1;

        for ($i = $openParenPos; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($inStr) {
                if ($ch === '\\') {
                    $i++;
                    continue;
                }
                if ($ch === $quoteChar) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quoteChar) {
                        $i++;
                        continue;
                    }
                    $inStr = false;
                }
                continue;
            }
            if ($ch === '\'' || $ch === '"') {
                $inStr = true;
                $quoteChar = $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $bodyEnd = $i;
                    break;
                }
            }
        }

        if ($bodyEnd === -1) {
            $errors[] = "Table `{$tableName}` has unterminated CREATE TABLE parentheses.";
            break;
        }

        $tables++;
        $body = substr($sql, $bodyStart, $bodyEnd - $bodyStart);

        // Extract trailing table options up to ';'
        $semiPos = strpos($sql, ';', $bodyEnd);
        $opts = $semiPos !== false ? substr($sql, $bodyEnd + 1, $semiPos - $bodyEnd - 1) : '';
        if (!preg_match('/\bENGINE\s*=\s*InnoDB\b/i', $opts)) {
            $errors[] = "Table `{$tableName}` does not explicitly declare ENGINE=InnoDB.";
        }

        // Split top-level comma-separated definitions (respecting parens & quotes)
        $defs = [];
        $cur = '';
        $d = 0;
        $q = false;
        $qc = '';
        $bLen = strlen($body);
        for ($j = 0; $j < $bLen; $j++) {
            $c = $body[$j];
            if ($q) {
                $cur .= $c;
                if ($c === '\\' && $j + 1 < $bLen) {
                    $cur .= $body[++$j];
                    continue;
                }
                if ($c === $qc) {
                    $q = false;
                }
                continue;
            }
            if ($c === '\'' || $c === '"') {
                $q = true;
                $qc = $c;
                $cur .= $c;
                continue;
            }
            if ($c === '(') {
                $d++;
            } elseif ($c === ')') {
                $d--;
            } elseif ($c === ',' && $d === 0) {
                $defs[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        if (trim($cur) !== '') {
            $defs[] = $cur;
        }

        $seenCols = [];
        foreach ($defs as $rawDef) {
            $line = preg_replace('/^\s*--[^\n]*\n?/m', '', trim($rawDef)) ?? trim($rawDef);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:PRIMARY\s+KEY|UNIQUE(?:\s+KEY|\s+INDEX)?|FULLTEXT(?:\s+KEY|\s+INDEX)?|SPATIAL(?:\s+KEY|\s+INDEX)?|KEY|INDEX|CONSTRAINT|CHECK)\b/i', $line)) {
                continue;
            }
            if (preg_match('/^`?([A-Za-z0-9_]+)`?\s+[A-Za-z]/', $line, $cm)) {
                $colLow = strtolower($cm[1]);
                if (isset($seenCols[$colLow])) {
                    $errors[] = "Duplicate column `{$cm[1]}` in table `{$tableName}`.";
                }
                $seenCols[$colLow] = true;
            }
        }

        $offset = $semiPos !== false ? ($semiPos + 1) : ($bodyEnd + 1);
    }

    if ($tables < 10) {
        $errors[] = "Expected at least 10 CREATE TABLE blocks in schema file; found {$tables}.";
    }

    return [
        'ok'     => empty($errors),
        'tables' => $tables,
        'errors' => $errors,
    ];
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath((string) $_SERVER['SCRIPT_FILENAME'])) {
    $schemaPath = dirname(__DIR__) . '/database/bookexa.sql';
    $res = bookexa_validate_schema_sql($schemaPath);
    if ($res['ok']) {
        echo "[PASS] Schema check passed: {$res['tables']} CREATE TABLE blocks validated in database/bookexa.sql with 0 duplicate columns.\n";
        exit(0);
    }
    foreach ($res['errors'] as $err) {
        fwrite(STDERR, "[FAIL] {$err}\n");
    }
    exit(1);
}
