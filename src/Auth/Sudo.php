<?php

namespace Nawasara\AuthPrimitives\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * Sudo window — single source of truth for "has the user recently
 * re-authenticated for a critical action?".
 *
 * GitHub-style: after a successful step-up (OTP), the user holds a
 * short-lived sudo window. Routes (`sudo` middleware) and Livewire
 * actions (`#[RequiresSudo]`) all consult this class — never the session
 * key directly — so the window length and key name live in one place.
 *
 * The window is per-session and tied to the user id: a session that
 * somehow changes user (re-login) cannot inherit a stale sudo grant.
 *
 * This class is intentionally stateless about HOW the step-up happens.
 * The integration package (nawasara/core) drives the redirect to the
 * IdP and the callback; on success, it calls Sudo::confirm() and the
 * window opens. This package never reaches across the abstraction.
 */
class Sudo
{
    /** Session key holding the unix timestamp of the last step-up. */
    protected const CONFIRMED_AT_KEY = '_sudo_confirmed_at';

    /** Session key pinning the grant to the user it was issued for. */
    protected const USER_ID_KEY = '_sudo_user_id';

    /**
     * Window length in minutes. Mirrors GitHub's sudo mode.
     * Configurable via config('auth-primitives.sudo.window_minutes').
     */
    public static function windowMinutes(): int
    {
        return (int) config('auth-primitives.sudo.window_minutes', 15);
    }

    /**
     * Mark the current session as sudo-confirmed, as of now, for $userId.
     */
    public static function confirm(int $userId): void
    {
        Session::put(self::CONFIRMED_AT_KEY, now()->timestamp);
        Session::put(self::USER_ID_KEY, $userId);
    }

    /**
     * Is the current session inside a valid sudo window for $userId?
     *
     * False when: never confirmed, confirmed for a different user, or the
     * window has elapsed.
     */
    public static function isActive(int $userId): bool
    {
        $confirmedAt = Session::get(self::CONFIRMED_AT_KEY);
        $grantUserId = Session::get(self::USER_ID_KEY);

        if (! is_int($confirmedAt) && ! is_numeric($confirmedAt)) {
            return false;
        }

        if ((int) $grantUserId !== $userId) {
            return false;
        }

        return Carbon::createFromTimestamp((int) $confirmedAt)
            ->addMinutes(self::windowMinutes())
            ->isFuture();
    }

    /**
     * Seconds remaining in the window, or 0 if not active.
     */
    public static function remainingSeconds(int $userId): int
    {
        if (! self::isActive($userId)) {
            return 0;
        }

        $expiresAt = Carbon::createFromTimestamp((int) Session::get(self::CONFIRMED_AT_KEY))
            ->addMinutes(self::windowMinutes());

        return max(0, now()->diffInSeconds($expiresAt, false));
    }

    /**
     * Drop the sudo grant (e.g. on logout, or after a one-shot action).
     */
    public static function forget(): void
    {
        Session::forget([self::CONFIRMED_AT_KEY, self::USER_ID_KEY]);
    }
}
