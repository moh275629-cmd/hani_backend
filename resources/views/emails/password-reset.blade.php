<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password Reset - Hani App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1E3A8A;
            margin-bottom: 10px;
        }
        .otp-code {
            background-color: #1E3A8A;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            margin: 20px 0;
        }
        .message {
            margin: 20px 0;
            font-size: 16px;
        }
        .warning {
            background-color: #FEF3C7;
            border: 1px solid #F59E0B;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Hani App</div>
            <h2>Password Reset</h2>
        </div>
        
        <div class="message">
            <p>Hello!</p>
            <p>You have requested to reset your password for your Hani App account.</p>
        </div>
        
        <div class="otp-code">
            {{ $otp }}
        </div>
        
        <div class="message">
            <p>Please enter this code in the app to reset your password.</p>
            <p>This code will expire in 10 minutes.</p>
        </div>
        
        <div class="warning">
            <strong>Security Notice:</strong>
            <ul>
                <li>Never share this OTP with anyone</li>
                <li>Our team will never ask for this code</li>
                <li>If you didn't request a password reset, please ignore this email</li>
                <li>Your current password will remain unchanged until you complete the reset process</li>
            </ul>
        </div>
        
        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
