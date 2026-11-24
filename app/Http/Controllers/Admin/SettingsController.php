<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Organization\UpdateOrganization;
use App\Enums\LateFeeType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\SiteController;
use App\Services\Messaging\MessagingConfig;
use App\Services\Payments\PaymentGateway;
use App\Services\SettingsService;
use App\Support\AppVersion;
use App\Support\CurrentOrganization;
use App\Support\Money;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Owner settings (spec 8.12, FINISH_BUILD Section 6). Secrets are stored
 * encrypted and only shown as their last 4 characters; leaving a secret
 * field empty keeps the saved value.
 */
class SettingsController extends Controller
{
    public const TABS = ['organization', 'website', 'billing', 'payments', 'email', 'sms', 'staff'];

    /** Keys per tab; the second list holds the encrypted ones. */
    private const KEYS = [
        'billing' => [['billing.default_lead_days', 'billing.default_grace_days', 'billing.late_fee_type', 'billing.late_fee_value', 'billing.allow_overpayment', 'billing.bank_name', 'billing.bank_account_name', 'billing.bank_account_number'], []],
        'payments' => [['paystack.public_key', 'paystack.secret_key', 'paystack.mode'], ['paystack.secret_key']],
        'email' => [['mail.host', 'mail.port', 'mail.encryption', 'mail.username', 'mail.password', 'mail.from_address', 'mail.from_name'], ['mail.password']],
        'sms' => [['sms.driver', 'sms.termii_api_key', 'sms.termii_sender_id', 'sms.termii_channel', 'sms.termii_base_url'], ['sms.termii_api_key']],
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CurrentOrganization $current,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $tab = (string) $request->query('tab', 'organization');
        abort_unless(in_array($tab, self::TABS, true), 404);
        if ($tab === 'staff') {
            return redirect()->route('app.staff.index');
        }

        $values = [];
        foreach (self::KEYS as [$keys, $secrets]) {
            foreach ($keys as $key) {
                $values[$key] = in_array($key, $secrets, true) ? $this->settings->masked($key) : $this->settings->get($key);
            }
        }

        return view('admin.settings.edit', [
            'tab' => $tab,
            'tabs' => array_combine(self::TABS, array_map(fn (string $t) => __('settings.tabs.'.$t), self::TABS)),
            'organization' => $this->current->get(),
            'values' => $values,
            'websiteEnabled' => $this->settings->bool(SiteController::ENABLED_KEY, true),
            'timezones' => UpdateOrganization::timezoneOptions(),
            'currencies' => UpdateOrganization::CURRENCIES,
            'lateFeeTypes' => LateFeeType::options(),
            'smsDrivers' => array_combine(MessagingConfig::SMS_DRIVERS, array_map(fn (string $d) => __('settings.sms.drivers.'.$d), MessagingConfig::SMS_DRIVERS)),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
            'version' => ['version' => AppVersion::version(), 'commit' => AppVersion::commit(), 'built_at' => AppVersion::builtAt()],
        ]);
    }

    public function update(Request $request, string $tab, UpdateOrganization $updateOrganization): RedirectResponse
    {
        $organization = $this->current->get() ?? abort(404);

        match ($tab) {
            'organization' => $updateOrganization->handle(
                $organization,
                $request->validate(UpdateOrganization::rules()),
                $request->file('logo'),
            ),
            'website' => $this->saveWebsite($request),
            'billing', 'payments', 'email', 'sms' => $this->saveKeys($tab, $this->normalize($tab, $request->validate($this->rules($tab)))),
            default => abort(404),
        };

        return redirect()->route('app.settings.edit', ['tab' => $tab])->with('toast', __('settings.saved'));
    }

    /** "Test connection", "Send test email" and "Send test SMS" (spec 8.12). */
    public function test(Request $request, string $kind, PaymentGateway $gateway, MessagingConfig $messaging): RedirectResponse
    {
        $tab = match ($kind) {
            'paystack' => 'payments',
            'email' => 'email',
            'sms' => 'sms',
            default => abort(404),
        };

        try {
            [$ok, $message] = match ($kind) {
                'paystack' => $gateway->isConfigured() && $gateway->testConnection()
                    ? [true, __('settings.test.paystack_ok')]
                    : [false, __('settings.test.paystack_failed')],
                'email' => $this->testEmail($request, $messaging),
                'sms' => $this->testSms($request, $messaging),
            };
        } catch (Throwable $e) {
            [$ok, $message] = [false, __('settings.test.error', ['error' => mb_substr($e->getMessage(), 0, 300)])];
        }

        return redirect()->route('app.settings.edit', ['tab' => $tab])
            ->with('toast', ['message' => $message, 'type' => $ok ? 'success' : 'danger']);
    }

