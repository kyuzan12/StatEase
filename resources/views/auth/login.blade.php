@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="auth-wrap">
        <div class="auth-intro">
            <h1 class="page-title">Welcome to StatEase</h1>
            <p class="page-lead">Upload your survey responses and get thesis-ready tables, charts and interpretations.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title-se">Log in to your account</h2>
            </div>
            <form action="{{ route('login') }}" method="POST" novalidate>
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password"
                               class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                        <label class="form-check-label" for="remember">Keep me logged in on this computer</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Log in
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center small text-secondary-se mt-3 mb-0">
            New to StatEase? <a href="{{ route('register') }}">Create an account</a>
        </p>
    </div>
@endsection
