<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flashes an invite link for <x-invite-share>, which offers "Copy invite
 * link" and "Share on WhatsApp", so invites work before SMTP or SMS is set up.
 */
class InviteShare
{
    /** @return array{name: string, link: string, whatsapp: string} */
    public static function data(string $name, string $link, ?string $phone): array
    {
        $organization = app(CurrentOrganization::class)->get()->name ?? config('rentora.name');
        $message = __('ui.invite_share.message', ['name' => $name, 'org' => $organization, 'link' => $link]);

        return [
            'name' => $name,
            'link' => $link,
            // Straight to the person's chat when the number is valid, otherwise WhatsApp's contact picker.
            'whatsapp' => PhoneNormalizer::whatsAppLink($phone, $message) ?? 'https://wa.me/?text='.rawurlencode($message),
        ];
    }

    public static function flash(string $name, string $link, ?string $phone): void
    {
        session()->flash('invite_share', self::data($name, $link, $phone));
    }
}
