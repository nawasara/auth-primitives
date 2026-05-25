<?php

namespace Nawasara\AuthPrimitives\Attributes;

use Attribute;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;
use Nawasara\AuthPrimitives\Auth\Sudo;

/**
 * Method-level Livewire attribute — gates a single action behind sudo mode.
 *
 *   use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
 *
 *   #[RequiresSudo]
 *   public function dropDatabase(string $name): void { ... }
 *
 * Livewire runs every method-level attribute's `call()` hook before the
 * action body. When the session is NOT inside a valid sudo window this
 * hook calls $returnEarly() (Livewire's clean abort) and dispatches the
 * `sudo-required` event; the WithSudo trait on the component picks the
 * event up and drives the step-up redirect, then the user retries.
 *
 * Gating one METHOD — not the whole component — is deliberate: a table
 * component can list rows freely and only demand sudo when the "Drop"
 * action fires.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequiresSudo extends LivewireAttribute
{
    /**
     * @param  string|null  $reason  Optional human-readable reason shown
     *                               in the step-up prompt (e.g. "menghapus
     *                               database"). Surfaced to the frontend.
     */
    public function __construct(public ?string $reason = null)
    {
    }

    /**
     * Livewire lifecycle hook — runs before the gated action.
     *
     * If sudo is active, return and the action proceeds normally.
     * Otherwise call $returnEarly() — Livewire then skips the action body
     * entirely (see HandleComponents::callMethods) — and dispatch
     * `sudo-required` so WithSudo's listener launches the OTP step-up.
     *
     * No exception is thrown: $returnEarly() is Livewire's own clean
     * abort mechanism; throwing here would surface a 500 and could drop
     * the dispatched event.
     */
    public function call($params, $returnEarly)
    {
        $userId = (int) Auth::id();

        if ($userId > 0 && Sudo::isActive($userId)) {
            return;
        }

        $returnEarly();

        $this->component->dispatch('sudo-required', reason: $this->reason);
    }
}
