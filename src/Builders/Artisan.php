<?php

declare(strict_types=1);

namespace Compose\Builders;

use Closure;
use Compose\Actions\Action;
use Compose\Actions\Artisan\ArtisanAction;
use Compose\Actions\Config\ConfigAction;

class Artisan
{
    /** @var list<array{type: 'command', value: string}|array{type: 'action', value: Action}> */
    protected array $entries = [];

    /**
     * Add a raw artisan command.
     */
    public function run(string $command): static
    {
        $this->entries[] = ['type' => 'command', 'value' => $command];

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
     * Modify a Laravel config file.
     *
     * Shorthand (dot-notation): first segment = config file name, rest = key path.
     *   config('permission.teams', true)  → sets 'teams' in config/permission.php
     *
     * Builder form: file name + closure for multiple operations.
     *   config('permission', fn (ConfigBuilder $c) => $c->set('teams', true))
     *
     * @param  Closure(ConfigBuilder): void|mixed  $valueOrCallback
     */
    public function config(string $fileOrDotPath, mixed $valueOrCallback = null): static
    {
        if ($valueOrCallback instanceof Closure) {
            $path = $this->resolveConfigPath($fileOrDotPath);
            $builder = new ConfigBuilder;
            $valueOrCallback($builder);
            $this->entries[] = ['type' => 'action', 'value' => new ConfigAction(path: $path, operations: $builder->operations())];

            return $this;
        }

        $dotSegments = explode('.', $fileOrDotPath);

        if (count($dotSegments) < 2) {
            throw new \InvalidArgumentException(
                "Dot-notation config shorthand requires at least two segments (e.g. 'app.timezone'): {$fileOrDotPath}",
            );
        }

        $configName = array_shift($dotSegments);
        $key = implode('.', $dotSegments);
        $path = $this->resolveConfigPath($configName);

        $builder = new ConfigBuilder;
        $builder->set($key, $valueOrCallback);
        $this->entries[] = ['type' => 'action', 'value' => new ConfigAction(path: $path, operations: $builder->operations())];

        return $this;
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
     * Compile the collected entries into Action instances.
     *
     * Command entries are converted to ArtisanActions; action entries pass through as-is.
     * Ordering is preserved as declared.
     *
     * @return list<Action>
     */
    public function actions(): array
    {
        return array_map(
            fn (array $entry): Action => $entry['type'] === 'command'
                ? new ArtisanAction($entry['value'])
                : $entry['value'],
            $this->entries,
        );
    }

    /**
     * Resolve a config name to a file path relative to the working directory.
     *
     * If the name already contains a path separator or ends in .php, it is used as-is.
     * Otherwise, it is treated as a Laravel config name: 'permission' → 'config/permission.php'.
     */
    private function resolveConfigPath(string $name): string
    {
        if (str_contains($name, '/') || str_contains($name, '\\') || str_ends_with($name, '.php')) {
            return $name;
        }

        return "config/{$name}.php";
    }
}
