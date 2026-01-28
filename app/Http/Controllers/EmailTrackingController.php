<?php

namespace App\Http\Controllers;

use App\Models\EmailEvent;
use App\Models\EmailMessage;
use Illuminate\Http\Request;

class EmailTrackingController extends Controller
{
    public function index(Request $request)
    {
        // filtros iguais ao seu padrão
        $useRange = $request->boolean('periodo');

        $data = $request->input('data');       // YYYY-MM-DD
        $dataFim = $request->input('data_fim'); // YYYY-MM-DD
        $search = trim((string) $request->input('search', ''));

        $q = EmailMessage::query();

        // filtro data (sent_at)
        if ($data) {
            if ($useRange && $dataFim) {
                $q->whereBetween('sent_at', [
                    $data . ' 00:00:00',
                    $dataFim . ' 23:59:59',
                ]);
            } else {
                $q->whereBetween('sent_at', [
                    $data . ' 00:00:00',
                    $data . ' 23:59:59',
                ]);
            }
        }

        // busca
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('to_email', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('token', 'like', "%{$search}%");
            });
        }

        // para mostrar eventos por linha (sem N+1 pesado)
        $messages = $q->with(['events' => function ($e) {
            $e->orderBy('id', 'desc')->limit(50);
        }])
            ->orderByDesc('sent_at')
            ->paginate(30)
            ->withQueryString();

        // totais (gerais e filtrados) tipo seu dashboard
        $totaisFiltrados = (clone $q)->selectRaw('
            COUNT(*) as total,
            SUM(open_count) as opens,
            SUM(LEAST(click_count, 4)) as clicks
        ')->first();

        $opensAtLeastOne = (clone $q)->where('open_count', '>', 0)->count();
        $clicksTotal = (int) (clone $q)->selectRaw('SUM(LEAST(click_count, 4)) as clicks')->value('clicks');

        $totaisGerais = EmailMessage::selectRaw('
            COUNT(*) as total,
            SUM(open_count) as opens,
            SUM(LEAST(click_count, 4)) as clicks
        ')->first();

        return view('email_tracking.index', [
            'messages' => $messages,
            'totaisGerais' => $totaisGerais,
            'totaisFiltrados' => $totaisFiltrados,
            'opensAtLeastOne' => $opensAtLeastOne,
            'clicksTotal' => $clicksTotal,
        ]);
    }

    public function open(Request $request, string $token)
    {
        $msg = EmailMessage::where('token', $token)->first();

        $ip = (string) $request->ip();
        $ua = substr((string) $request->userAgent(), 0, 500);

        // Gmail/Google proxy costuma ter isso no UA
        $isGoogleProxy = stripos($ua, 'GoogleImageProxy') !== false;

        if ($msg) {
            $now = now();

            $lastAt = $msg->last_opened_at ? \Carbon\Carbon::parse($msg->last_opened_at) : null;
            $tooSoon = $lastAt && $lastAt->diffInSeconds($now) < 10;

            $msg->last_opened_at = $now;

            if (!$tooSoon) {
                $msg->open_count = (int) $msg->open_count + 1;
                $msg->first_opened_at = $msg->first_opened_at ?: $now;

                // opcional: salvar último ip/ua pra debug rápido no painel
                if (property_exists($msg, 'last_ip')) $msg->last_ip = $ip;
                if (property_exists($msg, 'last_ua')) $msg->last_ua = $ua;

                // opcional: marcar se foi via proxy
                if (property_exists($msg, 'opened_via_google_proxy')) {
                    $msg->opened_via_google_proxy = $isGoogleProxy;
                }

                $msg->save();

                EmailEvent::create([
                    'email_message_id' => $msg->id,
                    'type'             => 'open',
                    'ip'               => $ip,
                    'user_agent'       => $ua,
                    // se você tiver a coluna, ótimo:
                    // 'is_proxy'       => $isGoogleProxy,
                ]);
            } else {
                $msg->save();
            }
        }

        // 1x1 gif transparente
        $gif = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type'     => 'image/gif',
            // anti-cache forte (browser + proxies + CDN)
            'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'           => 'no-cache',
            'Expires'          => '0',
            'Surrogate-Control' => 'no-store',
            // evita indexação acidental desse endpoint
            'X-Robots-Tag'     => 'noindex, nofollow, noarchive'
        ]);
    }

    public function click(Request $request, string $token, string $key)
    {
        $msg = EmailMessage::where('token', $token)->firstOrFail();
        $links = (array)($msg->links ?? []);

        $defaultLinks = [
            'site' => 'https://susanpetrescue.org/',
            'facebook' => 'https://www.facebook.com/susanpetrescue',
            'instagram' => 'https://www.instagram.com/susanpetrescue',
            'contact' => 'https://susanpetrescue.org/about-us',
        ];

        $target = (string)($links[$key] ?? '');
        if ($target === '' && isset($defaultLinks[$key])) {
            $target = $defaultLinks[$key];
        }
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            abort(404);
        }

        $now = now();

        $msg->click_count = (int)$msg->click_count + 1;
        $msg->first_clicked_at = $msg->first_clicked_at ?: $now;
        $msg->last_clicked_at  = $now;
        $msg->save();

        EmailEvent::create([
            'email_message_id' => $msg->id,
            'type' => 'click',
            'link_key' => $key,
            'ip' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
        ]);

        return redirect()->away($target);
    }
}
