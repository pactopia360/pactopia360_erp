<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\Concerns\HandlesStatementQrImages.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Concerns;

use BaconQrCode\Renderer\Image\GdImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;

trait HandlesStatementQrImages
{
    /**
     * Precompone QR + logo al centro y regresa data:image/png;base64,...
     */
    private function overlayLogoOnQrDataUri(
        string $qrDataUri,
        string $logoPath,
        int $logoPx = 38,
        int $paddingPx = 4
    ): ?string {
        try {
            $logoPx    = max(18, min(80, (int) $logoPx));
            $paddingPx = max(0, min(12, (int) $paddingPx));

            if (!preg_match('#^data:image/[^;]+;base64,#i', $qrDataUri)) {
                return null;
            }

            $b64   = preg_replace('#^data:image/[^;]+;base64,#i', '', $qrDataUri);
            $qrBin = base64_decode((string) $b64, true);

            if (!$qrBin || strlen($qrBin) < 20) {
                return null;
            }

            $qrImg = @imagecreatefromstring($qrBin);
            if (!$qrImg) {
                return null;
            }

            $logoBin = @file_get_contents($logoPath);
            if (!$logoBin || strlen($logoBin) < 20) {
                @imagedestroy($qrImg);
                return null;
            }

            $logoImg = @imagecreatefromstring($logoBin);
            if (!$logoImg) {
                @imagedestroy($qrImg);
                return null;
            }

            imagealphablending($qrImg, true);
            imagesavealpha($qrImg, true);

            imagealphablending($logoImg, true);
            imagesavealpha($logoImg, true);

            $logoScaled = imagescale($logoImg, $logoPx, $logoPx, IMG_BILINEAR_FIXED);
            if (!$logoScaled) {
                $logoScaled = $logoImg;
            }

            $bgSize = $logoPx + ($paddingPx * 2);

            $bg = imagecreatetruecolor($bgSize, $bgSize);
            imagesavealpha($bg, true);

            $transparent = imagecolorallocatealpha($bg, 0, 0, 0, 127);
            imagefill($bg, 0, 0, $transparent);

            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0, 0, $bgSize, $bgSize, $white);

            $dstX = (int) (($bgSize - imagesx($logoScaled)) / 2);
            $dstY = (int) (($bgSize - imagesy($logoScaled)) / 2);
            imagecopy($bg, $logoScaled, $dstX, $dstY, 0, 0, imagesx($logoScaled), imagesy($logoScaled));

            $qrW = imagesx($qrImg);
            $qrH = imagesy($qrImg);

            $cx = (int) (($qrW - $bgSize) / 2);
            $cy = (int) (($qrH - $bgSize) / 2);

            imagecopy($qrImg, $bg, $cx, $cy, 0, 0, $bgSize, $bgSize);

            ob_start();
            imagepng($qrImg);
            $out = ob_get_clean();

            @imagedestroy($bg);
            @imagedestroy($logoScaled);
            @imagedestroy($logoImg);
            @imagedestroy($qrImg);

            if (!$out || strlen($out) < 50) {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($out);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Genera QR PNG como data-uri y le pega el logo al centro.
     */
    private function makeQrWithCenterLogoDataUri(string $text, string $logoPath, int $logoPx = 38): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $size = 320;

        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new GdImageBackEnd()
        );

        $writer = new Writer($renderer);
        $png    = $writer->writeString($text);

        if (!is_string($png) || strlen($png) < 20) {
            return '';
        }

        if (!function_exists('imagecreatefromstring')) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $qrImg = @imagecreatefromstring($png);
        if (!$qrImg) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $logoBin = null;
        if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
            $logoBin = @file_get_contents($logoPath);
        }

        if (!$logoBin || strlen($logoBin) < 20) {
            imagedestroy($qrImg);
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $logoImg = @imagecreatefromstring($logoBin);
        if (!$logoImg) {
            imagedestroy($qrImg);
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $logoPx = max(18, min(64, (int) $logoPx));
        $lw = imagesx($logoImg);
        $lh = imagesy($logoImg);

        if ($lw <= 0 || $lh <= 0) {
            imagedestroy($logoImg);
            imagedestroy($qrImg);
            return 'data:image/png;base64,' . base64_encode($png);
        }

        $dst = imagecreatetruecolor($logoPx, $logoPx);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $logoPx, $logoPx, $transparent);

        imagecopyresampled($dst, $logoImg, 0, 0, 0, 0, $logoPx, $logoPx, $lw, $lh);

        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);

