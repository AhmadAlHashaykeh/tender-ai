@extends('layouts.app')

@section('title', 'TenderAI - Import Preview')

@section('content')
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-[90rem] mx-auto">
        <div>
            <a href="{{ route('imports.show', $batch) }}" class="text-xs text-muted-foreground hover:text-primary">← Back to batch</a>
            <h1 class="text-2xl font-bold text-foreground mt-2">Import Preview</h1>
            <p class="text-sm text-muted-foreground">{{ $batch->original_filename }} — all raw columns preserved</p>
        </div>

        <div class="bg-white rounded-2xl border border-border/50 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[10px]">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-left">#</th>
                            <th class="px-2 py-2 text-left">Status</th>
                            <th class="px-2 py-2 text-left">Code</th>
                            <th class="px-2 py-2 text-left">INN</th>
                            <th class="px-2 py-2 text-left">Product</th>
                            <th class="px-2 py-2 text-left">Country</th>
                            <th class="px-2 py-2 text-left">Tender #</th>
                            <th class="px-2 py-2 text-left">Awarded</th>
                            <th class="px-2 py-2 text-left">USD</th>
                            <th class="px-2 py-2 text-left">Winner</th>
                            <th class="px-2 py-2 text-left">Company</th>
                            <th class="px-2 py-2 text-left">Ver</th>
                            <th class="px-2 py-2 text-left">Year</th>
                            <th class="px-2 py-2 text-left">Qty</th>
                            <th class="px-2 py-2 text-left">Value</th>
                            <th class="px-2 py-2 text-left">Errors / Warnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-border/20 align-top {{ in_array($row->validation_status, ['invalid', 'duplicate']) ? 'bg-red-50/20' : '' }}">
                                <td class="px-2 py-2">{{ $row->row_number }}</td>
                                <td class="px-2 py-2 capitalize">{{ $row->validation_status }}</td>
                                <td class="px-2 py-2 font-mono">{{ $row->raw_code }}</td>
                                <td class="px-2 py-2">{{ $row->raw_inn }}</td>
                                <td class="px-2 py-2">{{ $row->raw_product_name }}</td>
                                <td class="px-2 py-2">{{ $row->raw_country }}</td>
                                <td class="px-2 py-2">{{ $row->raw_tender_number }}</td>
                                <td class="px-2 py-2">{{ $row->raw_awarded_price }}</td>
                                <td class="px-2 py-2 font-semibold">{{ $row->raw_price_usd }}</td>
                                <td class="px-2 py-2">{{ $row->raw_winner }}</td>
                                <td class="px-2 py-2">{{ $row->raw_company_name }}</td>
                                <td class="px-2 py-2">{{ $row->raw_version }}</td>
                                <td class="px-2 py-2">{{ $row->raw_year }}</td>
                                <td class="px-2 py-2">{{ $row->raw_qty }}</td>
                                <td class="px-2 py-2">{{ $row->raw_tender_value }}</td>
                                <td class="px-2 py-2 text-muted-foreground min-w-[180px]">
                                    @if ($row->error_message)
                                        <span class="text-red-600">{{ $row->error_message }}</span>
                                    @endif
                                    @if (is_array($row->warning_messages))
                                        @foreach ($row->warning_messages as $warning)
                                            <div>{{ $warning }}</div>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-border/30">{{ $rows->links() }}</div>
        </div>
    </div>
</main>
@endsection
