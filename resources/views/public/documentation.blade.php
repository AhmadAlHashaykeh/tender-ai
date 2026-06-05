@extends('layouts.guest')

@section('title', 'TenderAI Documentation — Pharmaceutical Tender Intelligence & Price Prediction')

@section('content')
<div class="pubdoc-page">

    <div class="pubdoc-progress" aria-hidden="true">
        <div class="pubdoc-progress-bar" id="pubdoc-progress-bar"></div>
    </div>

    <header class="pubdoc-nav">
        <div class="pubdoc-nav-inner">
            <a href="{{ route('landing') }}" class="pubdoc-brand">
                <span class="pubdoc-brand-icon"><i data-lucide="zap" style="width:1rem;height:1rem;"></i></span>
                <span class="pubdoc-brand-text">TenderAI</span>
            </a>
            <div class="pubdoc-nav-actions">
                <button type="button" class="pubdoc-mobile-toc-btn" id="pubdoc-mobile-toc-btn" aria-label="Open table of contents">
                    <i data-lucide="list" style="width:0.875rem;height:0.875rem;"></i>
                    Contents
                </button>
                <a href="{{ route('landing') }}" class="pubdoc-nav-link">Home</a>
                <a href="{{ route('login') }}" class="pubdoc-btn-login">Login</a>
            </div>
        </div>
    </header>

    <div class="pubdoc-hero">
        <div class="pubdoc-hero-inner">
            <p class="pubdoc-hero-eyebrow">Public Documentation</p>
            <h1 class="pubdoc-hero-title">TenderAI Documentation</h1>
            <p class="pubdoc-hero-subtitle">
                Pharmaceutical Tender Intelligence &amp; Price Prediction System — a decision-support platform
                for evidence-based bidding in pharmaceutical procurement.
            </p>
            <div class="pubdoc-hero-meta">
                <span class="pubdoc-badge pubdoc-badge--primary">Decision Support</span>
                <span class="pubdoc-badge">Graduation Report Ready</span>
                <span class="pubdoc-badge pubdoc-badge--success">No Login Required</span>
            </div>
        </div>
    </div>

    <div class="pubdoc-sidebar-backdrop" id="pubdoc-sidebar-backdrop"></div>

    <div class="pubdoc-shell">
        @include('public.partials.documentation-nav')

        <main class="pubdoc-main" id="pubdoc-main">

            {{-- WHY TENDERAI EXISTS --}}
            <article class="pubdoc-block" id="why-tenderai-exists">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Problem Analysis</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="target" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">Why TenderAI Exists</h2>
                    </div>
                    <p class="pubdoc-block-summary">Pharmaceutical tender pricing is high-stakes, data-rich, and historically managed through manual spreadsheets. TenderAI bridges that gap.</p>
                </header>
                <div class="pubdoc-compare">
                    <div class="pubdoc-compare-col pubdoc-compare-col--before">
                        <p class="pubdoc-compare-heading">Before TenderAI</p>
                        <ul>
                            <li>Manual Excel analysis across years</li>
                            <li>Hard price comparison between markets</li>
                            <li>Unclear market behavior patterns</li>
                            <li>High bidding uncertainty and risk</li>
                        </ul>
                    </div>
                    <div class="pubdoc-compare-col pubdoc-compare-col--process">
                        <p class="pubdoc-compare-heading">TenderAI Process</p>
                        <ul>
                            <li>Automated data standardization</li>
                            <li>Structured historical intelligence</li>
                            <li>Evidence-based price recommendation</li>
                            <li>AI strategic interpretation layer</li>
                        </ul>
                    </div>
                    <div class="pubdoc-compare-col pubdoc-compare-col--after">
                        <p class="pubdoc-compare-heading">After TenderAI</p>
                        <ul>
                            <li>Faster, repeatable decisions</li>
                            <li>Better pricing strategy</li>
                            <li>Lower risk through evidence</li>
                            <li>Organized knowledge base for future tenders</li>
                        </ul>
                    </div>
                </div>
            </article>

            {{-- 1. Executive Summary --}}
            <article class="pubdoc-block" id="executive-summary">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Introduction</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="file-text" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">Executive Summary</h2>
                    </div>
                    <p class="pubdoc-block-summary">TenderAI is a decision-support platform for pharmaceutical companies pricing bids in competitive public and institutional procurement.</p>
                </header>
                <div class="pubdoc-panel">
                    <p>Pharmaceutical tenders involve large volumes of historical award data spread across Excel spreadsheets, inconsistent product naming, and varying market conventions. Without structured analysis, pricing decisions rely heavily on intuition, fragmented spreadsheets, and limited visibility into what competitors have historically won at.</p>
                    <p>TenderAI addresses this gap by ingesting historical tender records, standardizing them into a unified knowledge base, generating market statistics at product and country levels, and supporting price recommendations grounded in real award evidence.</p>
                </div>
                <div class="pubdoc-card-grid">
                    <div class="pubdoc-card">
                        <div class="pubdoc-card-icon-wrap"><i data-lucide="history" style="width:1.125rem;height:1.125rem;"></i></div>
                        <p class="pubdoc-card-title">Historical Analysis</p>
                        <p class="pubdoc-card-text">Structured review of past tender awards, winners, and unit prices across programs and markets.</p>
                    </div>
                    <div class="pubdoc-card">
                        <div class="pubdoc-card-icon-wrap"><i data-lucide="bar-chart-3" style="width:1.125rem;height:1.125rem;"></i></div>
                        <p class="pubdoc-card-title">Market Intelligence</p>
                        <p class="pubdoc-card-text">Aggregated statistics revealing how products have been priced in specific countries and tender programs.</p>
                    </div>
                    <div class="pubdoc-card">
                        <div class="pubdoc-card-icon-wrap"><i data-lucide="trending-up" style="width:1.125rem;height:1.125rem;"></i></div>
                        <p class="pubdoc-card-title">Prediction Support</p>
                        <p class="pubdoc-card-text">Evidence-based price recommendations with layered fallback from program-specific to global data.</p>
                    </div>
                    <div class="pubdoc-card">
                        <div class="pubdoc-card-icon-wrap"><i data-lucide="brain" style="width:1.125rem;height:1.125rem;"></i></div>
                        <p class="pubdoc-card-title">AI Interpretation</p>
                        <p class="pubdoc-card-text">Strategic commentary on competitiveness, discount risk, and market context — without replacing the calculation engine.</p>
                    </div>
                </div>
            </article>

            {{-- 2. Problem Statement --}}
            <article class="pubdoc-block" id="problem-statement">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Problem Analysis</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="alert-circle" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">Problem Statement</h2>
                    </div>
                    <p class="pubdoc-block-summary">In pharmaceutical tendering, pricing is critical yet the evidence needed to price responsibly is rarely organized for fast, confident decision-making.</p>
                </header>
                <div class="pubdoc-panel">
                    <p>Pharmaceutical companies frequently submit tender prices without sufficient structured historical insight. Tender departments work with Excel files accumulated over years, each using different column layouts, product spellings, and country abbreviations. A product listed as "Acyclovir 200mg Tab" in one sheet may appear as "ACYCLOVIR TAB 200 MG" in another.</p>
                    <p>Countries and markets are recorded inconsistently — "KSA," "Saudi," and "Saudi Arabia" may all refer to the same market, while GCC or GHC tenders represent a distinct Gulf procurement context. Pricing decisions are often manual, time-consuming, and vulnerable to error.</p>
                    <p>The consequences are significant. A price too high may lose the tender entirely. A price too low may win but erode profitability. Historical patterns — such as whether KIMADIA program prices have risen or fallen — are extremely difficult to analyze manually across dozens of spreadsheets.</p>
                    <p>TenderAI was conceived to solve this operational intelligence problem: transforming scattered, inconsistent tender history into a structured decision-support system that pharmaceutical professionals can trust when preparing their next bid.</p>
                </div>
            </article>

            {{-- 3. Importance --}}
            <article class="pubdoc-block" id="importance">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Tender Analysis</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="pill" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">Importance of Pharmaceutical Tender Analysis</h2>
                    </div>
                    <p class="pubdoc-block-summary">Medicine procurement through tenders directly affects patient access, healthcare budgets, and supply chain sustainability.</p>
                </header>
                <div class="pubdoc-panel">
                    <p>Medicine procurement through public and institutional tenders directly affects patient access, healthcare budgets, and the sustainability of pharmaceutical supply chains. Because medicines are highly price-sensitive and often purchased in large volumes, even small percentage differences in unit price can translate into substantial financial impact.</p>
                    <p>Historical award prices are signals of market behavior — they reveal which price levels procurement authorities have accepted, how competition has evolved, and whether products are trending toward lower or higher award prices.</p>
                    <p>Repeated tender programs such as <strong>KIMADIA</strong> or <strong>GCC/GHC</strong> exhibit historical patterns across annual cycles. Price prediction here is not guessing — it is interpreting market evidence to support a defensible, competitive bid.</p>
                </div>
            </article>

            {{-- 4. Objectives --}}
            <article class="pubdoc-block" id="objectives">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Objectives</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="check-circle" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">System Objectives</h2>
                    </div>
                </header>
                <div class="pubdoc-panel">
                    <ul>
                        <li><strong>Organize historical tender data</strong> — consolidate fragmented Excel sources into a single structured repository.</li>
                        <li><strong>Standardize inconsistent Excel data</strong> — normalize product names, company names, countries, and tender references.</li>
                        <li><strong>Identify drugs, companies, countries, and tenders</strong> — build a canonical catalog enabling reliable comparison across years.</li>
                        <li><strong>Build clean bid records</strong> — transform raw rows into verified historical award evidence.</li>
                        <li><strong>Generate market statistics</strong> — compute averages, medians, trends, and last prices per product and market.</li>
                        <li><strong>Support price prediction</strong> — recommend bid prices using the most relevant historical evidence.</li>
                        <li><strong>Provide AI strategic interpretation</strong> — explain results in business language for decision-makers.</li>
                        <li><strong>Help users understand competitiveness and risk</strong> — surface whether a proposed price is aggressive, moderate, or uncompetitive.</li>
                    </ul>
                </div>
            </article>

            {{-- 5. Target Users --}}
            <article class="pubdoc-block" id="target-users">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Stakeholders</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="users" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">Target Users</h2>
                    </div>
                    <p class="pubdoc-block-summary">Professionals involved in pharmaceutical bidding, market access, and procurement strategy.</p>
                </header>
                <div class="pubdoc-card-grid">
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Pharmaceutical Companies</p><p class="pubdoc-card-text">Organizations submitting bids in public and institutional medicine tenders.</p></div>
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Tender Departments</p><p class="pubdoc-card-text">Teams preparing, reviewing, and submitting tender responses.</p></div>
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Business Development</p><p class="pubdoc-card-text">Teams evaluating market entry and competitive positioning.</p></div>
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Pricing Analysts</p><p class="pubdoc-card-text">Specialists modeling price sensitivity, discount scenarios, and margin impact.</p></div>
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Procurement Consultants</p><p class="pubdoc-card-text">Advisors supporting clients with tender intelligence and benchmarking.</p></div>
                    <div class="pubdoc-card"><p class="pubdoc-card-title">Decision Makers</p><p class="pubdoc-card-text">Managers and directors who approve final bid prices before submission.</p></div>
                </div>
            </article>

            {{-- 6. Workflow --}}
            <article class="pubdoc-block" id="workflow">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">System Workflow</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="git-branch" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">TenderAI Business Workflow</h2>
                    </div>
                    <p class="pubdoc-block-summary">From raw historical spreadsheets to an actionable price recommendation — mirroring how tender professionals prepare for a bid.</p>
                </header>
                <div class="pubdoc-steps">
                    @foreach ([
                        ['Historical Data Upload', 'Tender teams upload Excel files containing past award records, prices, winners, and quantities.'],
                        ['Data Mapping', 'Source columns are mapped to TenderAI fields so the system understands what each cell represents.'],
                        ['Validation', 'Rows are checked for completeness, valid prices, and acceptable country/market values.'],
                        ['Standardization', 'Product names, company names, and countries are normalized to canonical identities.'],
                        ['Product Matching', 'The system suggests matches and users review or correct them to protect data quality.'],
                        ['Entity Creation', 'Approved data becomes structured companies, drugs, tenders, and tender line items.'],
                        ['Bid Record Generation', 'Each verified award becomes a bid record — the foundation for all statistics and predictions.'],
                        ['Market Statistics', 'Aggregated pricing metrics calculated per product and market.'],
                        ['Tender Program Selection', 'User selects a tender program (e.g., KIMADIA or GCC), not a single historical row.'],
                        ['Product Selection', 'Only products historically present in the selected program are available.'],
                        ['Price Recommendation', 'System calculates recommended unit price using layered historical evidence.'],
                        ['AI Strategic Insight', 'AI-generated narrative explains competitiveness, risk, and strategic context.'],
                    ] as $i => $step)
                    <div class="pubdoc-step">
                        <span class="pubdoc-step-num">{{ $i + 1 }}</span>
                        <div>
                            <p class="pubdoc-step-name">{{ $step[0] }}</p>
                            <p class="pubdoc-step-desc">{{ $step[1] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </article>

            {{-- SYSTEM FLOW DIAGRAM --}}
            <article class="pubdoc-block" id="system-flow">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">Methodology</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="network" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">System Pipeline</h2>
                    </div>
                    <p class="pubdoc-block-summary">The end-to-end data journey from raw tender spreadsheets to AI-supported recommendations.</p>
                </header>
                <div class="pubdoc-pipeline pubdoc-pipeline--horizontal">
                    @foreach ([
                        ['Raw Tender Data', 'Excel uploads from historical awards'],
                        ['Data Standardization', 'Countries, companies, products normalized'],
                        ['Product Matching', 'Human-reviewed match approval'],
                        ['Bid Records', 'Verified historical award facts'],
                        ['Market Intelligence', 'Statistics per drug × market'],
                        ['Prediction Engine', 'Layered price recommendation'],
                        ['AI Strategic Insights', 'Interpretation and risk commentary'],
                    ] as $i => $node)
                        @if ($i > 0)
                        <div class="pubdoc-pipeline-arrow"><i data-lucide="arrow-right" style="width:1rem;height:1rem;"></i></div>
                        @endif
                        <div class="pubdoc-pipeline-node">
                            <p class="pubdoc-pipeline-node-title">{{ $node[0] }}</p>
                            <p class="pubdoc-pipeline-node-desc">{{ $node[1] }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            {{-- USER GUIDE --}}
            <article class="pubdoc-block" id="user-guide">
                <header class="pubdoc-block-header">
                    <span class="pubdoc-academic-label">User Guide</span>
                    <div class="pubdoc-block-title-row">
                        <span class="pubdoc-block-icon"><i data-lucide="map" style="width:1.25rem;height:1.25rem;"></i></span>
                        <h2 class="pubdoc-block-title">How to Use TenderAI</h2>
                    </div>
                    <p class="pubdoc-block-summary">A step-by-step visual guide for tender teams — from uploading historical data to understanding price recommendations.</p>
                </header>

                <div class="pubdoc-guide-timeline">
                    <div class="pubdoc-guide-step" id="user-guide-step-1">
                        <div class="pubdoc-guide-step-marker"><span class="pubdoc-guide-step-badge">01</span></div>
                        <div class="pubdoc-guide-step-card">
                            <h3 class="pubdoc-guide-title">Upload Historical Tender Data</h3>
                            <p class="pubdoc-guide-desc">The user uploads historical pharmaceutical tender spreadsheets. TenderAI converts raw Excel rows into structured import data ready for processing.</p>
                            <div class="pubdoc-guide-flow">
                                <span class="pubdoc-guide-chip"><i data-lucide="file-spreadsheet" style="width:0.875rem;height:0.875rem;"></i> Excel File</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">→</span>
                                <span class="pubdoc-guide-chip pubdoc-guide-chip--highlight"><i data-lucide="upload" style="width:0.875rem;height:0.875rem;"></i> TenderAI Upload</span>
                            </div>
                            <ol class="pubdoc-guide-actions">
                                <li>Open the <strong>Upload Data</strong> page in the dashboard</li>
                                <li>Select your Excel file containing historical awards</li>
                                <li>Map columns to TenderAI fields (product, price, country, company, tender)</li>
                                <li>Confirm mapping and start processing</li>
                            </ol>
                        </div>
                    </div>

                    <div class="pubdoc-guide-step" id="user-guide-step-2">
                        <div class="pubdoc-guide-step-marker"><span class="pubdoc-guide-step-badge">02</span></div>
                        <div class="pubdoc-guide-step-card">
                            <h3 class="pubdoc-guide-title">Review Product Matching</h3>
                            <p class="pubdoc-guide-desc">TenderAI detects similar products and suggests standardized matches. Users confirm or correct matches to protect prediction accuracy.</p>
                            <div class="pubdoc-guide-flow">
                                <span class="pubdoc-guide-chip">Raw Product Name</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">→</span>
                                <span class="pubdoc-guide-chip">System Suggestion</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">→</span>
                                <span class="pubdoc-guide-chip pubdoc-guide-chip--highlight">Approved Product</span>
                            </div>
                            <ol class="pubdoc-guide-actions">
                                <li>Open <strong>Product Matching</strong> review page</li>
                                <li>Review suggested matches and confidence scores</li>
                                <li>Bulk-approve high-confidence matches</li>
                                <li>Manually correct uncertain products</li>
                            </ol>
                        </div>
                    </div>

                    <div class="pubdoc-guide-step" id="user-guide-step-3">
                        <div class="pubdoc-guide-step-marker"><span class="pubdoc-guide-step-badge">03</span></div>
                        <div class="pubdoc-guide-step-card">
                            <h3 class="pubdoc-guide-title">Generate Market Intelligence</h3>
                            <p class="pubdoc-guide-desc">Once bid records are created, the statistics engine aggregates historical awards into actionable market intelligence.</p>
                            <div class="pubdoc-guide-flow pubdoc-guide-flow--stack">
                                <span class="pubdoc-guide-chip">Historical Records</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">↓</span>
                                <span class="pubdoc-guide-chip pubdoc-guide-chip--highlight">Statistics Engine</span>
                            </div>
                            <div class="pubdoc-metric-grid">
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="calculator" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Average Price</p><p class="pubdoc-metric-card-hint">Typical award level</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="scale" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Weighted Avg</p><p class="pubdoc-metric-card-hint">Volume-sensitive price</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="minus" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Median</p><p class="pubdoc-metric-card-hint">Outlier-resistant center</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="clock" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Last Price</p><p class="pubdoc-metric-card-hint">Most recent award</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="trending-up" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Market Trend</p><p class="pubdoc-metric-card-hint">Direction over time</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="pubdoc-guide-step" id="user-guide-step-4">
                        <div class="pubdoc-guide-step-marker"><span class="pubdoc-guide-step-badge">04</span></div>
                        <div class="pubdoc-guide-step-card">
                            <h3 class="pubdoc-guide-title">Create Price Recommendation</h3>
                            <p class="pubdoc-guide-desc">Select a tender program, choose a product from that program's history, enter quantity and discount, then generate a recommendation.</p>
                            <div class="pubdoc-guide-flow pubdoc-guide-flow--stack">
                                <span class="pubdoc-guide-chip pubdoc-guide-chip--highlight">Tender Program</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">↓</span>
                                <span class="pubdoc-guide-chip">Select Product</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">↓</span>
                                <span class="pubdoc-guide-chip">Enter Quantity</span>
                                <span class="pubdoc-guide-flow-arrow" aria-hidden="true">↓</span>
                                <span class="pubdoc-guide-chip pubdoc-guide-chip--highlight">Generate</span>
                            </div>
                            <ol class="pubdoc-guide-actions">
                                <li>Open <strong>Price Recommendation</strong> (AI Recommendations)</li>
                                <li>Select tender program — e.g. KIMADIA or GCC</li>
                                <li>Select product from the filtered dropdown</li>
                                <li>Enter quantity and proposed discount percentage</li>
                                <li>Click generate recommendation</li>
                            </ol>
                        </div>
                    </div>

                    <div class="pubdoc-guide-step pubdoc-guide-step--last" id="user-guide-step-5">
                        <div class="pubdoc-guide-step-marker"><span class="pubdoc-guide-step-badge">05</span></div>
                        <div class="pubdoc-guide-step-card">
                            <h3 class="pubdoc-guide-title">Understand Results</h3>
                            <p class="pubdoc-guide-desc">The recommendation screen presents calculated price, confidence indicators, risk level, market evidence, and AI strategic commentary.</p>
                            <div class="pubdoc-metric-grid">
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="dollar-sign" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Prediction Price</p><p class="pubdoc-metric-card-hint">Calculated recommendation</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="shield-check" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Confidence</p><p class="pubdoc-metric-card-hint">Data reliability indicator</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="alert-triangle" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Risk Level</p><p class="pubdoc-metric-card-hint">Competition warning</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="database" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">Market Evidence</p><p class="pubdoc-metric-card-hint">Historical basis used</p></div>
                                <div class="pubdoc-metric-card"><div class="pubdoc-metric-card-icon"><i data-lucide="sparkles" style="width:1rem;height:1rem;"></i></div><p class="pubdoc-metric-card-title">AI Insight</p><p class="pubdoc-metric-card-hint">Strategic commentary</p></div>
                            </div>
                            <p class="pubdoc-guide-footnote">Review the evidence tier used (program, country, region, or global), adjust discount if needed, and present to decision-makers before tender submission.</p>
                        </div>
                    </div>
                </div>
            </article>

            {{-- 7–24: remaining sections with improved headers --}}
            @include('public.partials.documentation-sections')

        </main>
    </div>

    <footer class="pubdoc-footer">
        <p>&copy; {{ date('Y') }} TenderAI — Pharmaceutical Tender Intelligence &amp; Price Prediction System.<br>
        <a href="{{ route('landing') }}">Return to Home</a> &middot; <a href="{{ route('login') }}">Login to Dashboard</a></p>
    </footer>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    var progressBar = document.getElementById('pubdoc-progress-bar');
    var sidebar = document.getElementById('pubdoc-sidebar');
    var backdrop = document.getElementById('pubdoc-sidebar-backdrop');
    var mobileBtn = document.getElementById('pubdoc-mobile-toc-btn');
    var sections = document.querySelectorAll('.pubdoc-block[id]');
    var navLinks = document.querySelectorAll('.pubdoc-sidebar-link');

    function updateProgress() {
        var scrollTop = window.scrollY;
        var docHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (progressBar && docHeight > 0) {
            progressBar.style.width = Math.min(100, (scrollTop / docHeight) * 100) + '%';
        }
    }

    function updateActiveNav() {
        var current = '';
        sections.forEach(function (section) {
            if (window.scrollY >= section.offsetTop - 120) {
                current = section.getAttribute('id');
            }
        });
        navLinks.forEach(function (link) {
            link.classList.toggle('pubdoc-sidebar-link--active', link.dataset.section === current);
        });
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) entry.target.classList.add('pubdoc-block--visible');
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    sections.forEach(function (s) { observer.observe(s); });

    window.addEventListener('scroll', function () {
        updateProgress();
        updateActiveNav();
    }, { passive: true });
    updateProgress();
    updateActiveNav();

    if (mobileBtn && sidebar && backdrop) {
        mobileBtn.addEventListener('click', function () {
            sidebar.classList.add('pubdoc-sidebar--open');
            backdrop.classList.add('pubdoc-sidebar-backdrop--open');
        });
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('pubdoc-sidebar--open');
            backdrop.classList.remove('pubdoc-sidebar-backdrop--open');
        });
        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                sidebar.classList.remove('pubdoc-sidebar--open');
                backdrop.classList.remove('pubdoc-sidebar-backdrop--open');
            });
        });
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            var target = document.getElementById(link.dataset.section);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});
</script>
@endpush
