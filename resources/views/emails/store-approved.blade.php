<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Approved - Hani App</title>
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
            padding-bottom: 20px;
            border-bottom: 2px solid #28a745;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
        }
        .welcome-section {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Hani App</div>
            <h2>Store Approval Successful</h2>
        </div>

        <div class="welcome-section">
            <h3>Congratulations {{ $user->name }}!</h3>
            <p>Your store <strong>{{ $store->store_name }}</strong> has been approved and is now live on the Hani App platform.</p>
        </div>

        <div class="info">
            <h4>🎉 What's Next:</h4>
            <ol>
                <li><strong>Login to your store account</strong> and start managing your store</li>
                <li><strong>Create and publish offers</strong> to attract customers</li>
                <li><strong>Update your store information</strong> anytime through the app</li>
                <li><strong>Monitor your store performance</strong> and customer feedback</li>
                <li><strong>Contact Hani App support</strong> if you need any assistance</li>
            </ol>
        </div>

        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
