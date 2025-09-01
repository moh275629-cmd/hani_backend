<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Action - Hani</title>
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
            background-color: #ff9800;
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
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 15px;
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
        <h1>📋 Report Action Taken</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->name }},</h2>
        
        <p>This email is to inform you that an action has been taken regarding a report filed against your account.</p>
        
        <div class="info">
            <p><strong>Action Taken:</strong> {{ ucfirst(str_replace('_', ' ', $action)) }}</p>
            @if($notes)
                <p><strong>Admin Notes:</strong> {{ $notes }}</p>
            @endif
        </div>
        
        @if($action === 'close_account')
            <div class="warning">
                <p><strong>Important:</strong> Your account has been temporarily deactivated due to the report filed against you.</p>
                <p>To reactivate your account, please contact your local Hani administrator for review.</p>
            </div>
        @elseif($action === 'warning')
            <div class="warning">
                <p><strong>Warning:</strong> This is an official warning regarding your account behavior.</p>
                <p>Please review your actions and ensure compliance with Hani's terms of service.</p>
            </div>
        @elseif($action === 'let_go')
            <div class="info">
                <p>The report against you has been reviewed and no action was taken.</p>
                <p>Your account remains active and unaffected.</p>
            </div>
        @endif
        
        <p><strong>What you should know:</strong></p>
        <ul>
            <li>All reports are reviewed by our admin team</li>
            <li>Actions are taken based on the severity of the reported behavior</li>
            <li>You can appeal any decision by contacting support</li>
            <li>Your account data and information are preserved</li>
        </ul>
        
        <p>If you have any questions about this action or would like to appeal, please contact our support team.</p>
        
        <p>Best regards,<br>
        The Hani Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $user->email }}. If you didn't expect this email, please ignore it.</p>
        <p>&copy; {{ date('Y') }} Hani. All rights reserved.</p>
    </div>
</body>
</html>
