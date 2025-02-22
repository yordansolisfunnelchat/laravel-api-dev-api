<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Ezcala AI</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #4a5568;
            background-color: #f7fafc;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-height: 50px;
        }
        h1 {
            color: #25D366;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .gradient-text {
            background: linear-gradient(to right, #25D366, #128C7E);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(to right, #25D366, #128C7E);
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            margin-top: 20px;
        }
        ul {
            list-style-type: none;
            padding-left: 0;
        }
        li {
            margin-bottom: 10px;
        }
        strong {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://ezcala-ai-2.s3.us-east-2.amazonaws.com/assets/logo-negro.png" alt="Ezcala AI Logo">
        </div>
        <h1>Bienvenido <span class="gradient-text">{{ $name }}</span> a Ezcala AI 🤖</h1>
        <p>Estamos super emocionados de tenerte aquí. Tu viaje hacia la automatización de ventas por WhatsApp comienza ahora.</p>
        <p>Aquí tienes los datos para ingresar:</p>
        <ul>
            <li><strong>Link:</strong> <a href="https://ai.ezcala.cloud">https://ai.ezcala.cloud</a></li>
            <li><strong>Usuario:</strong> {{ $email }}</li>
            <li><strong>Clave:</strong> ezcala123</li>
        </ul>
        <p>Te recomendamos cambiar tu contraseña después de tu primer inicio de sesión.</p>
        <a href="https://ai.ezcala.cloud" class="btn">Inicia sesión ahora</a>
        <p>¿Necesitas ayuda para comenzar? <a href="https://wa.me/573002717873">Da click aquí.</a> Estamos aquí para asegurarnos de que tengas éxito con Ezcala AI.</a></p>
    </div>
</body>
</html>