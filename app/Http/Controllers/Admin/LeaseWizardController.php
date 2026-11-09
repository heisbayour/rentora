<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LateFeeType;
use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeaseWizardRequest;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\LeaseWizard;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** "Add tenant and lease" wizard (spec 6.5.2). */
class LeaseWizardController extends Controller
{
    public function __construct(private readonly LeaseWizard $wizard) {}

    public function start(Request $request): RedirectResponse
    {
        $this->authorize('create', Lease::class);

        $tenant = $request->filled('tenant') ? Tenant::query()->findOrFail($request->integer('tenant')) : null;

        return redirect()->route('app.leases.wizard.show', $this->wizard->start($tenant));
    }

    public function show(string $step): View|RedirectResponse
    {
        $this->authorize('create', Lease::class);
        abort_unless(in_array($step, LeaseWizard::STEPS, true), 404);
        if (! $this->wizard->canOpen($step)) {
            return redirect()->route('app.leases.wizard.show', $this->wizard->firstIncompleteStep());
        }

        $draft = $this->wizard->draft();
        $unit = $this->wizard->unit();

        return view('admin.leases.wizard', [
            'step' => $step,
            'steps' => array_map(fn (string $s) => __('leases.wizard.steps.'.$s), LeaseWizard::STEPS),
            'position' => array_search($step, LeaseWizard::STEPS, true) + 1,
            'previous' => $this->wizard->previous($step),
            'draft' => $draft,
            'tenant' => $this->wizard->tenant(),
            'unit' => $unit,
            'terms' => $draft['terms'] ?? ($unit !== null ? $this->wizard->defaultTerms($unit) : []),
            'deposit' => $draft['deposit'] ?? ($unit !== null ? $this->wizard->defaultDeposit($unit) : []),
            'units' => $step === 'unit' ? $this->availableUnits() : collect(),
            'frequencies' => RentFrequency::options(),
            'lateFeeTypes' => LateFeeType::options(),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function store(LeaseWizardRequest $request, string $step): RedirectResponse
    {
        abort_unless(in_array($step, LeaseWizard::STEPS, true), 404);

        if ($step === 'review') {
            $lease = $this->wizard->complete([
                'activate_now' => $request->boolean('activate_now'),
                'send_invite' => $request->boolean('send_invite'),
            ]);

            return redirect()->route('app.leases.show', $lease)->with('toast', __('leases.wizard.created'));
        }

        $this->wizard->remember($step, $request->validated());

        return redirect()->route('app.leases.wizard.show', $this->wizard->next($step));
    }

    /** @return Collection<int, Unit> */
    private function availableUnits()
    {
        return Unit::query()->with('property')
            ->whereIn('status', [UnitStatus::Vacant->value, UnitStatus::Reserved->value])
            ->orderBy('property_id')->orderBy('label')->get();
    }
}
