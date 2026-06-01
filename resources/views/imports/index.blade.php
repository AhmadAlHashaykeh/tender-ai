@extends('layouts.app')

@section('title', 'TenderAI - Import Batches')

@section('content')
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-foreground">Import Batches</h1>
                <p class="text-sm text-muted-foreground mt-1">Raw Excel/CSV uploads and processing status</p>
            </div>
            <a href="{{ route('uploads.index') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-gradient-to-r from-primary to-secondary text-white text-xs font-semibold">
                Upload New File
            </a>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-border/50 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 border-b border-border/40">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">File</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Status</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Rows</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Valid</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Invalid</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Duplicates</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Uploaded</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr class="border-b border-border/30 hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium text-foreground">{{ $batch->original_filename ?? $batch->filename }}</td>
                                <td class="px-4 py-3"><x-import-status-badge :status="$batch->status" /></td>
                                <td class="px-4 py-3 text-right">{{ $batch->row_count }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">{{ $batch->metadata['valid_rows'] ?? $batch->success_count }}</td>
                                <td class="px-4 py-3 text-right text-red-500">{{ $batch->metadata['invalid_rows'] ?? $batch->error_count }}</td>
                                <td class="px-4 py-3 text-right text-amber-600">{{ $batch->duplicate_count }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ $batch->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('imports.show', $batch) }}" class="text-primary font-semibold hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">No import batches yet. <a href="{{ route('uploads.index') }}" class="text-primary font-semibold">Upload a file</a>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches->hasPages())
                <div class="px-4 py-3 border-t border-border/30">{{ $batches->links() }}</div>
            @endif
        </div>
    </div>
</main>
@endsection
