<nav class="w-64 flex-shrink-0">
    <div class="bg-white rounded-xl border sticky top-6 p-4">
        <div class="mb-3">
            <h4 class="text-base font-semibold text-foreground">Reports</h4>
            <p class="text-muted-foreground text-xs">Market analytics</p>
        </div>
        <div class="space-y-1">
            <a href="{{ route('reports.index') }}" class="block px-3 py-2 rounded-lg text-sm {{ ($active ?? '') === 'market' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/40' }}">Market Intelligence</a>
            <a href="{{ route('reports.company') }}" class="block px-3 py-2 rounded-lg text-sm {{ ($active ?? '') === 'company' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/40' }}">Company Performance</a>
            <a href="{{ route('reports.opportunity') }}" class="block px-3 py-2 rounded-lg text-sm {{ ($active ?? '') === 'opportunity' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/40' }}">Market Opportunities</a>
            <a href="{{ route('reports.history') }}" class="block px-3 py-2 rounded-lg text-sm {{ ($active ?? '') === 'history' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/40' }}">Recommendation History</a>
        </div>
    </div>
</nav>
