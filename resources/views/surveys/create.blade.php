@extends('layouts.app')

@section('title', 'Upload a Survey')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}">My Surveys</a></li>
            <li class="breadcrumb-item active" aria-current="page">Upload a Survey</li>
        </ol>
    </nav>

    <div class="page-header">
        <div>
            <h1 class="page-title">Upload a Survey</h1>
            <p class="page-lead">Upload the responses as a CSV or Excel file. Each row is one respondent.</p>
        </div>
    </div>

    @include('partials.stepper', ['current' => 1])

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title-se">Survey details</h2>
                </div>
                <form action="{{ route('surveys.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="card-body">
                        <div class="mb-4">
                            <label for="title" class="form-label">Survey title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}"
                                   class="form-control @error('title') is-invalid @enderror"
                                   placeholder="e.g. Usability of the DMMMSU Online Enrollment System" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description <span class="text-secondary-se fw-normal">(optional)</span></label>
                            <textarea id="description" name="description" rows="2"
                                      class="form-control @error('description') is-invalid @enderror"
                                      placeholder="Who answered, when, and why">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="likert_scale_id" class="form-label">Likert scale used <span class="text-danger">*</span></label>
                            @include('partials.scale-select', ['scales' => $scales, 'selected' => $defaultScaleId])
                        </div>

                        <div>
                            <label for="survey_file" class="form-label">Response file <span class="text-danger">*</span></label>
                            <input type="file" id="survey_file" name="survey_file" accept=".csv,.xlsx"
                                   class="form-control @error('survey_file') is-invalid @enderror" required>
                            @error('survey_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                .csv or .xlsx, up to 5 MB. The first row must contain the question headers.
                                For Excel files, the first worksheet is used.
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center gap-2">
                        <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            Upload and Continue <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title-se"><i class="bi bi-lightbulb me-1"></i> Where do I get the file?</h2>
                </div>
                <div class="card-body">
                    <ul class="small mb-0 ps-3">
                        <li class="mb-2"><strong>Google Forms:</strong> Responses tab &rarr; &#8942; menu &rarr; <em>Download responses (.csv)</em>.</li>
                        <li class="mb-2"><strong>Google Sheets:</strong> File &rarr; Download &rarr; <em>Microsoft Excel (.xlsx)</em> or <em>CSV</em>.</li>
                        <li><strong>Excel:</strong> save normally as <em>.xlsx</em>, or File &rarr; Save As &rarr; <em>CSV UTF-8</em>.</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title-se"><i class="bi bi-check2-square me-1"></i> How answers are read</h2>
                </div>
                <div class="card-body small">
                    <p class="mb-2">Rated answers can be the <strong>number</strong> (1 = lowest) or the <strong>exact label</strong> of the scale, e.g. <em>Strongly Agree</em>. Letter case does not matter.</p>
                    <p class="mb-0">Columns like Timestamp, Name and Email are ignored automatically. You can check everything in the next step.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title-se"><i class="bi bi-download me-1"></i> Sample files to try</h2>
                </div>
                <ul class="list-group list-group-flush small">
                    @foreach ([
                        'sample-survey.csv' => ['30 respondents · numbers', '4-point Agreement'],
                        'lms-satisfaction-420-text-answers.csv' => ['420 respondents · text answers', '5-point Agreement'],
                        'hr-portal-260-7-point.csv' => ['260 respondents · numbers', '7-point Agreement'],
                        'iso25010-evaluation-75.csv' => ['75 respondents · text answers', '4-point Acceptability (ISO 25010)'],
                    ] as $file => [$note, $scale])
                        <li class="list-group-item px-3 py-2">
                            <a href="{{ asset('samples/'.$file) }}" download class="fw-semibold text-decoration-none">{{ $file }}</a>
                            <div class="text-secondary-se">{{ $note }} &middot; use "{{ $scale }}"</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
