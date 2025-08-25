<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Registration OTP - Hani App</title>
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
        .otp-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 5px;
            margin: 15px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Hani App</div>
            <h2>Store Registration Verification</h2>
        </div>

        <p>Hello,</p>
        
        <p>Thank you for registering your store with Hani App. To complete your registration, please use the following OTP code:</p>

        <div class="otp-section">
            <p><strong>Your OTP Code:</strong></p>
            <div class="otp-code">{{ $otp }}</div>
            <p><small>This code will expire in 10 minutes</small></p>
        </div>

        <div class="documents-section">
            <div class="documents-title">📋 Required Documents for Store Registration</div>
            <p>Please prepare the following documents to complete your store verification:</p>
            
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
            Incomplete or unclear documents may delay your store approval process.
        </div>

        <p>After verifying your OTP, you will be able to upload these documents through the app to complete your store registration.</p>

        <p>If you didn't request this verification, please ignore this email.</p>

        <div class="footer">
            <p>This is an automated message from Hani App. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Hani App. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
