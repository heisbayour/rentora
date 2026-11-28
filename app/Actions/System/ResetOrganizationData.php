<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Services\SequenceService;
use App\Support\PrivateFiles;
use App\Support\PublicUploads;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clean-slate reset for handing a demo install over to the client (spec
 * addendum 1.0.3). Deletes every business record of one organization and its
 * uploaded files, removes all non-owner users and restarts document numbers.
 * Keeps the organization itself, its settings, the owner accounts and the
 * install lock. Runs in one transaction; files are removed only after the
 * transaction has committed.
 */
class ResetOrganizationData
{
    /** Sequences that restart at 000001. */
    public const SEQUENCES = [SequenceService::INVOICE, SequenceService::RECEIPT, SequenceService::MAINTENANCE];

    /**
     * What a reset would delete, as label key => count, for the confirmation screen.
     *
     * @return array<string, int>
     */
    public function counts(Organization $organization): array
    {
        $id = (int) $organization->getKey();
        $users = $this->nonOwnerUserIds($id);
        $files = $this->filePaths($id);
        $tenantUsers = $this->tenantUserCount($id);

        return [
            'properties' => $this->org('properties', $id)->count(),
            'units' => $this->org('units', $id)->count(),
            'unit_photos' => DB::table('unit_photos')->whereIn('unit_id', $this->org('units', $id)->select('id'))->count(),
            'tenants' => $this->org('tenants', $id)->count(),
            'leases' => $this->org('leases', $id)->count(),
            'invoices' => $this->org('invoices', $id)->count(),
            'payments' => $this->org('payments', $id)->count(),
            'allocations' => DB::table('payment_allocations')->whereIn('payment_id', $this->org('payments', $id)->select('id'))->count(),
            'receipts' => $this->org('payments', $id)->whereNotNull('receipt_number')->count(),
            'maintenance' => $this->org('maintenance_requests', $id)->count(),
            'maintenance_updates' => DB::table('maintenance_updates')->whereIn('maintenance_request_id', $this->org('maintenance_requests', $id)->select('id'))->count(),
            'maintenance_attachments' => DB::table('maintenance_attachments')->whereIn('maintenance_request_id', $this->org('maintenance_requests', $id)->select('id'))->count(),
            'vendors' => $this->org('vendors', $id)->count(),
            'expenses' => $this->org('expenses', $id)->count(),
            'announcements' => $this->org('announcements', $id)->count(),
            'notifications' => $this->notifications($id)->count(),
            'outbound_messages' => $this->org('outbound_messages', $id)->count(),
            'reminder_logs' => $this->org('reminder_logs', $id)->count(),
            'inquiries' => $this->org('inquiries', $id)->count(),
            'documents' => $this->org('documents', $id)->count(),
            'activity_logs' => $this->org('activity_logs', $id)->count(),
            'files' => count($files['private']) + count($files['public']),
            'staff_users' => $users->count() - $tenantUsers,
            'tenant_users' => $tenantUsers,
        ];
    }

