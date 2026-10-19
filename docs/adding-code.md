# Adding code to a module

Laravel's `make:*` commands take `--module`:

```bash
# a new module, declared in config/distributable.php
php artisan modules:make <module>
# apps/Notifications/app/Models, its migration and its factory
php artisan make:model Preference -mf --module=notifications
php artisan make:controller PreferenceController --module=notifications
```

The root `composer.json` lists the foundation under `autoload.psr-4`, and each module's own
`composer.json` its classes. The application doesn't need those entries: the package autoloads the
modules itself. They let your IDE resolve the classes, through the merge described below; a new
module needs its own, as in `apps/Iam/composer.json`.

A library only one module uses goes in that module's `composer.json`, as `spatie/laravel-permission`
does in `apps/Iam/composer.json`. The root `composer.json` merges `apps/*/composer.json`
(`wikimedia/composer-merge-plugin`), so there is still one `composer.lock` and one `vendor/`:

```bash
# declares it in the module
composer require league/csv --working-dir=apps/Analytics --no-update
# locks and installs it
composer update league/csv
```

What every module uses (`foundation/`) stays in the root file. An image built for one module leaves
out the packages only the other modules declared: the analytics image has no
`spatie/laravel-permission`.
