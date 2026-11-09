<?php

declare(strict_types=1);

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/** PNG QR codes as data URIs (GD output), embeddable in dompdf receipts. */
class QrCode
{
    public function dataUri(string $content): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 5,
            'quietzoneSize' => 2,
        ]);

        return (string) (new Generator($options))->render($content);
    }
}
