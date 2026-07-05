<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: sans-serif; background-color: #FFF5F7; padding: 40px 20px; margin: 0;">
    <div style="background-color: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(255, 143, 171, 0.1); border: 1px solid #FFE5EC; max-width: 600px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 24px;">
            <span style="background-color: #FF8FAB; color: white; padding: 12px 24px; border-radius: 12px; font-weight: bold; font-size: 20px; letter-spacing: 1px; display: inline-block;">OptiTask</span>
        </div>
        <h2 style="color: #1e293b; font-size: 22px; font-weight: 700; margin-bottom: 16px; font-family: sans-serif;">Hello {{ $to_name }},</h2>
        <p style="color: #475569; font-size: 16px; line-height: 1.6; font-family: sans-serif;">You have a new update regarding your tasks:</p>
        <div style="background-color: #FFF9FA; border-left: 4px solid #FB6F92; padding: 16px; margin: 20px 0; border-radius: 8px;">
            <p style="color: #db2777; font-size: 16px; font-weight: 600; margin: 0; font-family: sans-serif;">{!! $message_content !!}</p>
        </div>
        <p style="color: #64748b; font-size: 14px; font-family: sans-serif;">Please log in to your dashboard to review this update.</p>
        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ route('login') }}" style="background-color: #FF8FAB; color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(255, 143, 171, 0.3);">Go to Dashboard</a>
        </div>
    </div>
    <div style="text-align: center; margin-top: 24px; color: #94a3b8; font-size: 12px; font-family: sans-serif;">
        <p>This is an automated notification. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
