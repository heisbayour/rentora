<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Enums\ExpenseCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\MaintenanceCreated;
use App\Notifications\MaintenanceStatusChanged;
use App\Notifications\VendorJobAssigned;
use App\Services\ActivityLogger;
use App\Services\SequenceService;
use App\Support\CurrentOrganization;
use App\Support\PrivateFiles;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Repair requests (spec 8.7): creation, the status machine, the comment thread
 * (internal notes never reach tenants), assignment to staff and vendors, photos,
 * cost recording and rating. Actors are staff users, tenant users or vendors.
 */
class MaintenanceWorkflow
{
    public const MAX_PHOTOS = 5;

    public const AUTO_CLOSE_DAYS = 7;

    public function __construct(
        private readonly SequenceService $sequences,
        private readonly PrivateFiles $files,
        private readonly ActivityLogger $logger,
        private readonly CurrentOrganization $current,
    ) {}

    /**
     * @param  array{category: string, title: string, description: string, priority?: string, preferred_time?: ?string, access_instructions?: ?string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function create(Unit $unit, ?Tenant $tenant, ?User $reporter, array $data, array $photos = []): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($unit, $tenant, $reporter, $data): MaintenanceRequest {
            $request = new MaintenanceRequest([
                'number' => $this->sequences->next(SequenceService::MAINTENANCE, now(), $unit->organization_id),
                'unit_id' => $unit->getKey(),
                'tenant_id' => $tenant?->getKey(),
                'reported_by_user_id' => $reporter?->getKey(),
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? MaintenancePriority::Normal->value,
                'preferred_time' => $data['preferred_time'] ?? null,
                'access_instructions' => $data['access_instructions'] ?? null,
            ]);
            $request->organization_id = $unit->organization_id;
            $request->save();

            return $request;
        });

        $this->addPhotos($request, $photos, $reporter);
        $this->logger->log('maintenance.created', $request, ['number' => $request->number, 'priority' => $request->priority->value], $reporter);
        $request->refresh();
        $this->notifyStaff($request, $reporter);

        return $request;
    }

    /**
     * @param  array{scheduled_for?: ?CarbonInterface, cost_amount?: ?int, record_expense?: bool}  $extra
     */
    public function changeStatus(MaintenanceRequest $request, MaintenanceStatus $to, User|Vendor $actor, ?string $note = null, array $extra = []): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($request, $to, $actor, $note, $extra): MaintenanceRequest {
            $request = MaintenanceRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($request->getKey());
            $from = $request->status;
            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['status' => __('maintenance.errors.invalid_transition', ['from' => $from->label(), 'to' => $to->label()])]);
            }

            $request->status = $to;
            if (! $this->isTenant($actor)) {
                $request->first_response_at ??= now();
            }
            if ($to === MaintenanceStatus::Scheduled && isset($extra['scheduled_for'])) {
                $request->scheduled_for = $extra['scheduled_for'];
            }
            if ($to === MaintenanceStatus::Resolved) {
                $request->resolved_at = now();
                if (isset($extra['cost_amount']) && $extra['cost_amount'] > 0) {
                    $request->cost_amount = $extra['cost_amount'];
                }
            }
            if ($from === MaintenanceStatus::Resolved && $to === MaintenanceStatus::InProgress) {
                $request->resolved_at = null;
            }
            if ($to === MaintenanceStatus::Closed) {
                $request->closed_at = now();
            }
            $request->save();

            $this->record($request, $actor, MaintenanceUpdate::STATUS_CHANGE, $note, $from, $to);

            if ($to === MaintenanceStatus::Resolved && ($extra['record_expense'] ?? false) && $request->cost_amount > 0 && $actor instanceof User) {
                $this->recordExpense($request, $actor);
            }

            $this->logger->log('maintenance.status_changed', $request, ['from' => $from->value, 'to' => $to->value], $actor instanceof User ? $actor : null);

            return $request;
        });
        $this->notifyTenant($request, $actor);

        return $request;
    }

    public function comment(MaintenanceRequest $request, User|Vendor $actor, string $body, bool $internal = false): MaintenanceUpdate
    {
        // Tenants and vendors can never write internal notes.
        $internal = $internal && $actor instanceof User && $actor->isStaff();

        return $this->record($request, $actor, MaintenanceUpdate::COMMENT, $body, null, null, $internal);
    }

    public function assign(MaintenanceRequest $request, User $actor, ?int $assigneeId, ?int $vendorId): MaintenanceRequest
    {
        $previousVendor = $request->vendor_id;
        $request->forceFill(['assigned_user_id' => $assigneeId, 'vendor_id' => $vendorId]);
        $request->first_response_at ??= now();
        $request->save();
        $request->load(['assignee', 'vendor']);

        $names = array_filter([$request->assignee?->name, $request->vendor?->name]);
        $this->record($request, $actor, MaintenanceUpdate::ASSIGNMENT, __('maintenance.assigned_to', ['names' => $names === [] ? __('maintenance.nobody') : implode(', ', $names)]), null, null, true);
        $this->logger->log('maintenance.assigned', $request, ['user' => $assigneeId, 'vendor' => $vendorId], $actor);

        if ($request->vendor !== null && $request->vendor_id !== $previousVendor) {
            $request->vendor->notify(new VendorJobAssigned($request, self::vendorLink($request)));
        }

        return $request;
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function addPhotos(MaintenanceRequest $request, array $photos, User|Vendor|null $actor): int
    {
        $existing = $request->attachments()->count();
        if ($existing + count($photos) > self::MAX_PHOTOS * 4) {
            throw ValidationException::withMessages(['photos' => __('maintenance.errors.too_many_photos')]);
        }
        foreach ($photos as $photo) {
            MaintenanceAttachment::query()->create([
                'maintenance_request_id' => $request->getKey(),
                'path' => $this->files->store($photo, 'maintenance/'.$request->getKey()),
                'uploaded_by_user_id' => $actor instanceof User ? $actor->getKey() : null,
                'uploaded_by_vendor' => $actor instanceof Vendor,
            ]);
        }

        return count($photos);
    }

    /** The tenant confirms the fix (resolved to closed) and rates it 1 to 5 (spec 8.7). */
    public function confirmAndRate(MaintenanceRequest $request, User $tenantUser, int $rating, ?string $feedback): MaintenanceRequest
    {
        if ($request->status !== MaintenanceStatus::Resolved) {
            throw ValidationException::withMessages(['rating' => __('maintenance.errors.rate_needs_resolved')]);
        }
        $request->forceFill(['tenant_rating' => max(1, min(5, $rating)), 'tenant_feedback' => $feedback])->save();

        return $this->changeStatus($request, MaintenanceStatus::Closed, $tenantUser, $feedback);
    }

    /** Daily: resolved requests untouched for 7 days are closed (spec 8.7). */
    public function autoCloseResolved(?CarbonInterface $today = null): int
    {
        $cutoff = ($today ?? now())->copy()->subDays(self::AUTO_CLOSE_DAYS);
        $closed = 0;

        foreach (Organization::query()->get() as $organization) {
            $this->current->runAs($organization, function () use ($cutoff, &$closed): void {
                MaintenanceRequest::query()
                    ->where('status', MaintenanceStatus::Resolved->value)
                    ->where('resolved_at', '<=', $cutoff)
                    ->each(function (MaintenanceRequest $request) use (&$closed): void {
                        $request->forceFill(['status' => MaintenanceStatus::Closed, 'closed_at' => now()])->save();
                        $this->record($request, null, MaintenanceUpdate::STATUS_CHANGE, __('maintenance.auto_closed'), MaintenanceStatus::Resolved, MaintenanceStatus::Closed);
                        $this->notifyTenant($request, null);
                        $closed++;
                    });
            });
        }

        return $closed;
    }

    /** Signed for 14 days, for this request and its current vendor only (spec 8.7). */
    public static function vendorLink(MaintenanceRequest $request): string
    {
        return URL::temporarySignedRoute(
            'vendor.job',
            now()->addDays((int) config('rentora.links.vendor_days')),
            ['job' => $request->getKey(), 'vendor' => $request->vendor_id],
        );
    }

    /** Owners, managers and caretakers hear about new requests, except the person who logged it. */
    private function notifyStaff(MaintenanceRequest $request, ?User $reporter): void
    {
        $staff = User::query()->staff()
            ->where('organization_id', $request->organization_id)
            ->where('is_active', true)
            ->when($reporter !== null, fn ($query) => $query->whereKeyNot($reporter?->getKey()))
            ->get();

        Notification::send($staff, new MaintenanceCreated($request));
    }

    /** The tenant hears about every status change they did not make themselves (spec 8.7). */
    private function notifyTenant(MaintenanceRequest $request, User|Vendor|null $actor): void
    {
        $tenantUser = $request->tenant_id === null ? null : Tenant::query()->withoutGlobalScopes()->find($request->tenant_id)?->user;
        if ($tenantUser === null || ($actor instanceof User && $actor->is($tenantUser))) {
            return;
        }

        $tenantUser->notify(new MaintenanceStatusChanged($request));
    }

    private function recordExpense(MaintenanceRequest $request, User $actor): void
    {
        if (Expense::query()->where('maintenance_request_id', $request->getKey())->exists()) {
            return;
        }
        $unit = Unit::query()->withTrashed()->findOrFail($request->unit_id);
        $expense = new Expense([
            'property_id' => $unit->property_id,
            'unit_id' => $unit->getKey(),
            'maintenance_request_id' => $request->getKey(),
            'category' => ExpenseCategory::Repairs,
            'amount' => (int) $request->cost_amount,
            'incurred_on' => today(),
            'description' => $request->number.': '.$request->title,
            'recorded_by' => $actor->getKey(),
        ]);
        $expense->organization_id = $request->organization_id;
        $expense->save();
    }

    private function record(MaintenanceRequest $request, User|Vendor|null $actor, string $type, ?string $body, ?MaintenanceStatus $old = null, ?MaintenanceStatus $new = null, bool $internal = false): MaintenanceUpdate
    {
        return MaintenanceUpdate::query()->create([
            'maintenance_request_id' => $request->getKey(),
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'vendor_id' => $actor instanceof Vendor ? $actor->getKey() : null,
            'type' => $type,
            'body' => $body,
            'old_status' => $old,
            'new_status' => $new,
            'is_internal' => $internal,
        ]);
    }

    private function isTenant(User|Vendor $actor): bool
    {
        return $actor instanceof User && $actor->role === Role::Tenant;
    }
}
