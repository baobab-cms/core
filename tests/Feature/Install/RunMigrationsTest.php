<?php

use Baobab\Install\Actions\RunMigrations;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Illuminate\Contracts\Console\Kernel;

/**
 * Suivi n° 363, brique 2 — `InstallStepByStepTest` exerce déjà le chemin
 * heureux via le vrai migrateur ; ce fichier couvre les deux formes d'échec
 * (exception levée, code non nul sans exception) et l'analyse de sortie, en
 * substituant le Kernel au conteneur par un double réel plutôt qu'un mock —
 * le contrat `Illuminate\Contracts\Console\Kernel` est petit et stable, et
 * un double réel évite d'introduire Mockery pour six méthodes.
 */
final class FakeArtisanKernel implements Kernel
{
    public function __construct(
        private readonly int $status,
        private readonly string $output,
    ) {}

    public function bootstrap() {}

    public function handle($input, $output = null)
    {
        return 0;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        return $this->status;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function queue($command, array $parameters = [])
    {
        throw new RuntimeException('Not used by RunMigrations.');
    }

    /**
     * @return array<never, never>
     */
    public function all()
    {
        return [];
    }

    public function output()
    {
        return $this->output;
    }

    public function terminate($input, $status) {}
}

final class ThrowingArtisanKernel implements Kernel
{
    public function __construct(private readonly Throwable $exception) {}

    public function bootstrap() {}

    public function handle($input, $output = null)
    {
        return 0;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        throw $this->exception;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function queue($command, array $parameters = [])
    {
        throw new RuntimeException('Not used by RunMigrations.');
    }

    /**
     * @return array<never, never>
     */
    public function all()
    {
        return [];
    }

    public function output()
    {
        return '';
    }

    public function terminate($input, $status) {}
}

it('returns the migration names actually run, in order', function () {
    $output = <<<'TXT'
    INFO  Running migrations.

      2026_07_10_000001_create_users_table ................. 12.34ms DONE
      2026_07_10_000006_seed_default_roles .................. 4.56ms DONE

    TXT;

    app()->instance(Kernel::class, new FakeArtisanKernel(0, $output));

    $migrations = app(RunMigrations::class)();

    expect($migrations)->toBe([
        '2026_07_10_000001_create_users_table',
        '2026_07_10_000006_seed_default_roles',
    ]);
});

it('returns an empty list without failing when the output format cannot be parsed', function () {
    app()->instance(Kernel::class, new FakeArtisanKernel(0, "Nothing to migrate.\n"));

    expect(app(RunMigrations::class)())->toBe([]);
});

it('wraps an exception thrown by the migrator as an InstallationStepFailed, keeping the cause', function () {
    app()->instance(Kernel::class, new ThrowingArtisanKernel(new RuntimeException('la connexion a expiré')));

    try {
        app(RunMigrations::class)();
        $this->fail('InstallationStepFailed attendue.');
    } catch (InstallationStepFailed $e) {
        expect($e->step)->toBe('migrations')
            ->and($e->getMessage())->toContain('la connexion a expiré')
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }
});

it('throws an InstallationStepFailed on a non-zero exit code, with the last lines of output', function () {
    $output = <<<'TXT'
      2026_07_10_000001_create_users_table ................. 12.34ms DONE
      2026_07_10_000006_seed_default_roles .................. 4.56ms FAIL

    In Connection.php line 812:
      SQLSTATE[42S01]: Base table or view already exists
    TXT;

    app()->instance(Kernel::class, new FakeArtisanKernel(1, $output));

    try {
        app(RunMigrations::class)();
        $this->fail('InstallationStepFailed attendue.');
    } catch (InstallationStepFailed $e) {
        expect($e->step)->toBe('migrations')
            ->and($e->getMessage())->toContain('Relancez l\'installation')
            ->and($e->getMessage())->toContain('SQLSTATE[42S01]');
    }
});

it('throws with an empty tail when the migrator returns a non-zero code with no output at all', function () {
    app()->instance(Kernel::class, new FakeArtisanKernel(1, ''));

    expect(fn () => app(RunMigrations::class)())
        ->toThrow(InstallationStepFailed::class, 'Relancez l\'installation');
});
