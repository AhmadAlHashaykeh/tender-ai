@extends('layouts.guest')

@section('title', 'TenderAI Documentation — Pharmaceutical Tender Intelligence & Price Prediction')

@section('content')
<div class="pubdoc-page">

    <nav class="pubdoc-nav" aria-label="Public navigation">
        <div class="pubdoc-nav-inner">
            <a href="{{ route('landing') }}" class="pubdoc-brand">
                <span class="pubdoc-brand-icon">
                    <i data-lucide="zap" style="width:1.125rem;height:1.125rem;"></i>
                </span>
                <span class="pubdoc-brand-text">TenderAI</span>
            </a>
            <div class="pubdoc-nav-actions">
                <a href="{{ route('landing') }}" class="pubdoc-nav-link">Home</a>
                <a href="#executive-summary" class="pubdoc-nav-link">Overview</a>
                <a href="{{ route('login') }}" class="pubdoc-btn-login">
                    Login
                    <i data-lucide="arrow-right" style="width:0.875rem;height:0.875rem;"></i>
                </a>
            </div>
        </div>
    </nav>

    <header class="pubdoc-hero">
        <div class="pubdoc-hero-inner">
            <span class="pubdoc-hero-badge">
                <i data-lucide="book-open" style="width:0.75rem;height:0.75rem;"></i>
                Public Documentation
            </span>
            <h1 class="pubdoc-hero-title">TenderAI Documentation</h1>
            <p class="pubdoc-hero-subtitle">
                Pharmaceutical Tender Intelligence &amp; Price Prediction System — a decision-support platform
                that transforms historical tender data into structured market intelligence and evidence-based
                bidding guidance.
            </p>
        </div>
    </header>

    <div class="pubdoc-layout">
        <aside class="pubdoc-toc" aria-label="Table of contents">
            <p class="pubdoc-toc-title">Contents</p>
            <a href="#executive-summary" class="pubdoc-toc-link">1. Executive Summary</a>
            <a href="#problem-statement" class="pubdoc-toc-link">2. Problem Statement</a>
            <a href="#importance" class="pubdoc-toc-link">3. Importance of Analysis</a>
            <a href="#objectives" class="pubdoc-toc-link">4. System Objectives</a>
            <a href="#target-users" class="pubdoc-toc-link">5. Target Users</a>
            <a href="#workflow" class="pubdoc-toc-link">6. Business Workflow</a>
            <a href="#data-upload" class="pubdoc-toc-link">7. Data Upload</a>
            <a href="#standardization" class="pubdoc-toc-link">8. Standardization</a>
            <a href="#product-matching" class="pubdoc-toc-link">9. Product Matching</a>
            <a href="#materialization" class="pubdoc-toc-link">10. Bid Records</a>
            <a href="#market-statistics" class="pubdoc-toc-link">11. Market Statistics</a>
            <a href="#tender-program" class="pubdoc-toc-link">12. Tender Program Logic</a>
            <a href="#product-filtering" class="pubdoc-toc-link">13. Product Filtering</a>
            <a href="#price-methodology" class="pubdoc-toc-link">14. Price Methodology</a>
            <a href="#quantity-discount" class="pubdoc-toc-link">15. Quantity &amp; Discount</a>
            <a href="#ai-insights" class="pubdoc-toc-link">16. AI Strategic Insights</a>
            <a href="#gcc-market" class="pubdoc-toc-link">17. GCC Market</a>
            <a href="#example" class="pubdoc-toc-link">18. Example Scenario</a>
            <a href="#benefits" class="pubdoc-toc-link">19. Benefits</a>
            <a href="#limitations" class="pubdoc-toc-link">20. Limitations</a>
            <a href="#graduation" class="pubdoc-toc-link">21. Graduation Relevance</a>
            <a href="#technical" class="pubdoc-toc-link">22. Technical Overview</a>
            <a href="#production-status" class="pubdoc-toc-link">23. Production Status</a>
            <a href="#conclusion" class="pubdoc-toc-link">24. Conclusion</a>
        </aside>

        <div class="pubdoc-content">

            {{-- 1. Executive Summary --}}
            <section class="pubdoc-section" id="executive-summary">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 1</span>
                    <h2 class="pubdoc-section-title">Executive Summary</h2>
                    <p class="pubdoc-lead">
                        TenderAI is a decision-support platform designed for pharmaceutical companies and tender
                        professionals who need to price bids intelligently in competitive public and institutional
                        procurement environments.
                    </p>
                    <p>
                        Pharmaceutical tenders involve large volumes of historical award data spread across Excel
                        spreadsheets, inconsistent product naming, and varying market conventions. Without structured
                        analysis, pricing decisions rely heavily on intuition, fragmented spreadsheets, and limited
                        visibility into what competitors have historically won at.
                    </p>
                    <p>
                        TenderAI addresses this gap by ingesting historical tender records, standardizing them into a
                        unified knowledge base, generating market statistics at product and country levels, and
                        supporting price recommendations grounded in real award evidence. The platform reduces manual
                        analysis effort, reveals pricing patterns across years and markets, and helps organizations
                        make more informed, competitive, and financially responsible bidding decisions.
                    </p>
                    <div class="pubdoc-card-grid">
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Historical Analysis</p>
                            <p class="pubdoc-card-text">Structured review of past tender awards, winners, and unit prices across programs and markets.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Market Intelligence</p>
                            <p class="pubdoc-card-text">Aggregated statistics that reveal how products have been priced in specific countries and tender programs.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Prediction Support</p>
                            <p class="pubdoc-card-text">Evidence-based price recommendations with layered fallback from program-specific to global data.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">AI Interpretation</p>
                            <p class="pubdoc-card-text">Strategic commentary on competitiveness, discount risk, and market context — without replacing the calculation engine.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 2. Problem Statement --}}
            <section class="pubdoc-section" id="problem-statement">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 2</span>
                    <h2 class="pubdoc-section-title">Problem Statement</h2>
                    <p class="pubdoc-lead">
                        In pharmaceutical tendering, pricing is one of the most critical — and least transparent —
                        decisions a supplier must make. Yet the evidence needed to price responsibly is rarely
                        organized in a form that supports fast, confident decision-making.
                    </p>
                    <p>
                        Pharmaceutical companies frequently submit tender prices without sufficient structured
                        historical insight. Tender departments work with Excel files accumulated over years,
                        each using different column layouts, product spellings, and country abbreviations. A
                        product listed as "Acyclovir 200mg Tab" in one sheet may appear as "ACYCLOVIR TAB 200 MG"
                        in another. Company names vary between legal names, trade names, and local spellings.
                    </p>
                    <p>
                        Countries and markets are recorded inconsistently — "KSA," "Saudi," and "Saudi Arabia" may
                        all refer to the same market, while GCC or GHC tenders represent a distinct Gulf procurement
                        context that should not be collapsed into a single country. Because of this fragmentation,
                        pricing decisions are often manual, time-consuming, and vulnerable to error.
                    </p>
                    <p>
                        The consequences are significant. A price that is too high may lose the tender entirely,
                        excluding patients from access and eliminating revenue for the supplier. A price that is
                        too low may win the tender but erode profitability, setting unsustainable precedents for
                        future bids. Historical patterns — such as whether KIMADIA program prices have risen or
                        fallen for a given molecule — are extremely difficult to analyze manually across dozens of
                        spreadsheets and hundreds of product lines.
                    </p>
                    <p>
                        TenderAI was conceived to solve this operational intelligence problem: transforming
                        scattered, inconsistent tender history into a structured decision-support system that
                        pharmaceutical professionals can trust when preparing their next bid.
                    </p>
                </div>
            </section>

            {{-- 3. Importance --}}
            <section class="pubdoc-section" id="importance">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 3</span>
                    <h2 class="pubdoc-section-title">Importance of Pharmaceutical Tender Analysis</h2>
                    <p>
                        Medicine procurement through public and institutional tenders directly affects patient
                        access, healthcare budgets, and the sustainability of pharmaceutical supply chains. Because
                        medicines are highly price-sensitive and often purchased in large volumes, even small
                        percentage differences in unit price can translate into substantial financial impact.
                    </p>
                    <p>
                        Historical award prices are not merely records of the past — they are signals of market
                        behavior. They reveal which price levels have been accepted by procurement authorities,
                        how competition has evolved over time, and whether certain products are trending toward
                        lower or higher award prices. Product-level and country-level trends matter because the
                        same molecule may behave differently in Iraq, Saudi Arabia, Oman, or the GCC market.
                    </p>
                    <p>
                        Repeated tender programs such as <strong>KIMADIA</strong> (Iraq's central medical supply
                        procurement) or <strong>GCC/GHC</strong> Gulf health council tenders exhibit historical
                        patterns across annual cycles. Understanding these patterns allows suppliers to compete
                        more responsibly: neither underbidding destructively nor overbidding and losing share.
                        Price prediction, in this context, is not about guessing a number — it is about
                        interpreting market evidence to support a defensible, competitive bid.
                    </p>
                </div>
            </section>

            {{-- 4. Objectives --}}
            <section class="pubdoc-section" id="objectives">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 4</span>
                    <h2 class="pubdoc-section-title">System Objectives</h2>
                    <ul>
                        <li><strong>Organize historical tender data</strong> — consolidate fragmented Excel sources into a single structured repository.</li>
                        <li><strong>Standardize inconsistent Excel data</strong> — normalize product names, company names, countries, and tender references.</li>
                        <li><strong>Identify drugs, companies, countries, and tenders</strong> — build a canonical catalog that enables reliable comparison across years.</li>
                        <li><strong>Build clean bid records</strong> — transform raw rows into verified historical award evidence.</li>
                        <li><strong>Generate market statistics</strong> — compute averages, medians, trends, and last prices per product and market.</li>
                        <li><strong>Support price prediction</strong> — recommend bid prices using the most relevant historical evidence available.</li>
                        <li><strong>Provide AI strategic interpretation</strong> — explain results in business language for decision-makers.</li>
                        <li><strong>Help users understand competitiveness and risk</strong> — surface whether a proposed price is aggressive, moderate, or potentially uncompetitive.</li>
                    </ul>
                </div>
            </section>

            {{-- 5. Target Users --}}
            <section class="pubdoc-section" id="target-users">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 5</span>
                    <h2 class="pubdoc-section-title">Target Users</h2>
                    <p>TenderAI is designed for professionals involved in pharmaceutical bidding and market access:</p>
                    <div class="pubdoc-card-grid">
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Pharmaceutical Companies</p>
                            <p class="pubdoc-card-text">Organizations submitting bids in public and institutional medicine tenders.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Tender Departments</p>
                            <p class="pubdoc-card-text">Teams responsible for preparing, reviewing, and submitting tender responses.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Business Development</p>
                            <p class="pubdoc-card-text">Teams evaluating market entry and competitive positioning in target countries.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Pricing Analysts</p>
                            <p class="pubdoc-card-text">Specialists who model price sensitivity, discount scenarios, and margin impact.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Procurement Consultants</p>
                            <p class="pubdoc-card-text">Advisors supporting multiple clients with tender intelligence and benchmarking.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Decision Makers</p>
                            <p class="pubdoc-card-text">Managers and directors who approve final bid prices before submission.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 6. Workflow --}}
            <section class="pubdoc-section" id="workflow">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 6</span>
                    <h2 class="pubdoc-section-title">TenderAI Business Workflow</h2>
                    <p class="pubdoc-lead">
                        From raw historical spreadsheets to an actionable price recommendation, TenderAI follows
                        a structured pipeline that mirrors how tender professionals actually prepare for a bid.
                    </p>
                    <div class="pubdoc-flow">
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">1</span>
                            <div>
                                <p class="pubdoc-flow-name">Historical Data Upload</p>
                                <p class="pubdoc-flow-desc">Tender teams upload Excel files containing past award records, prices, winners, and quantities.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">2</span>
                            <div>
                                <p class="pubdoc-flow-name">Data Mapping</p>
                                <p class="pubdoc-flow-desc">Source columns are mapped to TenderAI fields so the system understands what each cell represents.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">3</span>
                            <div>
                                <p class="pubdoc-flow-name">Validation</p>
                                <p class="pubdoc-flow-desc">Rows are checked for completeness, valid prices, and acceptable country/market values.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">4</span>
                            <div>
                                <p class="pubdoc-flow-name">Standardization</p>
                                <p class="pubdoc-flow-desc">Product names, company names, and countries are normalized to canonical identities.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">5</span>
                            <div>
                                <p class="pubdoc-flow-name">Product Matching</p>
                                <p class="pubdoc-flow-desc">The system suggests matches and users review or correct them to protect data quality.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">6</span>
                            <div>
                                <p class="pubdoc-flow-name">Entity Creation</p>
                                <p class="pubdoc-flow-desc">Approved data becomes structured companies, drugs, tenders, and tender line items.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">7</span>
                            <div>
                                <p class="pubdoc-flow-name">Bid Record Generation</p>
                                <p class="pubdoc-flow-desc">Each verified award becomes a bid record — the evidentiary foundation for all statistics and predictions.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">8</span>
                            <div>
                                <p class="pubdoc-flow-name">Market Statistics</p>
                                <p class="pubdoc-flow-desc">Aggregated pricing metrics are calculated per product and market to reveal historical behavior.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">9</span>
                            <div>
                                <p class="pubdoc-flow-name">Tender Program Selection</p>
                                <p class="pubdoc-flow-desc">The user selects a tender program (e.g., KIMADIA or GCC), not a single historical row.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">10</span>
                            <div>
                                <p class="pubdoc-flow-name">Product Selection</p>
                                <p class="pubdoc-flow-desc">Only products historically present in the selected program are available for prediction.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">11</span>
                            <div>
                                <p class="pubdoc-flow-name">Price Recommendation</p>
                                <p class="pubdoc-flow-desc">The system calculates a recommended unit price using layered historical evidence.</p>
                            </div>
                        </div>
                        <div class="pubdoc-flow-step">
                            <span class="pubdoc-flow-num">12</span>
                            <div>
                                <p class="pubdoc-flow-name">AI Strategic Insight</p>
                                <p class="pubdoc-flow-desc">An AI-generated narrative explains competitiveness, risk, and strategic context for the recommendation.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 7. Data Upload --}}
            <section class="pubdoc-section" id="data-upload">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 7</span>
                    <h2 class="pubdoc-section-title">Data Upload and Preparation</h2>
                    <p>
                        The journey begins when tender teams upload Excel spreadsheets containing historical award
                        data. These files typically include product descriptions, awarded unit prices, winning
                        companies, tender references, countries, quantities, and award dates — but rarely in a
                        consistent format.
                    </p>
                    <p>
                        TenderAI guides users through <strong>column mapping</strong>, aligning source headers
                        with system fields. This step is essential because a column labeled "Item Description"
                        in one file may correspond to "Product Name" in another. After mapping, each row is
                        <strong>validated</strong> for required information: a recognizable product, a valid
                        price, and an identifiable country or market.
                    </p>
                    <p>
                        Data preparation is critical before any prediction can be trusted. Raw Excel rows contain
                        noise — missing countries, inconsistent spellings, duplicate entries, and formatting
                        artifacts. By converting raw rows into structured, validated data, TenderAI ensures that
                        downstream standardization, statistics, and recommendations are built on a solid
                        foundation rather than on fragile manual assumptions.
                    </p>
                </div>
            </section>

            {{-- 8. Standardization --}}
            <section class="pubdoc-section" id="standardization">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 8</span>
                    <h2 class="pubdoc-section-title">Data Standardization</h2>
                    <p>
                        Standardization is the process of making inconsistent source data comparable. Without it,
                        historical analysis across years and files is unreliable because the system cannot know
                        that two differently spelled entries refer to the same product or company.
                    </p>
                    <h3 class="pubdoc-subtitle">Country and Market Normalization</h3>
                    <p>Countries appear in tenders under many abbreviations and variants. TenderAI maps these to canonical market identities:</p>
                    <div class="pubdoc-table-wrap">
                        <table class="pubdoc-table">
                            <thead>
                                <tr><th>Source Variants</th><th>Standardized Market</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>KSA, Saudi, Saudi Arabia</td><td>Saudi Arabia</td></tr>
                                <tr><td>Oman, OM</td><td>Oman</td></tr>
                                <tr><td>Iraq, IRQ</td><td>Iraq</td></tr>
                                <tr><td>UAE, Kuwait, Qatar, Bahrain</td><td>Individual country markets</td></tr>
                                <tr><td>GCC, GHC, Gulf Health Council</td><td>GCC — a dedicated market entity</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h3 class="pubdoc-subtitle">Product and Company Normalization</h3>
                    <p>
                        Drug names can differ across sheets — variations in strength notation, dosage form
                        abbreviations, and brand vs. generic naming. Company names similarly vary between legal
                        entities, local distributors, and abbreviated forms. TenderAI uses matching algorithms
                        and <strong>alias tables</strong> to group these variants under a single product or
                        company identity, enabling meaningful historical comparison.
                    </p>
                    <div class="pubdoc-callout pubdoc-callout--info">
                        GCC is treated as a <strong>dedicated market</strong>, not mapped to Saudi Arabia or any
                        single country. This reflects the business reality of Gulf-wide procurement programs.
                    </div>
                </div>
            </section>

            {{-- 9. Product Matching --}}
            <section class="pubdoc-section" id="product-matching">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 9</span>
                    <h2 class="pubdoc-section-title">Product Matching and Approval</h2>
                    <p>
                        Automated matching is powerful but not infallible. TenderAI suggests product and company
                        matches with <strong>confidence scores</strong>, allowing users to focus review effort
                        on uncertain rows while quickly approving high-confidence matches in bulk.
                    </p>
                    <ul>
                        <li><strong>Suggested matches</strong> — the system proposes the most likely standardized product or company for each row.</li>
                        <li><strong>Confidence levels</strong> — each suggestion carries a score indicating match certainty.</li>
                        <li><strong>Bulk approval</strong> — high-confidence rows can be approved together, accelerating data preparation.</li>
                        <li><strong>Manual correction</strong> — users can search the catalog and assign the correct match when the suggestion is wrong.</li>
                    </ul>
                    <p>
                        This human-in-the-loop step protects prediction accuracy. A misidentified product would
                        cause statistics and recommendations to reference the wrong historical prices — potentially
                        leading to severely misleading bid guidance. By requiring review before materialization,
                        TenderAI balances automation efficiency with data integrity.
                    </p>
                </div>
            </section>

            {{-- 10. Materialization --}}
            <section class="pubdoc-section" id="materialization">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 10</span>
                    <h2 class="pubdoc-section-title">Materialization and Bid Records</h2>
                    <p>
                        After product matches are approved, TenderAI <strong>materializes</strong> the data —
                        converting validated import rows into real structured business entities. This includes
                        standardized companies, drugs, tender headers, tender line items, and most importantly,
                        <strong>bid records</strong>.
                    </p>
                    <p>
                        A bid record represents a single historical award: which product was purchased, at what
                        unit price, in what quantity, by which winning company, in which tender and market. Bid
                        records are the evidentiary foundation of the entire system. Every market statistic,
                        every price trend, and every recommendation ultimately traces back to these verified
                        award facts.
                    </p>
                    <p>
                        Without bid records, TenderAI would have nothing to analyze. With them, the platform can
                        answer questions such as: "What did KIMADIA historically pay for Acyclovir?" or "How have
                        GCC award prices for this molecule changed over the last three years?"
                    </p>
                </div>
            </section>

            {{-- 11. Market Statistics --}}
            <section class="pubdoc-section" id="market-statistics">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 11</span>
                    <h2 class="pubdoc-section-title">Market Statistics Engine</h2>
                    <p>
                        The market statistics engine aggregates bid records into pricing intelligence at the
                        <strong>drug × country</strong> and <strong>drug × GCC market</strong> levels. These
                        statistics power both analytical dashboards and the price recommendation engine.
                    </p>
                    <div class="pubdoc-table-wrap">
                        <table class="pubdoc-table">
                            <thead>
                                <tr><th>Metric</th><th>Business Meaning</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Weighted Average</strong></td>
                                    <td>Reflects volume-sensitive pricing — large-quantity awards influence the average more than small ones, giving a realistic picture of typical transaction prices.</td>
                                </tr>
                                <tr>
                                    <td><strong>Median Price</strong></td>
                                    <td>Reduces the effect of outlier awards (unusually high or low prices), providing a robust central estimate.</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Awarded Price</strong></td>
                                    <td>Captures the most recent market behavior, which is often the most relevant for upcoming bids.</td>
                                </tr>
                                <tr>
                                    <td><strong>Min / Max Prices</strong></td>
                                    <td>Define the historical price range, helping users understand how wide the competitive window has been.</td>
                                </tr>
                                <tr>
                                    <td><strong>Trend Direction</strong></td>
                                    <td>Indicates whether prices are rising, falling, or stable — critical for forward-looking bid strategy.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        All pricing statistics are calculated in <strong>USD</strong>, ensuring consistent
                        comparison across markets regardless of how source data was originally denominated.
                    </p>
                </div>
            </section>

            {{-- 12. Tender Program Logic --}}
            <section class="pubdoc-section" id="tender-program">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 12</span>
                    <h2 class="pubdoc-section-title">Tender Program Logic</h2>
                    <p class="pubdoc-lead">
                        One of TenderAI's most important business design decisions is that users select a
                        <strong>tender program</strong>, not an individual historical tender row.
                    </p>
                    <p>
                        In real pharmaceutical procurement, programs like <strong>KIMADIA</strong> run annually.
                        KIMADIA-2022, KIMADIA-2023, and KIMADIA-2024 are separate tender events, but they
                        belong to the same procurement program with shared behavioral patterns. Similarly,
                        <strong>GCC/GHC</strong> tenders represent a recurring Gulf-wide procurement context.
                    </p>
                    <p>
                        TenderAI groups these historical records under their program identity (e.g., "KIMADIA"
                        or "GCC"). When a user prepares a new bid, they select the program they are bidding
                        into — and the system analyzes <em>all</em> relevant historical records under that
                        program, not just one year's data.
                    </p>
                    <div class="pubdoc-callout pubdoc-callout--important">
                        <strong>Why this matters:</strong> Using only a single historical tender row provides
                        weak context — it may reflect an unusual year, a specific product mix, or atypical
                        competition. Grouping by program provides richer evidence, more stable statistics, and
                        better decision support. This mirrors how experienced tender professionals think: they
                        consider the full history of a program, not one isolated event.
                    </div>
                </div>
            </section>

            {{-- 13. Product Filtering --}}
            <section class="pubdoc-section" id="product-filtering">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 13</span>
                    <h2 class="pubdoc-section-title">Product Filtering by Tender Program</h2>
                    <p>
                        After selecting a tender program, the product dropdown shows only drugs that have
                        historically appeared in that program. This is a deliberate business constraint that
                        prevents users from generating predictions for products irrelevant to the selected
                        tender context.
                    </p>
                    <p>
                        For example, if a user selects the KIMADIA program, only products with historical
                        KIMADIA award records will appear. A product that has only been awarded in GCC tenders
                        will not be listed under KIMADIA — because predicting its KIMADIA price without
                        KIMADIA-specific evidence would be misleading.
                    </p>
                    <p>
                        This filtering improves business logic, reduces user error, and ensures that the
                        recommendation engine works with contextually appropriate product-program combinations.
                    </p>
                </div>
            </section>

            {{-- 14. Price Methodology --}}
            <section class="pubdoc-section" id="price-methodology">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 14</span>
                    <h2 class="pubdoc-section-title">Price Recommendation Methodology</h2>
                    <p>
                        TenderAI calculates recommended prices using a <strong>layered fallback</strong>
                        approach. The system always seeks the most specific, most relevant evidence first,
                        and only broadens its scope when narrower data is insufficient.
                    </p>
                    <div class="pubdoc-tier">
                        <span class="pubdoc-tier-rank">1</span>
                        <div class="pubdoc-tier-body">
                            <h4>Tender Program Data (Primary)</h4>
                            <p>Historical prices for the selected product within the selected tender program — the strongest and most contextually relevant evidence.</p>
                        </div>
                    </div>
                    <div class="pubdoc-tier">
                        <span class="pubdoc-tier-rank">2</span>
                        <div class="pubdoc-tier-body">
                            <h4>Country / Market Data (Secondary)</h4>
                            <p>If program-specific history is limited, the system uses the product's award history in the same country or market.</p>
                        </div>
                    </div>
                    <div class="pubdoc-tier">
                        <span class="pubdoc-tier-rank">3</span>
                        <div class="pubdoc-tier-body">
                            <h4>Region Data (Third)</h4>
                            <p>Regional pricing evidence is used when country-level data is insufficient, drawing on broader geographic patterns.</p>
                        </div>
                    </div>
                    <div class="pubdoc-tier">
                        <span class="pubdoc-tier-rank">4</span>
                        <div class="pubdoc-tier-body">
                            <h4>Global Data (Fourth)</h4>
                            <p>As a last resort, global product history provides a baseline — but the system clearly indicates when this less-specific evidence is being used.</p>
                        </div>
                    </div>
                    <p>
                        This hierarchy ensures TenderAI does not blindly default to global averages when
                        program-specific data exists. It also provides transparency about evidence quality,
                        helping users assess confidence and risk in the recommendation.
                    </p>
                </div>
            </section>

            {{-- 15. Quantity and Discount --}}
            <section class="pubdoc-section" id="quantity-discount">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 15</span>
                    <h2 class="pubdoc-section-title">Quantity and Discount Handling</h2>
                    <p>
                        Tender quantity is a significant pricing variable. Large-volume awards often justify
                        lower unit prices because suppliers benefit from economies of scale, guaranteed volume,
                        and reduced per-unit logistics costs. TenderAI allows users to specify the quantity they
                        intend to bid for, which informs the competitiveness assessment.
                    </p>
                    <p>
                        Users can also enter a <strong>proposed discount percentage</strong> relative to the
                        recommended price. The system evaluates whether the discount is aggressive (high risk
                        of unprofitability), moderate (competitive but sustainable), or conservative (lower
                        risk of losing on price but potentially less competitive).
                    </p>
                    <p>
                        Together, quantity and discount inputs transform a statistical recommendation into a
                        scenario-specific business decision that reflects the user's actual bidding strategy.
                    </p>
                </div>
            </section>

            {{-- 16. AI Insights --}}
            <section class="pubdoc-section" id="ai-insights">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 16</span>
                    <h2 class="pubdoc-section-title">AI Strategic Insights</h2>
                    <p>
                        A critical design principle of TenderAI is the separation between <strong>calculation</strong>
                        and <strong>interpretation</strong>. Price recommendations are computed deterministically
                        from market statistics and business rules. OpenAI is <em>not</em> used to generate or
                        alter the recommended price.
                    </p>
                    <p>Instead, AI serves as a strategic interpretation layer that provides:</p>
                    <ul>
                        <li><strong>Market overview</strong> — contextual summary of the pricing landscape for the selected product and program.</li>
                        <li><strong>Competition analysis</strong> — commentary on competitive dynamics implied by historical winners and price levels.</li>
                        <li><strong>Discount review</strong> — assessment of whether the proposed discount is aggressive, moderate, or risky.</li>
                        <li><strong>Risk commentary</strong> — flags when evidence is thin, trends are unfavorable, or the bid may be uncompetitive.</li>
                        <li><strong>Strategic recommendation</strong> — actionable narrative guidance for the tender team.</li>
                    </ul>
                    <div class="pubdoc-callout pubdoc-callout--important">
                        <strong>Key principle:</strong> The AI explains the result — it does not replace the
                        calculation engine. This responsible use of AI ensures that pricing remains
                        evidence-based while benefiting from natural-language interpretation for
                        decision-makers.
                    </div>
                </div>
            </section>

            {{-- 17. GCC --}}
            <section class="pubdoc-section" id="gcc-market">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 17</span>
                    <h2 class="pubdoc-section-title">GCC as a Dedicated Market</h2>
                    <p>
                        Gulf Cooperation Council (GCC) and Gulf Health Council (GHC) tenders represent a
                        distinct procurement context that spans multiple countries. TenderAI treats GCC as a
                        <strong>dedicated market entity</strong> — not mapped to Saudi Arabia, the UAE, or
                        any single nation.
                    </p>
                    <p>
                        This design reflects business reality: GCC tenders have their own award history, their
                        own competitive dynamics, and their own pricing patterns. Collapsing GCC data into a
                        single country would distort statistics and produce misleading recommendations.
                    </p>
                    <p>
                        GCC bid records and pricing statistics are maintained separately, enabling accurate
                        program-level analysis and prediction for Gulf-wide procurement bids.
                    </p>
                </div>
            </section>

            {{-- 18. Example --}}
            <section class="pubdoc-section" id="example">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 18</span>
                    <h2 class="pubdoc-section-title">Example Scenario</h2>
                    <div class="pubdoc-scenario">
                        <p class="pubdoc-scenario-title">Worked Example — KIMADIA Bid for Acyclovir</p>
                        <p>
                            A pharmaceutical company plans to bid for <strong>Acyclovir</strong> in the
                            upcoming <strong>KIMADIA</strong> tender. The pricing analyst opens TenderAI and
                            configures the recommendation:
                        </p>
                        <ul>
                            <li><strong>Tender Program:</strong> KIMADIA</li>
                            <li><strong>Product:</strong> Acyclovir</li>
                            <li><strong>Quantity:</strong> 500,000 units</li>
                            <li><strong>Proposed Discount:</strong> 8% below recommended price</li>
                        </ul>
                    </div>
                    <p>The system then performs the following analysis:</p>
                    <ol>
                        <li>Retrieves all historical KIMADIA award records for Acyclovir across prior years (2022, 2023, 2024, etc.).</li>
                        <li>Computes program-level statistics — weighted average, median, last price, and trend direction.</li>
                        <li>If KIMADIA-specific data is sparse, falls back to Iraq market statistics for the same product.</li>
                        <li>Calculates a recommended unit price in USD based on the most relevant evidence tier.</li>
                        <li>Applies the 8% discount and evaluates whether the resulting price is competitive or risky.</li>
                        <li>Assigns a risk level based on evidence quality, trend direction, and discount aggressiveness.</li>
                        <li>Generates an AI strategic commentary explaining market context, competitive implications, and bidding advice.</li>
                    </ol>
                    <p>
                        The analyst reviews the recommendation, adjusts the discount if needed, and presents
                        the evidence-backed price to the decision-maker for final approval before tender
                        submission.
                    </p>
                </div>
            </section>

            {{-- 19. Benefits --}}
            <section class="pubdoc-section" id="benefits">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 19</span>
                    <h2 class="pubdoc-section-title">Benefits of TenderAI</h2>
                    <div class="pubdoc-card-grid">
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Faster Analysis</p>
                            <p class="pubdoc-card-text">Reduces weeks of manual spreadsheet work to a structured, repeatable pipeline.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Better Price Visibility</p>
                            <p class="pubdoc-card-text">Clear statistics per product, country, and tender program.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Consistent Decisions</p>
                            <p class="pubdoc-card-text">Standardized methodology replaces ad-hoc individual judgment.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Risk Awareness</p>
                            <p class="pubdoc-card-text">Highlights when evidence is thin or discounts are aggressive.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Evidence-Based Bidding</p>
                            <p class="pubdoc-card-text">Every recommendation traces back to verified historical awards.</p>
                        </div>
                        <div class="pubdoc-card">
                            <p class="pubdoc-card-title">Knowledge Retention</p>
                            <p class="pubdoc-card-text">Builds a structured knowledge base that grows with each upload.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 20. Limitations --}}
            <section class="pubdoc-section" id="limitations">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 20</span>
                    <h2 class="pubdoc-section-title">Limitations</h2>
                    <p>TenderAI is an honest decision-support tool. Its limitations should be understood:</p>
                    <ul>
                        <li><strong>Data quality dependency</strong> — predictions are only as reliable as the historical data uploaded and approved.</li>
                        <li><strong>Mapping accuracy</strong> — incorrect product or company matching will distort statistics and recommendations.</li>
                        <li><strong>Market disruptions</strong> — unusual regulatory changes, supply shocks, or new competitors may not be reflected in historical data.</li>
                        <li><strong>No guarantee of winning</strong> — the system supports decision-making but cannot account for all competitive factors in a live tender.</li>
                        <li><strong>Human expertise remains essential</strong> — TenderAI augments professional judgment; it does not replace it.</li>
                    </ul>
                </div>
            </section>

            {{-- 21. Graduation --}}
            <section class="pubdoc-section" id="graduation">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 21</span>
                    <h2 class="pubdoc-section-title">Graduation Project Relevance</h2>
                    <p>
                        TenderAI is well-suited as a graduation project because it addresses a real-world
                        industrial problem at the intersection of multiple disciplines:
                    </p>
                    <ul>
                        <li><strong>Industrial engineering</strong> — optimizing decision processes and reducing manual workflow inefficiency.</li>
                        <li><strong>Data analysis</strong> — transforming unstructured operational data into structured, queryable intelligence.</li>
                        <li><strong>Business intelligence</strong> — market statistics, trend analysis, and competitive benchmarking.</li>
                        <li><strong>Software engineering</strong> — full-stack web application with data pipelines, background processing, and user interfaces.</li>
                        <li><strong>Responsible AI</strong> — using artificial intelligence as an interpretation layer rather than a black-box decision-maker.</li>
                    </ul>
                    <p>
                        The project demonstrates how operational data from a specific industry — pharmaceutical
                        procurement — can be systematically collected, standardized, analyzed, and transformed
                        into actionable decision-support intelligence. It moves from problem identification
                        through methodology design to a working deployed system with measurable business value.
                    </p>
                </div>
            </section>

            {{-- 22. Technical Overview --}}
            <section class="pubdoc-section" id="technical">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 22</span>
                    <h2 class="pubdoc-section-title">Technical Overview</h2>
                    <p>
                        While TenderAI's primary value is business and decision-support oriented, it is
                        implemented as a modern web application with the following technical foundations:
                    </p>
                    <ul>
                        <li><strong>Laravel</strong> — PHP application framework handling routing, authentication, data processing, and business logic.</li>
                        <li><strong>MySQL</strong> — relational database storing imports, entities, bid records, statistics, and predictions.</li>
                        <li><strong>Vite</strong> — frontend asset compilation for styles and interactive components.</li>
                        <li><strong>Queue processing</strong> — background jobs for standardization and materialization of large datasets.</li>
                        <li><strong>Statistics engine</strong> — dedicated calculation layer for market pricing aggregations.</li>
                        <li><strong>OpenAI insight layer</strong> — generates strategic narratives on top of deterministic price calculations.</li>
                        <li><strong>Secure authenticated dashboard</strong> — internal tools for upload, review, prediction, and administration.</li>
                        <li><strong>Public documentation</strong> — this page, accessible without login for reference and academic reporting.</li>
                    </ul>
                    <div class="pubdoc-callout pubdoc-callout--neutral">
                        Technical implementation details for operators and developers are available in the
                        authenticated internal documentation at <code>/internal/documentation</code> (login required).
                    </div>
                </div>
            </section>

            {{-- 23. Production Status --}}
            <section class="pubdoc-section" id="production-status">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 23</span>
                    <h2 class="pubdoc-section-title">Current Production Status</h2>
                    <p>TenderAI is deployed and operational as an online system. Current public-safe status indicators:</p>
                    <div class="pubdoc-status-row">
                        <span class="pubdoc-badge pubdoc-badge--success">Deployed Online</span>
                        <span class="pubdoc-badge pubdoc-badge--primary">Tender Group Flow Enabled</span>
                        <span class="pubdoc-badge pubdoc-badge--success">GCC Market Supported</span>
                        <span class="pubdoc-badge pubdoc-badge--primary">Bid Records Available</span>
                        <span class="pubdoc-badge pubdoc-badge--primary">Pricing Statistics Available</span>
                    </div>
                    <p style="margin-top:1rem;">
                        The system processes historical tender data, generates market statistics, and supports
                        AI-assisted price recommendations through the authenticated dashboard. Users can log in
                        to access the full operational platform.
                    </p>
                </div>
            </section>

            {{-- 24. Conclusion --}}
            <section class="pubdoc-section" id="conclusion">
                <div class="pubdoc-section-card">
                    <span class="pubdoc-section-num">Section 24</span>
                    <h2 class="pubdoc-section-title">Conclusion</h2>
                    <p class="pubdoc-lead">
                        Pharmaceutical tender pricing has long depended on manual analysis, fragmented
                        spreadsheets, and individual experience. TenderAI represents a structured alternative:
                        a system that organizes historical evidence, standardizes inconsistent data, generates
                        meaningful market statistics, and supports price recommendations with transparent
                        methodology and AI-powered strategic interpretation.
                    </p>
                    <p>
                        By grouping tender programs rather than isolated records, filtering products by program
                        context, and using layered evidence fallback, TenderAI mirrors how experienced
                        professionals think about bidding — while making that thinking faster, more consistent,
                        and more accessible across the organization.
                    </p>
                    <p>
                        TenderAI helps pharmaceutical companies move from manual tender pricing to structured,
                        evidence-based, and AI-supported decision-making — reducing risk, improving competitiveness,
                        and building a lasting knowledge base for future procurement cycles.
                    </p>
                </div>
            </section>

        </div>
    </div>

    <footer class="pubdoc-footer">
        <p>
            &copy; {{ date('Y') }} TenderAI — Pharmaceutical Tender Intelligence &amp; Price Prediction System.
            <br>
            <a href="{{ route('landing') }}">Return to Home</a> &middot;
            <a href="{{ route('login') }}">Login to Dashboard</a>
        </p>
    </footer>

</div>
@endsection
