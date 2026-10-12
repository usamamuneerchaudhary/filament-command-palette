# Contributing

Thanks for helping out.

## Getting set up

```bash
git clone https://github.com/usamamuneerchaudhary/filament-command-palette
cd filament-command-palette
composer install
composer test
```

## Before opening a pull request

```bash
composer format   # Pint
composer analyse  # PHPStan
composer test     # PHPUnit
```

CI is generated from `slipway.php` ([laravel-slipway](https://github.com/usamamuneerchaudhary/laravel-slipway)). After changing that file run `composer slipway:compile` and commit the YAML. Do not edit `.github/workflows/slipway.yml` by hand.

CI runs PHP 8.3–8.5 (Slipway is a require-dev and needs 8.3+). The package runtime still supports PHP 8.2. Guard any API that differs between Filament majors with `method_exists()` rather than assuming a signature.

## What a good pull request looks like

- One change per pull request.
- A test that fails without your change. Anything touching authorization needs
  one — that is the area most likely to regress quietly.
- Translation keys, not literal strings, for anything a user will read.
- CSS custom properties rather than hard-coded colours, so panel themes keep
  working.

## Reporting a bug

Include your PHP, Laravel and Filament versions, whether the panel uses tenancy,
and the smallest panel configuration that reproduces it.
