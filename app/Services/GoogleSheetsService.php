<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Throwable;

class GoogleSheetsService
{
    private const SKIP_FIELD_TYPES = ['heading', 'divider', 'paragraph', 'html', 'section'];

    private const FORM_ID_HEADER = 'Form ID';

    private const META_HEADERS = [
        'Form ID',
        'Form Name',
        'User Name',
        'User Email',
        'User Phone',
        'Created On',
    ];

    private const REMOVED_HEADERS = [
        'Submission ID',
        'Type',
    ];

    public function isEnabled(?string $sheetUrlOrId = null): bool
    {
        if (!defined('GOOGLE_SHEETS_ENABLED') || !GOOGLE_SHEETS_ENABLED) {
            return false;
        }

        $credentialsPath = defined('GOOGLE_SERVICE_ACCOUNT_JSON') ? (string)GOOGLE_SERVICE_ACCOUNT_JSON : '';
        if ($credentialsPath === '' || !is_readable($credentialsPath)) {
            return false;
        }

        // Require a product googlesheet_url (or explicit id). No global fallback sheet.
        return $this->resolveSpreadsheetId($sheetUrlOrId) !== '';
    }

    /**
     * Resolve spreadsheet id from a product googlesheet_url or raw id only.
     * Empty/invalid URL → no sheet write.
     */
    public function resolveSpreadsheetId(?string $sheetUrlOrId = null): string
    {
        return $this->extractSpreadsheetIdFromUrl((string)($sheetUrlOrId ?? ''));
    }

