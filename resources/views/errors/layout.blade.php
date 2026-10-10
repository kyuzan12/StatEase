{{--
    Shared error page in the StatEase design. Laravel shows resources/views/errors/<code>.blade.php
    automatically for that HTTP error (403, 404, 419, 429, 500).
--}}
@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="auth-wrap text-center">
        <div class="card">
            <div class="card-body py-5">
                <div class="error-code">{{ $code }}</div>
                <h1 class="h4 fw-bold mt-2">{{ $title }}</h1>
                <p class="text-secondary-se mb-4">{{ $message }}</p>
                <a href="{{ auth()->check() ? route('surveys.index') : route('login') }}" class="btn btn-primary">
                    <i class="bi bi-house me-1"></i> {{ auth()->check() ? 'Back to My Surveys' : 'Go to the login page' }}
                </a>
            </div>
        </div>
    </div>
@endsection
