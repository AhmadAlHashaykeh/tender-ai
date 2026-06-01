@props(['batch', 'standardization'])

@if (($standardization['status'] ?? 'not_started') === 'processing')
    <div
        id="standardization-progress-panel"
        class="p-5 rounded-2xl bg-gradient-to-br from-violet-500/5 to-purple-500/5 border border-violet-200/60"
        data-progress-url="{{ route('imports.progress', $batch) }}"
        data-poll-interval="4000"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-foreground">Standardizing Import</p>
                <p class="text-xs text-muted-foreground mt-1">You can leave this page and return later.</p>
            </div>
            <span id="std-progress-status" class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-violet-50 text-violet-700 border border-violet-100 capitalize">processing</span>
        </div>

        <div class="mt-4">
            <div class="h-2.5 rounded-full bg-violet-100 overflow-hidden">
                <div id="std-progress-bar" class="h-full bg-violet-600 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>
            <p id="std-progress-percent" class="text-xs text-muted-foreground mt-1 text-right">0%</p>
        </div>

        <p id="std-progress-rows" class="text-sm text-foreground mt-3 font-medium">0 / 0 rows processed</p>
        <p id="std-progress-chunks" class="text-xs text-muted-foreground mt-0.5">0 / 0 chunks completed</p>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div><span class="text-muted-foreground">Auto-approved:</span> <strong id="std-progress-auto">0</strong></div>
            <div><span class="text-muted-foreground">Needs review:</span> <strong id="std-progress-review">0</strong></div>
            <div><span class="text-muted-foreground">Rejected:</span> <strong id="std-progress-rejected">0</strong></div>
            <div><span class="text-muted-foreground">Failed rows:</span> <strong id="std-progress-failed">0</strong></div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const panel = document.getElementById('standardization-progress-panel');
            if (!panel) return;

            const url = panel.dataset.progressUrl;
            const interval = parseInt(panel.dataset.pollInterval || '4000', 10);

            const els = {
                bar: document.getElementById('std-progress-bar'),
                percent: document.getElementById('std-progress-percent'),
                rows: document.getElementById('std-progress-rows'),
                chunks: document.getElementById('std-progress-chunks'),
                auto: document.getElementById('std-progress-auto'),
                review: document.getElementById('std-progress-review'),
                rejected: document.getElementById('std-progress-rejected'),
                failed: document.getElementById('std-progress-failed'),
                status: document.getElementById('std-progress-status'),
            };

            function formatNum(n) {
                return new Intl.NumberFormat().format(n);
            }

            function update(std) {
                if (!std) return;
                const progress = std.progress ?? 0;
                els.bar.style.width = progress + '%';
                els.percent.textContent = progress + '%';
                els.rows.textContent = formatNum(std.processed_rows) + ' / ' + formatNum(std.total_rows) + ' rows processed';
                els.chunks.textContent = formatNum(std.completed_chunks) + ' / ' + formatNum(std.total_chunks) + ' chunks completed';
                els.auto.textContent = formatNum(std.auto_approved);
                els.review.textContent = formatNum(std.review_required);
                els.rejected.textContent = formatNum(std.rejected);
                els.failed.textContent = formatNum(std.failed_rows);
                els.status.textContent = (std.status || '').replace(/_/g, ' ');

                if (std.is_complete) {
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
                        update(data.standardization);
                    }
                } catch (e) {
                    console.warn('Standardization progress poll failed', e);
                }
            }

            poll();
            setInterval(poll, interval);
        })();
    </script>
    @endpush
@endif
