@extends('layouts.app')

@section('title', 'Price Recommendation Result | TenderAI')

@section('content')
@php
    use App\Support\RecommendationCurrency;
    $trendAdjustment = $breakdown['trend_adjustment'] ?? null;
    $confidenceBreakdown = $breakdown['confidence_breakdown'] ?? null;
    $weightsUsed = $breakdown['weights_used'] ?? [];
    $basePrice = $breakdown['base_price'] ?? null;
    $trendAdjustedPrice = $breakdown['trend_adjusted_price'] ?? null;
    $marketCalculatedPrice = $prediction->market_calculated_price
        ?? $breakdown['market_calculated_price']
        ?? $prediction->backend_recommended_price;
    $discountPercentage = $prediction->discount_percentage ?? $breakdown['discount_percentage'] ?? null;
    $hasDiscountField = $discountPercentage !== null;
    $effectiveDiscount = $hasDiscountField ? (float) $discountPercentage : 0.0;
    $finalRecommendedPrice = $prediction->final_recommended_price
        ?? $prediction->recommended_price
        ?? $prediction->backend_recommended_price;
    $aiInsightsStatus = $aiInsightsStatus ?? ($prediction->ai_response_raw['insights_status'] ?? $prediction->ai_response_raw['narrative_status'] ?? null);
    $aiInsightsMessage = $aiInsightsMessage ?? ($prediction->ai_response_raw['message'] ?? null);
    $insightSections = \App\Services\AI\PredictionNarrativeService::INSIGHT_SECTIONS;
    $tenderSnapshot = $tenderContext ?? $contextSnapshot?->snapshot_data['tender_context'] ?? null;
    $statsRow = $contextSnapshot?->snapshot_data['selected_stats_row'] ?? null;
    $hasTenderDetails = $prediction->tender !== null || $tenderSnapshot !== null;
    $detailFallback = 'Detailed explanation is available for new recommendations only.';
    $trendLabels = [
        'rising' => 'Rising',
        'falling' => 'Falling',
        'stable' => 'Stable',
        'unknown' => 'Unknown',
    ];
