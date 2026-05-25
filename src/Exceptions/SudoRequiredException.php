<?php

namespace Nawasara\AuthPrimitives\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a `#[RequiresSudo]` action runs without an active sudo
 * window. It aborts the action; the dispatched `sudo-required` Livewire
 * event drives the actual step-up redirect on the frontend.
 *
 * Renders as a 403 (rather than a 500 stack trace) so that any code
 * path reaching it outside Livewire — a direct API hit, a queued retry —
 * still fails cleanly and legibly.
 *
 * Note: the render path redirects to a route named `sudo.redirect`,
 * which is owned by the integration package (nawasara/core). If that
 * route does not exist, the redirect fall back to the previous URL with
 * a flash error — the primitives package must not assume any specific
 * route topology.
 */
class SudoRequiredException extends RuntimeException
{
    public function __construct(public ?string $sudoReason = null)
    {
        parent::__construct(
            $sudoReason
                ? "Aksi ini butuh konfirmasi sudo: {$sudoReason}"
                : 'Aksi ini butuh konfirmasi sudo.'
        );
    }

    public function render(Request $request)
    {
        $payload = [
            'error' => 'sudo_required',
            'message' => $this->getMessage(),
            'reason' => $this->sudoReason,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 403);
        }

        // Try the integration package's step-up route. If it isn't
        // registered (e.g. primitives loaded standalone), bounce back to
        // the previous URL with a flash error — better than a 500.
        if (\Illuminate\Support\Facades\Route::has('sudo.redirect')) {
            return redirect()->route('sudo.redirect', [
                'intended' => url()->previous(),
            ]);
        }

        return redirect()->back()->withErrors(['sudo' => $this->getMessage()]);
    }
}
