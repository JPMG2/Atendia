<?php

declare(strict_types=1);

namespace App\Enums;

use App\Mail\AccountClosed;
use App\Mail\AccountEmailChangeNotice;
use App\Mail\AccountEmailChangeRequested;
use App\Mail\AccountEmailUpdated;
use App\Mail\AccountEmailVerification;
use App\Mail\AccountPasswordChanged;
use App\Mail\AccountPasswordReset;
use App\Mail\AccountTwoFactorChanged;
use App\Mail\AccountTwoFactorReset;
use App\Mail\AiSpendAlert;
use App\Mail\BillingPaymentReviewed;
use App\Mail\BillingReminder;
use App\Mail\BusinessActivityStale;
use App\Mail\BusinessSuspended;
use App\Mail\BusinessWelcome;
use App\Mail\ContactEmailUpdated;
use App\Mail\DeviceChallengeCode;
use App\Mail\FirstPaymentReceived;
use App\Mail\HolidayBridgeNotice;
use App\Mail\IncidentsDigest;
use App\Mail\ModerationAlert;
use App\Mail\ModerationAppeal;
use App\Mail\NewBusinessJoined;
use App\Mail\NewCompany;
use App\Mail\NewDeviceLogin;
use App\Mail\ReferralLink;
use App\Mail\StaffTwoFactorDeadline;
use App\Mail\StalledAdoptionReport;
use App\Mail\SupportTicketAnswered;
use App\Mail\SupportTicketOpened;
use App\Mail\TeamInvitationMail;
use App\Models\Company;
use Illuminate\Support\Str;

/**
 * Who a mail says it comes from. Split by function, as the big senders do: a
 * code to type is no-reply, a payment notice has to be answerable.
 *
 * Only the local part lives here; the domain follows MAIL_FROM_ADDRESS so the
 * day the brand changes domain nothing in this file moves.
 */
enum MailSender: string
{
    case System = 'system';
    case Billing = 'billing';
    case Support = 'support';
    case Hello = 'hello';

    /**
     * The sender of a Mailable. A class nobody listed goes out as `System`
     * (no-reply, the safe one); the test makes sure none is left unlisted.
     *
     * @param  class-string  $mailable
     */
    public static function for(string $mailable): self
    {
        foreach (self::cases() as $sender) {
            if (in_array($mailable, $sender->mailables(), true)) {
                return $sender;
            }
        }

        return self::System;
    }

    public function address(): string
    {
        $domain = Str::after((string) config('mail.from.address'), '@');

        return $this->local().'@'.$domain;
    }

    public function displayName(string $locale): string
    {
        return __('mail.sender.'.$this->value, ['brand' => Company::brand()], $locale);
    }

    private function local(): string
    {
        return match ($this) {
            self::System => 'no-reply',
            self::Billing => 'cobranzas',
            self::Support => 'soporte',
            self::Hello => 'hola',
        };
    }

    /**
     * Account and security mail plus the platform's own alerts to its admin:
     * nobody has to answer them. Billing, support and the welcome invite a
     * reply, so their address has to be one somebody reads.
     *
     * @return list<class-string>
     */
    public function mailables(): array
    {
        return match ($this) {
            self::System => [
                AccountEmailChangeNotice::class,
                AccountEmailChangeRequested::class,
                AccountEmailUpdated::class,
                AccountEmailVerification::class,
                AccountPasswordChanged::class,
                AccountPasswordReset::class,
                AccountTwoFactorChanged::class,
                AccountTwoFactorReset::class,
                AiSpendAlert::class,
                BusinessActivityStale::class,
                ContactEmailUpdated::class,
                DeviceChallengeCode::class,
                FirstPaymentReceived::class,
                HolidayBridgeNotice::class,
                IncidentsDigest::class,
                ModerationAlert::class,
                ModerationAppeal::class,
                NewBusinessJoined::class,
                NewCompany::class,
                NewDeviceLogin::class,
                StaffTwoFactorDeadline::class,
                StalledAdoptionReport::class,
                TeamInvitationMail::class,
            ],
            self::Billing => [
                BillingPaymentReviewed::class,
                BillingReminder::class,
            ],
            self::Support => [
                AccountClosed::class,
                BusinessSuspended::class,
                SupportTicketAnswered::class,
                SupportTicketOpened::class,
            ],
            self::Hello => [
                BusinessWelcome::class,
                ReferralLink::class,
            ],
        };
    }
}
