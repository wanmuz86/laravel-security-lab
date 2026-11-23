<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSP Security Demo</title>
</head>
<body>
    <h1>Content Security Policy Demo</h1>
    <p id="result">Waiting for script...</p>

    <script nonce="{{ $cspNonce }}">
        document.getElementById('result').textContent = 'Authorized script executed successfully.';
    </script>

    <script>
        console.log('Unauthorized inline script');
    </script>
</body>
</html>
