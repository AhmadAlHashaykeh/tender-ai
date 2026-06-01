@props(['batch'])

@php
    $isActive = in_array($batch->status, ['queued', 'processing', 'parsing', 'validating'], true);
@endphp

@if ($isActive)
    <div
        id="import-progress-panel"
        class="p-5 rounded-2xl bg-gradient-to-br from-blue-500/5 to-indigo-500/5 border border-blue-200/60"
        data-progress-url="{{ route('imports.progress', $batch) }}"
        data-poll-interval="4000"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-foreground">Processing Import</p>
                <p class="text-xs text-muted-foreground mt-1">You can leave this page and return later.</p>
            </div>
            <span id="import-progress-status" class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-100 capitalize">
                {{ str_replace('_', ' ', $batch->status) }}
            </span>
        </div>

        <div class="mt-4">
            <div class="h-2.5 rounded-full bg-blue-100 overflow-hidden">
                <div id="import-progress-bar" class="h-full bg-primary rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>
            <p id="import-progress-percent" class="text-xs text-muted-foreground mt-1 text-right">0%</p>
        </div>

        <p id="import-progress-rows" class="text-sm text-foreground mt-3 font-medium">0 / 0 rows processed</p>
        <p id="import-progress-chunks" class="text-xs text-muted-foreground mt-0.5">0 / 0 chunks completed</p>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div><span class="text-muted-foreground">Valid:</span> <strong id="import-progress-valid">0</strong></div>
            <div><span class="text-muted-foreground">Warnings:</span> <strong id="import-progress-warnings">0</strong></div>
            <div><span class="text-muted-foreground">Invalid:</span> <strong id="import-progress-invalid">0</strong></div>
            <div><span class="text-muted-foreground">Duplicates:</span> <strong id="import-progress-duplicates">0</strong></div>
        </div>

        <p id="import-progress-failed-chunks" class="text-xs text-red-600 mt-2 hidden"></p>
    </div>

    @push('scripts')
    <script>
        (function () {
            const panel = document.getElementById('import-progress-panel');
            if (!panel) return;

            const url = panel.dataset.progressUrl;
            const interval = parseInt(panel.dataset.pollInterval || '4000', 10);

            const els = {
                bar: document.getElementById('import-progress-bar'),
                percent: document.getElementById('import-progress-percent'),
                rows: document.getElementById('import-progress-rows'),
                chunks: document.getElementById('import-progress-chunks'),
                valid: document.getElementById('import-progress-valid'),
                warnings: document.getElementById('import-progress-warnings'),
                invalid: document.getElementById('import-progress-invalid'),
                duplicates: document.getElementById('import-progress-duplicates'),
                status: document.getElementById('import-progress-status'),
                failedChunks: document.getElementById('import-progress-failed-chunks'),
            };

            function formatNum(n) {
                return new Intl.NumberFormat().format(n);
            }

            function update(data) {
                const progress = data.progress ?? 0;
                els.bar.style.width = progress + '%';
                els.percent.textContent = progress + '%';
                els.rows.textContent = formatNum(data.processed_rows) + ' / ' + formatNum(data.total_rows) + ' rows processed';
                els.chunks.textContent = formatNum(data.completed_chunks) + ' / ' + formatNum(data.total_chunks) + ' chunks completed';
                els.valid.textContent = formatNum(data.valid_rows);
                els.warnings.textContent = formatNum(data.warning_rows);
                els.invalid.textContent = formatNum(data.invalid_rows);
                els.duplicates.textContent = formatNum(data.duplicate_rows);
                els.status.textContent = (data.status || '').replace(/_/g, ' ');

                if (data.failed_chunks > 0) {
                    els.failedChunks.textContent = data.failed_chunks + ' chunk(s) failed';
                    els.failedChunks.classList.remove('hidden');
                } else {
                    els.failedChunks.classList.add('hidden');
                }

                if (data.is_complete) {
                    window.location.reload();
                }
            }

            async function poll() {
                try {
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (res.ok) {
                        update(await res.json());
                    }
                } catch (e) {
                    console.warn('Import progress poll failed', e);
                }
            }

            poll();
            const timer = setInterval(poll, interval);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) poll();
            });
        })();
    </script>
    @endpush
@endif
