<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approved - Hani</title>
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
            background-color: #4CAF50;
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
            background-color: #4CAF50;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Account Approved!</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->name }},</h2>
        
        <p>Great news! Your Hani client account has been approved by our admin team.</p>
        
        <p>You can now:</p>
        <ul>
            <li>Access all Hani features</li>
            <li>Browse stores and offers</li>
            <li>Use loyalty cards</li>
            <li>Make purchases</li>
            <li>Rate and review</li>
        </ul>
        
        <p><strong>Your account is now active and will remain so for 1 year.</strong></p>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url', 'https://hani.app') }}/login" class="button">
                Login to Your Account
            </a>
        </div>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <p>Welcome to Hani!</p>
        
        <p>Best regards,<br>
        The Hani Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $user->email }}. If you didn't expect this email, please ignore it.</p>
        <p>&copy; {{ date('Y') }} Hani. All rights reserved.</p>
    </div>
</body>
</html>
