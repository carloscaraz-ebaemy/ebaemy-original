<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin conexión — ebaemy Marketplace</title>
    <meta name="theme-color" content="#0f8a82">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f8a82 0%, #0a6b64 100%);
            color: #fff;
            padding: 24px;
            text-align: center;
        }
        .offline-card {
            max-width: 380px;
        }
        .offline-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 24px;
            background: rgba(255,255,255,.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        p { font-size: 15px; line-height: 1.5; opacity: .9; margin-bottom: 28px; }
        button {
            background: #fff;
            color: #0f8a82;
            border: none;
            border-radius: 10px;
            padding: 13px 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .12s, box-shadow .12s;
        }
        button:active { transform: scale(.97); }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon">📡</div>
        <h1>Sin conexión</h1>
        <p>No pudimos cargar el marketplace. Verifica tu conexión a internet e intenta de nuevo.</p>
        <button onclick="location.reload()">Reintentar</button>
    </div>
</body>
</html>
