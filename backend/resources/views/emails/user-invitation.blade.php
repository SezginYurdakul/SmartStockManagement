<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join {{ $companyName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .info-box {
            background-color: #f3f4f6;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>You're Invited!</h1>
        </div>

        <div class="content">
            <p>Hello,</p>
            
            <p><strong>{{ $inviterName }}</strong> has invited you to join <strong>{{ $companyName }}</strong> on our Smart Stock Management platform.</p>

            <div class="info-box">
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Company:</strong> {{ $companyName }}</p>
                <p><strong>Invited by:</strong> {{ $inviterName }}</p>
            </div>

            <p>To accept this invitation, please click the button below. You will need to provide:</p>

            <div class="info-box" style="background-color: #eff6ff; border-left-color: #3b82f6;">
                <p style="margin: 0 0 10px 0;"><strong>📝 Required Information:</strong></p>
                <p style="margin: 5px 0;">✓ First Name</p>
                <p style="margin: 5px 0;">✓ Last Name</p>
                <p style="margin: 5px 0;">✓ Password (minimum 8 characters)</p>
                <p style="margin: 5px 0;">✓ Password Confirmation</p>
            </div>

            <div class="button-container">
                <a href="{{ $acceptUrl }}" class="button">Accept Invitation</a>
            </div>

            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #2563eb; font-size: 14px;">{{ $acceptUrl }}</p>

            <div class="info-box">
                <p><strong>⚠️ Important:</strong> This invitation will expire on <strong>{{ $expiresAt }}</strong>.</p>
                <p>If you don't accept the invitation before it expires, you'll need to request a new one.</p>
            </div>

            <p>If you didn't expect this invitation, you can safely ignore this email.</p>
        </div>

        <div class="footer">
            <p>This is an automated message from {{ $companyName }}'s Smart Stock Management System.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
