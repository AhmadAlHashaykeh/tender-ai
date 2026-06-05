@extends('layouts.app')

@section('title', 'TenderAI System Documentation')

@section('content')
<main class="doc-page">
    <header class="doc-page-header">
        <h1 class="doc-page-title">TenderAI System Documentation</h1>
        <p class="doc-page-subtitle">
            Internal reference for delivery, demos, and operations. This page is not linked from navigation —
            bookmark the direct URL for access.
        </p>
        <div class="doc-meta-row">
            <span class="doc-badge doc-badge--primary">Internal Only</span>
            <span class="doc-badge">Auth Required</span>
            <span class="doc-badge doc-badge--purple">Delivery / Demo</span>
        </div>
    </header>

    <div class="doc-layout">
        <nav class="doc-toc" aria-label="Table of contents">
            <p class="doc-toc-title">Contents</p>
            <a href="#overview" class="doc-toc-link">1. System Overview</a>
            <a href="#stack" class="doc-toc-link">2. Technical Stack</a>
            <a href="#modules" class="doc-toc-link">3. Main Modules</a>
            <a href="#pipeline" class="doc-toc-link">4. Data Pipeline Flow</a>
            <a href="#entities" class="doc-toc-link">5. Database Entities</a>
            <a href="#countries" class="doc-toc-link">6. Country / Market</a>
            <a href="#queue" class="doc-toc-link">7. Queue / Hosting</a>
            <a href="#deployment" class="doc-toc-link">8. Production Deployment</a>
            <a href="#commands" class="doc-toc-link">9. Maintenance Commands</a>
            <a href="#troubleshooting" class="doc-toc-link">10. Troubleshooting</a>
            <a href="#demo" class="doc-toc-link">11. Demo Guide</a>
            <a href="#status" class="doc-toc-link">12. Production Status</a>
            <a href="#security" class="doc-toc-link">13. Security Notes</a>
        </nav>

        <div class="doc-content">

            {{-- 1. System Overview --}}
            <section class="doc-section" id="overview">
                <h2 class="doc-section-title">
                    <i data-lucide="layout-dashboard"></i>
                    System Overview
                </h2>
                <p class="doc-section-lead">
                    TenderAI is a pharmaceutical tender intelligence platform. It ingests historical tender
                    spreadsheets, standardizes messy source data into canonical entities, and produces market
                    statistics and AI-supported price recommendations for upcoming bids.
                </p>
                <ul>
                    <li><strong>Analyzes historical pharmaceutical tender data</strong> — award prices, winners, quantities, and tender metadata across countries and programs.</li>
                    <li><strong>Standardizes uploaded Excel data</strong> — maps columns, validates rows, and normalizes countries, companies, drugs, and tender references.</li>
                    <li><strong>Creates tender / company / drug entities</strong> — materializes normalized records with alias matching and confidence scoring.</li>
                    <li><strong>Builds bid records</strong> — one record per awarded line item, linked to standardized drugs, companies, tenders, and countries.</li>
                    <li><strong>Generates market statistics</strong> — weighted averages, medians, last prices, trends, and regional rollups per drug × country group.</li>
                    <li><strong>Provides AI-supported tender price recommendations</strong> — business calculations from historical stats with OpenAI as a strategic insight layer only.</li>
                </ul>
            </section>

            {{-- 2. Technical Stack --}}
            <section class="doc-section" id="stack">
                <h2 class="doc-section-title">
                    <i data-lucide="layers"></i>
                    Technical Stack
                </h2>
                <div class="doc-card-grid">
                    <div class="doc-card">
                        <p class="doc-card-title">Laravel 12</p>
                        <p class="doc-card-text">Application framework, routing, queues, scheduler, and Blade views.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">PHP 8.2+</p>
                        <p class="doc-card-text">Runtime on Hostinger via <span class="doc-inline-code">/opt/alt/php82/usr/bin/php</span>.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">MySQL</p>
                        <p class="doc-card-text">Primary data store for imports, entities, statistics, and predictions.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">Vite</p>
                        <p class="doc-card-text">Frontend asset bundling; production builds committed to <span class="doc-inline-code">public/build</span>.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">Database Queue</p>
                        <p class="doc-card-text">Jobs stored in <span class="doc-inline-code">jobs</span> table; processed by scheduler-driven worker.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">Hostinger Shared Hosting</p>
                        <p class="doc-card-text">No permanent queue worker; cron runs Laravel Scheduler every minute.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">GitHub Deployment</p>
                        <p class="doc-card-text">Local build → commit → push → server <span class="doc-inline-code">git pull</span> + artisan cache/migrate.</p>
                    </div>
                    <div class="doc-card">
                        <p class="doc-card-title">OpenAI</p>
                        <p class="doc-card-text">Strategic insight layer only — narrative context on top of deterministic price calculations.</p>
                    </div>
                </div>
            </section>

            {{-- 3. Main Modules --}}
            <section class="doc-section" id="modules">
                <h2 class="doc-section-title">
                    <i data-lucide="grid-3x3"></i>
                    Main Modules
                </h2>

                <h3 class="doc-subheading">Dashboard</h3>
                <ul>
                    <li>KPIs — import batches, bid records, companies, drugs, and prediction counts.</li>
                    <li>Data status — pipeline health indicators and recent activity.</li>
                    <li>Statistics — tender volume by country and price trend charts.</li>
                </ul>

                <h3 class="doc-subheading">Upload Module</h3>
                <ul>
                    <li>Upload Excel — create an <span class="doc-inline-code">import_batch</span> from spreadsheet files.</li>
                    <li>Column mapping — map source headers to TenderAI fields before import.</li>
                    <li>Validation — row-level checks for required fields, prices, and country rules.</li>
                    <li>Import rows — persist raw rows into <span class="doc-inline-code">import_rows</span>.</li>
                    <li>Chunk processing — large files split into queued chunks for async processing.</li>
                    <li>Async processing — standardization and materialization run as background jobs.</li>
                </ul>

                <h3 class="doc-subheading">Standardization</h3>
                <ul>
                    <li>Country mapping — KSA → Saudi Arabia, Oman → Oman, Iraq → Iraq; GCC as dedicated market.</li>
                    <li>Company matching — fuzzy match against catalog with alias support.</li>
                    <li>Drug / product matching — match source product names to standardized drugs.</li>
                    <li>Tender normalization — canonical tender names and program grouping.</li>
                    <li>Aliases — <span class="doc-inline-code">company_aliases</span> and <span class="doc-inline-code">drug_aliases</span> for alternate spellings.</li>
                    <li>Confidence scores — each match carries a score for review prioritization.</li>
                </ul>

                <h3 class="doc-subheading">Product Matching Review</h3>
                <ul>
                    <li>Approve suggestions — accept high-confidence automatic matches.</li>
                    <li>Manual correction — search and assign the correct drug or company.</li>
                    <li>Bulk approve — approve all review-pending rows for a batch.</li>
                    <li>Confidence levels — filter and sort by match confidence.</li>
                </ul>

                <h3 class="doc-subheading">Materialization</h3>
                <ul>
                    <li>Companies — create or link standardized company records.</li>
                    <li>Drugs — create or link standardized drug catalog entries.</li>
                    <li>Tenders — create tender headers with program metadata.</li>
                    <li>Tender items — line items within each tender.</li>
                    <li>Bid records — awarded bid facts (price, quantity, winner, country).</li>
                    <li>Skip diagnostics — explains why rows were skipped (<span class="doc-inline-code">imports:diagnose-materialization</span>).</li>
                </ul>

                <h3 class="doc-subheading">Market Statistics</h3>
                <ul>
                    <li>Weighted average — quantity-weighted mean unit price per drug × country.</li>
                    <li>Median — robust central price measure.</li>
                    <li>Last price — most recent award price in the group.</li>
                    <li>Trend — direction and percentage change over time.</li>
                    <li>Drug × country groups — primary aggregation dimension.</li>
                    <li>GCC as dedicated country-like market — not mapped to Saudi Arabia.</li>
                    <li>USD-only calculations — all pricing normalized to USD.</li>
                </ul>

                <h3 class="doc-subheading">AI Recommendations</h3>
                <ul>
                    <li>Tender / program selection — user picks a tender group, not individual historical rows.</li>
                    <li>Tender group logic — e.g. KIMADIA-2022, KIMADIA-2023, or GCC historical tenders.</li>
                    <li>Product filtering by tender group — drug dropdown scoped to selected group.</li>
                    <li>Quantity and discount percentage — business inputs for price calculation.</li>
                    <li>Business calculation — deterministic recommendation from market statistics.</li>
                    <li>AI strategic insights — OpenAI narrative layer on top of calculated numbers.</li>
                    <li>Fallback hierarchy:
                        <ol>
                            <li>Tender Program Data</li>
                            <li>Country Data</li>
                            <li>Region Data</li>
                            <li>Global Data</li>
                        </ol>
                    </li>
                </ul>

                <h3 class="doc-subheading">Tender Groups</h3>
                <p>
                    Users select a <strong>tender program</strong>, not individual historical tender rows.
                    Examples: KIMADIA groups (KIMADIA-2022, KIMADIA-2023, …) and GHC/GCC groups that aggregate
                    historical GCC tenders. The drug dropdown filters to products that appear inside the
                    selected tender group only.
                </p>

                <h3 class="doc-subheading">Global Search</h3>
                <ul>
                    <li>Live search — instant results as you type in the top bar.</li>
                    <li>Entity coverage — tenders, drugs, companies, and predictions.</li>
                    <li>Keyboard support — arrow keys and Enter to navigate results.</li>
                </ul>
            </section>

            {{-- 4. Data Pipeline Flow --}}
            <section class="doc-section" id="pipeline">
                <h2 class="doc-section-title">
                    <i data-lucide="git-branch"></i>
                    Data Pipeline Flow
                </h2>
                <p class="doc-section-lead">
                    End-to-end flow from spreadsheet upload to AI recommendation.
                </p>
                <div class="doc-pipeline">
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">1</span>
                        <div>
                            <p class="doc-pipeline-name">Upload Excel</p>
                            <p class="doc-pipeline-desc">File uploaded; <span class="doc-inline-code">import_batch</span> created with source metadata and row count.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">2</span>
                        <div>
                            <p class="doc-pipeline-name">Mapping</p>
                            <p class="doc-pipeline-desc">User maps Excel columns to TenderAI fields (drug, company, country, price, quantity, tender, etc.).</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">3</span>
                        <div>
                            <p class="doc-pipeline-name">Validation</p>
                            <p class="doc-pipeline-desc">Each row validated for required fields, USD price, country rules, and data quality flags.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">4</span>
                        <div>
                            <p class="doc-pipeline-name">Standardization</p>
                            <p class="doc-pipeline-desc">Queued chunks match countries, companies, drugs; assign confidence scores and review status.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">5</span>
                        <div>
                            <p class="doc-pipeline-name">Product Matching Approval</p>
                            <p class="doc-pipeline-desc">Human review approves or corrects drug/company matches before materialization.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">6</span>
                        <div>
                            <p class="doc-pipeline-name">Materialization</p>
                            <p class="doc-pipeline-desc">Approved rows create/link companies, drugs, tenders, tender items, and bid records.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">7</span>
                        <div>
                            <p class="doc-pipeline-name">Bid Records</p>
                            <p class="doc-pipeline-desc">Award facts stored with standardized foreign keys; skipped rows logged with reasons.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">8</span>
                        <div>
                            <p class="doc-pipeline-name">Market Statistics</p>
                            <p class="doc-pipeline-desc"><span class="doc-inline-code">pricing_statistics</span> refreshed — averages, medians, trends per drug × country.</p>
                        </div>
                    </div>
                    <div class="doc-pipeline-step">
                        <span class="doc-pipeline-num">9</span>
                        <div>
                            <p class="doc-pipeline-name">AI Recommendation</p>
                            <p class="doc-pipeline-desc">User selects tender group + drug; system calculates price and generates strategic AI insight.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 5. Database Entities --}}
            <section class="doc-section" id="entities">
                <h2 class="doc-section-title">
                    <i data-lucide="database"></i>
                    Database / Data Entities
                </h2>
                <div class="doc-table-wrap">
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Entity</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="doc-inline-code">import_batches</span></td>
                                <td>Uploaded file metadata, mapping config, pipeline status, and progress tracking.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">import_rows</span></td>
                                <td>Raw and standardized row data; validation status, match suggestions, materialization state.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">companies</span></td>
                                <td>Canonical pharmaceutical company records.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">company_aliases</span></td>
                                <td>Alternate spellings and source names mapped to companies.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">drugs</span></td>
                                <td>Standardized drug / product catalog entries.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">drug_aliases</span></td>
                                <td>Alternate product names mapped to standardized drugs.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">tenders</span></td>
                                <td>Tender program headers with country, dates, and reference codes.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">tender_items</span></td>
                                <td>Line items within a tender (drug, quantity, specifications).</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">bid_records</span></td>
                                <td>Awarded bid facts — unit price, winner, quantity, linked entities.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">pricing_statistics</span></td>
                                <td>Aggregated market stats per drug × country (and regional/global rollups).</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">predictions</span></td>
                                <td>AI recommendation records with calculated price and strategic insights.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">jobs</span></td>
                                <td>Queued background jobs (standardization, materialization, etc.).</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">failed_jobs</span></td>
                                <td>Failed queue jobs with exception details for debugging.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 6. Country / Market Handling --}}
            <section class="doc-section" id="countries">
                <h2 class="doc-section-title">
                    <i data-lucide="globe"></i>
                    Country / Market Handling
                </h2>
                <div class="doc-table-wrap">
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Source / Market</th>
                                <th>Maps To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>KSA</td><td>Saudi Arabia</td></tr>
                            <tr><td>Oman</td><td>Oman</td></tr>
                            <tr><td>Iraq</td><td>Iraq</td></tr>
                            <tr><td>UAE, Kuwait, Qatar, Bahrain</td><td>Supported as individual countries</td></tr>
                            <tr><td>GCC / GHC</td><td>Dedicated country-like market — <strong>not</strong> mapped to Saudi Arabia</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="doc-alert doc-alert--info">
                    All pricing calculations are <strong>USD-only</strong>. Source currencies are normalized during import and statistics aggregation.
                </div>
            </section>

            {{-- 7. Queue / Hosting Logic --}}
            <section class="doc-section" id="queue">
                <h2 class="doc-section-title">
                    <i data-lucide="server"></i>
                    Queue / Hosting Logic
                </h2>
                <p class="doc-section-lead">
                    Hostinger shared hosting cannot run a permanent <span class="doc-inline-code">queue:work</span> daemon.
                    Instead, the Laravel Scheduler runs every minute via cron and dispatches pending jobs.
                </p>
                <ul>
                    <li><strong>No permanent queue worker</strong> — jobs sit in the database until processed.</li>
                    <li><strong>Laravel Scheduler</strong> — runs every minute via hPanel cron.</li>
                    <li><strong><span class="doc-inline-code">queue:process-pending</span></strong> — processes up to 25 jobs per scheduler tick (120s timeout).</li>
                    <li><strong>UI fallback</strong> — "Run Pending Processing" on import batch pages triggers the same command.</li>
                </ul>
                <p class="doc-subheading">Cron command (hPanel)</p>
                <pre class="doc-code">* * * * * /opt/alt/php82/usr/bin/php /home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai/artisan schedule:run >> /dev/null 2>&1</pre>
            </section>

            {{-- 8. Production Deployment --}}
            <section class="doc-section" id="deployment">
                <h2 class="doc-section-title">
                    <i data-lucide="rocket"></i>
                    Production Deployment
                </h2>

                <h3 class="doc-subheading">Local</h3>
                <pre class="doc-code">npm run build
