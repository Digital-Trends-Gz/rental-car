<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reply to your inquiry</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:640px; margin:0 auto; padding:32px 20px;">
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:28px;">
            <h1 style="margin:0 0 12px; font-size:24px; line-height:1.3;">Reply to your inquiry</h1>
            <p style="margin:0 0 16px; color:#475569;">
                Thank you for contacting us about <strong>{{ $ticket->subject }}</strong>.
            </p>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; white-space:pre-line; line-height:1.7;">
{{ $replyMessage }}
            </div>

            <p style="margin:20px 0 0; color:#475569; line-height:1.7;">
                Ticket reference: <strong>{{ $ticket->ticket_number }}</strong>
            </p>

            <p style="margin:12px 0 0; color:#475569; line-height:1.7;">
                If you need anything else, just reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
