<?php

declare(strict_types=1);

namespace Compose\Builders;

use Closure;
use Compose\Actions\ArtisanAction;

class Artisan
{
    /** @var string[] */
    protected array $commands = [];

    /**
     * Add a raw artisan command.
     */
    public function run(string $command): static
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * Run a migration.
     */
    public function migrate(bool $fresh = false, bool $seed = false): static
    {
        $command = $fresh ? 'migrate:fresh' : 'migrate';

        if ($seed) {
            $command .= ' --seed';
        }

        return $this->run($command);
    }

    /**
     * Seed the database.
     *
     * When called with no arguments, runs `db:seed`.
     * When called with seeder names, runs `db:seed --class=<name>` for each.
     */
    public function seed(string ...$seeders): static
    {
        if ($seeders === []) {
            return $this->run('db:seed');
        }

        foreach ($seeders as $seeder) {
            $this->run("db:seed --class={$seeder}");
        }

        return $this;
    }

    /**
     * Publish vendor assets.
     */
    public function publish(?string $provider = null, ?string $tag = null): static
    {
        $command = 'vendor:publish';

        if ($provider !== null) {
            $command .= " --provider={$provider}";
        }

        if ($tag !== null) {
            $command .= " --tag={$tag}";
        }

        return $this->run($command);
    }

    /**
     * Run a make command.
     */
    public function make(string $resource, string $name): static
    {
        return $this->run("make:{$resource} {$name}");
    }

    /**
     * Make a model with optional related files.
     */
    public function makeModel(
        string $name,
        bool $migration = false,
        bool $factory = false,
        bool $seeder = false,
    ): static {
        $flags = '';

        if ($migration) {
            $flags .= ' -m';
        }

        if ($factory) {
            $flags .= ' -f';
        }

        if ($seeder) {
            $flags .= ' -s';
        }

        return $this->run("make:model {$name}{$flags}");
    }

    /**
     * Conditionally add commands.
     */
    public function when(bool|Closure $condition, Closure $callback): static
    {
        $result = $condition instanceof Closure ? $condition() : $condition;

        if ($result) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Compile the collected commands into ArtisanAction instances.
     *
     * @return ArtisanAction[]
     */
    public function actions(): array
    {
        return array_map(
            fn (string $command): ArtisanAction => new ArtisanAction($command),
            $this->commands,
        );
    }
}