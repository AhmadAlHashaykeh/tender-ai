{{-- Sections 7–24: preserved content with improved structure --}}

<article class="pubdoc-block" id="data-upload">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Data Processing</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="upload" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Data Upload and Preparation</h2>
        </div>
        <p class="pubdoc-block-summary">Converting raw Excel spreadsheets into structured, validated data — the foundation for all downstream analysis.</p>
    </header>
    <div class="pubdoc-panel">
        <p>The journey begins when tender teams upload Excel spreadsheets containing historical award data. These files typically include product descriptions, awarded unit prices, winning companies, tender references, countries, quantities, and award dates — but rarely in a consistent format.</p>
        <p>TenderAI guides users through <strong>column mapping</strong>, aligning source headers with system fields. After mapping, each row is <strong>validated</strong> for required information: a recognizable product, a valid price, and an identifiable country or market.</p>
        <p>Data preparation is critical before any prediction can be trusted. Raw Excel rows contain noise — missing countries, inconsistent spellings, duplicate entries, and formatting artifacts. By converting raw rows into structured, validated data, TenderAI ensures downstream standardization, statistics, and recommendations are built on a solid foundation.</p>
    </div>
</article>

<article class="pubdoc-block" id="standardization">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Data Processing</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="shuffle" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Data Standardization</h2>
        </div>
        <p class="pubdoc-block-summary">Making inconsistent source data comparable across years, files, and markets.</p>
    </header>
    <div class="pubdoc-panel">
        <p>Standardization is the process of making inconsistent source data comparable. Without it, historical analysis across years and files is unreliable.</p>
        <h3 class="pubdoc-subtitle">Country and Market Normalization</h3>
        <div class="pubdoc-table-wrap">
            <table class="pubdoc-table">
                <thead><tr><th>Source Variants</th><th>Standardized Market</th></tr></thead>
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
        <p>Drug names can differ across sheets — variations in strength notation, dosage form abbreviations, and brand vs. generic naming. TenderAI uses matching algorithms and <strong>alias tables</strong> to group variants under a single product or company identity.</p>
        <div class="pubdoc-callout pubdoc-callout--info">GCC is treated as a <strong>dedicated market</strong>, not mapped to Saudi Arabia or any single country.</div>
    </div>
</article>

<article class="pubdoc-block" id="product-matching">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Data Processing</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="search" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Product Matching and Approval</h2>
        </div>
        <p class="pubdoc-block-summary">Human-reviewed matching protects prediction accuracy while enabling bulk approval of high-confidence rows.</p>
    </header>
    <div class="pubdoc-card-grid">
        <div class="pubdoc-card"><p class="pubdoc-card-title">Suggested Matches</p><p class="pubdoc-card-text">System proposes the most likely standardized product or company.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Confidence Levels</p><p class="pubdoc-card-text">Each suggestion carries a score indicating match certainty.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Bulk Approval</p><p class="pubdoc-card-text">High-confidence rows approved together for speed.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Manual Correction</p><p class="pubdoc-card-text">Search catalog and assign correct match when needed.</p></div>
    </div>
    <div class="pubdoc-panel">
        <p>This human-in-the-loop step protects prediction accuracy. A misidentified product would cause statistics and recommendations to reference wrong historical prices. By requiring review before materialization, TenderAI balances automation efficiency with data integrity.</p>
    </div>
</article>

<article class="pubdoc-block" id="materialization">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Data Processing</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="layers" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Materialization and Bid Records</h2>
        </div>
        <p class="pubdoc-block-summary">Approved data becomes structured business entities — bid records are the evidentiary foundation of all predictions.</p>
    </header>
    <div class="pubdoc-panel">
        <p>After product matches are approved, TenderAI <strong>materializes</strong> the data — converting validated import rows into standardized companies, drugs, tender headers, tender line items, and <strong>bid records</strong>.</p>
        <p>A bid record represents a single historical award: which product was purchased, at what unit price, in what quantity, by which winning company, in which tender and market. Every market statistic, price trend, and recommendation traces back to these verified award facts.</p>
        <p>Without bid records, TenderAI would have nothing to analyze. With them, the platform can answer: "What did KIMADIA historically pay for Acyclovir?" or "How have GCC award prices changed over three years?"</p>
    </div>
</article>

