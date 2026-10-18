<?php

declare(strict_types=1);

namespace Apps\Iam\Console;

use Illuminate\Console\Command;

/** Writes the RSA key pair iam signs JWTs with; the gateway reads the public one. */
final class GenerateJwtKeys extends Command
{
    protected $signature = 'auth:jwt-keys {--force : Replace the keys that exist}';

    protected $description = 'Generate the RSA key pair iam signs JWTs with';

    public function handle(): int
    {
        $path = (string) config('auth.tokens.jwt.path');
        is_dir($path) || mkdir($path, 0755, true);
        $public = "{$path}/jwt-public.key";
        $private = "{$path}/jwt-private.key";

        if (is_file($private) && ! $this->option('force')) {
            $this->components->info('The JWT keys exist already.');

            return self::SUCCESS;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        file_put_contents($private, $pem);
        file_put_contents($public, openssl_pkey_get_details($key)['key']);
        chmod($private, 0600);

        $this->components->info("JWT keys written to {$private} and {$public}.");

        return self::SUCCESS;
    }
}
