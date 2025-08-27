<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Registration - Required Documents - Hani App</title>
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
            border-bottom: 2px solid #007bff;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .welcome-section {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
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
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
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
            <h2>Store Registration Successful</h2>
        </div>

        <div class="welcome-section">
            <h3>🎉 Welcome to Hani App, {{ $user->name }}!</h3>
            <p>Your store <strong>{{ $store->store_name }}</strong> has been registered successfully. However, before you can start using the platform, you need to complete a few more steps.</p>
        </div>

        <div class="info">
            <h4>📋 Next Steps:</h4>
            <ol>
                <li><strong>Verify your email</strong> using the OTP code sent separately</li>
                <li><strong>Prepare the required documents</strong> listed below</li>
                <li><strong>Wait for admin approval</strong> from your state's Hani App administrator</li>
                <li><strong>Visit the Hani App desktop application</strong> in your state to complete the verification process</li>
            </ol>
        </div>

        <div class="documents-section">
            <div class="documents-title">📋 Required Documents for Store Verification</div>
            <p>Please prepare the following documents to complete your store verification process:</p>
            
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

       

       

        <p>If you have any questions about the registration process, please contact the Hani App support team.</p>

        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
