<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PARDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --pards-primary: #2d2078;
            --pards-primary-dark: #170a58;
            --pards-ink: #0d075e;
            --pards-muted: #4d5273;
            --pards-panel: #edf2fb;
            --pards-border: #cbd4ea;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--pards-ink);
            font-family: Arial, Helvetica, sans-serif;
            background:
                linear-gradient(180deg, #f3ead7 0%, #f7f8fd 42%, #dfe5f5 100%);
        }

        .login-page {
            width: min(100%, 760px);
            margin: 0 auto;
            padding: 25px 24px 24px;
        }

        .school-header {
            text-align: center;
            margin-bottom: 27px;
        }

        .school-logo {
            display: block;
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin: 0 auto 12px;
            border-radius: 50%;
            clip-path: circle(50%);
        }

        .school-name {
            margin: 0;
            color: #101080;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 2px;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .school-location {
            margin: 4px 0 24px;
            color: #cf140f;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
        }

        .portal-title {
            margin: 0;
            color: var(--pards-ink);
            font-size: 25px;
            font-weight: 800;
            line-height: 1.25;
        }

        .login-card {
            overflow: hidden;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(35, 26, 98, .08);
        }

        .login-card-title {
            margin: 0;
            padding: 23px 24px 18px;
            color: #fff;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.25;
            text-align: center;
            background: var(--pards-primary);
        }

        .notice-section {
            padding: 22px 32px 26px;
            background: #fff;
            border-bottom: 1px solid #d9dfec;
        }

        .notice-title {
            margin: 0 0 16px;
            font-size: 14px;
            font-weight: 800;
        }

        .notice-copy {
            margin: 0;
            color: #343a6a;
            font-size: 15px;
            line-height: 1.75;
        }

        .login-form-section {
            padding: 30px 44px 28px;
            background: var(--pards-panel);
        }

        .form-label {
            color: var(--pards-ink);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .form-control {
            min-height: 44px;
            color: var(--pards-ink);
            border: 1px solid var(--pards-border);
            border-radius: 10px;
            background-color: #fff;
            font-size: 16px;
            padding: 10px 14px;
        }

        .form-control:focus {
            border-color: var(--pards-primary);
            box-shadow: 0 0 0 .2rem rgba(45, 32, 120, .15);
        }

        .password-field {
            position: relative;
        }

        .password-field .form-control {
            padding-right: 64px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            z-index: 2;
            transform: translateY(-50%);
            border: 0;
            color: var(--pards-primary-dark);
            background: transparent;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 6px;
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 14px 0 16px;
        }

        .form-check {
            min-height: 24px;
            margin: 0;
        }

        .form-check-input {
            width: 16px;
            height: 16px;
            margin-top: 3px;
            border-color: #8f95a8;
            border-radius: 2px;
        }

        .form-check-input:checked {
            border-color: var(--pards-primary);
            background-color: var(--pards-primary);
        }

        .form-check-label,
        .login-note {
            color: var(--pards-muted);
            font-size: 14px;
        }

        .create-account-link {
            color: var(--pards-primary);
            font-size: 14px;
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .create-account-link:hover,
        .create-account-link:focus-visible {
            color: var(--pards-primary-dark);
        }

        .consent-check {
            margin-bottom: 14px;
        }

        .consent-check .form-check-label {
            color: var(--pards-ink);
            font-weight: 800;
        }

        .login-button {
            width: 100%;
            min-height: 47px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: var(--pards-primary);
            font-size: 16px;
            font-weight: 800;
            box-shadow: 0 10px 18px rgba(45, 32, 120, .18);
        }

        .login-button:hover,
        .login-button:focus {
            color: #fff;
            background: var(--pards-primary-dark);
        }

        .alert {
            border-radius: 10px;
        }

        @media (max-width: 576px) {
            .login-page {
                padding: 18px 12px;
            }

            .school-logo {
                width: 98px;
                height: 98px;
            }

            .school-name {
                font-size: 14px;
                letter-spacing: 1.5px;
            }

            .portal-title {
                font-size: 22px;
            }

            .login-card-title {
                font-size: 26px;
            }

            .notice-section,
            .login-form-section {
                padding-left: 18px;
                padding-right: 18px;
            }

            .login-options {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    @include('layouts.partials.button-styles')
</head>
<body>
    <main class="login-page">
        <header class="school-header">
            <img src="{{ asset('ANHS LOGO.jpg') }}" alt="Abanon National High School logo" class="school-logo">
            <h1 class="school-name">Abanon National High School</h1>
            <p class="school-location">San Carlos City, Pangasinan</p>
            <p class="portal-title">PARDS: Property Accountability Records and Disposal System</p>
        </header>

        <section class="login-card" aria-labelledby="login-title">
            <h2 id="login-title" class="login-card-title">Login</h2>

            <div class="notice-section">
                <h3 class="notice-title">Privacy Notice</h3>
                <p class="notice-copy">
                    By checking the consent box below, I freely agree that <strong>authorized personnel of Abanon National High School</strong> may process the information I provide for login and access management in accordance with applicable privacy and data protection policies.
                </p>
            </div>

            <div class="login-form-section">
                @if (session('status'))
                    <div class="alert alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="{{ old('email') }}"
                            placeholder="Enter your Gmail Account"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-field">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter your password"
                                required
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-password-toggle
                                data-target="password"
                                aria-label="Show password"
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                class="form-check-input"
                            >
                            <label for="remember" class="form-check-label">Remember me</label>
                        </div>

                        <a href="{{ route('register') }}" class="create-account-link">Create Account</a>
                    </div>

                    <div class="form-check consent-check">
                        <input
                            type="checkbox"
                            id="privacy_consent"
                            name="privacy_consent"
                            class="form-check-input"
                            required
                        >
                        <label for="privacy_consent" class="form-check-label">I have read and agree to the privacy notice.</label>
                    </div>

                    <button type="submit" class="btn login-button">Login to PARDS</button>
                </form>

            </div>
        </section>  
    </main>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);

                if (! input) {
                    return;
                }

                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                button.textContent = isHidden ? 'Hide' : 'Show';
                button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        });
    </script>
    @include('partials.ajax-forms')
</body>
</html>