<article class="pubdoc-block" id="market-statistics">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Market Intelligence</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="bar-chart-2" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Market Statistics Engine</h2>
        </div>
        <p class="pubdoc-block-summary">Aggregated pricing intelligence at drug × country and drug × GCC market levels.</p>
    </header>
    <div class="pubdoc-metrics">
        <div class="pubdoc-metric"><p class="pubdoc-metric-name">Weighted Average</p><p class="pubdoc-metric-desc">Volume-sensitive typical transaction price</p></div>
        <div class="pubdoc-metric"><p class="pubdoc-metric-name">Median Price</p><p class="pubdoc-metric-desc">Robust central estimate, outlier-resistant</p></div>
        <div class="pubdoc-metric"><p class="pubdoc-metric-name">Last Awarded Price</p><p class="pubdoc-metric-desc">Most recent market behavior</p></div>
        <div class="pubdoc-metric"><p class="pubdoc-metric-name">Min / Max Range</p><p class="pubdoc-metric-desc">Historical competitive window</p></div>
        <div class="pubdoc-metric"><p class="pubdoc-metric-name">Trend Direction</p><p class="pubdoc-metric-desc">Rising, falling, or stable market</p></div>
    </div>
    <div class="pubdoc-panel">
        <p>All pricing statistics are calculated in <strong>USD</strong>, ensuring consistent comparison across markets regardless of how source data was originally denominated.</p>
    </div>
</article>

<article class="pubdoc-block" id="tender-program">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Decision Support Model</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="folder-tree" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Tender Program Logic</h2>
        </div>
        <p class="pubdoc-block-summary">Users select a tender program — not an individual historical tender row. KIMADIA-2022, KIMADIA-2023, and KIMADIA-2024 are grouped under KIMADIA.</p>
    </header>
    <div class="pubdoc-panel">
        <p>In real pharmaceutical procurement, programs like <strong>KIMADIA</strong> run annually. Separate tender events belong to the same procurement program with shared behavioral patterns. Similarly, <strong>GCC/GHC</strong> tenders represent a recurring Gulf-wide procurement context.</p>
        <p>TenderAI groups historical records under their program identity. When preparing a new bid, the user selects the program — and the system analyzes <em>all</em> relevant historical records under that program.</p>
        <div class="pubdoc-callout pubdoc-callout--important"><strong>Why this matters:</strong> Using only a single historical tender row provides weak context. Grouping by program provides richer evidence, more stable statistics, and better decision support.</div>
    </div>
</article>

<article class="pubdoc-block" id="product-filtering">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Decision Support Model</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="filter" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Product Filtering by Tender Program</h2>
        </div>
        <p class="pubdoc-block-summary">The drug dropdown shows only products that historically appeared in the selected tender program.</p>
    </header>
    <div class="pubdoc-panel">
        <p>After selecting a tender program, the product dropdown shows only drugs historically present in that program. This prevents predictions for products irrelevant to the selected tender context.</p>
        <p>For example, selecting KIMADIA shows only products with historical KIMADIA award records. A product awarded only in GCC tenders will not appear under KIMADIA — because predicting without KIMADIA-specific evidence would be misleading.</p>
    </div>
</article>

<article class="pubdoc-block" id="price-methodology">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Methodology</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="calculator" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Price Recommendation Methodology</h2>
        </div>
        <p class="pubdoc-block-summary">Layered fallback — the system seeks the most specific, most relevant evidence first.</p>
    </header>
    <div class="pubdoc-tier"><span class="pubdoc-tier-rank">1</span><div class="pubdoc-tier-body"><h4>Tender Program Data (Primary)</h4><p>Historical prices for the selected product within the selected tender program.</p></div></div>
    <div class="pubdoc-tier"><span class="pubdoc-tier-rank">2</span><div class="pubdoc-tier-body"><h4>Country / Market Data (Secondary)</h4><p>Product award history in the same country or market when program data is limited.</p></div></div>
    <div class="pubdoc-tier"><span class="pubdoc-tier-rank">3</span><div class="pubdoc-tier-body"><h4>Region Data (Third)</h4><p>Regional pricing evidence when country-level data is insufficient.</p></div></div>
    <div class="pubdoc-tier"><span class="pubdoc-tier-rank">4</span><div class="pubdoc-tier-body"><h4>Global Data (Fourth)</h4><p>Global product history as last resort — with clear indication of less-specific evidence.</p></div></div>
    <div class="pubdoc-panel">
        <p>This hierarchy ensures TenderAI does not blindly default to global averages when program-specific data exists, providing transparency about evidence quality and risk.</p>
    </div>
</article>

<article class="pubdoc-block" id="quantity-discount">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Decision Support Model</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="percent" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Quantity and Discount Handling</h2>
        </div>
        <p class="pubdoc-block-summary">Quantity and discount inputs transform statistical recommendations into scenario-specific business decisions.</p>
    </header>
    <div class="pubdoc-panel">
        <p>Tender quantity is a significant pricing variable. Large-volume awards often justify lower unit prices through economies of scale. Users specify intended bid quantity, which informs competitiveness assessment.</p>
        <p>Users can enter a <strong>proposed discount percentage</strong> relative to the recommended price. The system evaluates whether the discount is aggressive, moderate, or conservative.</p>
    </div>
