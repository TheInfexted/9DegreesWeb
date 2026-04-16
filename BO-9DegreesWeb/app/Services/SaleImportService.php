<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;
use App\Repositories\SaleRepository;
use CodeIgniter\HTTP\Files\UploadedFile;
use Smalot\PdfParser\Parser;

class SaleImportService
{
    private const REMARKS_PREFIX     = 'Receipt: ';
    private const MAX_FILE_SIZE      = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIME_TYPES = ['application/pdf'];

    public function __construct(
        private SaleService $saleService = new SaleService(),
        private SaleRepository $saleRepo = new SaleRepository(),
        private AmbassadorRepository $ambassadorRepo = new AmbassadorRepository(),
    ) {}

    /**
     * Parse an uploaded PDF and return the rows for preview, with duplicate flags.
     *
     * @return array{
     *   rows: list<array<string,mixed>>,
     *   errors: list<array{line:int, text:string, reason:string}>,
     *   summary: array{total:int, ready:int, duplicates:int, errors:int}
     * }
     */
    public function parsePdf(UploadedFile $file, int $ambassadorId): array
    {
        $this->validateUpload($file);

        $ambassador = $this->ambassadorRepo->findById($ambassadorId);
        if (!$ambassador) {
            throw new \RuntimeException('Ambassador not found.', 404);
        }

        $text  = (new Parser())->parseFile($file->getTempName())->getText();
        $lines = preg_split('/\r?\n/', $text) ?: [];

        $rows   = [];
        $errors = [];
        $seen   = [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $row = $this->parseRowLine($line);
            if ($row === null) {
                if ($this->looksLikeRowAttempt($line)) {
                    $errors[] = [
                        'line'   => $i + 1,
                        'text'   => $trimmed,
                        'reason' => 'Could not parse row.',
                    ];
                }
                continue;
            }

            $row['duplicate_in_file'] = false;
            $row['existing_sale']     = null;

            $seen[$row['receipt']][] = count($rows);
            $rows[] = $row;
        }

        // Mark within-file duplicates
        foreach ($seen as $receipt => $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $idx) {
                    $rows[$idx]['duplicate_in_file'] = true;
                }
                $errors[] = [
                    'line'   => 0,
                    'text'   => $receipt,
                    'reason' => 'Receipt appears ' . count($indexes) . ' times in this PDF.',
                ];
            }
        }

        // Look up DB duplicates
        $receipts = array_values(array_unique(array_column($rows, 'receipt')));
        $existing = $this->saleRepo->findExistingByReceipts($receipts);
        foreach ($rows as &$row) {
            if (isset($existing[$row['receipt']])) {
                $row['existing_sale'] = $existing[$row['receipt']];
            }
        }
        unset($row);

        $duplicates = 0;
        foreach ($rows as $r) {
            if ($r['existing_sale'] !== null) {
                $duplicates++;
            }
        }

        return [
            'rows'    => $rows,
            'errors'  => $errors,
            'summary' => [
                'total'      => count($rows),
                'ready'      => count($rows) - $duplicates,
                'duplicates' => $duplicates,
                'errors'     => count($errors),
            ],
        ];
    }

    /**
     * Apply user decisions to commit the import: create new draft sales, overwrite
     * existing draft sales, or skip rows. Confirmed/voided sales are never overwritten.
     *
     * @param  list<array<string,mixed>> $decisions
     * @return array{created:int, updated:int, skipped:int, failed:list<array{receipt:string, message:string}>}
     */
    public function commit(array $decisions, int $ambassadorId, int $createdBy): array
    {
        if ($ambassadorId <= 0) {
            throw new \RuntimeException('ambassador_id is required.', 422);
        }
        $ambassador = $this->ambassadorRepo->findById($ambassadorId);
        if (!$ambassador) {
            throw new \RuntimeException('Ambassador not found.', 404);
        }
        if (!is_array($decisions) || $decisions === []) {
            throw new \RuntimeException('No decisions submitted.', 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed  = [];

        // Pre-fetch all existing sales for overwrite rows in one query (avoids N+1).
        $overwriteReceipts = [];
        foreach ($decisions as $d) {
            if (($d['action'] ?? '') === 'overwrite' && isset($d['receipt']) && trim((string) $d['receipt']) !== '') {
                $overwriteReceipts[] = trim((string) $d['receipt']);
            }
        }
        $existingByReceipt = $overwriteReceipts !== []
            ? $this->saleRepo->findExistingByReceipts($overwriteReceipts)
            : [];

        foreach ($decisions as $d) {
            $receipt = trim((string) ($d['receipt'] ?? ''));
            $action  = (string) ($d['action'] ?? '');

            if ($receipt === '') {
                $failed[] = ['receipt' => '(missing)', 'message' => 'Receipt is required.'];
                continue;
            }

            try {
                if ($action === 'skip') {
                    $skipped++;
                    continue;
                }

                $payload = $this->buildSalePayload($d, $ambassadorId, $receipt);

                if ($action === 'create') {
                    $this->saleService->create($payload, $createdBy);
                    $created++;
                } elseif ($action === 'overwrite') {
                    $existing = $existingByReceipt[$receipt] ?? null;
                    if ($existing === null) {
                        // Receipt no longer exists — fail loudly so the user knows.
                        $failed[] = [
                            'receipt' => $receipt,
                            'message' => 'Existing sale not found; cannot overwrite.',
                        ];
                    } elseif ($existing['status'] !== 'draft') {
                        $failed[] = [
                            'receipt' => $receipt,
                            'message' => "Cannot overwrite a {$existing['status']} sale.",
                        ];
                    } else {
                        $this->saleService->update($existing['id'], $payload);
                        $updated++;
                    }
                } else {
                    $failed[] = ['receipt' => $receipt, 'message' => "Unknown action '{$action}'."];
                }
            } catch (\Throwable $e) {
                $failed[] = ['receipt' => $receipt, 'message' => $e->getMessage()];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed'  => $failed,
        ];
    }

    private function validateUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid upload: ' . $file->getErrorString(), 400);
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('File exceeds 10 MB limit.', 400);
        }
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException("Unsupported file type: {$mime}.", 400);
        }
    }

    /**
     * @return array<string,mixed>|null  Parsed row, or null if line is not a sale row.
     */
    private function parseRowLine(string $line): ?array
    {
        // Each data row is concatenated by pdfparser with no delimiters between columns:
        //   capture 1 = day      e.g. "2"
        //   capture 2 = month    e.g. "Feb"
        //   capture 3 = year     e.g. "2026"
        //   (non-capturing)      settlement datetime e.g. "2026-02-03 12:23 AM"
        //   capture 4 = receipt  e.g. "A00101202602030006"
        //   capture 5 = rest     e.g. "Johnny(PP)L10\tRM 6,028.0011.00% RM 663.08"
        if (!preg_match(
            '/^(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}\s+(?:AM|PM)([A-Z]\d+)(.+)$/',
            $line,
            $m,
        )) {
            return null;
        }

        [$_, $day, $monthName, $year, $receipt, $rest] = $m;

        $date = \DateTimeImmutable::createFromFormat('!j M Y', "{$day} {$monthName} {$year}");
        if (!$date) {
            return null;
        }

        // BGO rows: amount sits in the BGO SALES column; the adjacent AMT ORD column is
        // blank, so the PDF text has a space between the amount and the rate.
        // Table rows: amount sits in AMT ORD which directly precedes COMM %, so they
        // concatenate with no whitespace.
        $isBgo = (bool) preg_match('/RM\s*[\d,]+\.\d{2}\s+\d+\.\d{2}%/', $rest);

        // The gross amount is the RM value immediately before (or with a space before) the rate%.
        if (!preg_match('/RM\s*([\d,]+\.\d{2})\s*\d+\.\d{2}%/', $rest, $am)) {
            return null;
        }
        $gross = (float) str_replace(',', '', $am[1]);
        if ($gross <= 0) {
            return null;
        }

        $tableNumber = null;
        $saleType    = 'BGO';

        if (!$isBgo) {
            // Table sale: extract table number from after the order name.
            $afterName = preg_replace('/^[A-Za-z][A-Za-z\s.\'-]*(?:\([^)]*\))?/', '', $rest);
            if (preg_match('/^([A-Z0-9]+)(?:\t|\s|RM)/', (string) $afterName, $tm)) {
                $tableNumber = $tm[1];
                $saleType    = 'Table';
            }
        }

        return [
            'receipt'      => $receipt,
            'date'         => $date->format('Y-m-d'),
            'sale_type'    => $saleType,
            'table_number' => $tableNumber,
            'gross_amount' => $gross,
            'remarks'      => self::REMARKS_PREFIX . $receipt,
        ];
    }

    /**
     * Heuristic to decide whether an unparseable line was probably trying to be a sale row
     * (so we surface it as an error) vs. heading/footer text (which we silently skip).
     */
    private function looksLikeRowAttempt(string $line): bool
    {
        return (bool) preg_match(
            '/^\s*\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}/',
            $line,
        );
    }

    /**
     * @param  array<string,mixed> $decision  User-edited row from the preview.
     * @return array<string,mixed>            Payload accepted by SaleService.
     */
    private function buildSalePayload(array $decision, int $ambassadorId, string $receipt): array
    {
        $saleType    = (string) ($decision['sale_type'] ?? '');
        $tableNumber = isset($decision['table_number']) && $decision['table_number'] !== ''
            ? (string) $decision['table_number']
            : null;

        return [
            'ambassador_id' => $ambassadorId,
            'date'          => (string) ($decision['date'] ?? ''),
            'sale_type'     => $saleType,
            'table_number'  => $tableNumber,
            'gross_amount'  => (float) ($decision['gross_amount'] ?? 0),
            'remarks'       => self::REMARKS_PREFIX . $receipt,
        ];
    }
}
