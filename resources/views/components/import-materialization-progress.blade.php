@props(['batch', 'materialization'])

@if (($materialization['status'] ?? 'not_started') === 'processing')
    <div
        id="materialization-progress-panel"
        class="p-5 rounded-2xl bg-gradient-to-br from-violet-500/5 to-purple-500/5 border border-violet-200/60"
        data-progress-url="{{ route('imports.progress', $batch) }}"
        data-poll-interval="4000"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-foreground">Materialization in progress</p>
                <p class="text-xs text-muted-foreground mt-1">You can leave this page and return later.</p>
            </div>
            <span id="mat-progress-status" class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-violet-50 text-violet-700 border border-violet-100 capitalize">processing</span>
        </div>

        <div class="mt-4">
            <div class="h-2.5 rounded-full bg-violet-100 overflow-hidden">
                <div id="mat-progress-bar" class="h-full bg-violet-600 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>
            <p id="mat-progress-percent" class="text-xs text-muted-foreground mt-1 text-right">0%</p>
        </div>

        <p id="mat-progress-rows" class="text-sm text-foreground mt-3 font-medium">0 / 0 rows processed</p>
        <p id="mat-progress-chunks" class="text-xs text-muted-foreground mt-0.5">0 / 0 chunks completed</p>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-3 text-xs">
            <div><span class="text-muted-foreground">Materialized:</span> <strong id="mat-progress-materialized">0</strong></div>
            <div><span class="text-muted-foreground">Skipped:</span> <strong id="mat-progress-skipped">0</strong></div>
            <div><span class="text-muted-foreground">Failed:</span> <strong id="mat-progress-failed">0</strong></div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const panel = document.getElementById('materialization-progress-panel');
            if (!panel) return;

            const url = panel.dataset.progressUrl;
            const interval = parseInt(panel.dataset.pollInterval || '4000', 10);

            const els = {
                bar: document.getElementById('mat-progress-bar'),
                percent: document.getElementById('mat-progress-percent'),
                rows: document.getElementById('mat-progress-rows'),
                chunks: document.getElementById('mat-progress-chunks'),
                materialized: document.getElementById('mat-progress-materialized'),
                skipped: document.getElementById('mat-progress-skipped'),
                failed: document.getElementById('mat-progress-failed'),
                status: document.getElementById('mat-progress-status'),
            };

            function formatNum(n) {
                return new Intl.NumberFormat().format(n);
            }

            function update(mat) {
                if (!mat) return;
                const progress = mat.progress ?? 0;
                els.bar.style.width = progress + '%';
                els.percent.textContent = progress + '%';
                els.rows.textContent = formatNum(mat.processed_rows) + ' / ' + formatNum(mat.total_rows) + ' rows processed';
                els.chunks.textContent = formatNum(mat.completed_chunks) + ' / ' + formatNum(mat.total_chunks) + ' chunks completed';
                els.materialized.textContent = formatNum(mat.materialized_rows);
                els.skipped.textContent = formatNum(mat.skipped_rows);
                els.failed.textContent = formatNum(mat.failed_rows);
                els.status.textContent = (mat.status || '').replace(/_/g, ' ');

                if (mat.is_complete) {
                    window.location.reload();
                }
            }

            async function poll() {
                try {
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (res.ok) {
                        const data = await res.json();
                        update(data.materialization);
                    }
                } catch (e) {
                    console.warn('Materialization progress poll failed', e);
                }
            }

            poll();
            setInterval(poll, interval);
        })();
    </script>
    @endpush
@endif
