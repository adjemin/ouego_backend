<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OUEGO — {{ $success ? 'Paiement reçu' : 'Paiement non abouti' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; min-height: 100vh; margin: 0;
               align-items: center; justify-content: center; text-align: center; padding: 24px; color: #1f2937; }
        a { display: inline-block; margin-top: 16px; padding: 12px 20px; border-radius: 8px;
            background: #111827; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<main>
    @if ($success)
        <h1>Paiement reçu</h1>
        <p>Nous confirmons votre paiement. Vous pouvez retourner dans l'application.</p>
    @else
        <h1>Paiement non abouti</h1>
        <p>Votre paiement n'a pas été effectué. Vous pouvez réessayer depuis l'application.</p>
    @endif

    @if ($deeplink)
        <a href="{{ $deeplink }}">Retourner dans l'application</a>
        <script>window.location.href = @json($deeplink);</script>
    @endif
</main>
</body>
</html>
