# Clovic development guidance

This is a Laravel 13 / PHP 8.4 application using Blade, Tailwind 4, Alpine.js and MySQL. Read README.md and docs/ARCHITECTURE.md before changing commerce logic.

- Preserve integer-paisa calculations, trusted cart loading, row locks, token ownership, order snapshots and once-only restocking.
- Keep COD payment collection independent of fulfillment status.
- Keep admin authorization on the server. Only owners may grant/revoke admin access.
- Never put secrets in Git, application output, public files, fixtures or command arguments.
- Use migrations for every schema change and keep MySQL/InnoDB compatibility.
- Demo products remain labeled, noindex and excluded from live checkout by default.
- Product photos, exact specifications and business policy promises must come from the owner.
- Use Laravel validation, escaped Blade output, authorization policies and the existing service layer.
- Run relevant PHPUnit tests and production asset compilation after behavior changes. Use the isolated MySQL harness when changing locks/idempotency/stock logic.
- Run Laravel Pint on edited PHP files. No additional agent delegation is required.
- The ignored .tools directory contains local development runtimes only; never deploy it.
