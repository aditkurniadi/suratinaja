<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Bergabung ke {{ $workspace->name ?? 'Workspace' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .content p {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 13px;
        }
        .button-wrapper {
            margin: 28px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            background: #0284c7;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 14px;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }
        .link-text {
            word-break: break-all;
            color: #0284c7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SuratinAja</h1>
        </div>
        <div class="content">
            <p>Halo,</p>
            <p>
                <strong>{{ $inviter->name ?? 'Admin' }}</strong> telah mengundang Anda untuk bergabung ke dalam workspace:
            </p>
            <p style="font-size: 16px; font-weight: 600; color: #0f172a;">
                🏛️ {{ $workspace->name }}
            </p>
            <p>
                Peran yang diberikan kepada Anda: <span class="badge">{{ $roleName }}</span>
            </p>
            <div class="button-wrapper">
                <a href="{{ $inviteUrl }}" class="button" target="_blank">
                    Terima Undangan & Bergabung
                </a>
            </div>
            <p style="font-size: 12px; color: #64748b;">
                Tautan undangan ini berlaku selama <strong>7 hari</strong> hingga {{ $invitation->expires_at?->translatedFormat('d F Y') }}.
            </p>
        </div>
        <div class="footer">
            <p>
                Jika tombol di atas tidak dapat diklik, salin dan tempel URL berikut pada peramban web Anda:<br>
                <a href="{{ $inviteUrl }}" class="link-text">{{ $inviteUrl }}</a>
            </p>
            <p style="margin-top: 16px;">
                Jika Anda merasa tidak mengenal pengundang ini, Anda dapat mengabaikan email ini dengan aman.
            </p>
        </div>
    </div>
</body>
</html>
