<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Account Rejected - Hani App</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f4f4; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #007bff; }
        .logo { font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 10px; }
        .warning { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Hani App</div>
            <h2>Account Rejected</h2>
        </div>

        <div class="warning">
            <h3>Dear {{ $user->name }}!</h3>
            <p>Your client account has been rejected. Reason: <strong>{{ $reason }}</strong></p>
        </div>

        <div class="info">
            <h4>What you can do:</h4>
            <ol>
                <li>Review the reason and prepare correct information</li>
                <li>Contact Hani App support if you need clarification</li>
                <li>Re-register with accurate details</li>
            </ol>
        </div>

        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>


