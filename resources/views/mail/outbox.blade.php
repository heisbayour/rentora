<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $message->title }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F7F9;font-family:Arial,Helvetica,sans-serif;color:#1F2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F7F9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">
                    <tr>
                        <td style="padding:24px 24px 0;font-size:16px;font-weight:bold;color:#1A73E8;">{{ $organization }}</td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 0;font-size:20px;font-weight:bold;">{{ $message->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;font-size:15px;line-height:22px;">
                            @if ($name)
                                <p style="margin:0 0 12px;">{{ __('messaging.mail.greeting', ['name' => $name]) }}</p>
                            @endif
                            {!! nl2br(e($message->body)) !!}
                        </td>
                    </tr>
                    @if ($message->link)
                        <tr>
                            <td style="padding:0 24px 24px;">
                                <a href="{{ $message->link }}" style="display:inline-block;background:#1A73E8;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:bold;">{{ $message->actionLabel ?? __('messaging.mail.open') }}</a>
                            </td>
                        </tr>
                    @endif
                </table>
                <p style="font-size:12px;color:#6B7280;margin:16px 0 0;">{{ __('messaging.mail.footer', ['org' => $organization]) }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
