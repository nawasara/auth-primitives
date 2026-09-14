# nawasara/auth-primitives

Low-level auth primitives for [Nawasara](https://github.com/nawasara) packages. It lives below the application shell (`nawasara/core`) so any domain package can depend on it without pulling in the rest of Nawasara.

## What's in the box

| Primitive | Purpose |
|---|---|
| `Nawasara\AuthPrimitives\Auth\Sudo` | Session window: the single source of truth for whether the user recently re-authenticated |
| `Nawasara\AuthPrimitives\Http\Middleware\EnsureSudo` | Route gate, registered as the `sudo` middleware alias |
| `#[Nawasara\AuthPrimitives\Attributes\RequiresSudo]` | Livewire method attribute that gates one action behind sudo |
| `Nawasara\AuthPrimitives\Traits\WithSudo` | Livewire component trait that handles the step-up redirect |
| `Nawasara\AuthPrimitives\Exceptions\SudoRequiredException` | Renderable exception (403 or redirect) |
| `sudo_active()`, `sudo_remaining_seconds()` | Blade helpers |

## What's NOT in here

The OTP step-up itself (IdP redirect, callback, ID-token verification) is **not** in this package. It lives in `nawasara/core`'s `SudoController`, which calls `Sudo::confirm($userId)` on a verified step-up. This split lets domain packages enforce a sudo window without depending on the integration plumbing.

## Usage

### Route-level

```php
Route::get('db/drop/{name}', ...)->middleware(['auth', 'sudo']);
```

The `sudo` alias is registered automatically by `AuthPrimitivesServiceProvider`.

### Livewire action-level

```php
use Livewire\Component;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;

class DangerousThings extends Component
{
    use WithSudo;

    #[RequiresSudo(reason: 'menghapus database')]
    public function dropDatabase(string $name): void
    {
        // …only runs inside an active sudo window
    }
}
```

### Blade display

```blade
@if (sudo_active())
    <button wire:click="dropDatabase">Hapus</button>
@else
    <button wire:click="$dispatch('sudo-required')">Hapus (butuh konfirmasi)</button>
@endif
```

## Config

Defaults are bundled. Publish to override:

```bash
php artisan vendor:publish --tag=auth-primitives:config
```

```php
// config/auth-primitives.php
return [
    'sudo' => [
        'window_minutes' => env('NAWASARA_SUDO_WINDOW_MINUTES', 15),
        'acr' => env('NAWASARA_SUDO_ACR', 'sudo'),
    ],
];
```

## License

MIT.
