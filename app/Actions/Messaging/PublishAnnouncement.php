<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\ActivityLogger;
use App\Services\Messaging\AnnouncementAudienceResolver;
use App\Services\Messaging\Notifier;
use Illuminate\Support\Facades\DB;

/** Sends an announcement now to its resolved audience on the chosen channels (spec 8.8). */
class PublishAnnouncement
{
    public function __construct(
        private readonly AnnouncementAudienceResolver $audience,
        private readonly Notifier $notifier,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  list<int>  $audienceIds
     * @param  list<string>  $channels
     */
    public function handle(User $author, string $title, string $body, AnnouncementAudience $audience, array $audienceIds, array $channels): Announcement
    {
        $announcement = DB::transaction(function () use ($author, $title, $body, $audience, $audienceIds, $channels): Announcement {
            $announcement = Announcement::query()->create([
                'title' => $title,
                'body' => $body,
                'audience' => $audience,
                'audience_ids' => $audience === AnnouncementAudience::AllTenants ? null : $audienceIds,
                'channels' => array_values(array_intersect(Announcement::CHANNELS, $channels)),
                'created_by' => $author->getKey(),
            ]);

            $tenantIds = $this->audience->query($audience, $audienceIds)->pluck('id')->all();
            $announcement->tenants()->attach($tenantIds);
            $announcement->forceFill(['recipient_count' => count($tenantIds), 'sent_at' => now()])->save();

            return $announcement;
        });

        $notification = new AnnouncementPublished($announcement);
        $announcement->tenants()->chunkById(100, fn ($tenants) => $this->notifier->send($tenants, $notification), 'tenants.id', 'id');

        $this->logger->log('announcement.published', $announcement, ['recipients' => $announcement->recipient_count, 'channels' => $announcement->channels], $author);

        return $announcement;
    }
}