        $pad = 6;
        $box = $logoPx + ($pad * 2);

        $x = (int) (($qrW - $box) / 2);
        $y = (int) (($qrH - $box) / 2);

        $white = imagecolorallocate($qrImg, 255, 255, 255);
        imagefilledrectangle($qrImg, $x, $y, $x + $box, $y + $box, $white);

        imagecopy($qrImg, $dst, $x + $pad, $y + $pad, 0, 0, $logoPx, $logoPx);

        ob_start();
        imagepng($qrImg);
        $out = (string) ob_get_clean();

        imagedestroy($dst);
        imagedestroy($logoImg);
        imagedestroy($qrImg);

        if (strlen($out) < 20) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        return 'data:image/png;base64,' . base64_encode($out);
    }

    /**
     * Intenta resolver PNG del QR desde data array.
     */
    private function resolveQrPngBinaryFromData(array $data): ?string
    {
        $qrDataUri = (string) ($data['qr_data_uri'] ?? $data['qr_data'] ?? '');
        if ($qrDataUri !== '' && str_starts_with($qrDataUri, 'data:image')) {
            $pos = strpos($qrDataUri, 'base64,');
            if ($pos !== false) {
                $b64 = substr($qrDataUri, $pos + 7);
                $bin = base64_decode($b64, true);
                if (is_string($bin) && strlen($bin) > 50) {
                    return $bin;
                }
            }
        }

        $qrUrl = (string) ($data['qr_url'] ?? $data['qr_path'] ?? '');
        $qrUrl = trim($qrUrl);

        if ($qrUrl === '') {
            return null;
        }

        $tryLocal = null;

        if (str_starts_with($qrUrl, '/')) {
            $tryLocal = public_path(ltrim($qrUrl, '/'));
        } elseif (!preg_match('#^https?://#i', $qrUrl)) {
            $tryLocal = public_path(ltrim($qrUrl, '/'));
        }

        if ($tryLocal && is_file($tryLocal) && is_readable($tryLocal)) {
            $bin = @file_get_contents($tryLocal);
            if (is_string($bin) && strlen($bin) > 50) {
                return $bin;
            }
        }

        if (preg_match('#^https?://#i', $qrUrl)) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 3],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $bin = @file_get_contents($qrUrl, false, $ctx);
            if (is_string($bin) && strlen($bin) > 50) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Genera PNG binario QR sin logo.
     */
    private function makeQrPngBinary(string $text, int $size = 320): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (!class_exists(\BaconQrCode\Writer::class)) {
            return null;
        }

        $size = max(160, min(520, (int) $size));

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 2),
            new \BaconQrCode\Renderer\Image\GdImageBackEnd()
        );

        $writer = new \BaconQrCode\Writer($renderer);
        $png    = $writer->writeString($text);

        if (is_string($png) && strlen($png) > 50) {
            return $png;
        }

        return null;
    }

    /**
     * Recibe QR PNG binario y embebe el logo al centro.
     */
    private function embedCenterLogoIntoQrPngDataUri(string $qrPngBin, string $logoPath, int $logoPx = 38): string
    {
        $fallback = 'data:image/png;base64,' . base64_encode($qrPngBin);

        if (!function_exists('imagecreatefromstring')) {
            return $fallback;
        }

        $qrImg = @imagecreatefromstring($qrPngBin);
        if (!$qrImg) {
            return $fallback;
        }

        $logoPx = max(18, min(64, (int) $logoPx));

        if ($logoPath === '' || !is_file($logoPath) || !is_readable($logoPath)) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $logoBin = @file_get_contents($logoPath);
        if (!is_string($logoBin) || strlen($logoBin) < 50) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $logoImg = @imagecreatefromstring($logoBin);
        if (!$logoImg) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $lw = imagesx($logoImg);
        $lh = imagesy($logoImg);

        if ($lw <= 0 || $lh <= 0) {
            imagedestroy($logoImg);
            imagedestroy($qrImg);
            return $fallback;
        }

        $dst = imagecreatetruecolor($logoPx, $logoPx);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $logoPx, $logoPx, $transparent);

        imagecopyresampled($dst, $logoImg, 0, 0, 0, 0, $logoPx, $logoPx, $lw, $lh);

        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);

        $pad = 6;
        $box = $logoPx + ($pad * 2);

        $x = (int) (($qrW - $box) / 2);
        $y = (int) (($qrH - $box) / 2);

        $white = imagecolorallocate($qrImg, 255, 255, 255);
        imagefilledrectangle($qrImg, $x, $y, $x + $box, $y + $box, $white);

        imagecopy($qrImg, $dst, $x + $pad, $y + $pad, 0, 0, $logoPx, $logoPx);

        ob_start();
        imagepng($qrImg);
        $out = (string) ob_get_clean();

        imagedestroy($dst);
        imagedestroy($logoImg);
        imagedestroy($qrImg);

        if (strlen($out) < 50) {
            return $fallback;
        }

        return 'data:image/png;base64,' . base64_encode($out);
    }

    /**
     * Genera un QR PNG data-uri con logo centrado.
     */
    private function makeQrDataUriWithCenterLogo(string $payload, int $logoPx = 38): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        try {
            $size = 260;

            $renderer = new ImageRenderer(
                new RendererStyle($size),
                new GdImageBackEnd()
            );

            $writer = new Writer($renderer);
            $qrBin  = $writer->writeString($payload);

            if (!is_string($qrBin) || strlen($qrBin) < 50) {
                return null;
            }

            $qrImg = @imagecreatefromstring($qrBin);
            if (!$qrImg) {
                return null;
            }

            imagesavealpha($qrImg, true);
            imagealphablending($qrImg, true);

            $logoPath = public_path('assets/client/Logo1Pactopia.png');
            if (!is_file($logoPath) || !is_readable($logoPath)) {
                $out = $this->gdPngToDataUri($qrImg);
                imagedestroy($qrImg);
                return $out;
            }

            $logoBin = @file_get_contents($logoPath);
            if ($logoBin === false || strlen($logoBin) < 50) {
                $out = $this->gdPngToDataUri($qrImg);
                imagedestroy($qrImg);
                return $out;
            }

            $logoImg = @imagecreatefromstring($logoBin);
            if (!$logoImg) {
                $out = $this->gdPngToDataUri($qrImg);
                imagedestroy($qrImg);
                return $out;
            }

            imagesavealpha($logoImg, true);
            imagealphablending($logoImg, true);

            $logoPx = (int) $logoPx;
            if ($logoPx < 18) {
                $logoPx = 18;
            }
            if ($logoPx > 64) {
                $logoPx = 64;
            }

            $pad   = 6;
            $plate = $logoPx + ($pad * 2);

            $plateImg = imagecreatetruecolor($plate, $plate);
            imagesavealpha($plateImg, true);
            imagealphablending($plateImg, false);

            $transparent = imagecolorallocatealpha($plateImg, 0, 0, 0, 127);
            imagefill($plateImg, 0, 0, $transparent);

            $white = imagecolorallocatealpha($plateImg, 255, 255, 255, 0);
            imagefilledrectangle($plateImg, 0, 0, $plate, $plate, $white);

            $dstX = (int) floor(($plate - $logoPx) / 2);
            $dstY = (int) floor(($plate - $logoPx) / 2);

            $logoW = imagesx($logoImg);
            $logoH = imagesy($logoImg);

            imagecopyresampled(
                $plateImg,
                $logoImg,
                $dstX,
                $dstY,
                0,
                0,
                $logoPx,
                $logoPx,
                $logoW,
                $logoH
            );

            $qrW = imagesx($qrImg);
            $qrH = imagesy($qrImg);

            $centerX = (int) floor(($qrW - $plate) / 2);
            $centerY = (int) floor(($qrH - $plate) / 2);

            imagecopy(
                $qrImg,
                $plateImg,
                $centerX,
                $centerY,
                0,
                0,
                $plate,
                $plate
            );

            $out = $this->gdPngToDataUri($qrImg);

            imagedestroy($logoImg);
            imagedestroy($plateImg);
            imagedestroy($qrImg);

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[STATEMENT_PDF] QR embed logo failed', [
                'err' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convierte recurso GD a data-uri PNG.
     */
    private function gdPngToDataUri($gdImg): ?string
    {
        if (!$gdImg) {
            return null;
        }

        ob_start();
        imagepng($gdImg);
        $bin = ob_get_clean();

        if (!is_string($bin) || strlen($bin) < 50) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($bin);
    }

    /**
     * Admin: hornea el logo dentro del QR.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function adminBakeLogoIntoQr(array $data): array
    {
        $force = (bool) ($data['qr_force_overlay'] ?? false);
        if (!$force) {
            return $data;
        }

        $logoPx = (int) ($data['qr_logo_px'] ?? 38);
        if ($logoPx < 18) {
            $logoPx = 18;
        }
        if ($logoPx > 64) {
            $logoPx = 64;
        }

        $qrDataUri = (string) (
            $data['qr_data_uri'] ?? $data['qrDataUri'] ?? $data['qr_data'] ?? ''
        );

        if ($qrDataUri === '' || !str_starts_with($qrDataUri, 'data:image/')) {
            return $data;
        }

        $logoDataUri = (string) (
            $data['logo_data_uri'] ?? $data['logoDataUri'] ?? ''
        );

        if ($logoDataUri === '') {
            $logoFile = public_path('assets/client/Logo1Pactopia.png');
            if (is_file($logoFile) && is_readable($logoFile)) {
                try {
                    $bin = file_get_contents($logoFile);
                    if ($bin !== false && strlen($bin) > 10) {
                        $logoDataUri = 'data:image/png;base64,' . base64_encode($bin);
                    }
                } catch (\Throwable $e) {
                    // noop
                }
            }
        }

        if ($logoDataUri === '' || !str_starts_with($logoDataUri, 'data:image/')) {
            return $data;
        }

        $baked = $this->embedLogoIntoQrDataUri($qrDataUri, $logoDataUri, $logoPx);

        if ($baked !== '' && $baked !== $qrDataUri) {
            $data['qr_data_uri'] = $baked;
            $data['qrDataUri']   = $baked;
            $data['qr_data']     = $baked;
        }

        return $data;
    }

    /**
     * Inserta logo dentro del QR data-uri.
     */
    private function embedLogoIntoQrDataUri(string $qrDataUri, string $logoDataUri, int $logoPx = 38): string
    {
        if ($qrDataUri === '' || $logoDataUri === '') {
            return $qrDataUri;
        }

        $qrBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $qrDataUri);
        $lgBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $logoDataUri);

        if (!is_string($qrBase64) || !is_string($lgBase64)) {
            return $qrDataUri;
        }

        $qrBin = base64_decode($qrBase64, true);
        $lgBin = base64_decode($lgBase64, true);

        if ($qrBin === false || $lgBin === false) {
            return $qrDataUri;
        }

        if (!function_exists('imagecreatefromstring')) {
            return $qrDataUri;
        }

        $qrImg = @imagecreatefromstring($qrBin);
        $lgImg = @imagecreatefromstring($lgBin);

        if (!$qrImg || !$lgImg) {
            return $qrDataUri;
        }

        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);

        if ($qrW <= 0 || $qrH <= 0) {
            return $qrDataUri;
        }

        $logoPx = max(18, min(64, (int) $logoPx));

        $dstX = (int) round(($qrW - $logoPx) / 2);
        $dstY = (int) round(($qrH - $logoPx) / 2);

        if (function_exists('imagecolorallocate')) {
            $white = imagecolorallocate($qrImg, 255, 255, 255);
            $pad   = 5;

            imagefilledrectangle(
                $qrImg,
                max(0, $dstX - $pad),
                max(0, $dstY - $pad),
                min($qrW - 1, $dstX + $logoPx + $pad),
                min($qrH - 1, $dstY + $logoPx + $pad),
                $white
            );
        }

        $lgW = imagesx($lgImg);
        $lgH = imagesy($lgImg);

        $srcSize = min($lgW, $lgH);
        $srcX    = (int) round(($lgW - $srcSize) / 2);
        $srcY    = (int) round(($lgH - $srcSize) / 2);

        imagecopyresampled(
            $qrImg,
            $lgImg,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $logoPx,
            $logoPx,
            $srcSize,
            $srcSize
        );

        ob_start();
        imagepng($qrImg);
        $out = ob_get_clean();

        imagedestroy($qrImg);
        imagedestroy($lgImg);

        if (!is_string($out) || strlen($out) < 10) {
            return $qrDataUri;
        }

        return 'data:image/png;base64,' . base64_encode($out);
    }

    /**
     * Genera data-uri local o fallback remoto para QR.
     *
     * @return array{0:?string,1:?string}
     */
    private function makeQrDataForText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [null, null];
        }

        $size = 170;

        try {
            if (class_exists(Writer::class) && class_exists(GdImageBackEnd::class)) {
                $renderer = new ImageRenderer(new RendererStyle($size), new GdImageBackEnd());
                $writer   = new Writer($renderer);
                $png      = $writer->writeString($text);

                if (is_string($png) && $png !== '') {
                    return ['data:image/png;base64,' . base64_encode($png), null];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] QR local failed (bacon)', [
                'err' => $e->getMessage(),
            ]);
        }

        $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($text)
            . '&size=' . $size
            . '&margin=1&format=png';

        return [null, $qrUrl];
    }
}