<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeGenerator
{
    /**
     * Generate a base64-encoded PNG QR code for the given payload.
     * Uses GD (via endroid/qr-code) instead of Imagick.
     */
    public static function generateBase64(string $payload, int $size = 300): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($payload)
            ->size($size)
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->build();

        return base64_encode($result->getString());
    }
}
