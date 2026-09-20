<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password | CobraCart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7f0;
            color: #09213d;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 105px;
        }

        .brand {
            font-size: 34px;
            font-weight: 700;
            color: #4d8508;
            margin-bottom: 28px;
        }

        .heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .heading h1 {
            margin: 0 0 12px;
            font-size: 34px;
            color: #061d39;
        }

        .heading p {
            margin: 0;
            font-size: 16px;
            color: #173d65;
        }

        .card {
            width: 490px;
            background: #ffffff;
            border-radius: 9px;
            border-top: 5px solid #f4b400;
            padding: 38px 27px 34px;
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.10);
        }

        .form-group {
            margin-bottom: 21px;
        }

        label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #09213d;
        }

        input {
            width: 100%;
            height: 50px;
            border: 1px solid #c9d2df;
            border-radius: 10px;
            background: #edf3fc;
            padding: 0 15px;
            font-size: 15px;
            color: #09213d;
            outline: none;
        }

        input:focus {
            border: 2px solid #f0b400;
            background: #f1f5fb;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 50px;
        }

        .eye-button {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .eye-button svg {
            width: 21px;
            height: 21px;
            stroke: #52657d;
        }

        .eye-button:hover svg {
            stroke: #4d8508;
        }

        .error {
            color: #d93025;
            font-size: 14px;
            margin-top: 6px;
        }

        .reset-button {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 8px;
            background: #4d850d;
            color: white;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 5px;
        }

        .reset-button:hover {
            background: #416f0b;
        }

        .back-login {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #176b08;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
        }

        .back-login:hover {
            text-decoration: underline;
        }

        .status-message {
            background: #e8f5e9;
            color: #28752b;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .page {
                padding: 50px 20px;
            }

            .card {
                width: 100%;
            }

            .brand {
                font-size: 30px;
            }

            .heading h1 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <x-customer-auth-brand />

    <div class="heading">
        <h1>Reset Password</h1>
        <p>Create a new password for your CobraCart account.</p>
    </div>

    <div class="card">

        @if (session('status'))
            <div class="status-message">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <!-- Token -->
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ $email ?? old('email') }}"
                    required
                    autocomplete="email"
                    placeholder="Enter your email address"
                >

                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <!-- New Password -->
            <div class="form-group">
                <label for="password">New Password</label>

                <div class="password-wrapper">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Enter your new password"
                    >

                    <button
                        type="button"
                        class="eye-button"
                        onclick="togglePassword('password', this)"
                        aria-label="Show password"
                    >
                        <!-- Eye icon -->
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>

                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>

                <div class="password-wrapper">
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm your new password"
                    >

                    <button
                        type="button"
                        class="eye-button"
                        onclick="togglePassword('password_confirmation', this)"
                        aria-label="Show password"
                    >
                        <!-- Eye icon -->
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="reset-button">
                Reset Password
            </button>

        </form>

        <a href="{{ route('login') }}" class="back-login">
            ← Back to Login
        </a>

    </div>

</div>

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);

        if (input.type === "password") {
            input.type = "text";
            button.setAttribute("aria-label", "Hide password");
        } else {
            input.type = "password";
            button.setAttribute("aria-label", "Show password");
        }
    }
</script>

</body>
</html>