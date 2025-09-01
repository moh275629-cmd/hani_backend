<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Expired - Hani</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f44336;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            background-color: #2196F3;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Account Expired</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->name }},</h2>
        
        <div class="warning">
            <p><strong>Important Notice:</strong> Your Hani account has expired and has been temporarily deactivated.</p>
        </div>
        
        <p>Your account was automatically deactivated because it has reached its expiration date. This is a security measure to ensure account safety.</p>
        
        <p><strong>What this means:</strong></p>
        <ul>
            <li>You cannot currently access your Hani account</li>
            <li>All your data and information are safely preserved</li>
            <li>You can reactivate your account by contacting an admin</li>
        </ul>
        
        <p><strong>To reactivate your account:</strong></p>
        <ol>
            <li>Contact your local Hani administrator</li>
            <li>Provide your account details</li>
            <li>Wait for approval and reactivation</li>
        </ol>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <p>We look forward to welcoming you back to Hani!</p>
        
        <p>Best regards,<br>
        The Hani Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $user->email }}. If you didn't expect this email, please ignore it.</p>
        <p>&copy; {{ date('Y') }} Hani. All rights reserved.</p>
    </div>
</body>
</html>
