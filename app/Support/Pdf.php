<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Symfony\Component\HttpFoundation\Response;

/** Renders a Blade view in resources/views/pdf as a downloadable A4 PDF. */
class Pdf
{
    /** @param  array<string, mixed>  $data */
    public function download(string $view, array $data, string $filename): Response
    {
        $organization = app(CurrentOrganization::class)->get();

        return DomPdf::loadView($view, $data + ['organization' => $organization])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->download($filename);
    }
}