    /**
     * Extract spreadsheet id from a Google Sheets URL or return the value if it is already an id.
     * Example: https://docs.google.com/spreadsheets/d/14Rum5hOhAcfQoDrXGBDYMKCUc-fWrjFAYM-Ivn4QMtw/edit
     */
    public function extractSpreadsheetIdFromUrl(string $urlOrId): string
    {
        $urlOrId = trim($urlOrId);
        if ($urlOrId === '') {
            return '';
        }

        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $urlOrId, $m)) {
            return $m[1];
        }

        // Already a bare spreadsheet id
        if (preg_match('/^[a-zA-Z0-9-_]{20,}$/', $urlOrId)) {
            return $urlOrId;
        }

        return '';
    }

    /** @deprecated Use resolveSpreadsheetId() with a product URL */
    public function spreadsheetId(): string
    {
        return '';
    }

    /**
     * Encode submission id for the Form ID column (used to match refunds later).
     */
    public function encodeFormId(int $submissionId): string
    {
        return rtrim(strtr(base64_encode('sub:' . $submissionId), '+/', '-_'), '=');
    }

    public function decodeFormId(string $encoded): ?int
    {
        $padded = strtr($encoded, '-_', '+/');
        $padLen = (4 - (strlen($padded) % 4)) % 4;
        $decoded = base64_decode($padded . str_repeat('=', $padLen), true);
        if ($decoded === false || !preg_match('/^sub:(\d+)$/', $decoded, $m)) {
            return null;
        }
        return (int)$m[1];
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function fieldHeadsFromFormJson(string $formJson): array
    {
        $fields = json_decode($formJson, true);
        if (!is_array($fields)) {
            return [];
        }

        $heads = [];
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['id'])) {
                continue;
            }
            $type = strtolower((string)($field['type'] ?? ''));
            if (in_array($type, self::SKIP_FIELD_TYPES, true)) {
                continue;
            }
            $label = trim((string)($field['label'] ?? ''));
            $heads[] = [
                'id' => (string)$field['id'],
                'label' => $label !== '' ? $label : (string)$field['id'],
            ];
        }
        return $heads;
    }

    public function valueFromSubmissionField($fieldValue): string
    {
        if (is_array($fieldValue)) {
            $value = $fieldValue['value'] ?? '';
            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }
            return is_scalar($value) || $value === null ? (string)$value : json_encode($value);
        }

        return is_scalar($fieldValue) || $fieldValue === null ? (string)$fieldValue : json_encode($fieldValue);
    }

    /**
     * Append one form submission row (includes encoded Form ID).
     *
     * @param array<string,mixed> $meta
     * @param array<string,string> $fieldValues label => value
     * @param string|null $sheetUrlOrId product googlesheet_url or spreadsheet id
     * @return array{status:bool,message?:string,form_id_encoded?:string}
     */
    public function appendSubmission(array $meta, array $fieldValues, ?string $sheetUrlOrId = null): array
    {
        $spreadsheetId = $this->resolveSpreadsheetId($sheetUrlOrId);
        if (!$this->isEnabled($sheetUrlOrId)) {
            return [
                'status' => false,
                'message' => $spreadsheetId === ''
                    ? 'Product Google Sheet URL is missing or invalid'
                    : 'Google Sheets is not configured',
            ];
        }

        try {
            $sheets = new Sheets($this->makeClient());
            $rangePrefix = $this->sheetRangePrefix();

            $submissionId = (int)($meta['submission_id'] ?? 0);
            $encodedFormId = $submissionId > 0 ? $this->encodeFormId($submissionId) : '';
            $meta['form_id_encoded'] = $encodedFormId;

            $headers = $this->normalizeHeaders(
                $this->getHeaderRow($sheets, $spreadsheetId, $rangePrefix),
                array_keys($fieldValues)
            );
            $this->writeHeaderRow($sheets, $spreadsheetId, $rangePrefix, $headers);

            $row = [];
            foreach ($headers as $header) {
                $row[] = $this->cellForHeader($header, $meta, $fieldValues);
            }

            $body = new ValueRange(['values' => [$row]]);
            $sheets->spreadsheets_values->append(
                $spreadsheetId,
                $rangePrefix . 'A:ZZ',
                $body,
                [
                    'valueInputOption' => 'USER_ENTERED',
                    'insertDataOption' => 'INSERT_ROWS',
                ]
            );

            return [
                'status' => true,
                'form_id_encoded' => $encodedFormId,
            ];
        } catch (Throwable $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find the form submission row by encoded Form ID and write refund columns onto it.
     *
     * @param array<string,string> $refundFieldValues label => value (will be prefixed with "Refund: ")
     * @param array<string,mixed> $refundMeta optional Refund Status / Refund Created On
     * @param string|null $sheetUrlOrId product googlesheet_url or spreadsheet id
     * @return array{status:bool,message?:string,row?:int}
     */
    public function updateRefundByEncodedFormId(
        string $encodedFormId,
        array $refundFieldValues,
        array $refundMeta = [],
        ?string $sheetUrlOrId = null
    ): array {
        $spreadsheetId = $this->resolveSpreadsheetId($sheetUrlOrId);
        if (!$this->isEnabled($sheetUrlOrId)) {
            return [
                'status' => false,
                'message' => $spreadsheetId === ''
                    ? 'Product Google Sheet URL is missing or invalid'
                    : 'Google Sheets is not configured',
            ];
        }

        $encodedFormId = trim($encodedFormId);
        if ($encodedFormId === '') {
            return [
                'status' => false,
                'message' => 'Encoded Form ID is required',
            ];
        }

        try {
            $sheets = new Sheets($this->makeClient());
            $rangePrefix = $this->sheetRangePrefix();

            $prefixedRefundValues = [];
            foreach ($refundFieldValues as $label => $value) {
                $label = trim((string)$label);
                if ($label === '') {
                    continue;
                }
                if (stripos($label, 'Refund:') !== 0) {
                    $label = 'Refund: ' . $label;
                }
                $prefixedRefundValues[$label] = (string)$value;
            }

            if (!empty($refundMeta['refund_status'])) {
                $prefixedRefundValues['Refund Status'] = (string)$refundMeta['refund_status'];
            }
            if (!empty($refundMeta['refund_created_on'])) {
                $prefixedRefundValues['Refund Created On'] = (string)$refundMeta['refund_created_on'];
            }

            $all = $sheets->spreadsheets_values->get($spreadsheetId, $rangePrefix . 'A:ZZ');
            $values = $all->getValues() ?: [];
            if (empty($values)) {
                return [
                    'status' => false,
                    'message' => 'Google Sheet is empty; form submission row not found',
                ];
            }

            $headers = $this->normalizeHeaders(
                array_map(static fn($v) => trim((string)$v), $values[0] ?? []),
                array_keys($prefixedRefundValues)
            );

            $formIdCol = $this->headerIndex($headers, self::FORM_ID_HEADER);
            if ($formIdCol < 0) {
                return [
                    'status' => false,
                    'message' => 'Form ID column not found in Google Sheet',
                ];
            }

            $rowIndex = -1; // 0-based in $values
            for ($i = 1, $n = count($values); $i < $n; $i++) {
                $cell = isset($values[$i][$formIdCol]) ? trim((string)$values[$i][$formIdCol]) : '';
                if ($cell !== '' && hash_equals($encodedFormId, $cell)) {
                    $rowIndex = $i;
                    break;
                }
            }

            if ($rowIndex < 0) {
                return [
                    'status' => false,
                    'message' => 'No Google Sheet row found for Form ID ' . $encodedFormId,
                ];
            }

            // Rebuild existing row mapped to (possibly expanded) headers
            $oldHeaders = array_map(static fn($v) => trim((string)$v), $values[0] ?? []);
            $oldRow = $values[$rowIndex];
            $existingByHeader = [];
            foreach ($oldHeaders as $col => $header) {
                if ($header === '') {
                    continue;
                }
                $existingByHeader[$header] = isset($oldRow[$col]) ? (string)$oldRow[$col] : '';
            }

            $newRow = [];
            foreach ($headers as $header) {
                if (array_key_exists($header, $prefixedRefundValues)) {
                    $newRow[] = $prefixedRefundValues[$header];
                    continue;
                }
                $newRow[] = $existingByHeader[$header]
                    ?? $this->findExistingCaseInsensitive($existingByHeader, $header)
                    ?? '';
            }

            $this->writeHeaderRow($sheets, $spreadsheetId, $rangePrefix, $headers);

            $sheetRowNumber = $rowIndex + 1; // Sheets is 1-based
            $endCol = $this->columnLetter(count($headers));
            $body = new ValueRange(['values' => [$newRow]]);
            $sheets->spreadsheets_values->update(
                $spreadsheetId,
                $rangePrefix . 'A' . $sheetRowNumber . ':' . $endCol . $sheetRowNumber,
                $body,
                ['valueInputOption' => 'USER_ENTERED']
            );

            return [
                'status' => true,
                'row' => $sheetRowNumber,
            ];
        } catch (Throwable $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function sheetRangePrefix(): string
    {
        $name = defined('GOOGLE_SHEETS_TAB_NAME') ? trim((string)GOOGLE_SHEETS_TAB_NAME) : 'Sheet1';
        if ($name === '') {
            $name = 'Sheet1';
        }
        $safe = str_replace("'", "''", $name);
        return "'{$safe}'!";
    }

    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName('Revo Forms');
        $client->setAuthConfig((string)GOOGLE_SERVICE_ACCOUNT_JSON);
        $client->setScopes([Sheets::SPREADSHEETS]);
        return $client;
    }

    /**
     * @return string[]
     */
    private function getHeaderRow(Sheets $sheets, string $spreadsheetId, string $rangePrefix): array
    {
        $response = $sheets->spreadsheets_values->get($spreadsheetId, $rangePrefix . '1:1');
        $values = $response->getValues();
        if (empty($values[0]) || !is_array($values[0])) {
            return [];
        }
        return array_map(static fn($v) => trim((string)$v), $values[0]);
    }

    /**
     * @param string[] $existingHeaders
     * @param list<string|int> $extraLabels
     * @return string[]
     */
    private function normalizeHeaders(array $existingHeaders, array $extraLabels): array
    {
        if (empty($existingHeaders)) {
            $headers = self::META_HEADERS;
        } else {
            $kept = [];
            foreach ($existingHeaders as $header) {
                $header = trim((string)$header);
                if ($header === '') {
                    continue;
                }
                $removed = false;
                foreach (self::REMOVED_HEADERS as $drop) {
                    if (strcasecmp($header, $drop) === 0) {
                        $removed = true;
                        break;
                    }
                }
                if (!$removed) {
                    $kept[] = $header;
                }
            }
            $headers = $kept;
            foreach (self::META_HEADERS as $metaHeader) {
                if (!$this->headerExists($headers, $metaHeader)) {
                    // Keep Form ID as the first column when adding it
                    if ($metaHeader === self::FORM_ID_HEADER) {
                        array_unshift($headers, $metaHeader);
                    } else {
                        $headers[] = $metaHeader;
                    }
                }
            }
        }

        foreach ($extraLabels as $label) {
            $label = trim((string)$label);
            if ($label === '') {
                continue;
            }
            if (!$this->headerExists($headers, $label)) {
                $headers[] = $label;
            }
        }

        return $headers;
    }

    /**
     * @param string[] $headers
     */
    private function writeHeaderRow(Sheets $sheets, string $spreadsheetId, string $rangePrefix, array $headers): void
    {
        $body = new ValueRange([
            'values' => [array_values($headers)],
        ]);
        $sheets->spreadsheets_values->update(
            $spreadsheetId,
            $rangePrefix . 'A1',
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * @param string[] $headers
     */
    private function headerExists(array $headers, string $label): bool
    {
        return $this->headerIndex($headers, $label) >= 0;
    }

    /**
     * @param string[] $headers
     */
    private function headerIndex(array $headers, string $label): int
    {
        foreach ($headers as $index => $header) {
            if (strcasecmp(trim((string)$header), $label) === 0) {
                return (int)$index;
            }
        }
        return -1;
    }

    /**
     * @param array<string,string> $existingByHeader
     */
    private function findExistingCaseInsensitive(array $existingByHeader, string $header): ?string
    {
        foreach ($existingByHeader as $key => $value) {
            if (strcasecmp((string)$key, $header) === 0) {
                return (string)$value;
            }
        }
        return null;
    }

    private function columnLetter(int $count): string
    {
        $count = max(1, $count);
        $letter = '';
        while ($count > 0) {
            $mod = ($count - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $count = intdiv($count - 1, 26);
        }
        return $letter;
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<string,string> $fieldValues
     */
    private function cellForHeader(string $header, array $meta, array $fieldValues): string
    {
        $map = [
            self::FORM_ID_HEADER => (string)($meta['form_id_encoded'] ?? ''),
            'Form Name' => (string)($meta['form_name'] ?? ''),
            'User Name' => (string)($meta['user_name'] ?? ''),
            'User Email' => (string)($meta['user_email'] ?? ''),
            'User Phone' => (string)($meta['user_phone'] ?? ''),
            'Created On' => (string)($meta['created_on'] ?? ''),
        ];

        foreach ($map as $key => $value) {
            if (strcasecmp($header, $key) === 0) {
                return $value;
            }
        }

        foreach ($fieldValues as $label => $value) {
            if (strcasecmp($header, (string)$label) === 0) {
                return (string)$value;
            }
        }

        return '';
    }
}
