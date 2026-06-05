@php
    $navGroups = [
        'Overview' => [
            ['id' => 'why-tenderai-exists', 'label' => 'Why TenderAI Exists'],
            ['id' => 'executive-summary', 'label' => 'Introduction'],
            ['id' => 'problem-statement', 'label' => 'Problem Statement'],
            ['id' => 'objectives', 'label' => 'Objectives'],
            ['id' => 'target-users', 'label' => 'Target Users'],
        ],
        'Business Intelligence' => [
            ['id' => 'importance', 'label' => 'Tender Analysis'],
            ['id' => 'workflow', 'label' => 'System Workflow'],
            ['id' => 'system-flow', 'label' => 'Pipeline Diagram'],
            ['id' => 'data-upload', 'label' => 'Data Upload'],
            ['id' => 'standardization', 'label' => 'Standardization'],
            ['id' => 'product-matching', 'label' => 'Product Matching'],
            ['id' => 'materialization', 'label' => 'Bid Records'],
            ['id' => 'market-statistics', 'label' => 'Market Statistics'],
        ],
        'Prediction Engine' => [
            ['id' => 'tender-program', 'label' => 'Tender Programs'],
            ['id' => 'product-filtering', 'label' => 'Product Filtering'],
            ['id' => 'price-methodology', 'label' => 'Price Recommendation'],
            ['id' => 'quantity-discount', 'label' => 'Quantity & Discount'],
            ['id' => 'ai-insights', 'label' => 'AI Insights'],
            ['id' => 'gcc-market', 'label' => 'GCC Market'],
            ['id' => 'example', 'label' => 'Example Scenario'],
        ],
        'User Guide' => [
            ['id' => 'user-guide', 'label' => 'How to Use TenderAI'],
        ],
        'Results & Report' => [
            ['id' => 'benefits', 'label' => 'Benefits'],
            ['id' => 'limitations', 'label' => 'Limitations'],
            ['id' => 'graduation', 'label' => 'Graduation Relevance'],
            ['id' => 'production-status', 'label' => 'Production Status'],
            ['id' => 'conclusion', 'label' => 'Conclusion'],
        ],
        'Technical Overview' => [
            ['id' => 'technical', 'label' => 'Architecture'],
        ],
    ];
@endphp

<nav class="pubdoc-sidebar" id="pubdoc-sidebar" aria-label="Documentation sections">
    <div class="pubdoc-sidebar-inner">
        <p class="pubdoc-sidebar-heading">On this page</p>
        @foreach ($navGroups as $group => $links)
            <p class="pubdoc-sidebar-label">{{ $group }}</p>
            @foreach ($links as $link)
                <a href="#{{ $link['id'] }}" class="pubdoc-sidebar-link" data-section="{{ $link['id'] }}">{{ $link['label'] }}</a>
            @endforeach
        @endforeach
    </div>
</nav>
