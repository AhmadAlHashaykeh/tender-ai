@extends('layouts.app')

@section('title', 'Import Mapping Preview | TenderAI')

@section('content')
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-5xl mx-auto">
        <div>
            <a href="{{ route('uploads.index') }}" class="text-xs text-muted-foreground hover:text-primary">← Data Entry Hub</a>
            <h1 class="text-2xl font-bold text-foreground mt-2">Review Detected Columns</h1>
            <p class="text-sm text-muted-foreground mt-1">{{ $batch->original_filename }} · ~{{ number_format($estimatedRowCount) }} rows detected</p>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-2xl bg-white border border-border/40">
                <p class="text-[10px] text-muted-foreground uppercase">Mapping Confidence</p>
                <p class="text-2xl font-bold text-foreground">{{ number_format($mappingConfidence, 0) }}%</p>
            </div>
            <div class="p-4 rounded-2xl bg-white border border-border/40">
                <p class="text-[10px] text-muted-foreground uppercase">Required Fields</p>
                <p class="text-2xl font-bold {{ empty($missingRequired) && ! $missingDrugIdentity ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ empty($missingRequired) && ! $missingDrugIdentity ? 'Complete' : 'Incomplete' }}
                </p>
            </div>
            <div class="p-4 rounded-2xl bg-white border border-border/40">
                <p class="text-[10px] text-muted-foreground uppercase">Additional Columns</p>
                <p class="text-2xl font-bold text-foreground">{{ count($extraColumns) }}</p>
                <p class="text-xs text-muted-foreground">Stored as Additional Information</p>
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-border/40 overflow-hidden">
            <div class="px-5 py-4 border-b border-border/30">
                <h2 class="font-semibold text-foreground">Auto-Detected Mapping</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Detected Column</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">→ Canonical Field</th>
                            <th class="px-4 py-3 font-medium text-muted-foreground">Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mappings as $mapping)
                            @if (($mapping['match_type'] ?? '') === 'additional')
                                @continue
                            @endif
                            <tr class="border-t border-border/20">
                                <td class="px-4 py-3 font-medium">{{ $mapping['header'] }}</td>
                                <td class="px-4 py-3">{{ $headerLabels[$mapping['canonical_field']] ?? $mapping['canonical_field'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ ($mapping['confidence'] ?? 0) >= 90 ? 'text-emerald-600' : 'text-amber-600' }} font-semibold">
                                        {{ number_format($mapping['confidence'] ?? 0, 0) }}%
                                    </span>
                                    <span class="text-xs text-muted-foreground ml-1">({{ str_replace('_', ' ', $mapping['match_type'] ?? '') }})</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if (! empty($extraColumns))
            <div class="p-4 rounded-xl bg-slate-50 border border-border/40 text-sm">
                <strong>Additional Information columns</strong> (preserved in raw_data, not used in analytics):
                {{ implode(', ', $extraColumns) }}
            </div>
        @endif

        <form method="POST" action="{{ route('imports.mapping.confirm', $batch) }}" class="space-y-6">
            @csrf

            <div class="rounded-2xl bg-white border border-border/40 overflow-hidden">
                <div class="px-5 py-4 border-b border-border/30">
                    <h2 class="font-semibold text-foreground">Confirm or Change Mapping</h2>
                    <p class="text-xs text-muted-foreground mt-1">Select which file column maps to each canonical field</p>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($canonicalFields as $field)
                        @php
                            $isRequired = in_array($field, config('import.required_canonical_fields', []), true);
                            $isDrugIdentity = in_array($field, config('import.drug_identity_fields', []), true);
                            $currentIndex = $mappedHeaders[$field] ?? null;
                        @endphp
                        <div>
                            <label class="text-xs text-muted-foreground block mb-1">
                                {{ $headerLabels[$field] ?? $field }}
                                @if ($isRequired)
                                    <span class="text-red-500">*</span>
                                @elseif ($isDrugIdentity)
                                    <span class="text-amber-500">†</span>
                                @endif
                            </label>
                            <select name="mapping[{{ $field }}]" class="text-sm border rounded-lg px-2 py-2 w-full">
                                <option value="">— Not mapped —</option>
                                @foreach ($detectedHeaders as $index => $header)
                                    @if ($header === '') @continue @endif
                                    <option value="{{ $index }}" @selected($currentIndex === $index)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <p class="px-5 pb-4 text-xs text-muted-foreground">* Required · † At least one drug identity field required</p>
            </div>

            <div class="rounded-2xl bg-white border border-border/40 p-5 space-y-4">
                <div>
                    <label class="text-xs text-muted-foreground block mb-1">Save mapping as template (optional)</label>
                    <input type="text" name="template_name" placeholder="e.g. Saudi MOH Tender Format" class="text-sm border rounded-lg px-3 py-2 w-full max-w-md">
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="h-10 px-6 rounded-lg bg-primary text-white text-sm font-semibold hover:opacity-90">
                        Confirm Mapping
                    </button>
                    <a href="{{ route('uploads.index') }}" class="text-sm text-muted-foreground hover:text-primary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</main>
@endsection
