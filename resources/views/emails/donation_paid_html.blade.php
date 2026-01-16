@php
    $humanNow = $data['human_now'] ?? now()->format('M d, Y H:i');
    $payerName = $data['payer_name'] ?? ($data['first_name'] ?? 'friend');
    $amountLbl = $data['amount_label'] ?? '$0.00';
    $email = $data['email'] ?? '';
    $dynMessage = $data['dynamic_message'] ?? '';
    $donationId = $data['donation_id'] ?? ($data['external_id'] ?? '');
    $donatedAt = $data['donated_at'] ?? ($data['created_at_human'] ?? '');
    $method = $data['method'] ?? '';

    $token = $data['track_token'] ?? null;

    $link = function (string $key, string $fallback) use ($token) {
        if (!$token) {
            return $fallback;
        }
        return route('t.click', ['token' => $token, 'key' => $key]);
    };
@endphp

<!-- Preheader (hidden) -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Thank you for your donation! Here is your receipt.
</div>

<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">

<style>
    @media only screen and (max-width: 480px) {
        table.wrapper {
            width: 100% !important;
            max-width: 100% !important;
        }

        td.mobile-center {
            text-align: center !important;
            display: block !important;
            width: 100% !important;
        }
    }
</style>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="wrapper"
    style="max-width:600px; margin:0 auto; border:4px solid #8d0000; background:#ffffff; font-family:Poppins,Arial,Helvetica,sans-serif;">
    <tr>
        <td bgcolor="#FFFFFF" style="background:#ffffff;font-family:Poppins,Arial,Helvetica,sans-serif;">

            <!-- Header -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#8d0000"
                class="force-gold"
                style="background:#8d0000;color:#ffffff;font-family:Poppins,Arial,Helvetica,sans-serif;mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;">
                <tr>
                    <td bgcolor="#8d0000" style="padding:12px 16px;background:#8d0000;font-family:Poppins,Arial,Helvetica,sans-serif;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                            style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;">
                            <tr>
                                <td valign="middle" style="vertical-align:middle;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            <!-- Logo -->
                                            <td valign="middle" style="vertical-align:middle; line-height:0; font-size:0;">
                                                <a href="{{ $link('site', 'https://susanpetrescue.org/') }}" target="_blank" rel="noopener"
                                                    title="Susan Pet Rescue" style="text-decoration:none;display:inline-block;">
                                                    <img src="https://susanpetrescue.org/wp-content/uploads/2025/12/Susan-Pet-Rescue-vermelha.png"
                                                        alt="Susan Pet Rescue" width="85"
                                                        style="display:block;border:0;outline:none;text-decoration:none;border-radius:4px;height:auto;-ms-interpolation-mode:bicubic;">
                                                </a>
                                            </td>

                                            <!-- Date + email -->
                                            <td valign="middle" align="right" style="vertical-align:middle; padding-left:16px;">
                                                <div style="opacity:.95;color:#ffffff;font-family:Poppins,Arial,Helvetica,sans-serif;font-size:14px;line-height:1.4;word-break:break-word;">
                                                    <strong>{{ $humanNow }}</strong>
                                                </div>
                                                <div style="font-size:14px;line-height:1.4;word-break:break-word;">
                                                    <a href="mailto:contact@susanpetrescue.com"
                                                        style="color:#ffffff;text-decoration:none;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                                        contact@susanpetrescue.com
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Content -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF"
                class="force-white" style="background:#ffffff;font-family:Poppins,Arial,Helvetica,sans-serif;">
                <tr>
                    <td bgcolor="#FFFFFF"
                        style="padding:20px;background:#ffffff;font-family:Poppins,Arial,Helvetica,sans-serif;">
                        <h1
                            style="margin:0 0 8px 0;font-weight:600;font-size:22px;color:#1f2937;font-family:Poppins,Arial,Helvetica,sans-serif;">
                            Thank you {{ $payerName }}, for your donation!
                        </h1>

                        <p
                            style="margin:0 0 10px 0;font-size:15px;color:#1f2937;font-family:Poppins,Arial,Helvetica,sans-serif;">
                            <strong>Amount:</strong> {{ $amountLbl }}<br>
                            <strong>Your e-mail:</strong> {{ $email }}
                        </p>

                        <!-- 🔹 Mensagem dinâmica -->
                        @if (!empty($dynMessage))
                            <p
                                style="margin:12px 0 18px 0;font-size:15px;line-height:1.7;color:#111111;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                {!! nl2br(e($dynMessage)) !!}
                            </p>
                        @endif

                        <!-- Receipt block -->
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                            bgcolor="#F7F7F8"
                            style="margin:0 0 18px 0;background:#F7F7F8;border:1px solid #E5E7EB;border-radius:8px;font-family:Poppins,Arial,Helvetica,sans-serif;">
                            <tr>
                                <td bgcolor="#F7F7F8"
                                    style="padding:14px 16px;background:#F7F7F8;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                    <div
                                        style="font-size:14px;color:#1f2937;line-height:1.6;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                        <div><strong>Donation ID:</strong> {{ $donationId }}</div>
                                        <div><strong>Donated at:</strong> {{ $donatedAt }}</div>
                                        <div><strong>Donor name:</strong> {{ $payerName }}</div>
                                        <div><strong>Organization:</strong> Susan Pet Rescue</div>
                                        <div><strong>Amount:</strong> {{ $amountLbl }}</div>
                                        <div><strong>Payment method:</strong> {{ $method }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- Button -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td bgcolor="#8d0000" class="force-gold"
                                    style="background:#8d0000;border-radius:8px;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                    <a href="mailto:contact@susanpetrescue.com"
                                        style="display:inline-block;padding:12px 18px;font-weight:600;font-size:14px;
                            color:#ffffff;text-decoration:none;background:#8d0000;border-radius:8px;font-family:Poppins,Arial,Helvetica,sans-serif;">
                                        Any questions? Contact us here
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p
                            style="margin:10px 0 0 0;font-size:12.5px;color:#111111;font-family:Poppins,Arial,Helvetica,sans-serif;">
                            If you have any questions about your donation, please contact us via email.
                        </p>

                        <hr style="border:none;border-top:1px solid #CCCCCC;margin:20px 0;">
                        <div
                            style="font-size:14px;color:#555;font-style:italic;font-family:Poppins,Arial,Helvetica,sans-serif;">
                            With love,<br>
                            Susan and little angels! <br>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Footer -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                style="background:#8d0000;">
                <tr>
                    <td style="padding:16px 10px;text-align:center;">
                        <div
                            style="font-size:14px;line-height:1.6;font-family:Poppins,Arial,Helvetica,sans-serif;color:#ffffff;text-align:center;">
                            Follow us on social medias:
                            <a href="{{ $link('facebook', 'https://www.facebook.com/susanpetrescue') }}"
                                target="_blank" rel="noopener" style="margin:0 6px;display:inline-block;">
                                <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook"
                                    width="24" height="24" style="vertical-align:middle;">
                            </a>
                            <a href="{{ $link('instagram', 'https://www.instagram.com/susanpetrescue') }}"
                                target="_blank" rel="noopener" style="margin:0 6px;display:inline-block;">
                                <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram"
                                    width="24" height="24" style="vertical-align:middle;">
                            </a>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<style>
    [data-ogsc] .force-gold {
        background: #8d0000 !important;
    }

    [data-ogsc] .force-white {
        background: #FFFFFF !important;
    }
</style>

@if ($token)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td>
                <img src="{{ route('t.open', ['token' => $token]) }}?v={{ time() }}" 
                    width="1" height="1" alt=""
                    style="border:0;outline:none;text-decoration:none;display:block;width:1px;height:1px;opacity:0.01;max-height:1px;overflow:hidden" />
            </td>
        </tr>
    </table>
@endif