</article>

<article class="pubdoc-block" id="ai-insights">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Decision Support Model</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="sparkles" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">AI Strategic Insights</h2>
        </div>
        <p class="pubdoc-block-summary">AI explains the result — it does not replace the calculation engine.</p>
    </header>
    <div class="pubdoc-card-grid">
        <div class="pubdoc-card"><p class="pubdoc-card-title">Market Overview</p><p class="pubdoc-card-text">Contextual summary of the pricing landscape.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Competition Analysis</p><p class="pubdoc-card-text">Commentary on competitive dynamics from historical winners.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Discount Review</p><p class="pubdoc-card-text">Assessment of discount aggressiveness and risk.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Strategic Recommendation</p><p class="pubdoc-card-text">Actionable narrative guidance for the tender team.</p></div>
    </div>
    <div class="pubdoc-panel">
        <p>Price recommendations are computed deterministically from market statistics and business rules. OpenAI is <em>not</em> used to generate or alter the recommended price. AI serves as interpretation: market overview, competition analysis, discount review, risk commentary, and strategic recommendation.</p>
        <div class="pubdoc-callout pubdoc-callout--important"><strong>Key principle:</strong> The AI explains the result — it does not replace the calculation engine.</div>
    </div>
</article>

<article class="pubdoc-block" id="gcc-market">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Market Context</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="globe-2" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">GCC as a Dedicated Market</h2>
        </div>
        <p class="pubdoc-block-summary">GCC/GHC tenders are a distinct procurement context — not mapped to any single country.</p>
    </header>
    <div class="pubdoc-panel">
        <p>Gulf Cooperation Council (GCC) and Gulf Health Council (GHC) tenders represent a distinct procurement context spanning multiple countries. TenderAI treats GCC as a <strong>dedicated market entity</strong> — not mapped to Saudi Arabia, the UAE, or any single nation.</p>
        <p>GCC bid records and pricing statistics are maintained separately, enabling accurate program-level analysis and prediction for Gulf-wide procurement bids.</p>
    </div>
</article>

<article class="pubdoc-block" id="example">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Results</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="clipboard-list" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Example Scenario</h2>
        </div>
        <p class="pubdoc-block-summary">Worked example — KIMADIA bid for Acyclovir with quantity and discount inputs.</p>
    </header>
    <div class="pubdoc-scenario">
        <p class="pubdoc-scenario-title">KIMADIA Bid for Acyclovir</p>
        <ul>
            <li><strong>Tender Program:</strong> KIMADIA</li>
            <li><strong>Product:</strong> Acyclovir</li>
            <li><strong>Quantity:</strong> 500,000 units</li>
            <li><strong>Proposed Discount:</strong> 8% below recommended price</li>
        </ul>
    </div>
    <div class="pubdoc-panel">
        <ol>
            <li>Retrieves all historical KIMADIA award records for Acyclovir across prior years.</li>
            <li>Computes program-level statistics — weighted average, median, last price, and trend.</li>
            <li>Falls back to Iraq market statistics if KIMADIA-specific data is sparse.</li>
            <li>Calculates recommended unit price in USD based on the most relevant evidence tier.</li>
            <li>Applies the 8% discount and evaluates competitiveness and risk.</li>
            <li>Generates AI strategic commentary on market context and bidding advice.</li>
        </ol>
    </div>
</article>

<article class="pubdoc-block" id="benefits">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Results</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="award" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Benefits of TenderAI</h2>
        </div>
    </header>
    <div class="pubdoc-card-grid">
        <div class="pubdoc-card"><p class="pubdoc-card-title">Faster Analysis</p><p class="pubdoc-card-text">Weeks of manual spreadsheet work becomes a structured pipeline.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Better Price Visibility</p><p class="pubdoc-card-text">Clear statistics per product, country, and tender program.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Consistent Decisions</p><p class="pubdoc-card-text">Standardized methodology replaces ad-hoc judgment.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Risk Awareness</p><p class="pubdoc-card-text">Highlights thin evidence and aggressive discounts.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Evidence-Based Bidding</p><p class="pubdoc-card-text">Every recommendation traces to verified historical awards.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Knowledge Retention</p><p class="pubdoc-card-text">Structured knowledge base growing with each upload.</p></div>
    </div>
</article>

<article class="pubdoc-block" id="limitations">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Results</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="info" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Limitations</h2>
        </div>
        <p class="pubdoc-block-summary">An honest decision-support tool — limitations that users should understand.</p>
    </header>
    <div class="pubdoc-panel">
        <ul>
            <li><strong>Data quality dependency</strong> — predictions depend on uploaded and approved historical data quality.</li>
            <li><strong>Mapping accuracy</strong> — incorrect product matching distorts statistics and recommendations.</li>
            <li><strong>Market disruptions</strong> — regulatory changes or supply shocks may not appear in historical data.</li>
            <li><strong>No guarantee of winning</strong> — supports decision-making but cannot account for all competitive factors.</li>
            <li><strong>Human expertise remains essential</strong> — TenderAI augments professional judgment; it does not replace it.</li>
        </ul>
    </div>
