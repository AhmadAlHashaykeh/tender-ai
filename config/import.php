<?php

return [
    'disk' => 'local',
    'storage_path' => 'imports',

    'allowed_extensions' => ['xlsx', 'xls', 'csv'],
    'max_upload_size_kb' => 51200,

    /**
     * Number of data rows processed per background chunk job.
     */
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 500),

    /**
     * Rows standardized per background chunk job.
     */
    'standardization_chunk_size' => (int) env('STANDARDIZATION_CHUNK_SIZE', 100),

    /**
     * Rows materialized per background chunk job.
     */
    'materialization_chunk_size' => (int) env('MATERIALIZATION_CHUNK_SIZE', 100),

    /**
     * Chunks in "processing" longer than this are reset to pending and redispatched.
     */
    'materialization_stuck_chunk_minutes' => (int) env('MATERIALIZATION_STUCK_CHUNK_MINUTES', 10),

    /**
     * Admin queue-health warning when no job has been processed for this many minutes.
     */
    'queue_worker_stale_minutes' => (int) env('QUEUE_WORKER_STALE_MINUTES', 5),

    /**
     * Imports with at most this many rows use one chunk per pipeline stage (still queued).
     */
    'single_job_max_rows' => (int) env('IMPORT_SINGLE_JOB_MAX_ROWS', 500),

    /**
     * Minimum match confidence (0–100) for automatic approval when not exact/alias.
     */
    'auto_approve_threshold' => (float) env('STANDARDIZATION_AUTO_APPROVE_THRESHOLD', 95),

    /**
     * When true, import → matching → review → ready runs without manual pipeline clicks.
     */
    'pipeline_automation_enabled' => (bool) env('IMPORT_PIPELINE_AUTOMATION', true),

    /**
     * Show chunk/queue/raw status panels on the import detail page (admin/debug).
     */
    'show_advanced_details' => (bool) env('IMPORT_SHOW_ADVANCED_DETAILS', false),

    /**
     * Internal canonical fields — the ONLY keys used by the import pipeline.
     */
    'canonical_fields' => [
        'code',
        'inn',
        'product_name',
        'country',
        'tender_number',
        'awarded_price',
        'price_usd',
        'winner',
        'company_name',
        'version',
        'year',
        'quantity',
        'tender_value',
    ],

    /**
     * Minimum header mapping required before import can proceed.
     * At least one drug_identity field must also be mapped.
     */
    'required_canonical_fields' => [
        'country',
        'year',
        'price_usd',
    ],

    'drug_identity_fields' => [
        'code',
        'inn',
        'product_name',
    ],

    /**
     * Display labels for canonical fields (UI / error messages).
     */
    'header_labels' => [
        'code' => 'Code',
        'inn' => 'INN',
        'product_name' => 'Product Name',
        'country' => 'Country',
        'tender_number' => 'Tender #',
        'awarded_price' => 'Awarded price',
        'price_usd' => 'Price USD',
        'winner' => 'Winner',
        'company_name' => 'Company Name',
        'version' => 'Version',
        'year' => 'Year',
        'quantity' => 'Quantity',
        'tender_value' => 'Tender Value',
    ],

    /**
     * Configurable column alias registry (extendable from admin settings in future).
     * Keys are canonical field names; values are known header aliases.
     */
    'column_aliases' => [
        'code' => ['code', 'product code', 'drug code', 'item code', 'sku'],
        'inn' => ['inn', 'international nonproprietary name', 'generic name', 'active ingredient'],
        'product_name' => ['product name', 'product', 'drug name', 'productname', 'description', 'item name'],
        'country' => ['country', 'nation', 'market', 'country name'],
        'tender_number' => ['tender number', 'tender #', 'tender no', 'tender ref', 'reference number', 'rfq number', 'bid number', 'tender id', 'tender'],
        'awarded_price' => ['awarded price', 'award price', 'local price', 'unit price local'],
        'price_usd' => ['price usd', 'price (usd)', 'usd price', 'unit price usd', 'price in usd', 'awarded price usd'],
        'winner' => ['winner', 'winning company', 'awarded to', 'awarded company'],
        'company_name' => ['company name', 'company', 'bidder', 'supplier', 'vendor', 'manufacturer'],
        'version' => ['version', 'tender version', 'rev', 'revision'],
        'year' => ['year', 'award year', 'tender year', 'fiscal year'],
        'quantity' => ['qty', 'qty.', 'quantity', 'q', 'units', 'volume', 'required qty', 'tender qty', 'awarded qty', 'q ty'],
        'tender_value' => ['tender value', 'total value', 'contract value', 'line value', 'total amount'],
    ],

    /**
     * @deprecated Use column_aliases — kept for backward compatibility with legacy code paths.
     */
    'expected_columns' => [
        'code' => ['code', 'product code', 'drug code'],
        'inn' => ['inn', 'international nonproprietary name'],
        'product_name' => ['product name', 'product', 'drug name', 'productname'],
        'country' => ['country', 'market', 'nation', 'country name'],
        'tender_number' => ['tender #', 'tender no', 'tender number', 'tender ref', 'reference number', 'rfq number', 'bid number', 'tender', 'tender id'],
        'awarded_price' => ['awarded price', 'award price', 'local price'],
        'price_usd' => ['price usd', 'price (usd)', 'usd price', 'unit price usd', 'price in usd', 'awarded price usd'],
        'winner' => ['winner', 'winning company', 'awarded to'],
        'company_name' => ['company name', 'company', 'bidder', 'supplier', 'vendor', 'manufacturer'],
        'version' => ['version', 'tender version'],
        'year' => ['year', 'award year', 'tender year'],
        'quantity' => ['qty', 'qty.', 'quantity', 'q', 'units', 'volume', 'required qty', 'tender qty', 'awarded qty', 'q ty'],
        'tender_value' => ['tender value', 'total value', 'contract value'],
    ],

    /**
     * Fuzzy header match threshold (0–100).
     */
    'fuzzy_header_match_min' => 78,

    /**
     * Country alias registry for standardization (never auto-create countries).
     */
    'country_aliases' => [
        'uae' => 'united arab emirates',
        'u.a.e' => 'united arab emirates',
        'u.a.e.' => 'united arab emirates',
        'emirates' => 'united arab emirates',
        'ksa' => 'saudi arabia',
        'kingdom of saudi arabia' => 'saudi arabia',
        'saudi' => 'saudi arabia',
        'sa' => 'saudi arabia',
        'sau' => 'saudi arabia',
        'egy' => 'egypt',
        'eg' => 'egypt',
        'jor' => 'jordan',
        'jo' => 'jordan',
        'kwt' => 'kuwait',
        'kw' => 'kuwait',
        'irq' => 'iraq',
        'iq' => 'iraq',
        'ae' => 'united arab emirates',
    ],

    /**
     * Import quality score rating thresholds.
     */
    'quality_ratings' => [
        'excellent' => 90,
        'good' => 75,
        'needs_review' => 50,
    ],
];
