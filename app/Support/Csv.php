<?php

namespace App\Support;

/**
 * Reading the files spreadsheets actually produce.
 *
 * Excel writes a byte-order mark, uses the list separator of whoever's machine
 * saved it, and quotes anything with a comma in it. Google Sheets writes UTF-8
 * without a mark. A person editing by hand leaves a blank line at the end.
 * All of that arrives here and none of it should be the user's problem.
 */
final class Csv
{
    /** Separators worth guessing between, in the order they are tried. */
    private const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * Read a delimited file into headers and rows.
     *
     * Headers are lower-cased and trimmed so "Employee Code", "employee_code"
     * and "EMPLOYEE CODE " all arrive as the same column. Each row keeps the
     * line number it came from, because "row 42 is wrong" is only useful if it
     * is the same 42 the person sees in their spreadsheet.
     *
     * @return array{headers: array<int, string>, rows: array<int, array{line: int, values: array<string, string>}>}
     */
    public static function read(string $path, int $limit = 0): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['headers' => [], 'rows' => []];
        }

        $first = fgets($handle);

        if ($first === false) {
            fclose($handle);

            return ['headers' => [], 'rows' => []];
        }

        $first = self::stripBom($first);
        $delimiter = self::sniff($first);

        $headers = array_map(
            self::normaliseHeader(...),
            str_getcsv(rtrim($first, "\r\n"), $delimiter, '"', '\\'),
        );

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            // A trailing newline reads as a row of one empty cell.
            if ($values === [null] || $values === ['']) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            // A row where every cell is blank is a gap, not data.
            if (collect($row)->filter(fn (string $value) => $value !== '')->isEmpty()) {
                continue;
            }

            $rows[] = ['line' => $line, 'values' => $row];

            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
    }

    /** Write rows to a string, ready to be streamed as a download. */
    public static function write(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        // The mark is what makes Excel open a UTF-8 file as UTF-8.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value),
                $row,
            ), ',', '"', '\\');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /** How many data rows a file holds, without loading it. */
    public static function countRows(string $path): int
    {
        return count(self::read($path)['rows']);
    }

    private static function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    /** Whichever separator appears most often outside quotes wins. */
    private static function sniff(string $line): string
    {
        $best = ',';
        $most = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = count(str_getcsv($line, $delimiter, '"', '\\'));

            if ($count > $most) {
                $most = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    /** "Employee Code" and "employee-code" are both employee_code. */
    private static function normaliseHeader(?string $header): string
    {
        $header = strtolower(trim(self::stripBom((string) $header)));

        return preg_replace('/[\s\-]+/', '_', $header) ?? '';
    }
}
