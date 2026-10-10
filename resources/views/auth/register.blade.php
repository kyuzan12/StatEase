@extends('layouts.app')

@section('title', 'Create an account')

@section('content')
    <div class="auth-wrap">
        <div class="auth-intro">
            <h1 class="page-title">Create your StatEase account</h1>
            <p class="page-lead">Your surveys stay private: only you can see the data you upload.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title-se">Account details</h2>
            </div>
            <form action="{{ route('register') }}" method="POST" novalidate>
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" autofocus
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">8 to 72 characters, with letters and numbers.</div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="form-label">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                               class="form-control" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center small text-secondary-se mt-3 mb-0">
            Already have an account? <a href="{{ route('login') }}">Log in</a>
        </p>
    </div>
@endsection
