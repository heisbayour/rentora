<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flashes an invite or password reset link for <x-invite-share>: Copy link,
 * WhatsApp, SMS, Email, the phone's share sheet, and a plain line for each
 * channel saying whether it was also sent (DECISIONS 94 and 104). Every
 * invite and reset flow ends here, so a person can always be reached even
 * when email or SMS is not set up or broken.
 */
class InviteShare
{
    public const INVITE = 'invite';

    public const RESET = 'reset';

    /**
     * @param  list<Delivery>  $deliveries  empty when the link was only issued, not sent
     * @return array<string, mixed>
     */
    public static function data(string $name, string $link, ?string $phone, ?string $email = null, array $deliveries = [], string $kind = self::INVITE): array
    {
        $organization = app(CurrentOrganization::class)->get()->name ?? config('rentora.name');
        $replace = [
            'name' => $name,
            'org' => $organization,
            'link' => $link,
            'days' => config('rentora.links.invite_days'),
            'minutes' => config('auth.passwords.users.expire', 60),
        ];
        $reset = $kind === self::RESET;
        $message = __('ui.invite_share.'.($reset ? 'reset_message' : 'message'), $replace);
        $subject = __('ui.invite_share.'.($reset ? 'reset_subject' : 'subject'), $replace);
        $e164 = PhoneNormalizer::toE164($phone);

        return [
            'kind' => $kind,
            'name' => $name,
            'link' => $link,
            'title' => __('ui.invite_share.'.($reset ? 'reset_title' : 'title'), $replace),
            'text' => __('ui.invite_share.'.($reset ? 'reset_text' : 'text'), $replace),
            'message' => $message,
            'subject' => $subject,
            // Straight to the person's chat when the number is valid, otherwise WhatsApp's contact picker.
            'whatsapp' => PhoneNormalizer::whatsAppLink($phone, $message) ?? 'https://wa.me/?text='.rawurlencode($message),
            'sms' => 'sms:'.($e164 ?? '').'?body='.rawurlencode($message),
            'mailto' => 'mailto:'.str_replace(['?', '&', '#', ' '], '', (string) $email).'?subject='.rawurlencode($subject).'&body='.rawurlencode($message),
            'deliveries' => array_map(fn (Delivery $delivery) => $delivery->toArray(), $deliveries),
        ];
    }

    /** @param  list<Delivery>  $deliveries */
    public static function flash(string $name, string $link, ?string $phone, ?string $email = null, array $deliveries = [], string $kind = self::INVITE): void
    {
        session()->flash('invite_share', self::data($name, $link, $phone, $email, $deliveries, $kind));
    }
}
