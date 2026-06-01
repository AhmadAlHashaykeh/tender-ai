<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

class ImportParserService
{
    public function __construct(
        protected ColumnMappingService $columnMapper,
    ) {}

    /**
     * Parse file headers and sample rows only (for mapping wizard).
     *
     * @return array{
     *     headers: array<int, string>,
     *     mapped_headers: array<string, int|null>,
     *     mapping_result: array<string, mixed>,
     *     sample_rows: array<int, array<string, mixed>>,
     *     total_row_count: int
     * }
     */
    public function parseHeaders(string $absolutePath, ?array $userMapping = null, int $sampleLimit = 5): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $reader = $this->createReader($extension);
        $reader->open($absolutePath);

        try {
            return $this->readHeadersAndSample($reader, $userMapping, $sampleLimit);
        } finally {
            $reader->close();
        }
    }

    /**
     * Parse a slice of data rows (1-based inclusive indices among non-empty data rows).
     *
     * @return array{headers: array<int, string>, mapped_headers: array<string, int|null>, rows: array<int, array<string, mixed>>, mapping_result: array<string, mixed>}
     */
    public function parseRowRange(
        string $absolutePath,
        int $startDataRow,
        int $endDataRow,
        ?array $userMapping = null,
    ): array {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $reader = $this->createReader($extension);
        $reader->open($absolutePath);

        try {
            return $this->readSheetRange($reader, $userMapping, $startDataRow, $endDataRow);
        } finally {
            $reader->close();
        }
    }

    /**
     * Count non-empty data rows (excludes header).
     */
    public function countDataRows(string $absolutePath): int
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $reader = $this->createReader($extension);
        $reader->open($absolutePath);

        try {
            $count = 0;
            $headerSeen = false;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->rowToArray($row);

                    if (! $headerSeen) {
                        $headerSeen = true;

                        continue;
                    }

                    if (! $this->isEmptyRow($cells)) {
                        $count++;
                    }
                }

                break;
            }

            return $count;
        } finally {
            $reader->close();
        }
    }

    /**
     * @return array{headers: array<int, string>, mapped_headers: array<string, int|null>, rows: array<int, array<string, mixed>>}
     */
    public function parse(string $absolutePath, ?array $userMapping = null): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $reader = $this->createReader($extension);
        $reader->open($absolutePath);

        try {
            return $this->readSheet($reader, $userMapping);
        } finally {
            $reader->close();
        }
    }

    protected function createReader(string $extension): ReaderInterface
    {
        return match ($extension) {
            'csv' => new CsvReader,
            'xlsx', 'xls' => new XlsxReader,
            default => throw new RuntimeException("Unsupported file type: {$extension}"),
        };
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     mapped_headers: array<string, int|null>,
     *     mapping_result: array<string, mixed>,
     *     sample_rows: array<int, array<string, mixed>>,
     *     total_row_count: int
     * }
     */
    protected function readHeadersAndSample(ReaderInterface $reader, ?array $userMapping, int $sampleLimit): array
    {
        $headerRow = null;
        $mappedHeaders = [];
        $mappingResult = [];
        $sampleRows = [];
        $totalRowCount = 0;
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                $cells = $this->rowToArray($row);

                if ($headerRow === null) {
                    $headerRow = $this->normalizeHeaders($cells);
                    $mappingResult = $this->columnMapper->detectMapping($headerRow, $userMapping);
                    $mappedHeaders = $mappingResult['mapped_headers'];

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $totalRowCount++;

                if (count($sampleRows) < $sampleLimit) {
                    $sampleRows[] = $this->buildRowPayload($headerRow, $cells, $mappedHeaders, $rowIndex, $mappingResult);
                }
            }

            break;
        }

        if ($headerRow === null) {
            throw new RuntimeException('The uploaded file does not contain a header row.');
        }

        return [
            'headers' => $headerRow,
            'mapped_headers' => $mappedHeaders,
            'mapping_result' => $mappingResult,
            'sample_rows' => $sampleRows,
            'total_row_count' => $totalRowCount,
        ];
    }

    /**
     * @return array{headers: array<int, string>, mapped_headers: array<string, int|null>, rows: array<int, array<string, mixed>>, mapping_result: array<string, mixed>}
     */
    protected function readSheetRange(
        ReaderInterface $reader,
        ?array $userMapping,
        int $startDataRow,
        int $endDataRow,
    ): array {
        $headerRow = null;
        $mappedHeaders = [];
        $mappingResult = [];
        $parsedRows = [];
        $rowIndex = 0;
        $dataRowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                $cells = $this->rowToArray($row);

                if ($headerRow === null) {
                    $headerRow = $this->normalizeHeaders($cells);
                    $mappingResult = $this->columnMapper->detectMapping($headerRow, $userMapping);
                    $mappedHeaders = $mappingResult['mapped_headers'];

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $dataRowIndex++;

                if ($dataRowIndex < $startDataRow || $dataRowIndex > $endDataRow) {
                    continue;
                }

                $parsedRows[] = $this->buildRowPayload($headerRow, $cells, $mappedHeaders, $rowIndex, $mappingResult);
            }

            break;
        }

        if ($headerRow === null) {
            throw new RuntimeException('The uploaded file does not contain a header row.');
        }

        return [
            'headers' => $headerRow,
            'mapped_headers' => $mappedHeaders,
            'rows' => $parsedRows,
            'mapping_result' => $mappingResult,
        ];
    }

    /**
     * @return array{headers: array<int, string>, mapped_headers: array<string, int|null>, rows: array<int, array<string, mixed>>, mapping_result: array<string, mixed>}
     */
    protected function readSheet(ReaderInterface $reader, ?array $userMapping): array
    {
        $headerRow = null;
        $mappedHeaders = [];
        $mappingResult = [];
        $parsedRows = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                $cells = $this->rowToArray($row);

                if ($headerRow === null) {
                    $headerRow = $this->normalizeHeaders($cells);
                    $mappingResult = $this->columnMapper->detectMapping($headerRow, $userMapping);
                    $mappedHeaders = $mappingResult['mapped_headers'];

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $parsedRows[] = $this->buildRowPayload($headerRow, $cells, $mappedHeaders, $rowIndex, $mappingResult);
            }

            break;
        }

        if ($headerRow === null) {
            throw new RuntimeException('The uploaded file does not contain a header row.');
        }

        return [
            'headers' => $headerRow,
            'mapped_headers' => $mappedHeaders,
            'rows' => $parsedRows,
            'mapping_result' => $mappingResult,
        ];
    }

    /**
     * @param  array<int, string|null>  $cells
     * @return array<int, string>
     */
    protected function normalizeHeaders(array $cells): array
    {
        return $this->columnMapper->normalizeHeaderRow($cells);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    public function mapHeaders(array $headers): array
    {
        return $this->columnMapper->detectMapping($headers)['mapped_headers'];
    }

    /**
     * @param  array<string, int|null>  $mappedHeaders
     * @return list<string> Missing display labels
     */
    public function missingRequiredHeaders(array $mappedHeaders): array
    {
        $missing = $this->columnMapper->missingRequiredFields($mappedHeaders);

        if (! $this->columnMapper->hasDrugIdentityMapped($mappedHeaders)) {
            $missing[] = 'Drug Identity (Code, INN, or Product Name)';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public function requiredHeaderLabels(): array
    {
        return $this->columnMapper->requiredHeaderLabels();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $cells
     * @param  array<string, int|null>  $mappedHeaders
     * @param  array<string, mixed>  $mappingResult
     * @return array<string, mixed>
     */
    protected function buildRowPayload(
        array $headers,
        array $cells,
        array $mappedHeaders,
        int $rowNumber,
        array $mappingResult,
    ): array {
        $rawByHeader = [];
        $additionalColumns = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = $this->cellValue($cells[$index] ?? null);
            $rawByHeader[$header] = $value;

            $isMapped = in_array($index, array_filter($mappedHeaders, fn ($i) => $i !== null), true);

            if (! $isMapped) {
                $additionalColumns[$header] = $value;
            }
        }

        $canonical = [];

        foreach ($mappedHeaders as $field => $index) {
            $canonical[$field] = $index !== null
                ? $this->cellValue($cells[$index] ?? null)
                : null;
        }

        $canonical = $this->columnMapper->normalizeCanonicalKeys($canonical);

        return [
            'row_number' => $rowNumber,
            'raw_by_header' => $rawByHeader,
            'additional_columns' => $additionalColumns,
            'canonical' => $canonical,
            'extra_columns' => $mappingResult['extra_columns'] ?? [],
        ];
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    protected function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function cellValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return array<int, string|null>
     */
    protected function rowToArray(Row $row): array
    {
        $values = [];

        foreach ($row->getCells() as $cell) {
            $values[] = $this->cellValue($cell->getValue());
        }

        return $values;
    }
}
