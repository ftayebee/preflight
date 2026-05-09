@extends('preflight::layout')

@section('title', 'Preflight Reports')

@section('content')
    <section class="panel stack">
        <div>
            <h2>Generated HTML Reports</h2>
            <p class="muted">Reports are read from the configured Preflight reports directory and fallback HTML report path.</p>
        </div>

        @if ($reports === [])
            <p>No HTML reports found. Generate one with <code>php artisan preflight:audit --format=html</code>.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Modified</th>
                            <th>Size</th>
                            <th>Source</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr>
                                <td><code>{{ $report['filename'] }}</code></td>
                                <td>{{ $report['modified_at_human'] }}</td>
                                <td>{{ number_format($report['size']) }} bytes</td>
                                <td>
                                    @if ($report['is_fallback'])
                                        <span class="badge">fallback</span>
                                    @else
                                        <span class="badge">reports directory</span>
                                    @endif
                                </td>
                                <td><a class="button" href="{{ route('preflight.report', ['filename' => $report['filename']]) }}">View report</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