git add -f public/build
git commit -m "build: production assets"
git push</pre>

                <h3 class="doc-subheading">Server</h3>
                <pre class="doc-code">cd /home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai
git pull origin main
/opt/alt/php82/usr/bin/php $(which composer) install --no-dev --optimize-autoloader
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan stats:refresh</pre>

                <p>Or use the automated release script:</p>
                <pre class="doc-code">bash deploy/hostinger-production-release.sh</pre>
            </section>

            {{-- 9. Maintenance Commands --}}
            <section class="doc-section" id="commands">
                <h2 class="doc-section-title">
                    <i data-lucide="terminal"></i>
                    Maintenance Commands
                </h2>
                <div class="doc-table-wrap">
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Command</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="doc-inline-code">imports:diagnose</span></td>
                                <td>Read-only pipeline diagnostics — queue state, batch statuses, and global health metrics. Optional batch ID argument.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">imports:diagnose-countries</span></td>
                                <td>Inspect country mapping issues for a batch — unmapped codes, region rules, and repair candidates.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">imports:diagnose-materialization</span></td>
                                <td>Explain why rows were skipped during materialization with example rows per skip reason.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">imports:repair-countries</span></td>
                                <td>Re-run country standardization and repair mappings for eligible rows in a batch.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">imports:materialize --batch=ID --retry-skipped</span></td>
                                <td>Materialize approved rows for a batch; <span class="doc-inline-code">--retry-skipped</span> retries previously skipped eligible rows.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">queue:process-pending</span></td>
                                <td>Process pending database queue jobs (default: max 25 jobs, 120s timeout). Used by scheduler and UI.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">stats:refresh</span></td>
                                <td>Recalculate <span class="doc-inline-code">pricing_statistics</span> from bid records. Use <span class="doc-inline-code">--all</span> for regional/global rollups.</td>
                            </tr>
                            <tr>
                                <td><span class="doc-inline-code">tenderai:reset-data</span></td>
                                <td>Destructive reset of import pipeline and derived data. Requires <span class="doc-inline-code">--force</span> confirmation.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 10. Troubleshooting --}}
            <section class="doc-section" id="troubleshooting">
                <h2 class="doc-section-title">
                    <i data-lucide="wrench"></i>
                    Troubleshooting
                </h2>

                <h3 class="doc-subheading">Market statistics pending</h3>
                <ul>
                    <li><strong>Cause:</strong> Materialization incomplete or <span class="doc-inline-code">stats:refresh</span> not yet run.</li>
                    <li><strong>Solution:</strong> Complete materialization, then run <span class="doc-inline-code">php artisan stats:refresh --all</span> or use the retry button on the import batch page.</li>
                </ul>

                <h3 class="doc-subheading">Data preparation in progress</h3>
                <ul>
                    <li><strong>Cause:</strong> Standardization or materialization jobs still queued or running.</li>
                    <li><strong>Solution:</strong> Wait for cron cycle, or click "Run Pending Processing" on the import batch. Check <span class="doc-inline-code">imports:diagnose</span>.</li>
                </ul>

                <h3 class="doc-subheading">Rows skipped</h3>
                <ul>
                    <li><span class="doc-inline-code">missing_country</span> — no country value or unmapped country code.</li>
                    <li><span class="doc-inline-code">region_requires_country</span> — regional entry needs a specific country.</li>
                    <li><span class="doc-inline-code">invalid_price_usd</span> — price missing, zero, or non-numeric.</li>
                    <li><span class="doc-inline-code">invalid validation status</span> — row failed validation or not approved for materialization.</li>
                </ul>
                <p>Run <span class="doc-inline-code">imports:diagnose-materialization BATCH_ID</span> for detailed skip breakdown.</p>

                <h3 class="doc-subheading">Drug dropdown empty</h3>
                <ul>
                    <li>Selected tender group has no products — choose a different tender program.</li>
                    <li>Product not materialized — complete product matching approval and materialization.</li>
                    <li>No bid records — verify materialization produced bid records for the group's tenders.</li>
                </ul>

                <h3 class="doc-subheading">Queue not processing</h3>
                <ul>
                    <li><strong>Cron issue</strong> — verify hPanel cron uses PHP 8.2 path and <span class="doc-inline-code">schedule:run</span>.</li>
                    <li><strong>Pending jobs</strong> — check <span class="doc-inline-code">jobs</span> table count via <span class="doc-inline-code">imports:diagnose</span>.</li>
                    <li><strong>Manual trigger</strong> — use "Run Pending Processing" or <span class="doc-inline-code">queue:process-pending --max-jobs=25 --timeout=120</span>.</li>
                </ul>
            </section>

            {{-- 11. Demo Guide --}}
            <section class="doc-section" id="demo">
                <h2 class="doc-section-title">
                    <i data-lucide="presentation"></i>
                    Demo Guide
                </h2>
                <p class="doc-section-lead">Recommended flow for a live delivery or client demo.</p>
                <ol class="doc-demo-steps">
                    <li>Log in with a verified user account.</li>
                    <li>Open the <strong>Dashboard</strong> — show KPIs and data health.</li>
                    <li>Navigate to <strong>Upload Data</strong> — upload a sample Excel file.</li>
                    <li>Complete column mapping and wait for validation / standardization.</li>
                    <li>Open <strong>Product Matching</strong> — approve suggestions or bulk-approve review rows.</li>
                    <li>If pipeline is stalled, click <strong>Run Pending Processing</strong> on the import batch page.</li>
                    <li>Open <strong>Price Recommendation</strong> (AI Recommendations).</li>
                    <li>Select a <strong>tender program</strong> (e.g. KIMADIA-2023 or GCC group).</li>
                    <li>Select a <strong>drug</strong> from the filtered dropdown.</li>
                    <li>Enter <strong>quantity</strong> and <strong>discount percentage</strong>.</li>
                    <li>Generate the recommendation — review calculated price and fallback tier used.</li>
                    <li>Review the <strong>AI strategic insight</strong> narrative.</li>
                </ol>
            </section>

            {{-- 12. Production Status --}}
            <section class="doc-section" id="status">
                <h2 class="doc-section-title">
                    <i data-lucide="activity"></i>
                    Current Production Status
                </h2>
                <p class="doc-section-lead">Snapshot as of latest known production release.</p>
                <div class="doc-status-grid">
                    <div class="doc-status-item">
                        <p class="doc-status-value">a135c37</p>
                        <p class="doc-status-label">Commit</p>
                    </div>
                    <div class="doc-status-item">
                        <p class="doc-status-value">3,105</p>
                        <p class="doc-status-label">Bid Records</p>
                    </div>
                    <div class="doc-status-item">
                        <p class="doc-status-value">6,479</p>
                        <p class="doc-status-label">Pricing Statistics</p>
                    </div>
                    <div class="doc-status-item">
                        <p class="doc-status-value">0</p>
                        <p class="doc-status-label">Pending Jobs</p>
                    </div>
                    <div class="doc-status-item">
                        <p class="doc-status-value">0</p>
                        <p class="doc-status-label">Failed Jobs</p>
                    </div>
                </div>
                <div class="doc-meta-row" style="margin-top: 1rem;">
                    <span class="doc-badge doc-badge--success">GCC Enabled</span>
                    <span class="doc-badge doc-badge--success">Tender Group Flow</span>
                    <span class="doc-badge doc-badge--primary">USD Pricing</span>
                </div>
            </section>

            {{-- 13. Security Notes --}}
            <section class="doc-section" id="security">
                <h2 class="doc-section-title">
                    <i data-lucide="shield"></i>
                    Security Notes
                </h2>
                <ul>
                    <li>This page is <strong>hidden from navigation</strong> — not in sidebar, topbar, footer, or public menus.</li>
                    <li>Protected by <span class="doc-inline-code">auth</span> and <span class="doc-inline-code">verified</span> middleware — guests are redirected to login.</li>
                    <li>No <span class="doc-inline-code">.env</span> values, database passwords, SSH credentials, or API keys are displayed on this page.</li>
                    <li>OpenAI API key is configured in Settings and stored encrypted — never exposed in documentation.</li>
                </ul>
                <div class="doc-alert doc-alert--warning">
                    Do not share this URL publicly. Treat it as internal documentation for authenticated team members only.
                </div>
            </section>

        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endpush