    /** @return array{0: bool, 1: string} */
    private function testEmail(Request $request, MessagingConfig $messaging): array
    {
        $to = (string) $request->validate(['to' => ['required', 'email']])['to'];
        $from = $messaging->from($this->current->get()?->name);
        $messaging->mailer()->raw(__('settings.test.email_body'), function (Message $mail) use ($to, $from): void {
            $mail->to($to)->subject(__('settings.test.email_subject'));
            if ($from['address'] !== '') {
                $mail->from($from['address'], $from['name']);
            }
        });

        return [true, __('settings.test.email_sent', ['to' => $to])];
    }

    /** @return array{0: bool, 1: string} */
    private function testSms(Request $request, MessagingConfig $messaging): array
    {
        $phone = (string) $request->validate(['to' => ['required', 'string', 'max:32']])['to'];
        $e164 = PhoneNormalizer::toE164($phone);
        if ($e164 === null) {
            return [false, __('messaging.errors.invalid_phone', ['phone' => $phone])];
        }
        $result = $messaging->smsDriver()->send($e164, __('settings.test.sms_body', ['app' => config('rentora.name')]));

        return $result->ok
            ? [true, __('settings.test.sms_sent', ['to' => $e164])]
            : [false, __('settings.test.error', ['error' => (string) $result->error])];
    }

    private function saveWebsite(Request $request): void
    {
        $data = $request->validate([
            'website_headline' => ['nullable', 'string', 'max:150'],
            'website_about' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
        ]);
        $organization = $this->current->get() ?? abort(404);
        $organization->fill(['website_headline' => $data['website_headline'] ?? null, 'website_about' => $data['website_about'] ?? null])->save();
        $this->settings->set(SiteController::ENABLED_KEY, $data['enabled'] ? '1' : '0');
    }

    /**
     * The late fee is typed in naira (fixed) or percent (percentage); both are
     * stored times 100, as kobo or basis points, like the lease columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(string $tab, array $data): array
    {
        if ($tab === 'billing' && isset($data['billing__late_fee_value'])) {
            $data['billing__late_fee_value'] = (string) (int) round((float) $data['billing__late_fee_value'] * 100);
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function saveKeys(string $tab, array $data): void
    {
        [$keys, $secrets] = self::KEYS[$tab];
        foreach ($keys as $key) {
            $field = str_replace('.', '__', $key);
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $value = $value === null ? null : trim((string) $value);
            // An empty secret field keeps the saved secret.
            if (in_array($key, $secrets, true) && ($value === null || $value === '')) {
                continue;
            }
            $this->settings->set($key, $value, in_array($key, $secrets, true));
        }
    }

    /** @return array<string, mixed> */
    private function rules(string $tab): array
    {
        $rules = match ($tab) {
            'billing' => [
                'billing.default_lead_days' => ['nullable', 'integer', 'min:0', 'max:120'],
                'billing.default_grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
                'billing.late_fee_type' => ['required', Rule::in(LateFeeType::values())],
                'billing.late_fee_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
                'billing.allow_overpayment' => ['required', 'boolean'],
                'billing.bank_name' => ['nullable', 'string', 'max:100'],
                'billing.bank_account_name' => ['nullable', 'string', 'max:150'],
                'billing.bank_account_number' => ['nullable', 'string', 'max:30'],
            ],
            'payments' => [
                'paystack.public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_'],
                'paystack.secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_'],
                'paystack.mode' => ['required', Rule::in(['test', 'live'])],
            ],
            'email' => [
                'mail.host' => ['nullable', 'string', 'max:191'],
                'mail.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'mail.encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
                'mail.username' => ['nullable', 'string', 'max:191'],
                'mail.password' => ['nullable', 'string', 'max:191'],
                'mail.from_address' => ['nullable', 'email', 'max:191'],
                'mail.from_name' => ['nullable', 'string', 'max:150'],
            ],
            'sms' => [
                'sms.driver' => ['required', Rule::in(MessagingConfig::SMS_DRIVERS)],
                'sms.termii_api_key' => ['nullable', 'string', 'max:200'],
                'sms.termii_sender_id' => ['nullable', 'string', 'max:11'],
                'sms.termii_channel' => ['required', Rule::in(['generic', 'dnd'])],
                'sms.termii_base_url' => ['nullable', 'url', 'max:191'],
            ],
            default => abort(404),
        };

        // Form field names use "__" for the dots, so validation does not read them as nested arrays.
        $flat = [];
        foreach ($rules as $key => $rule) {
            $flat[str_replace('.', '__', $key)] = $rule;
        }

        return $flat;
    }
}
