@extends('layouts.app')

@section('title', 'TenderAI - Settings')

@section('content')
<main class="sc-page">

    {{-- Page header --}}
    <div class="sc-page-header">
        <div>
            <h2 class="sc-page-title">Configuration</h2>
            <p class="sc-page-subtitle">System settings and operational preferences</p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="sc-flash sc-flash--success">
            <i data-lucide="check-circle" class="icon-sm"></i>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="sc-flash sc-flash--error">
            <i data-lucide="alert-circle" class="icon-sm"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="sc-layout">

        {{-- Vertical nav --}}
        <nav class="sc-nav" role="tablist">
            @php
                $navItems = [
                    'overview'         => ['icon' => 'monitor',      'label' => 'Overview'],
                    'general'          => ['icon' => 'settings-2',   'label' => 'General'],
                    'prediction'       => ['icon' => 'trending-up',  'label' => 'Prediction'],
                    'standardization'  => ['icon' => 'check-circle', 'label' => 'Standardization'],
                    'ai'               => ['icon' => 'cpu',          'label' => 'AI / OpenAI'],
                    'users'            => ['icon' => 'users',        'label' => 'Users'],
                ];
            @endphp
            @foreach ($navItems as $tabId => $meta)
                <button type="button"
                    class="sc-nav-item {{ $activeTab === $tabId ? 'sc-nav-item--active' : '' }}"
                    data-tab="{{ $tabId }}"
                    role="tab"
                    aria-selected="{{ $activeTab === $tabId ? 'true' : 'false' }}">
                    <i data-lucide="{{ $meta['icon'] }}" class="sc-nav-icon"></i>
                    <span>{{ $meta['label'] }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Content panels --}}
        <div class="sc-content">

            <div class="sc-panel {{ $activeTab === 'overview' ? 'sc-panel--active' : '' }}" id="sc-tab-overview">
                @include('partials.settings.overview')
            </div>

            <div class="sc-panel {{ $activeTab === 'general' ? 'sc-panel--active' : '' }}" id="sc-tab-general">
                @include('partials.settings.general')
            </div>

            <div class="sc-panel {{ $activeTab === 'prediction' ? 'sc-panel--active' : '' }}" id="sc-tab-prediction">
                @include('partials.settings.prediction')
            </div>

            <div class="sc-panel {{ $activeTab === 'standardization' ? 'sc-panel--active' : '' }}" id="sc-tab-standardization">
                @include('partials.settings.standardization')
            </div>

            <div class="sc-panel {{ $activeTab === 'ai' ? 'sc-panel--active' : '' }}" id="sc-tab-ai">
                @include('partials.settings.ai')
            </div>

            <div class="sc-panel {{ $activeTab === 'users' ? 'sc-panel--active' : '' }}" id="sc-tab-users">
                @include('partials.settings.users')
            </div>

        </div>
    </div>

</main>
@endsection

@push('scripts')
    @vite(['resources/js/pages/settings.js'])
@endpush
