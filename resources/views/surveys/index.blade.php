@extends('layouts.app')

@section('title', 'My Surveys')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">My Surveys</h1>
            <p class="page-lead">Upload your survey responses and StatEase computes the results for your paper.</p>
        </div>
        <a href="{{ route('surveys.create') }}" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i> Upload a Survey
        </a>
    </div>

    {{-- How it works --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="how-it-works">
                <div class="how-step">
                    <span class="how-step-icon"><i class="bi bi-upload"></i></span>
                    <div>
                        <div class="fw-semibold">1. Upload</div>
                        <div class="small text-secondary-se">A CSV or Excel file of responses, e.g. from Google Forms.</div>
                    </div>
                </div>
                <div class="how-step">
                    <span class="how-step-icon"><i class="bi bi-list-columns"></i></span>
                    <div>
                        <div class="fw-semibold">2. Confirm columns</div>
                        <div class="small text-secondary-se">Tell StatEase which questions are rated items and which describe respondents.</div>
                    </div>
                </div>
                <div class="how-step">
                    <span class="how-step-icon"><i class="bi bi-bar-chart-line"></i></span>
                    <div>
                        <div class="fw-semibold">3. Get results</div>
                        <div class="small text-secondary-se">Tables, charts and written interpretations, plus a Word report.</div>
                    </div>
                </div>
                <div class="how-step">
                    <span class="how-step-icon"><i class="bi bi-diagram-3"></i></span>
                    <div>
                        <div class="fw-semibold">4. Go further</div>
                        <div class="small text-secondary-se">Check reliability (Cronbach's alpha) and run chi-square, Pearson r or ANOVA.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title-se">Uploaded surveys</h2>
            <span class="small text-secondary-se">{{ $surveys->total() }} {{ \Illuminate\Support\Str::plural('survey', $surveys->total()) }}</span>
        </div>

        @if ($surveys->isEmpty())
            <div class="empty-state">
                <i class="bi bi-clipboard-data"></i>
                <p class="mb-3">No surveys yet. Upload your first file to get started.</p>
                <a href="{{ route('surveys.create') }}" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i> Upload a Survey
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Survey</th>
                            <th class="text-center">Respondents</th>
                            <th>Scale</th>
                            <th class="text-center">Status</th>
                            <th>Uploaded</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($surveys as $survey)
                            <tr>
                                <td>
                                    <a href="{{ route($survey->is_mapped ? 'surveys.results' : 'surveys.columns.edit', $survey) }}" class="fw-semibold text-decoration-none">
                                        {{ $survey->title }}
                                    </a>
                                    <div class="small text-secondary-se">{{ $survey->original_filename }}</div>
                                </td>
                                <td class="text-center num">{{ number_format($survey->respondent_count) }}</td>
                                <td class="small">{{ $survey->scale->name }}</td>
                                <td class="text-center">
                                    @if ($survey->is_mapped)
                                        <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle me-1"></i>Ready</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-warning"><i class="bi bi-exclamation-circle me-1"></i>Confirm columns</span>
                                    @endif
                                </td>
                                <td class="small text-secondary-se text-nowrap">{{ $survey->created_at->format('M j, Y · g:i A') }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($survey->is_mapped)
                                        <a href="{{ route('surveys.results', $survey) }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-bar-chart-line me-1"></i>Results
                                        </a>
                                    @else
                                        <a href="{{ route('surveys.columns.edit', $survey) }}" class="btn btn-sm btn-warning">
                                            <i class="bi bi-list-columns me-1"></i>Confirm
                                        </a>
                                    @endif
                                    <a href="{{ route('surveys.show', $survey) }}" class="btn btn-sm btn-outline-secondary" title="View the uploaded data">
                                        <i class="bi bi-table"></i><span class="visually-hidden">Data</span>
                                    </a>
                                    <form action="{{ route('surveys.destroy', $survey) }}" method="POST" class="d-inline"
                                          data-confirm="&quot;{{ $survey->title }}&quot; and all {{ number_format($survey->respondent_count) }} of its responses will be permanently deleted. This cannot be undone."
                                          data-confirm-title="Delete this survey?" data-confirm-button="Delete survey">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete survey">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($surveys->hasPages())
                <div class="card-footer">
                    {{ $surveys->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
