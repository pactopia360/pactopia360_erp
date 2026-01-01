<?php

namespace App\Http\Controllers\Cliente\Billing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class BillingPdfController
{
    public function statementPdf(Request $request)
    {
        $period = (string) $request->get('period', now()->format('Y-m'));

        $theme = strtolower((string) $request->get('theme', 'light'));
        if (!in_array($theme, ['light','dark'], true)) $theme = 'light';

        /**
         * IMPORTANTE:
         * Aquí debes traer tus datos reales del estado de cuenta.
         * Ejemplo:
         * $payload = app(\App\Services\Billing\StatementService::class)->build($period, auth()->id());
         *
         * Ajusta a tu flujo actual.
         */
        $items = $request->get('items', []); // reemplaza por tus items reales
        $total = (float) ($request->get('total', 0)); // reemplaza por tu total real

        $razon_social = $request->get('razon_social', '—');
        $rfc          = $request->get('rfc', '—');
        $email        = $request->get('email', '—');
        $account_id   = (int) ($request->get('account_id', 0));

        // LOGO (cacheado)
        $logoCacheKey = 'p360_pdf_logo_uri_'.$theme;
        $logo_data_uri = Cache::rememberForever($logoCacheKey, function () use ($theme) {
            $black = public_path('assets/client/p360-black.png');
            $white = public_path('assets/client/p360-white.png');

            $path = ($theme === 'dark') ? $white : $black;
            if (!is_file($path)) $path = is_file($black) ? $black : $white;
            if (!is_file($path)) return null;

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['jpg','jpeg'], true) ? 'image/jpeg' : 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
        });

        // QR (cacheado por periodo + url)
        $payUrl = route('cliente.billing.pay', ['period' => $period]); // AJUSTA a tu route real de pago
        $qrCacheKey = 'p360_pdf_qr_'.md5($period.'|'.$payUrl);

        $qr_data_uri = Cache::remember($qrCacheKey, now()->addDays(2), function () use ($payUrl) {
            if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                $png = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                    ->size(220)->margin(1)->generate($payUrl);

                return 'data:image/png;base64,'.base64_encode($png);
            }
            return null;
        });

        $pdf = Pdf::loadView('cliente.billing.pdf.statement', compact(
            'period','theme','items','total','razon_social','rfc','email','account_id',
            'logo_data_uri','qr_data_uri'
        ));

        $pdf->setPaper('letter', 'portrait');

        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'dpi' => 96,
            'defaultFont' => 'DejaVu Sans',
            'fontHeightRatio' => 1.0,
        ]);

        return $pdf->download('EstadoCuenta_'.$period.'.pdf');
    }
}
