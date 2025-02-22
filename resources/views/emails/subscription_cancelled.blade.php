<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción Cancelada - Ezcala AI</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://ezcala-ai-2.s3.us-east-2.amazonaws.com/assets/logo-negro.png" alt="Ezcala AI Logo">
        </div>
        <h1>Hola <span class="gradient-text">{{ $name }}</span>,</h1>
        <p>Lamentamos informarte que tu suscripción a Ezcala AI ha sido cancelada.</p>
        <p>Valoramos mucho el tiempo que has pasado con nosotros y esperamos que hayas encontrado valor en nuestro servicio.</p>
        <p>Si tienes alguna pregunta o deseas reactivar tu cuenta, estamos aquí para ayudarte.</p>
        <a href="https://wa.me/573002717873" class="btn">Contactar soporte</a>
        <p>Gracias por haber sido parte de Ezcala AI. Te deseamos éxito en tus futuros proyectos.</p>
    </div>
</body>
</html>