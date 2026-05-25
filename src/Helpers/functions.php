<?php

use Illuminate\Support\Facades\Auth;
use Nawasara\AuthPrimitives\Auth\Sudo;

if (! function_exists('sudo_active')) {
    /**
     * Whether the current session holds a valid sudo window — i.e. the
     * user recently completed the OTP step-up.
     *
     * Use in Blade to toggle UI affordances:
     *
     *   @if (sudo_active())
     *       <button wire:click="dropDatabase">Hapus</button>
     *   @endif
     *
     * Gating the actual action still belongs to the `sudo` route
     * middleware or the #[RequiresSudo] attribute — this is only for
     * display logic.
     */
    function sudo_active(): bool
    {
        $userId = (int) Auth::id();

        return $userId > 0 && Sudo::isActive($userId);
    }
}

if (! function_exists('sudo_remaining_seconds')) {
    /**
     * Seconds left in the current sudo window, or 0 when not active.
     * Handy for a countdown badge.
     */
    function sudo_remaining_seconds(): int
    {
        $userId = (int) Auth::id();

        return $userId > 0 ? Sudo::remainingSeconds($userId) : 0;
    }
}
