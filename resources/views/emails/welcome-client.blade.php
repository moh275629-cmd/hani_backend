<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Hani App - Client Registration</title>
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
            border-bottom: 2px solid #1E3A8A;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1E3A8A;
            margin-bottom: 10px;
        }
        .welcome-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .documents-section {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .documents-title {
            color: #856404;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .document-item {
            background-color: #ffffff;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
        .document-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .document-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .document-details {
            font-size: 12px;
            color: #888;
        }
        .admin-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .admin-title {
            color: #1565c0;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .admin-details {
            color: #333;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .warning {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Hani App</div>
            <h2>Welcome to Hani App!</h2>
        </div>

        <div class="success">
            <strong>🎉 Congratulations!</strong> Your account has been successfully verified and activated.
        </div>

        <div class="welcome-section">
            <h3>Hello {{ $user->name }}!</h3>
            <p>Welcome to Hani App! Your account has been successfully created and verified. You can now start exploring our amazing offers and discounts from local stores in your area.</p>
        </div>

        @if($requiredDocuments && count($requiredDocuments) > 0)
        <div class="documents-section">
            <div class="documents-title">📋 Required Documents for Account Verification</div>
            <p>To complete your account setup and unlock all features, please prepare the following documents:</p>
            
            @foreach($requiredDocuments as $document)
            <div class="document-item">
                <div class="document-name">{{ $document->getDocumentName('en') }}</div>
                <div class="document-description">{{ $document->getDescription('en') }}</div>
                <div class="document-details">
                    <strong>File Types:</strong> {{ $document->getFileTypesText() }} | 
                    <strong>Max Size:</strong> {{ $document->getMaxFileSizeText() }} | 
                    <strong>Required:</strong> {{ $document->isRequired() ? 'Yes' : 'No' }}
                </div>
            </div>
            @endforeach
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> Please ensure all required documents are properly scanned or photographed in clear, readable format. 
            You can upload these documents through the app to complete your account verification.
        </div>
        @endif

        @if($adminInfo)
        <div class="admin-info">
            <div class="admin-title">📍 Regional Admin Contact Information</div>
            <div class="admin-details">
                @if($adminInfo['office_address'])
                <p><strong>Office Address:</strong> {{ $adminInfo['office_address'] }}</p>
                @endif
                @if($adminInfo['office_location_lat'] && $adminInfo['office_location_lng'])
                <p><strong>Location:</strong> 
                    <a href="https://maps.google.com/?q={{ $adminInfo['office_location_lat'] }},{{ $adminInfo['office_location_lng'] }}" 
                       target="_blank" style="color: #1E3A8A;">
                        View on Google Maps
                    </a>
                </p>
                @endif
                @if($adminInfo['phone'])
                <p><strong>Phone:</strong> {{ $adminInfo['phone'] }}</p>
                @endif
                @if($adminInfo['email'])
                <p><strong>Email:</strong> {{ $adminInfo['email'] }}</p>
                @endif
            </div>
        </div>
        @endif

        <div class="welcome-section">
            <h4>What's Next?</h4>
            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                <li>Complete your profile information</li>
                <li>Upload required documents for verification</li>
                <li>Explore nearby stores and offers</li>
                <li>Start earning loyalty points</li>
            </ul>
        </div>

        <p>If you have any questions or need assistance, please don't hesitate to contact our support team or your regional admin.</p>

        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
