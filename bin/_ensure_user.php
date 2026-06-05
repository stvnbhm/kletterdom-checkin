<?php

declare(strict_types=1);

namespace Kletterdom\Cli;

use Kletterdom\Auth\Hash;
use Kletterdom\Bootstrap;
use Kletterdom\Repositories\UserRepository;
use PDO;

require_once dirname(__DIR__) . '/src/bootstrap.php';

final class EnsureUser
{
    /** @param array<int,string> $argv */
    public static function run(array $argv, bool $isAdmin, string $label): int
    {
        $args = self::parse($argv);

        $email = strtolower(trim($args['_args'][0] ?? ''));
        $name  = trim($args['name'] ?? '') !== '' ? trim($args['name']) : $label;
        $pass  = (string) ($args['password'] ?? '');
        $help  = isset($args['help']);

        if ($help || $email === '') {
            self::usage($label);
            return $email === '' && ! $help ? 1 : 0;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "Invalid email: {$email}\n");
            return 1;
        }

        if ($pass !== '' && strlen($pass) < 8) {
            fwrite(STDERR, "Password must be at least 8 characters.\n");
            return 1;
        }

        Bootstrap::init();
        $config = require dirname(__DIR__) . '/config/database.php';
        $pdo    = \Kletterdom\Database\Connection::pdo($config);

        $repo     = new UserRepository($pdo);
        $existing = $repo->findByEmail($email);

        if ($existing === null && $pass === '') {
            if (! self::isInteractive()) {
                fwrite(STDERR, "A password (--password=...) is required when creating a user non-interactively.\n");
                return 1;
            }
            $pass = self::promptHidden("Password for {$email}: ");
            if (strlen($pass) < 8) {
                fwrite(STDERR, "Password must be at least 8 characters.\n");
                return 1;
            }
        }

        $hash = $pass !== '' ? Hash::make($pass) : null;

        if ($existing === null) {
            $repo->upsert($email, $name, $hash, $isAdmin);
            fwrite(STDOUT, "{$label} user {$email} created.\n");
            return 0;
        }

        $repo->upsert($email, (string) $existing['name'], $hash, $isAdmin);
        fwrite(STDOUT, "User {$email} is now " . ($isAdmin ? 'an admin' : 'hallendienst') . ".\n");
        return 0;
    }

    private static function usage(string $label): void
    {
        $cmd = ($label === 'Admin') ? 'ensure-admin' : 'ensure-staff';
        $msg = <<<TXT
        Usage: bin/{$cmd} <email> [--name=NAME] [--password=PASS]

          <email>            Email address of the {$label} account.
          --name=NAME        Display name (used only when creating a new user).
          --password=PASS    Password (required for new users in non-interactive mode).

        Examples:
          bin/{$cmd} admin@example.com --password=changeme123
          bin/{$cmd} admin@example.com --name="Halle Dom" --password=changeme123

        TXT;
        fwrite(STDOUT, $msg);
    }

    /**
     * Parses argv into {_args, --flags}.
     *
     * @param array<int,string> $argv
     * @return array<string,mixed>
     */
    private static function parse(array $argv): array
    {
        array_shift($argv);
        $out = ['_args' => []];
        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2);
                if (str_contains($token, '=')) {
                    [$k, $v] = explode('=', $token, 2);
                    $out[$k] = $v;
                } else {
                    $out[$token] = true;
                }
            } else {
                $out['_args'][] = $token;
            }
        }
        return $out;
    }

    private static function isInteractive(): bool
    {
        return function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
    }

    private static function promptHidden(string $prompt): string
    {
        fwrite(STDOUT, $prompt);
        if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'win') === false) {
            system('stty -echo');
            $line = (string) fgets(STDIN);
            system('stty echo');
            fwrite(STDOUT, "\n");
            return trim($line);
        }
        return trim((string) fgets(STDIN));
    }
}
