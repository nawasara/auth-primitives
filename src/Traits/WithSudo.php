<?php

namespace Nawasara\AuthPrimitives\Traits;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Nawasara\AuthPrimitives\Auth\Sudo;

/**
 * Livewire component trait for sudo-gated components.
 *
 * Two ways to gate an action:
 *
 *  1. Declarative — annotate the method (component must `use WithSudo`):
 *
 *         #[RequiresSudo(reason: 'menghapus database')]
 *         public function dropDatabase(string $name): void { ... }
 *
 *     The attribute aborts the action and dispatches `sudo-required`;
 *     this trait's #[On('sudo-required')] listener turns that into the
 *     step-up redirect.
 *
 *  2. Imperative — call the guard mid-method when the check is dynamic.
 *     requireSudo() returns false (and starts a redirect) when sudo is
 *     missing; the caller MUST return on false:
 *
 *         public function save(): void
 *         {
 *             if ($this->isDestructive() && ! $this->requireSudo('menyimpan perubahan destruktif')) {
 *                 return; // redirect to step-up already issued
 *             }
 *             // ...proceeds only when sudo is active
 *         }
 *
 * Either way the user is sent to `sudo.redirect` (a route owned by the
 * integration package) with the current page as `intended`, completes
 * the OTP step-up, and lands back here — now inside the sudo window, so
 * the retried action passes the gate.
 */
trait WithSudo
{
    /**
     * Livewire event listener — fired by the #[RequiresSudo] attribute
     * when an action is hit without an active sudo window. The #[On]
     * attribute self-registers; no getListeners() wiring needed.
     */
    #[On('sudo-required')]
    public function handleSudoRequired(?string $reason = null): void
    {
        $this->redirectToSudo($reason);
    }

    /**
     * Imperative guard.
     *
     * Returns true when the session already holds an active sudo window —
     * the caller proceeds. Returns false when sudo is missing, having
     * first issued the step-up redirect; the caller MUST `return`
     * immediately on false so no destructive work runs before the
     * redirect takes effect.
     */
    public function requireSudo(?string $reason = null): bool
    {
        $userId = (int) Auth::id();

        if ($userId > 0 && Sudo::isActive($userId)) {
            return true;
        }

        $this->redirectToSudo($reason);

        return false;
    }

    /**
     * Whether the current session already holds a valid sudo window —
     * handy for toggling UI affordances (e.g. enabling a "Drop" button).
     */
    public function sudoActive(): bool
    {
        $userId = (int) Auth::id();

        return $userId > 0 && Sudo::isActive($userId);
    }

    /**
     * Issue the Livewire redirect to the step-up flow, returning to the
     * page the user is actually looking at.
     *
     * Inside a Livewire action the request URL is `/livewire/update` (the
     * XHR endpoint) — NOT the visible page. Redirecting `intended` there
     * would, after the step-up, bounce the browser to /livewire/update via
     * GET → 405 Method Not Allowed. Livewire itself reads the originating
     * page from the `Referer` header (see SupportQueryString\BaseUrl); we
     * do the same, and fall back to url()->current() for non-Livewire HTTP.
     */
    protected function redirectToSudo(?string $reason = null): void
    {
        $this->redirectRoute('sudo.redirect', [
            'intended' => $this->currentPageUrl(),
        ]);
    }

    /**
     * The URL of the page the user is on — Referer when this is a Livewire
     * XHR, otherwise the current request URL.
     */
    protected function currentPageUrl(): string
    {
        $request = request();

        if ($request->hasHeader('X-Livewire')) {
            $referer = $request->header('Referer');

            if (is_string($referer) && $referer !== '') {
                return $referer;
            }
        }

        return url()->current();
    }
}
