<?php

declare(strict_types=1);

namespace Kletterdom\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

final class Qr
{
    /**
     * Generiert einen QR-Code als data:image/png-base64-URL. Die
     * URL kann direkt in ein <img src="…">-Element eingesetzt werden.
     */
    public function dataUri(string $content, int $size = 220): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }
}
