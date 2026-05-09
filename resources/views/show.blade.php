@extends('preflight::layout')

@section('title', 'Preflight Report - ' . $report['filename'])

@section('content')
    <section class="panel stack">
        <div>
            <h2>{{ $report['filename'] }}</h2>
            <p class="muted">
                Modified {{ $report['modified_at_human'] }} · {{ number_format($report['size']) }} bytes
                @if ($report['is_fallback'])
                    · fallback report
                @endif
            </p>
        </div>

        <iframe title="Preflight HTML report: {{ $report['filename'] }}" sandbox srcdoc="{{ $reportHtml }}"></iframe>
    </section>
@endsection
