<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password - CobraCart</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f7f9f3;
            font-family: Arial, Helvetica, sans-serif;
            color: #06264d;
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 105px;
        }

        .brand {
            font-size: 34px;
            font-weight: 700;
            color: #4d850d;
            margin-bottom: 27px;
        }

        .auth-title {
            font-size: 32px;
            font-weight: 700;
            color: #061c3a;
            margin: 0 0 10px 0;
            text-align: center;
        }

        .auth-subtitle {
            font-size: 16px;
            color: #17385f;
            margin-bottom: 35px;
            text-align: center;
        }

        .auth-card {
            width: 490px;
            background: #ffffff;
            border-radius: 9px;
            border-top: 4px solid #f2b500;
            padding: 38px 27px 34px 27px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.10);
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #09284e;
            margin-bottom: 8px;
        }

        input[type="email"] {
            width: 100%;
            height: 50px;
            padding: 0 16px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: #eaf2ff;
            color: #071d3a;
            font-size: 15px;
            outline: none;
        }

        input[type="email"]:focus {
            border: 2px solid #f2b500;
            box-shadow: 0 0 0 1px #f2b500;
        }

        .error-message {
            color: #dc2626;
            font-size: 14px;
            margin-top: 7px;
        }

        .status-message {
            background: #e9f7e8;
            color: #34700a;
            border: 1px solid #b8dcae;
            border-radius: 7px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .submit-button {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 8px;
            background: #4d850d;
            color: white;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.12);
            transition: 0.2s ease;
        }

        .submit-button:hover {
            background: #416f0b;
        }

        .back-link {
            margin-top: 32px;
            color: #347500;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .auth-container {
                padding: 70px 20px 40px;
            }

            .auth-card {
                width: 100%;
                max-width: 490px;
            }

            .brand {
                font-size: 30px;
            }

            .auth-title {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

    <div class="auth-container">

        <!-- CobraCart -->
        <div class="brand">
            CobraCart
        </div>

        <!-- Title -->
        <h1 class="auth-title">
            Forgot password?
        </h1>

        <div class="auth-subtitle">
            Enter your email address to reset your password.
        </div>

        <!-- Card -->
        <div class="auth-card">

            @if (session('status'))
                <div class="status-message">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="status-message" style="background:#fff1f1; color:#b91c1c; border-color:#f1b5b5;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="form-group">
                    <label for="email">
                        Email Address
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Enter your email address"
                        required
                        autofocus
                    >

                    @error('email')
                        <div class="error-message">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="submit-button">
                    Send Password Reset Link
                </button>
            </form>

        </div>

        <!-- Back to Login -->
        <a href="{{ route('login') }}" class="back-link">
            ← Back to Login
        </a>

    </div>

</body>

</html>