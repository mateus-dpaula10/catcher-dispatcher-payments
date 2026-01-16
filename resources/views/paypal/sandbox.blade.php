<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Sandbox - Simulador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0f1a;
            --bg-2: #141a2a;
            --card: #0f1626;
            --line: #1d2741;
            --ink: #f3f6ff;
            --muted: #b7c2dd;
            --accent: #f7c948;
            --accent-2: #66e2ff;
            --danger: #ff6b6b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
            color: var(--ink);
            background: radial-gradient(1200px 600px at 10% -10%, #1b2550 0%, transparent 60%),
                        radial-gradient(1000px 500px at 90% 10%, #1f3a30 0%, transparent 60%),
                        linear-gradient(180deg, var(--bg) 0%, #0a1220 100%);
            min-height: 100vh;
            padding: 24px;
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        header {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        header h1 {
            font-size: 28px;
            margin: 0;
            letter-spacing: 0.4px;
        }

        header p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.01) 100%), var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 20px 50px rgba(3, 10, 30, 0.35);
        }

        .card h2 {
            margin: 0 0 14px 0;
            font-size: 18px;
            letter-spacing: 0.2px;
        }

        .field {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
        }

        .field label {
            font-size: 13px;
            color: var(--muted);
        }

        .field input, .field select, .field textarea {
            width: 100%;
            background: #0c1424;
            border: 1px solid #22314f;
            color: var(--ink);
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        .field input:focus, .field select:focus, .field textarea:focus {
            border-color: var(--accent-2);
            box-shadow: 0 0 0 2px rgba(102, 226, 255, 0.15);
        }

        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:active { transform: translateY(1px); }

        .btn-primary {
            background: var(--accent);
            color: #1b1405;
            box-shadow: 0 10px 24px rgba(247, 201, 72, 0.22);
        }

        .btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1px dashed #3a4a72;
        }

        .status {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(102, 226, 255, 0.08);
            border: 1px solid rgba(102, 226, 255, 0.3);
            color: var(--muted);
            font-size: 13px;
        }

        .log {
            background: #0a111f;
            border: 1px solid #1e2a45;
            padding: 12px;
            border-radius: 12px;
            min-height: 200px;
            font-size: 12px;
            color: #c7d2f0;
            white-space: pre-wrap;
        }

        .alert {
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ffd2d2;
            font-size: 13px;
        }

        .tips {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        .paypal-box {
            border: 1px solid #24355a;
            border-radius: 12px;
            padding: 12px;
            background: #0c1426;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
            header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>PayPal Sandbox - Simulador de Pagamento</h1>
        <p>Crie a order, aprove o pagamento com conta sandbox e capture via seus endpoints.</p>
    </header>

    <div class="grid">
        <section class="card">
            <h2>Dados do pagamento</h2>

            <div class="row">
                <div class="field">
                    <label for="amount">Valor</label>
                    <input id="amount" type="number" min="1" step="0.01" value="10.00">
                </div>
                <div class="field">
                    <label for="currency">Moeda</label>
                    <select id="currency">
                        <option value="USD">USD</option>
                        <option value="BRL">BRL</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="period">Tipo</label>
                    <select id="period">
                        <option value="one_time">One time</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="field">
                    <label for="external_id">External ID (opcional)</label>
                    <input id="external_id" type="text" placeholder="auto">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="first_name">Nome</label>
                    <input id="first_name" type="text" value="John">
                </div>
                <div class="field">
                    <label for="last_name">Sobrenome</label>
                    <input id="last_name" type="text" value="Doe">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" value="buyer@example.com">
                </div>
                <div class="field">
                    <label for="phone">Telefone</label>
                    <input id="phone" type="text" value="+1 555 0100">
                </div>
            </div>

            <div class="field">
                <label for="page_url">Page URL</label>
                <input id="page_url" type="text" value="https://susanpetrescue.org/">
            </div>

            <div class="row">
                <div class="field">
                    <label for="utm_source">utm_source</label>
                    <input id="utm_source" type="text" value="sandbox">
                </div>
                <div class="field">
                    <label for="utm_campaign">utm_campaign</label>
                    <input id="utm_campaign" type="text" value="B1S">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="utm_medium">utm_medium</label>
                    <input id="utm_medium" type="text" value="paypal">
                </div>
                <div class="field">
                    <label for="fbclid">fbclid</label>
                    <input id="fbclid" type="text" placeholder="opcional">
                </div>
            </div>

            <div class="btns">
                <button class="btn-primary" id="btnCreate">Criar order (API)</button>
                <button class="btn-ghost" id="btnCapture">Capturar order (API)</button>
            </div>

            <div class="status" id="statusBox">Status: pronto</div>
        </section>

        <aside class="card">
            <h2>PayPal Buttons</h2>
            <div class="paypal-box" id="paypalBox">
                <div id="paypalButtons"></div>
            </div>
            <div class="status" id="orderInfo">Order: -</div>

            <h2 style="margin-top:18px;">Logs</h2>
            <div class="log" id="log"></div>

            <div class="tips" style="margin-top:12px;">
                Use a conta sandbox Personal para aprovar. Depois o capture ocorre automaticamente no onApprove.
            </div>
        </aside>
    </div>
</div>

<script>
    (function () {
        const PAYPAL_DONATE_SDK = 'https://www.paypalobjects.com/donate/sdk/donate-sdk.js';
        const PAYPAL_HOSTED_BUTTON_ID = 'YEFP7T8X23SLC';
        const PAYPAL_NOTIFY_ENDPOINT = '/api/paypal/donation-notify';
        const logEl = document.getElementById('log');
        const statusEl = document.getElementById('statusBox');
        const orderInfoEl = document.getElementById('orderInfo');
        const donateContainer = document.getElementById('paypalButtons');

        const log = (msg, obj) => {
            const line = obj ? `${msg} ${JSON.stringify(obj, null, 2)}` : msg;
            logEl.textContent = `${new Date().toISOString()} - ${line}\n` + logEl.textContent;
        };

        const setStatus = (text) => { statusEl.textContent = `Status: ${text}`; };
        const setOrderInfo = (orderId = null, externalId = null) => {
            orderInfoEl.textContent = orderId ? `Order: ${orderId} | external_id: ${externalId || '-'}` : 'Order: -';
        };

        const readForm = () => ({
            amount: parseFloat(document.getElementById('amount').value || '0'),
            currency: document.getElementById('currency').value,
            period: document.getElementById('period').value,
            external_id: document.getElementById('external_id').value || undefined,
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            page_url: document.getElementById('page_url').value,
            utm_source: document.getElementById('utm_source').value,
            utm_campaign: document.getElementById('utm_campaign').value,
            utm_medium: document.getElementById('utm_medium').value,
            fbclid: document.getElementById('fbclid').value,
        });

        let donateSdkPromise = null;
        let donateRenderKey = '';

        function loadDonateSdk() {
            if (donateSdkPromise) return donateSdkPromise;
            donateSdkPromise = new Promise((resolve, reject) => {
                if (window.PayPal?.Donation) return resolve(window.PayPal);
                const script = document.createElement('script');
                script.src = PAYPAL_DONATE_SDK;
                script.charset = 'UTF-8';
                script.async = true;
                script.onload = () => {
                    if (window.PayPal?.Donation) resolve(window.PayPal);
                    else reject(new Error('PayPal Donate SDK não disponível'));
                };
                script.onerror = () => reject(new Error('Falha ao carregar o PayPal Donate SDK'));
                document.head.appendChild(script);
            });
            return donateSdkPromise;
        }

        async function notifyPayPalDonation(params, amount, donorInfo) {
            const orderId = params?.tx;
            if (!orderId) {
                log('notifyPayPalDonation: orderId ausente', params);
                setStatus('ordem ausente');
                return false;
            }

            const payload = {
                orderId,
                amount,
                currency: donorInfo.currency,
                period: donorInfo.period || 'one_time',
                external_id: donorInfo.external_id || ensureExternalId(),
                first_name: donorInfo.first_name || '',
                last_name: donorInfo.last_name || '',
                email: donorInfo.email || '',
                phone: donorInfo.phone || '',
                page_url: donorInfo.page_url || '',
                utm_source: donorInfo.utm_source || '',
                utm_campaign: donorInfo.utm_campaign || '',
                utm_medium: donorInfo.utm_medium || '',
                utm_content: donorInfo.utm_content || '',
                utm_term: donorInfo.utm_term || '',
                fbclid: donorInfo.fbclid || '',
                fbp: donorInfo.fbp || '',
                fbc: donorInfo.fbc || '',
            };

            try {
                const res = await fetch(PAYPAL_NOTIFY_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.ok) {
                    log('donation-notify falhou', json);
                    setStatus('notificação falhou');
                    return false;
                }
                log('donation-notify ok', json);
                setStatus('doação registrada');
                setOrderInfo(json.order_id, json.external_id);
                return true;
            } catch (err) {
                log('donation-notify erro fetch', err);
                setStatus('erro de rede');
                return false;
            }
        }

        function ensureExternalId(){
            const stored = window.__PP_EXTID;
            if (stored) return stored;
            const id = 'don_' + Date.now() + '_' + Math.random().toString(16).slice(2);
            window.__PP_EXTID = id;
            return id;
        }

        async function renderDonateButton() {
            if (!donateContainer) return;
            const data = readForm();
            const amount = Number(data.amount || 0);
            const key = `${data.currency}|${data.period}|${amount}|${data.external_id || ''}`;
            if (donateRenderKey === key) return;
            donateRenderKey = key;
            donateContainer.innerHTML = '';
            log('renderPayPalDonateButton', { amount, currency: data.currency });

            try {
                await loadDonateSdk();
                if (!window.PayPal?.Donation) throw new Error('SDK não disponível');

                const config = {
                    env: 'sandbox',
                    hosted_button_id: PAYPAL_HOSTED_BUTTON_ID,
                    notify_url: PAYPAL_NOTIFY_ENDPOINT,
                    image: {
                        src: 'https://pics-v2.sandbox.paypal.com/00/s/NzAzMTM3ZTctMmQwNC00YTlhLWFkODgtNGNjYmU0YzAxYTgy/file.PNG',
                        alt: 'Donate with PayPal button',
                        title: 'PayPal - The safer, easier way to pay online!',
                    },
                    onComplete: async (params) => {
                        log('PayPal donate onComplete', params);
                        await notifyPayPalDonation(params, amount, {
                            ...data,
                            currency: data.currency,
                            fbclid: data.fbclid,
                            period: data.period,
                        });
                    },
                    onError: (err) => {
                        log('PayPal donate error', err);
                        setStatus('erro no PayPal');
                    },
                };

                if (amount > 0) {
                    config.amount = {
                        value: amount.toFixed(2),
                        currency: data.currency,
                    };
                }

                if (data.email) {
                    config.payer = { email_address: data.email };
                }

                window.PayPal.Donation.Button(config).render('#paypalButtons');
            } catch (err) {
                log('Falha ao renderizar PayPal donate', err);
                setStatus('botão indisponível');
            }
        }

        ['amount', 'currency', 'period', 'email', 'external_id', 'first_name', 'last_name', 'phone', 'page_url', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'fbclid'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) return;
            input.addEventListener('input', () => {
                renderDonateButton();
            });
        });
        ['btnCreate', 'btnCapture'].forEach((id) => {
            const button = document.getElementById(id);
            if (button) button.disabled = true;
        });

        renderDonateButton();
        setOrderInfo();
    })();
</script>
</body>
</html>
