<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importante: Actualización sobre tu membresía</title>
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
            color: #e74c3c;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .warning-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(to right, #e74c3c, #c0392b);
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            margin-top: 20px;
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
        <div class="warning-icon">⚠️</div>
        <h1>Importante: Actualización sobre tu membresía</h1>
        <p>Hola <strong>{{ $name }}</strong>,</p>
        <p>Intentamos hacer el cobro de tu membresía, pero no ha sido posible.</p>
        <p>Queremos ayudarte a ponerte al día para que tu servicio no se pause. Tienes <strong>24 horas</strong> para regularizar tu pago.</p>
        <p>Si necesitas asistencia o tienes alguna pregunta, no dudes en contactarnos. Estamos aquí para ayudarte.</p>
        <a href="https://wa.me/573002717873" class="btn">Contactar a Soporte</a>
        <p>¡Estamos pendientes! 😊</p>
        <p>Atentamente,<br>El equipo de Ezcala AI</p>
    </div>
</body>
</html>