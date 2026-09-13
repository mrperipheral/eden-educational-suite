<?php

namespace App\Support\Csv;

/**
 * Neutralizes formula-injection-prone CSV cells (M28 — Platform Security
 * Hardening). A cell whose text starts with `=`, `+`, `-`, `@`, a tab, or a
 * carriage return is treated as a formula by Excel/Google Sheets/LibreOffice
 * on open — a school-controlled free-text field written straight into an
 * export (a student's preferred name, a manual `FeePayment.reference`, a
 * graduation note, an audit summary) could otherwise be weaponized into a
 * formula-injection payload against whoever opens the file later. The
 * OWASP-recommended mitigation is applied: prefix a leading single quote so
 * spreadsheet applications render the value as literal text.
 */
class CsvSanitizer
{
    private const DANGEROUS_LEADING_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS_LEADING_CHARS, true) ? "'".$value : $value;
    }
}
