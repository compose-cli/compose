<?php

declare(strict_types=1);

namespace Compose\Actions;

use Compose\Contracts\Operation;
use Compose\Enums\FileOperation;

class Sink extends Action
{
    protected ?string $resolvedUrl = null;

    protected ?string $resolvedTarget = null;

    public function __construct(
        public readonly string $from,
        public readonly ?string $to = null,
        public readonly bool $force = true,
    ) {}

    #[\Override]
    public function type(): Operation
    {
        return FileOperation::Sink;
    }

    #[\Override]
    public function command(): PendingCommand
    {
        $url = $this->resolveUrl();
        $target = $this->resolveTarget();

        return (new PendingCommand('curl', '-fsSL'))
            ->flag('-o')
            ->argument($target)
            ->argument($url);
    }

    #[\Override]
    public function rollback(): ?PendingCommand
    {
        $target = $this->resolveTarget();

        if (! $this->force) {
            return null;
        }

        return PHP_OS_FAMILY === 'Windows'
            ? new PendingCommand('cmd', '/c', 'del', '/q', $target)
            : new PendingCommand('rm', '-f', $target);
    }

    #[\Override]
    public function canBeRolledBack(): bool
    {
        return $this->force;
    }

    #[\Override]
    public function describe(): string
    {
        $target = $this->resolveTarget();

        $source = str_starts_with($this->from, 'github:')
            ? $this->from
            : $this->shortenUrl($this->from);

        return "sink {$source} → {$target}";
    }

    protected function shortenUrl(string $url): string
    {
        if (mb_strlen($url) <= 60) {
            return $url;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';

        return "{$host}/.../".basename($path);
    }

    public function resolveUrl(): string
    {
        if ($this->resolvedUrl !== null) {
            return $this->resolvedUrl;
        }

        if (str_starts_with($this->from, 'github:')) {
            return $this->resolvedUrl = $this->resolveGitHubUrl($this->from);
        }

        return $this->resolvedUrl = $this->from;
    }

    public function resolveTarget(): string
    {
        if ($this->resolvedTarget !== null) {
            return $this->resolvedTarget;
        }

        if ($this->to !== null) {
            return $this->resolvedTarget = $this->to;
        }

        if (str_starts_with($this->from, 'github:')) {
            return $this->resolvedTarget = $this->extractGitHubPath($this->from);
        }

        $parsed = parse_url($this->from);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/'.basename($this->from);

        return $this->resolvedTarget = $host.$path;
    }

    protected function resolveGitHubUrl(string $url): string
    {
        $without_prefix = substr($url, 7);

        $colonPos = strpos($without_prefix, ':');

        if ($colonPos === false) {
            throw new \InvalidArgumentException(
                "Invalid GitHub shorthand: [{$url}]. Expected format: github:owner/repo@ref:path/to/file",
            );
        }

        $repoPart = substr($without_prefix, 0, $colonPos);
        $path = substr($without_prefix, $colonPos + 1);

        if (str_contains($repoPart, '@')) {
            [$repo, $ref] = explode('@', $repoPart, 2);
        } else {
            $repo = $repoPart;
            $ref = 'main';
        }

        if (! str_contains($repo, '/')) {
            throw new \InvalidArgumentException(
                "Invalid GitHub shorthand: [{$url}]. Repository must be in owner/repo format.",
            );
        }

        if ($path === '') {
            throw new \InvalidArgumentException(
                "Invalid GitHub shorthand: [{$url}]. File path is required after the colon.",
            );
        }

        return "https://raw.githubusercontent.com/{$repo}/{$ref}/{$path}";
    }

    protected function extractGitHubPath(string $url): string
    {
        $without_prefix = substr($url, 7);
        $colonPos = strpos($without_prefix, ':');

        if ($colonPos === false) {
            return basename($url);
        }

        return substr($without_prefix, $colonPos + 1);
    }
}