    /**
     * @param  array{name: string, email: string}|null  $newOwner
     * @return array{counts: array<string, int>, new_owner: array{name: string, email: string, password: string}|null}
     */
    public function handle(Organization $organization, ?array $newOwner = null): array
    {
        $id = (int) $organization->getKey();
        $counts = $this->counts($organization);
        $files = $this->filePaths($id);
        $created = null;

        DB::transaction(function () use ($id, $newOwner, &$created): void {
            $units = $this->org('units', $id)->pluck('id');
            $payments = $this->org('payments', $id)->pluck('id');
            $requests = $this->org('maintenance_requests', $id)->pluck('id');
            $announcements = $this->org('announcements', $id)->pluck('id');
            $userIds = $this->nonOwnerUserIds($id);

            // Children first, so foreign keys never block a delete.
            $this->whereInChunks('payment_allocations', 'payment_id', $payments->all());
            $this->org('expenses', $id)->delete();
            $this->whereInChunks('maintenance_updates', 'maintenance_request_id', $requests->all());
            $this->whereInChunks('maintenance_attachments', 'maintenance_request_id', $requests->all());
            $this->org('maintenance_requests', $id)->delete();
            $this->whereInChunks('announcement_tenant', 'announcement_id', $announcements->all());
            $this->org('announcements', $id)->delete();
            $this->notifications($id)->delete();
            $this->org('outbound_messages', $id)->delete();
            $this->org('reminder_logs', $id)->delete();
            $this->org('inquiries', $id)->delete();
            $this->org('documents', $id)->delete();
            // Self references (credit-note parents, renewals) are cleared before the rows go.
            $this->org('invoices', $id)->update(['parent_invoice_id' => null]);
            $this->org('payments', $id)->delete();
            $this->org('invoices', $id)->delete();
            $this->org('leases', $id)->update(['renewal_of_id' => null]);
            $this->org('leases', $id)->delete();
            $this->org('tenants', $id)->delete();
            $this->org('vendors', $id)->delete();
            $this->whereInChunks('unit_photos', 'unit_id', $units->all());
            $this->org('units', $id)->delete();
            $this->org('properties', $id)->delete();
            $this->org('activity_logs', $id)->delete();

            $emails = DB::table('users')->whereIn('id', $userIds)->pluck('email');
            $this->whereInChunks('password_reset_tokens', 'email', $emails->all());
            if (Schema::hasTable('sessions')) {
                $this->whereInChunks('sessions', 'user_id', $userIds->all());
            }
            $this->whereInChunks('users', 'id', $userIds->all());

            $this->org('sequences', $id)->whereIn('key', self::SEQUENCES)->delete();

            if ($newOwner !== null) {
                $password = Str::password(14, symbols: false);
                User::query()->withoutGlobalScopes()->create([
                    'organization_id' => $id,
                    'name' => $newOwner['name'],
                    'email' => Str::lower($newOwner['email']),
                    'password' => $password,
                    'role' => Role::Owner,
                    'is_active' => true,
                ]);
                $created = ['name' => $newOwner['name'], 'email' => Str::lower($newOwner['email']), 'password' => $password];
            }
        });

        // Files cannot be rolled back, so they go only once the database has committed.
        Storage::disk(PrivateFiles::DISK)->delete($files['private']);
        Storage::disk(PublicUploads::DISK)->delete($files['public']);

        return ['counts' => $counts, 'new_owner' => $created];
    }

    private function org(string $table, int $organizationId): Builder
    {
        return DB::table($table)->where('organization_id', $organizationId);
    }

    /** In-app notifications of every user of the organization (owners included). */
    private function notifications(int $organizationId): Builder
    {
        return DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', DB::table('users')->where('organization_id', $organizationId)->select('id'));
    }

    /** @return Collection<int, int> */
    private function nonOwnerUserIds(int $organizationId): Collection
    {
        return DB::table('users')
            ->where('organization_id', $organizationId)
            ->whereNotIn('role', [Role::Owner->value, Role::Developer->value])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    private function tenantUserCount(int $organizationId): int
    {
        return DB::table('users')->where('organization_id', $organizationId)->where('role', Role::Tenant->value)->count();
    }

    /**
     * Uploaded files that belong to the deleted records (the logo is kept).
     *
     * @return array{private: list<string>, public: list<string>}
     */
    private function filePaths(int $organizationId): array
    {
        $private = collect()
            ->merge($this->org('documents', $organizationId)->pluck('path'))
            ->merge($this->org('payments', $organizationId)->whereNotNull('proof_path')->pluck('proof_path'))
            ->merge($this->org('expenses', $organizationId)->whereNotNull('receipt_path')->pluck('receipt_path'))
            ->merge(DB::table('maintenance_attachments')
                ->whereIn('maintenance_request_id', $this->org('maintenance_requests', $organizationId)->select('id'))
                ->pluck('path'));

        $public = collect()
            ->merge(DB::table('unit_photos')->whereIn('unit_id', $this->org('units', $organizationId)->select('id'))->pluck('path'))
            ->merge($this->org('properties', $organizationId)->whereNotNull('cover_image_path')->pluck('cover_image_path'));

        $clean = fn (Collection $paths): array => $paths->filter(fn ($p): bool => is_string($p) && $p !== '')->unique()->values()->all();

        return ['private' => $clean($private), 'public' => $clean($public)];
    }

    /** @param  array<int, int|string>  $values */
    private function whereInChunks(string $table, string $column, array $values): void
    {
        foreach (array_chunk($values, 500) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->delete();
        }
    }
}
