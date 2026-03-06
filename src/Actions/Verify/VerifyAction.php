<?php

declare(strict_types=1);

namespace Compose\Actions\Verify;

use Closure;
use Compose\Actions\Action;
use Compose\Enums\VerifyOperation;
use Compose\Execution\ActionResult;
use Compose\RecipeContext;

class VerifyAction extends Action
{
    public function __construct(
        public readonly Closure|string $assertion,
        bool $allowFailure = false,
    ) {
        $this->allowFailure = $allowFailure;
    }

    #[\Override]
    public function type(): VerifyOperation
    {
        return VerifyOperation::Verify;
    }

    #[\Override]
    public function execute(RecipeContext $context): ActionResult
    {
        if (is_string($this->assertion)) {
            return ActionResult::success(
                command: ['verify', $this->assertion],
                output: "Skipped (AI not available): {$this->assertion}",
            );
        }

        $result = ($this->assertion)();

        if ($result) {
            return ActionResult::success(
                command: ['verify', '(closure)'],
                output: 'Verification passed',
            );
        }

        return ActionResult::failure(
            errorOutput: 'Verification failed',
            command: ['verify', '(closure)'],
        );
    }

    #[\Override]
    public function describe(): string
    {
        if (is_string($this->assertion)) {
            return "verify: {$this->assertion}";
        }

        return 'verify: (closure)';
    }
}
