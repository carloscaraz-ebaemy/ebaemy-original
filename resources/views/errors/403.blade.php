<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="sidebar-light sidebar-left-big-icons">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <title>Acceso denegado</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

    {{-- <link rel="stylesheet" href="{{ asset('porto-light/vendor/bootstrap/css/bootstrap.css') }}" /> --}}
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/animate/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/css/theme.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/css/custom.css') }}" />

    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --border: 214.3 31.8% 91.4%;
            --destructive: 0 84.2% 60.2%;
            --radius: 0.5rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(to bottom right, hsl(210, 40%, 98%), hsl(210, 40%, 96%));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: hsl(var(--foreground));
        }

        .card {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            width: 100%;
            max-width: 32rem;
            padding: 3rem 2rem;
            text-align: center;
        }

        .icon-container {
            width: 5rem;
            height: 5rem;
            margin: 0 auto 1.5rem;
            background: hsl(var(--destructive) / 0.1);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-container svg {
            width: 2.5rem;
            height: 2.5rem;
            stroke: hsl(var(--destructive));
        }

        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: hsl(var(--destructive));
            line-height: 1;
            margin-bottom: 1rem;
        }

        .title {
            font-size: 1.875rem;
            font-weight: 600;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }

        .description {
            font-size: 1rem;
            color: hsl(var(--muted-foreground));
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: calc(var(--radius) - 2px);
            font-size: 0.875rem;
            font-weight: 500;
            height: 2.5rem;
            padding: 0 1.5rem;
            background: hsl(var(--foreground));
            color: hsl(var(--background));
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .button:hover {
            opacity: 0.9;
        }

        .button:active {
            transform: scale(0.98);
        }

        @media (max-width: 640px) {
            .card {
                padding: 2.5rem 1.5rem;
            }

            .error-code {
                font-size: 3rem;
            }

            .title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-container">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        
        <div class="error-code">403</div>
        <h1 class="title">Acceso Denegado</h1>
        <p class="description">
            Su cuenta está inactiva y no tienes los permisos necesarios para acceder a este recurso.
        </p>

        <button class="button" onclick="goBack()">
            Volver Atrás
        </button>
    </div>

    <script>
        function goBack() {
            window.history.back();
        }
    </script>
</body>
</html>
