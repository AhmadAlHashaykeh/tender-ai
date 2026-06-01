$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent

$map = @{
    'dashboard.html' = 'dashboard/index.blade.php'
    'upload.html' = 'uploads/index.blade.php'
    'management.html' = 'management/index.blade.php'
    'companies.html' = 'companies/index.blade.php'
    'company-detail.html' = 'companies/show.blade.php'
    'tenders.html' = 'tenders/index.blade.php'
    'tender-details.html' = 'tenders/show.blade.php'
    'drugs.html' = 'drugs/index.blade.php'
    'drug-details.html' = 'drugs/show.blade.php'
    'drug-standardization.html' = 'standardization/index.blade.php'
    'ai-recommendations.html' = 'ai/recommendations/create.blade.php'
    'prediction-history.html' = 'predictions/index.blade.php'
    'reports.html' = 'reports/index.blade.php'
    'reports-company.html' = 'reports/company.blade.php'
    'reports-opportunity.html' = 'reports/opportunity.blade.php'
    'reports-history.html' = 'reports/history.blade.php'
    'settings.html' = 'settings/index.blade.php'
}

$scripts = @{
    'dashboard/index.blade.php' = 'dashboard.js'
    'uploads/index.blade.php' = 'upload.js'
    'management/index.blade.php' = 'management.js'
    'companies/index.blade.php' = 'companies.js'
    'companies/show.blade.php' = 'company-detail.js'
    'tenders/index.blade.php' = 'tenders.js'
    'tenders/show.blade.php' = 'tender-details.js'
    'drugs/index.blade.php' = 'drugs.js'
    'drugs/show.blade.php' = 'drug-details.js'
    'standardization/index.blade.php' = 'drug-standardization.js'
    'ai/recommendations/create.blade.php' = 'ai-recommendations.js'
    'predictions/index.blade.php' = 'prediction-history.js'
    'reports/index.blade.php' = 'reports.js'
    'reports/company.blade.php' = 'reports.js'
    'reports/opportunity.blade.php' = 'reports.js'
    'reports/history.blade.php' = 'reports.js'
    'settings/index.blade.php' = 'settings.js'
}

$linkMap = @{
    'dashboard.html' = "route('dashboard')"
    'upload.html' = "route('uploads.index')"
    'management.html' = "route('management.index')"
    'companies.html' = "route('companies.index')"
    'company-detail.html' = "route('companies.show', 1)"
    'tenders.html' = "route('tenders.index')"
    'tender-details.html' = "route('tenders.show', 1)"
    'drugs.html' = "route('drugs.index')"
    'drug-details.html' = "route('drugs.show', 1)"
    'drug-standardization.html' = "route('standardization.index')"
    'ai-recommendations.html' = "route('ai.recommendations.create')"
    'prediction-history.html' = "route('predictions.index')"
    'reports.html' = "route('reports.index')"
    'reports-company.html' = "route('reports.company')"
    'reports-opportunity.html' = "route('reports.opportunity')"
    'reports-history.html' = "route('reports.history')"
    'settings.html' = "route('settings.index')"
    '../index.html' = "route('landing')"
}

foreach ($entry in $map.GetEnumerator()) {
    $src = Join-Path (Join-Path $root 'pages') $entry.Key
    $dest = Join-Path (Join-Path $root 'resources/views') $entry.Value
    $destDir = Split-Path $dest -Parent
    New-Item -ItemType Directory -Force -Path $destDir | Out-Null

    $html = Get-Content $src -Raw -Encoding UTF8
    if ($html -notmatch '(?s)(<main[^>]*>)(.*)(</main>)') {
        Write-Warning "No main in $($entry.Key)"
        continue
    }

    $mainOpen = $Matches[1]
    $mainBody = $Matches[2]
    $mainClose = $Matches[3]

    foreach ($link in $linkMap.GetEnumerator()) {
        $mainBody = $mainBody -replace [regex]::Escape("href=""$($link.Key)"""), "href=""{{ $($link.Value) }}"""
    }

    $title = 'TenderAI'
    if ($html -match '<title>([^<]+)</title>') {
        $title = $Matches[1].Trim() -replace "'", "''"
    }

    $script = $scripts[$entry.Value]
    $content = @"
@extends('layouts.app')

@section('title', '$title')

@section('content')
$mainOpen
$mainBody
$mainClose
@endsection

@push('scripts')
    @vite(['resources/js/pages/$script'])
@endpush
"@

    Set-Content -Path $dest -Value $content -Encoding UTF8
    Write-Host "Created $($entry.Value)"
}