@endphp
<main class="p-6 min-h-screen">
    <div class="space-y-7 max-w-4xl mx-auto">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">
                    <i data-lucide="sparkles" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Price Recommendation</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <x-prediction-status-badge :status="$prediction->status" />
                    </div>
                </div>
            </div>
            <p class="text-xs text-muted-foreground font-mono">{{ Str::limit($prediction->uuid, 13, '') }}</p>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="rounded-xl border border-border/40 bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
            Historical pricing data, market statistics, and all recommendation prices are stored and calculated in <strong class="text-foreground">USD</strong>. AI insights interpret those USD values only and do not change the calculated bid.
        </div>

        {{-- Prominent tender context banner --}}
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border-2 border-secondary/25 overflow-hidden shadow-sm">
            <div class="bg-gradient-to-r from-secondary/10 to-primary/8 px-6 py-3 border-b border-secondary/15">
                <p class="text-sm font-semibold text-foreground flex items-center gap-2">
                    <i data-lucide="file-text" class="w-4 h-4 text-secondary"></i>
                    Tender Recommendation Context
                </p>
            </div>
            <div class="p-6">
                @if($hasTenderDetails)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Tender Name</p>
                            <p class="font-semibold text-foreground">{{ $prediction->tender?->title ?? $tenderSnapshot['title'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Tender Number</p>
                            <p class="font-semibold text-foreground">{{ $prediction->tender?->tender_number ?? $tenderSnapshot['tender_number'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Country</p>
                            <p class="font-semibold text-foreground">{{ $prediction->tender?->country?->name ?? $tenderSnapshot['country_name'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Quantity</p>
                            <p class="font-semibold text-foreground">
                                @if($prediction->quantity)
                                    {{ number_format((float) $prediction->quantity) }} {{ $prediction->quantity_unit ?? 'units' }}
                                @else
                                    Not specified
                                @endif
                            </p>
                        </div>
                        @if($tenderSnapshot['region_name'] ?? $prediction->tender?->country?->region?->name ?? null)
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Region</p>
                            <p class="font-semibold text-foreground">{{ $tenderSnapshot['region_name'] ?? $prediction->tender?->country?->region?->name }}</p>
                        </div>
                        @endif
                    </div>
                    <p class="text-xs text-muted-foreground mt-4 pt-3 border-t border-border/30">This recommendation was generated for the tender above.</p>
                @else
                    <p class="text-sm text-muted-foreground">Tender details are unavailable for older recommendations.</p>
                @endif
            </div>
        </div>

        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border-2 border-primary/20 overflow-hidden shadow-sm">
            <div class="bg-gradient-to-r from-primary/8 to-secondary/8 px-6 py-4 border-b border-primary/10">
                <p class="text-sm font-semibold text-primary">Recommended Bid Summary</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Drug</p>
                        <p class="font-semibold text-foreground">{{ $prediction->standardizedDrug?->display_name }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Tender</p>
                        <p class="font-semibold text-foreground">{{ $prediction->tender?->title ?? $prediction->tender?->tender_number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Quantity</p>
                        <p class="font-semibold text-foreground">
                            @if($prediction->quantity)
                                {{ number_format((float) $prediction->quantity) }} {{ $prediction->quantity_unit ?? 'units' }}
                            @else
                                Not specified
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium">Market Data Scope</p>
                        <p class="font-semibold text-foreground">{{ $marketDataScope }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl bg-muted/20 border border-border/40 text-center">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Calculated Market Price</p>
                        <p class="text-3xl font-bold text-foreground">{{ RecommendationCurrency::format($marketCalculatedPrice) }}</p>
                        <p class="text-[10px] text-muted-foreground mt-1">per unit · before your discount</p>
                    </div>
                    <div class="p-4 rounded-xl bg-muted/20 border border-border/40 text-center">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">User Discount</p>
                        @if($hasDiscountField && $effectiveDiscount > 0)
                            <p class="text-3xl font-bold text-foreground">{{ number_format($effectiveDiscount, 2) }}%</p>
                            <p class="text-[10px] text-muted-foreground mt-1">bid discount applied</p>
                        @elseif($hasDiscountField)
                            <p class="text-3xl font-bold text-foreground">0%</p>
                            <p class="text-[10px] text-muted-foreground mt-1">no discount applied</p>
                        @else
                            <p class="text-lg font-semibold text-muted-foreground">—</p>
                            <p class="text-[10px] text-muted-foreground mt-1">No manual discount was applied.</p>
                        @endif
                    </div>
                    <div class="p-4 rounded-xl bg-gradient-to-br from-primary/8 to-secondary/8 border border-primary/15 text-center">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Final Recommended Bid Price</p>
                        <p class="text-3xl font-bold text-primary">{{ RecommendationCurrency::format($finalRecommendedPrice) }}</p>
                        <p class="text-[10px] text-muted-foreground mt-1">per unit · price to submit</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-muted/20 border border-border/40 text-center">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Data Confidence</p>
                        <p class="text-3xl font-bold text-foreground">{{ number_format((float) ($prediction->confidence_score ?? 0), 0) }}%</p>
                        <p class="text-[10px] text-muted-foreground mt-1">Based on historical award volume and data quality</p>

                        <details class="mt-3 text-left group">
                            <summary class="cursor-pointer text-[11px] font-semibold text-primary hover:text-primary/80 list-none flex items-center justify-center gap-1">
                                <i data-lucide="chevron-down" class="w-3 h-3 transition-transform group-open:rotate-180"></i>
                                Why this confidence score?
                            </summary>
                            <div class="mt-3 pt-3 border-t border-border/30 space-y-1.5">
                                @if($confidenceBreakdown && !empty($confidenceBreakdown['items']))
                                    @foreach($confidenceBreakdown['items'] as $item)
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-muted-foreground">{{ $item['label'] ?? 'Factor' }}</span>
                                            <span class="font-semibold text-foreground">+{{ (int) ($item['points'] ?? 0) }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex items-center justify-between text-[11px] pt-1.5 mt-1.5 border-t border-border/20 font-semibold">
                                        <span class="text-foreground">Total</span>
                                        <span class="text-primary">{{ (int) ($confidenceBreakdown['total'] ?? $prediction->confidence_score ?? 0) }}</span>
                                    </div>
                                @else
                                    <p class="text-[11px] text-muted-foreground text-center">{{ $detailFallback }}</p>
                                @endif
                            </div>
                        </details>
                    </div>
                    <div class="p-4 rounded-xl bg-muted/20 border border-border/40 text-center">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Risk Level</p>
                        <x-prediction-risk-badge :risk="$prediction->risk_level" class="text-xs" />
                        <p class="text-[10px] text-muted-foreground mt-2">Pricing and market uncertainty indicator</p>

                        <details class="mt-3 text-left group">
                            <summary class="cursor-pointer text-[11px] font-semibold text-primary hover:text-primary/80 list-none flex items-center justify-center gap-1">
                                <i data-lucide="chevron-down" class="w-3 h-3 transition-transform group-open:rotate-180"></i>
                                Why this risk level?
                            </summary>
                            <div class="mt-3 pt-3 border-t border-border/30 space-y-1.5">
                                @if($riskBreakdown && !empty($riskBreakdown['items']))
                                    @foreach($riskBreakdown['items'] as $item)
                                        <div class="flex items-center justify-between text-[11px] gap-2">
                                            <span class="text-muted-foreground text-left">{{ $item['label'] ?? 'Factor' }}</span>
                                            <span class="font-semibold text-foreground shrink-0">{{ ($item['points'] ?? 0) >= 0 ? '+' : '' }}{{ (int) ($item['points'] ?? 0) }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex items-center justify-between text-[11px] pt-1.5 mt-1.5 border-t border-border/20 font-semibold">
                                        <span class="text-foreground capitalize">{{ $riskBreakdown['level'] ?? $prediction->risk_level }} risk</span>
                                        <span class="text-muted-foreground">{{ (int) ($riskBreakdown['total_points'] ?? 0) }} risk points</span>
                                    </div>
                                @else
                                    <p class="text-[11px] text-muted-foreground text-center">{{ $detailFallback }}</p>
                                @endif
                            </div>
                        </details>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 text-xs text-muted-foreground pt-2 border-t border-border/30">
                    <span>Created by <strong class="text-foreground">{{ $prediction->user?->name ?? 'User' }}</strong></span>
                    <span>{{ $prediction->created_at?->format('M j, Y H:i') }}</span>
                    @if($prediction->stats_version)
                        <span>Market stats {{ $prediction->stats_version }}</span>
                    @endif
                    @if($prediction->calculation_model_version)
                        <span>Pricing model {{ $prediction->calculation_model_version }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if($prediction->status === 'failed')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $prediction->rationale }}</div>
        @elseif($prediction->rationale)
            <div class="bg-gradient-to-br from-primary/4 via-secondary/4 to-transparent border border-primary/12 rounded-2xl p-6 shadow-sm">
                <h3 class="font-semibold text-foreground mb-2">Pricing Rationale</h3>
                <p class="text-sm text-foreground/80">{{ $prediction->rationale }}</p>
            </div>
        @endif

        @if($prediction->status !== 'failed')
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-violet-200/60 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-violet-600"></i>
                        <h3 class="font-semibold text-foreground">AI Strategic Insights</h3>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">Interpretation and strategic guidance based on the calculated recommendation above.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if($prediction->ai_narrative_generated_at)
                        <span class="text-[11px] text-muted-foreground">Generated {{ $prediction->ai_narrative_generated_at->format('M j, Y H:i') }}</span>
                    @endif
                    @if($canRegenerateInsights ?? false)
                        <form method="POST" action="{{ route('ai.recommendations.regenerate-insights', $prediction) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-violet-200 bg-violet-50 text-[11px] font-semibold text-violet-700 hover:bg-violet-100 transition-colors">
                                <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                                Regenerate insights
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if($aiInsights)
                <div class="space-y-4">
                    @foreach($insightSections as $sectionKey => $sectionLabel)
                        @if(filled($aiInsights[$sectionKey] ?? null))
                            <div class="rounded-xl border border-violet-100 bg-violet-50/40 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-violet-700 mb-1.5">{{ $sectionLabel }}</p>
                                <p class="text-sm leading-6 text-foreground/85">{{ $aiInsights[$sectionKey] }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @elseif($aiInsightsStatus === 'skipped')
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ $aiInsightsMessage ?? 'AI insights were not generated due to current settings or data quality thresholds.' }}
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    AI insights are not available for this recommendation.
                </div>
            @endif
        </div>
        @endif

        @if($scenarios->isNotEmpty())
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 overflow-hidden shadow-sm">
            <div class="p-5 border-b border-border/40">
                <h3 class="font-semibold text-foreground">Bid Scenarios</h3>
                <p class="text-xs text-muted-foreground mt-1">Alternative pricing strategies derived from the same market data.</p>
                <p class="text-xs text-amber-700/90 mt-2 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                    Competitive Position scores are estimated positioning indicators based on available market data, not a guaranteed win probability.
                </p>
            </div>
            <div class="p-5 space-y-3">
                @foreach($scenarios as $scenario)
                    @php
                        $positionMeta = $scenario->metadata['competitive_position'] ?? null;
                        $positionBreakdown = $positionMeta['breakdown'] ?? null;
                        $positionScore = $positionMeta['score'] ?? $scenario->win_probability;
                    @endphp
                    <div class="p-4 rounded-xl border {{ $scenario->is_recommended ? 'border-2 border-primary bg-primary/4 relative' : 'border-border/50 bg-white/60' }}">
                        @if($scenario->is_recommended)
                            <div class="absolute -top-2.5 left-4 px-2 py-0.5 rounded-full bg-primary text-white text-[10px] font-semibold">Recommended</div>
                        @endif
                        <div class="flex items-start justify-between mb-2 gap-2 flex-wrap">
                            <p class="font-semibold text-sm">{{ ucfirst($scenario->scenario_name) }}</p>
                            <div class="flex items-center gap-2">
                                @if($positionScore)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200 font-semibold" title="Estimated competitive positioning based on data confidence and pricing strategy">
                                        ~{{ number_format((float) $positionScore, 0) }}% competitive position
                                    </span>
                                @endif
                                <x-prediction-risk-badge :risk="$scenario->risk_level" />
                            </div>
                        </div>
                        <p class="text-2xl font-bold {{ $scenario->is_recommended ? 'text-primary' : 'text-foreground' }}">
                            {{ RecommendationCurrency::format($scenario->recommended_price) }}
                            <span class="text-sm font-normal text-muted-foreground">/ unit</span>
                        </p>
                        @if($scenario->rationale)
                            <p class="text-xs text-muted-foreground mt-2">{{ $scenario->rationale }}</p>
                        @endif
                        @if($positionBreakdown && !empty($positionBreakdown['items']))
                            <details class="mt-3 group">
                                <summary class="cursor-pointer text-[11px] font-semibold text-primary hover:text-primary/80 list-none flex items-center gap-1">
                                    <i data-lucide="chevron-down" class="w-3 h-3 transition-transform group-open:rotate-180"></i>
                                    Why this competitive position?
                                </summary>
                                <div class="mt-2 pt-2 border-t border-border/30 space-y-1.5">
                                    @foreach($positionBreakdown['items'] as $item)
                                        <div class="flex items-center justify-between text-[11px] gap-2">
                                            <span class="text-muted-foreground">{{ $item['label'] ?? 'Factor' }}</span>
                                            <span class="font-semibold text-foreground shrink-0">{{ $item['value'] ?? '—' }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex items-center justify-between text-[11px] pt-1.5 mt-1.5 border-t border-border/20 font-semibold">
                                        <span class="text-foreground">Position score</span>
                                        <span class="text-primary">~{{ number_format((float) ($positionBreakdown['total'] ?? $positionScore), 0) }}%</span>
                                    </div>
                                </div>
                            </details>
                        @elseif(!$positionBreakdown)
                            <p class="text-[11px] text-muted-foreground mt-2 italic">{{ $detailFallback }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($calculation)
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 shadow-sm space-y-4">
            <div>
                <h3 class="font-semibold text-foreground mb-1">Price Calculation Details</h3>
                <p class="text-xs text-muted-foreground">How the recommended price was built from market statistics.</p>
            </div>

            <div class="rounded-xl border border-border/40 bg-muted/10 px-4 py-3 text-sm space-y-2">
                <p><span class="text-muted-foreground">Pricing method:</span> <strong class="text-foreground">Weighted blend of historical award prices</strong> (40% weighted average, 30% median, 20% last winning price, 10% average)</p>
                <p><span class="text-muted-foreground">Market data scope:</span> <strong class="text-foreground">{{ $marketDataScope }}</strong></p>
                @if($basePrice !== null)
                    <p><span class="text-muted-foreground">Base market price:</span> <strong class="text-foreground">{{ RecommendationCurrency::format($basePrice, 4) }}</strong></p>
                @endif
                @if($trendAdjustment)
                    @php $trendDir = $trendLabels[strtolower($trendAdjustment['direction'] ?? 'unknown')] ?? ucfirst($trendAdjustment['direction'] ?? 'Unknown'); @endphp
                    <p>
                        <span class="text-muted-foreground">Market adjustment (trend):</span>
                        <strong class="text-foreground">
                            {{ $trendDir }}
                            @if(($trendAdjustment['applied_pct'] ?? 0) > 0)
                                — {{ $trendAdjustment['applied_pct'] }}% applied
                            @else
                                — no adjustment
                            @endif
                        </strong>
                        @if($trendAdjustedPrice !== null)
                            <span class="text-muted-foreground">→ {{ RecommendationCurrency::format($trendAdjustedPrice, 4) }}</span>
                        @endif
                    </p>
                @endif
                @if($marketCalculatedPrice !== null)
                    <p>
                        <span class="text-muted-foreground">Calculated market price:</span>
                        <strong class="text-foreground">{{ RecommendationCurrency::format($marketCalculatedPrice, 4) }}</strong>
                        <span class="text-muted-foreground">— before bid discount</span>
                    </p>
                @endif
                @if($hasDiscountField)
                    <p>
                        <span class="text-muted-foreground">Bid discount:</span>
                        <strong class="text-foreground">{{ number_format($effectiveDiscount, 2) }}%</strong>
                        @if($finalRecommendedPrice !== null)
                            <span class="text-muted-foreground">→ final bid {{ RecommendationCurrency::format($finalRecommendedPrice, 4) }}</span>
                        @endif
                    </p>
                @else
                    <p><span class="text-muted-foreground">Bid discount:</span> <strong class="text-foreground">No manual discount was applied.</strong></p>
                @endif
            </div>

            <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                <div><dt class="text-muted-foreground text-xs">Weighted average</dt><dd class="font-semibold">{{ RecommendationCurrency::format($calculation->weighted_average_price, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Median price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($calculation->median_price, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Last winning price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($calculation->last_winning_price, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Average price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($calculation->average_price, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Market trend</dt><dd class="font-semibold">{{ $trendLabels[strtolower($calculation->price_trend ?? 'unknown')] ?? ucfirst($calculation->price_trend ?? '—') }} @if($calculation->trend_pct)({{ $calculation->trend_pct }}%)@endif</dd></div>
                <div><dt class="text-muted-foreground text-xs">Historical awards</dt><dd class="font-semibold">{{ $calculation->historical_award_count }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Outliers flagged</dt><dd class="font-semibold">{{ $calculation->outlier_count }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Market competition</dt><dd class="font-semibold capitalize">{{ $calculation->competition_level }}</dd></div>
            </dl>
        </div>
        @endif

        @if($statsRow)
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 shadow-sm">
            <h3 class="font-semibold text-foreground mb-3">Market Statistics</h3>
            <p class="text-xs text-muted-foreground mb-3">Summary of the pricing statistics used for this recommendation ({{ $marketDataScope }}).</p>
            <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                <div><dt class="text-muted-foreground text-xs">Historical awards</dt><dd class="font-semibold">{{ $statsRow['award_count'] ?? '—' }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Weighted average</dt><dd class="font-semibold">{{ RecommendationCurrency::format($statsRow['weighted_avg_unit_price'] ?? null, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Median price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($statsRow['median_unit_price'] ?? null, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Last winning price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($statsRow['last_unit_price'] ?? null, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Average price</dt><dd class="font-semibold">{{ RecommendationCurrency::format($statsRow['avg_unit_price'] ?? null, 4) }}</dd></div>
                <div><dt class="text-muted-foreground text-xs">Market trend</dt><dd class="font-semibold capitalize">{{ $statsRow['trend_direction'] ?? '—' }} @if(!empty($statsRow['trend_pct']))({{ $statsRow['trend_pct'] }}%)@endif</dd></div>
            </dl>
        </div>
        @endif

        @if($prediction->tender || $contextSnapshot)
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 shadow-sm">
            <h3 class="font-semibold text-foreground mb-3">Tender Context</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                @if($prediction->tender)
                    <div><dt class="text-muted-foreground text-xs">Tender</dt><dd class="font-semibold">{{ $prediction->tender->title ?? $prediction->tender->tender_number ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground text-xs">Tender number</dt><dd class="font-semibold">{{ $prediction->tender->tender_number ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground text-xs">Country</dt><dd class="font-semibold">{{ $prediction->tender->country?->name ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground text-xs">Year</dt><dd class="font-semibold">{{ $prediction->tender->year ?? '—' }}</dd></div>
                    <div><dt class="text-muted-foreground text-xs">Status</dt><dd class="font-semibold capitalize">{{ $prediction->tender->status ?? '—' }}</dd></div>
                @else
                    <div class="md:col-span-2"><dd class="text-muted-foreground text-sm">Tender details are unavailable for older recommendations.</dd></div>
                @endif
                @if($prediction->quantity)
                    <div><dt class="text-muted-foreground text-xs">Requested quantity</dt><dd class="font-semibold">{{ number_format((float) $prediction->quantity) }} {{ $prediction->quantity_unit ?? 'units' }}</dd></div>
                @endif
            </dl>
        </div>
        @endif

        @if($contextSnapshot)
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 shadow-sm">
            <h3 class="font-semibold text-foreground mb-3">Analysis Summary</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-muted-foreground text-xs">Recent winning bids</dt>
                    <dd class="font-semibold">{{ count($contextSnapshot->snapshot_data['recent_winning_bids'] ?? []) }} records summarized</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Outliers flagged</dt>
                    <dd class="font-semibold">{{ $contextSnapshot->snapshot_data['outlier_summary']['count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Distinct winners</dt>
                    <dd class="font-semibold">{{ $contextSnapshot->snapshot_data['competition_summary']['distinct_winners'] ?? '—' }}</dd>
                </div>
                @if(!empty($contextSnapshot->snapshot_data['discount_applied']))
                <div>
                    <dt class="text-muted-foreground text-xs">Bid discount applied</dt>
                    <dd class="font-semibold">{{ number_format((float) ($contextSnapshot->snapshot_data['discount_applied']['discount_percentage'] ?? 0), 2) }}%</dd>
                </div>
                @endif
            </dl>
        </div>
        @endif

        <div class="flex flex-wrap gap-4">
            <a href="{{ route('predictions.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-border/50 bg-white text-sm font-semibold text-foreground hover:border-primary/30">
                <i data-lucide="history" class="w-4 h-4"></i> Back to Recommendation History
            </a>
            <a href="{{ route('ai.recommendations.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-primary to-secondary text-white text-sm font-semibold shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> New Recommendation
            </a>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
@endpush