</article>

<article class="pubdoc-block" id="graduation">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Graduation Report</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="graduation-cap" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Graduation Project Relevance</h2>
        </div>
        <p class="pubdoc-block-summary">A real-world industrial problem at the intersection of engineering, data analysis, and responsible AI.</p>
    </header>
    <div class="pubdoc-panel">
        <ul>
            <li><strong>Industrial engineering</strong> — optimizing decision processes and reducing manual workflow inefficiency.</li>
            <li><strong>Data analysis</strong> — transforming unstructured operational data into structured intelligence.</li>
            <li><strong>Business intelligence</strong> — market statistics, trend analysis, and competitive benchmarking.</li>
            <li><strong>Software engineering</strong> — full-stack application with data pipelines and user interfaces.</li>
            <li><strong>Responsible AI</strong> — AI as interpretation layer, not black-box decision-maker.</li>
        </ul>
        <p>The project demonstrates how pharmaceutical procurement data can be systematically collected, standardized, analyzed, and transformed into actionable decision-support intelligence.</p>
    </div>
</article>

<article class="pubdoc-block" id="technical">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Technical Overview</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="cpu" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Technical Overview</h2>
        </div>
        <p class="pubdoc-block-summary">Brief technical foundations — business value remains the primary focus of this documentation.</p>
    </header>
    <div class="pubdoc-card-grid">
        <div class="pubdoc-card"><p class="pubdoc-card-title">Laravel</p><p class="pubdoc-card-text">Application framework for routing, auth, and business logic.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">MySQL</p><p class="pubdoc-card-text">Relational database for entities, statistics, and predictions.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">Data Processing</p><p class="pubdoc-card-text">Background jobs for standardization and materialization.</p></div>
        <div class="pubdoc-card"><p class="pubdoc-card-title">AI Insight Layer</p><p class="pubdoc-card-text">OpenAI narratives on top of deterministic calculations.</p></div>
    </div>
    <div class="pubdoc-callout pubdoc-callout--neutral">Operator and developer details are available in the authenticated internal documentation (login required).</div>
</article>

<article class="pubdoc-block" id="production-status">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Results</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="activity" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Current Production Status</h2>
        </div>
        <p class="pubdoc-block-summary">TenderAI is deployed and operational as an online system.</p>
    </header>
    <div class="pubdoc-status-row">
        <span class="pubdoc-badge pubdoc-badge--success">Deployed Online</span>
        <span class="pubdoc-badge pubdoc-badge--primary">Tender Group Flow Enabled</span>
        <span class="pubdoc-badge pubdoc-badge--success">GCC Market Supported</span>
        <span class="pubdoc-badge pubdoc-badge--primary">Bid Records Available</span>
        <span class="pubdoc-badge pubdoc-badge--primary">Pricing Statistics Available</span>
    </div>
    <div class="pubdoc-panel" style="margin-top:1rem;">
        <p>The system processes historical tender data, generates market statistics, and supports AI-assisted price recommendations through the authenticated dashboard.</p>
    </div>
</article>

<article class="pubdoc-block" id="conclusion">
    <header class="pubdoc-block-header">
        <span class="pubdoc-academic-label">Conclusion</span>
        <div class="pubdoc-block-title-row">
            <span class="pubdoc-block-icon"><i data-lucide="flag" style="width:1.25rem;height:1.25rem;"></i></span>
            <h2 class="pubdoc-block-title">Conclusion</h2>
        </div>
        <p class="pubdoc-block-summary">From manual tender pricing to structured, evidence-based, AI-supported decision-making.</p>
    </header>
    <div class="pubdoc-panel">
        <p>Pharmaceutical tender pricing has long depended on manual analysis, fragmented spreadsheets, and individual experience. TenderAI organizes historical evidence, standardizes inconsistent data, generates meaningful market statistics, and supports price recommendations with transparent methodology and AI-powered strategic interpretation.</p>
        <p>By grouping tender programs rather than isolated records, filtering products by program context, and using layered evidence fallback, TenderAI mirrors how experienced professionals think about bidding — while making that thinking faster, more consistent, and more accessible.</p>
        <p>TenderAI helps pharmaceutical companies move from manual tender pricing to structured, evidence-based, and AI-supported decision-making — reducing risk, improving competitiveness, and building a lasting knowledge base for future procurement cycles.</p>
    </div>
</article>
