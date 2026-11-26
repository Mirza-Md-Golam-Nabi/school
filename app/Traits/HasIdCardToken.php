<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * For profile models that get a printed ID card (student, teacher, staff):
 * each profile holds one secret token, and the card's QR code opens the
 * public verification page for it. The token outlives reprints, so a card
 * printed years ago still scans — until the school reissues it after a card
 * is lost, which voids every card printed before.
 *
 * @property string|null $id_card_token
 */
trait HasIdCardToken
{
    public static function bootHasIdCardToken(): void
    {
        static::creating(function (self $profile): void {
            $profile->id_card_token ??= static::newIdCardToken();
        });
    }

    /**
     * A short, unguessable token — short so the QR stays sparse enough to
     * scan from a small printed card.
     */
    public static function newIdCardToken(): string
    {
        return Str::random(16);
    }

    /**
     * The public page the card's QR code opens, or null for a profile
     * without a token (e.g. an unsaved sample).
     */
    public function idCardVerificationUrl(): ?string
    {
        return filled($this->id_card_token)
            ? route('id-cards.verify', ['token' => $this->id_card_token])
            : null;
    }

    /**
     * Give the profile a new token: cards printed before stop verifying and
     * the next printed card carries the new QR.
     */
    public function reissueIdCardToken(): void
    {
        $this->forceFill(['id_card_token' => static::newIdCardToken()])->saveQuietly();
    }

    /**
     * The profile whose card carries this token, including profiles that were
     * deleted — so a removed person's card says "no longer here" rather than
     * looking forged.
     */
    public static function findByIdCardToken(string $token): ?static
    {
        return static::query()->withTrashed()->where('id_card_token', $token)->first();
    }
}
