<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DashboardIndexService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardIndexService $service)
    {
        $data = $service->handle($request);
        return view('dashboard.index', $data);
    }

    public function export(Request $request, DashboardIndexService $service)
    {
        $tab = (string) $request->get('tab', 'susan');
        $query = $service->buildFilteredQuery($request);

        $columns = [
            'id' => '#',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'status' => 'status',
            'popup_5dol' => 'popup_backredirect',
            'amount' => 'amount',
            'amount_cents' => 'amount_cents',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'method' => 'method',
            'email' => 'email',
            'phone' => 'phone',
            'cpf' => 'cpf',
            'ip' => 'ip',
            '_country' => 'country',
            '_region_code' => 'region_code',
            '_region' => 'region',
            '_city' => 'city',
            'event_time' => 'event_time',
            'utm_campaign' => 'utm_campaign',
            'page_url' => 'page_url',
            'client_user_agent' => 'client_user_agent',
            'fbp' => 'fbp',
            'fbc' => 'fbc',
            'fbclid' => 'fbclid',
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_content' => 'utm_content',
            'utm_term' => 'utm_term',
            'pix_key' => 'pix_key',
            'pix_description' => 'pix_description',
        ];

        if ($tab === 'susan') {
            $columns = array_merge($columns, [
                'external_id' => 'external_id',
                'give_payment_id' => 'give_payment_id',
                'transaction_id' => 'transaction_id',
                'currency' => 'currency',
                'give_form_id' => 'give_form_id',
            ]);
        }

        $filename = 'dashboard_' . $tab . '_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            if (!$out) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns), ';');

            foreach ($query->orderBy('created_at', 'desc')->cursor() as $row) {
                $line = [];
                foreach (array_keys($columns) as $key) {
                    $value = $row->{$key} ?? null;

                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    } elseif ($value === null) {
                        $value = '';
                    }

                    $line[] = (string) $value;
                }

                fputcsv($out, $line, ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
