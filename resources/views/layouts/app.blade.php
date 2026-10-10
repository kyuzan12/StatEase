<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'StatEase') · StatEase</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    {{-- Main navigation: stays at the top while scrolling --}}
    <nav class="navbar navbar-expand-lg navbar-dark se-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ auth()->check() ? route('surveys.index') : route('login') }}">
                <i class="bi bi-bar-chart-line-fill me-1"></i> StatEase
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('surveys.*') && ! request()->routeIs('surveys.create') ? 'active' : '' }}" href="{{ route('surveys.index') }}">
                                <i class="bi bi-folder2-open me-1"></i> My Surveys
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('scales.*') ? 'active' : '' }}" href="{{ route('scales.index') }}">
                                <i class="bi bi-sliders me-1"></i> Likert Scales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('slovin.*') ? 'active' : '' }}" href="{{ route('slovin.index') }}">
                                <i class="bi bi-calculator me-1"></i> Sample Size
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2 my-2 my-lg-0">
                            <a class="btn btn-sm btn-new-survey px-3" href="{{ route('surveys.create') }}">
                                <i class="bi bi-plus-lg me-1"></i> New Survey
                            </a>
                        </li>
                        <li class="nav-item dropdown ms-lg-1">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> {{ \Illuminate\Support\Str::limit(auth()->user()->name, 18) }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="px-3 py-2 small text-secondary-se">
                                    Signed in as<br><strong class="text-body">{{ auth()->user()->email }}</strong>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">Log in</a>
                        </li>
                        <li class="nav-item ms-lg-2 my-2 my-lg-0">
                            <a class="btn btn-sm btn-new-survey px-3" href="{{ route('register') }}">Create an account</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        {{-- Only visible on paper: what this document is and when it was printed --}}
        <div class="print-header">
            <div><strong>StatEase</strong> &middot; @yield('title', 'StatEase')</div>
            <div>Printed {{ now()->format('F j, Y g:i A') }}</div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-check-circle-fill mt-1"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <span><i class="bi bi-bar-chart-line-fill me-1"></i> StatEase &middot; Survey data analysis for students and researchers</span>
            <span>Weighted mean · Reliability · Chi-square · Pearson r · ANOVA</span>
        </div>
    </footer>

    {{-- One confirmation dialog for the whole app (see the script below) --}}
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body d-flex gap-3">
                    <span class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <div>
                        <h2 class="h5 fw-semibold mb-1" id="confirmModalTitle">Are you sure?</h2>
                        <p class="mb-0 text-secondary-se" id="confirmModalMessage"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmModalButton">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="btn btn-primary back-to-top d-print-none" id="backToTop" aria-label="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. Confirmation dialog.
        //    Any form with data-confirm="message" asks first. Optional: data-confirm-title, data-confirm-button.
        (function () {
            const modalElement = document.getElementById('confirmModal');
            const modal = new bootstrap.Modal(modalElement);
            const confirmButton = document.getElementById('confirmModalButton');
            let pendingForm = null;

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (!form.dataset.confirm || form.dataset.confirmed === 'yes') return;

                event.preventDefault();
                pendingForm = form;
                document.getElementById('confirmModalTitle').textContent = form.dataset.confirmTitle || 'Are you sure?';
                document.getElementById('confirmModalMessage').textContent = form.dataset.confirm;
                confirmButton.textContent = form.dataset.confirmButton || 'Delete';
                modal.show();
            });

            confirmButton.addEventListener('click', () => {
                if (!pendingForm) return;
                pendingForm.dataset.confirmed = 'yes';
                confirmButton.disabled = true;
                pendingForm.submit();
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                pendingForm = null;
                confirmButton.disabled = false;
            });
        })();

        // 2. Copy buttons: data-copy-target="<element id>" copies that element's text.
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-target]');
            if (!button) return;

            const target = document.getElementById(button.dataset.copyTarget);
            const label = button.querySelector('span');
            try {
                await navigator.clipboard.writeText(target.innerText);
                label.textContent = 'Copied!';
            } catch (error) {
                window.getSelection().selectAllChildren(target);
                label.textContent = 'Press Ctrl+C';
            }
            setTimeout(() => label.textContent = 'Copy', 2000);
        });

        // 3. Back-to-top button appears after scrolling down.
        (function () {
            const button = document.getElementById('backToTop');
            const toggle = () => button.classList.toggle('is-visible', window.scrollY > 600);
            window.addEventListener('scroll', toggle, { passive: true });
            button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
            toggle();
        })();

        // 4. Tooltips (title text on hover) for elements with data-bs-toggle="tooltip".
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // 5. Printing charts. Chart.js does not resize charts for print by itself, so we give each chart
        //    the width of an A4 page before printing, and restore the normal size afterwards.
        //    (Chart.js documentation, "Printing Resizable Charts")
        window.addEventListener('beforeprint', () => {
            if (!window.Chart) return;
            const PRINT_WIDTH = 640; // fits inside an A4 page (minus margins and card padding), in CSS pixels
            Object.values(Chart.instances).forEach(chart => {
                chart.resize(PRINT_WIDTH, chart.canvas.parentNode.clientHeight);
            });
        });
        window.addEventListener('afterprint', () => {
            if (!window.Chart) return;
            Object.values(Chart.instances).forEach(chart => chart.resize());
        });
    </script>
    @stack('scripts')
</body>
</html>
