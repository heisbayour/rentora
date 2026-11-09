<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes activity_logs rows for every financial or security-relevant action
 * (spec 4.1 and 11). Action names are dotted: "payment.approved".
 */
class ActivityLogger
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        $user ??= $this->request->user();
        $organizationId = $subject?->getAttribute('organization_id');
        if ($organizationId === null && $user instanceof User) {
            $organizationId = $user->organization_id;
        }
        $organizationId ??= $this->organization->id();

        return ActivityLog::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
        ]);
    }
}
