@extends('layouts.auth')

@section('title', 'Login — TenderAI')

@section('content')
<div class="lp-root">

  {{-- ── Background effects ───────────────────────────────────────────────── --}}
  <div class="lp-grid" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-1" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-2" aria-hidden="true"></div>
  <div class="lp-glow lp-glow-3" aria-hidden="true"></div>

  <div class="lp-layout">

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- LEFT — Marketing panel                                               --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    <aside class="lp-panel">
      <div class="lp-panel-inner">

        {{-- Brand --}}
        <div class="lp-brand lp-anim-up lp-d1">
          <div class="lp-logo" aria-hidden="true">
            <i data-lucide="zap" style="width:1.75rem;height:1.75rem;color:#fff;position:relative;z-index:1;"></i>
          </div>
          <div>
            <p class="lp-brand-name">TenderAI</p>
            <p class="lp-brand-tag">Pricing Intelligence</p>
          </div>
        </div>

        {{-- Headline --}}
        <div class="lp-headline lp-anim-up lp-d2">
          <h1>AI-Powered<br><span class="lp-grad">Tender Intelligence</span></h1>
          <p class="lp-subhead">
            Analyze tenders, compare competitors, and generate smarter pricing
            recommendations using AI-powered market intelligence.
          </p>
        </div>

        {{-- Feature list --}}
        <ul class="lp-feats lp-anim-up lp-d3" role="list">
          <li class="lp-feat">
            <span class="lp-feat-ico lp-feat-ico--blue" aria-hidden="true">
              <i data-lucide="database" style="width:1.125rem;height:1.125rem;color:#fff;"></i>
            </span>
            <div>
              <p class="lp-feat-title">Historical Tender Intelligence</p>
              <p class="lp-feat-desc">Deep analysis of past tender outcomes and pricing trends</p>
            </div>
          </li>
          <li class="lp-feat">
            <span class="lp-feat-ico lp-feat-ico--purple" aria-hidden="true">
              <i data-lucide="cpu" style="width:1.125rem;height:1.125rem;color:#fff;"></i>
            </span>
            <div>
              <p class="lp-feat-title">Pricing Analysis</p>
              <p class="lp-feat-desc">ML models trained on market data for accurate price forecasting</p>
            </div>
          </li>
          <li class="lp-feat">
            <span class="lp-feat-ico lp-feat-ico--cyan" aria-hidden="true">
              <i data-lucide="sparkles" style="width:1.125rem;height:1.125rem;color:#fff;"></i>
            </span>
            <div>
              <p class="lp-feat-title">AI Market Insights</p>
              <p class="lp-feat-desc">AI-generated strategic summaries and bid recommendations</p>
            </div>
          </li>
        </ul>

        {{-- Enterprise notice --}}
        <div class="lp-enterprise lp-anim-up lp-d4">
          <i data-lucide="building-2" style="width:1rem;height:1rem;color:#3b82f6;flex-shrink:0;"></i>
          <span>Enterprise-grade security &amp; compliance</span>
        </div>

      </div>
    </aside>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- RIGHT — Login card                                                   --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    <main class="lp-card-col">
      <div class="lp-card lp-anim-right lp-d2">

        {{-- Card header icon --}}
        <div class="lp-card-icon" aria-hidden="true">
          <i data-lucide="shield-check" style="width:1.625rem;height:1.625rem;color:#fff;position:relative;z-index:1;"></i>
        </div>

        <header class="lp-card-header">
          <h2>Welcome Back</h2>
          <p>Sign in to access your tender intelligence dashboard</p>
        </header>

        {{-- Session status --}}
        @if (session('status'))
          <div class="lp-alert lp-alert--ok" role="alert">
            <i data-lucide="check-circle-2" style="width:1rem;height:1rem;flex-shrink:0;margin-top:0.1rem;"></i>
            <span>{{ session('status') }}</span>
          </div>
        @endif

        {{-- Validation errors --}}
        @if ($errors->any())
          <div class="lp-alert lp-alert--err" role="alert">
            <i data-lucide="alert-triangle" style="width:1rem;height:1rem;flex-shrink:0;margin-top:0.1rem;"></i>
            <span>{{ $errors->first() }}</span>
          </div>
        @endif

        {{-- Auth form — DO NOT change action, method, field names, or CSRF --}}
        <form method="POST" action="{{ route('login') }}" id="lp-form" novalidate>
          @csrf

          {{-- Email --}}
          <div class="lp-field">
            <label for="email" class="lp-label">Email Address</label>
            <div class="lp-input-wrap">
              <span class="lp-ico-l" aria-hidden="true">
                <i data-lucide="mail" style="width:1.125rem;height:1.125rem;"></i>
              </span>
              <input
                type="email"
                id="email"
                name="email"
                class="lp-input{{ $errors->has('email') ? ' lp-input--err' : '' }}"
                placeholder="you@company.com"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
              >
            </div>
          </div>

          {{-- Password --}}
          <div class="lp-field">
            <label for="password" class="lp-label">Password</label>
            <div class="lp-input-wrap">
              <span class="lp-ico-l" aria-hidden="true">
                <i data-lucide="lock" style="width:1.125rem;height:1.125rem;"></i>
              </span>
              <input
                type="password"
                id="password"
                name="password"
                class="lp-input{{ $errors->has('password') ? ' lp-input--err' : '' }}"
                placeholder="••••••••••"
                required
                autocomplete="current-password"
              >
              <button
                type="button"
                class="lp-pw-toggle"
                id="lp-pw-toggle"
                aria-label="Toggle password visibility"
              >
                <i data-lucide="eye" id="lp-pw-icon" style="width:1.125rem;height:1.125rem;"></i>
              </button>
            </div>
          </div>

          {{-- Remember me + Forgot password --}}
          <div class="lp-row">
            <label class="lp-remember">
              <input
                type="checkbox"
                name="remember"
                id="remember"
                class="lp-check"
                {{ old('remember') ? 'checked' : '' }}
              >
              <span>Remember me</span>
            </label>
            @if (Route::has('password.request'))
              <a href="{{ route('password.request') }}" class="lp-forgot">Forgot password?</a>
            @endif
          </div>

          {{-- Submit --}}
          <button type="submit" class="lp-btn" id="lp-submit">
            <span class="lp-btn-text">Sign In</span>
            <span class="lp-btn-spin" aria-hidden="true" style="display:none;">
              <i data-lucide="loader-2" style="width:1.125rem;height:1.125rem;animation:lp-spin 1s linear infinite;"></i>
              Signing in&hellip;
            </span>
          </button>

          {{-- Demo credentials — LOCAL only, never shown in production --}}
          @if (app()->environment('local'))
            <div class="lp-demo">
              <i data-lucide="info" style="width:0.875rem;height:0.875rem;flex-shrink:0;color:#3b82f6;"></i>
              <span>Demo:&nbsp;<strong>admin@tendar.ai</strong>&nbsp;/&nbsp;<strong>password</strong></span>
            </div>
          @endif

        </form>

        {{-- Secure footer --}}
        <footer class="lp-secure">
          <i data-lucide="lock-keyhole" style="width:0.875rem;height:0.875rem;"></i>
          <span>Secure, encrypted connection</span>
        </footer>

      </div>
    </main>

  </div>
</div>
@endsection

@push('scripts')
  @vite(['resources/js/pages/login.js'])
@endpush
