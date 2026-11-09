<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StatementRequest;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Billing\StatementBuilder;
use App\Support\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant statements (spec 8.6), for staff (any tenant) and for a tenant
 * (themselves only; the portal route passes no tenant).
 */
class StatementController extends Controller
{
    public function __construct(private readonly StatementBuilder $builder) {}

    public function show(StatementRequest $request, ?Tenant $tenant = null): View
    {
        $tenant = $this->resolve($request, $tenant);

        return view('statements.show', [
            'tenant' => $tenant,
            'from' => $request->from(),
            'to' => $request->to(),
            'statement' => $this->builder->build($tenant, $request->from(), $request->to()),
            'portal' => $request->user()->role === Role::Tenant,
        ]);
    }

    public function pdf(StatementRequest $request, Pdf $pdf, ?Tenant $tenant = null): Response
    {
        $tenant = $this->resolve($request, $tenant);

        return $pdf->download('pdf.statement', [
            'tenant' => $tenant,
            'from' => $request->from(),
            'to' => $request->to(),
            'statement' => $this->builder->build($tenant, $request->from(), $request->to()),
        ], 'statement-'.$request->from()->toDateString().'-'.$request->to()->toDateString().'.pdf');
    }

    private function resolve(StatementRequest $request, ?Tenant $tenant): Tenant
    {
        $user = $request->user();
        if ($user->role === Role::Tenant) {
            return $user->tenant ?? abort(404);
        }
        abort_unless($tenant !== null && $user->can('viewDetails', $tenant) && $user->can('viewAny', Payment::class), 403);

        return $tenant;
    }
}
