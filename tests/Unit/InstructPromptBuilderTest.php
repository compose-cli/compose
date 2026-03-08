<?php

declare(strict_types=1);

use Compose\AI\Prompts\InstructPromptBuilder;
use Compose\Payloads\InstructPayload;

describe('InstructPromptBuilder', function (): void {
    // -------------------------------------------------------------------
    // Task section
    // -------------------------------------------------------------------

    it('includes the description under Task heading', function (): void {
        $payload = new InstructPayload('Build a dashboard', [], [], [], [], [], [], []);
        $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

        expect($prompt)->toContain("## Task\nBuild a dashboard");
    });

    // -------------------------------------------------------------------
    // Scope section
    // -------------------------------------------------------------------

    describe('scope', function (): void {
        it('lists creating files under Scope', function (): void {
            $payload = new InstructPayload('desc', ['app/Widget.php'], [], [], [], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('## Scope');
            expect($prompt)->toContain('Files to create:');
            expect($prompt)->toContain('- app/Widget.php');
        });

        it('lists modifying files under Scope', function (): void {
            $payload = new InstructPayload('desc', [], ['routes/web.php'], [], [], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('Files to modify:');
            expect($prompt)->toContain('- routes/web.php');
        });

        it('omits Scope section when no creating or modifying', function (): void {
            $payload = new InstructPayload('desc', [], [], [], [], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->not->toContain('## Scope');
        });
    });

    // -------------------------------------------------------------------
    // Context files
    // -------------------------------------------------------------------

    describe('context files', function (): void {
        it('lists hint-only files by path without contents', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'app/Models/User.php', 'include' => false],
                ], [], [], [], [],
            );

            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('## Context Files');
            expect($prompt)->toContain('- app/Models/User.php');
        });

        it('inlines contents for included files', function (): void {
            $this->createFile('src/Service.php', '<?php class Service {}');

            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'src/Service.php', 'include' => true],
                ], [], [], [], [],
            );

            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('```php');
            expect($prompt)->toContain('<?php class Service {}');
        });

        it('shows file not found for missing included files', function (): void {
            $payload = new InstructPayload(
                'desc', [], [], [
                    ['path' => 'missing.php', 'include' => true],
                ], [], [], [], [],
            );

            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('(file not found)');
        });

        it('omits context section when no using entries', function (): void {
            $payload = new InstructPayload('desc', [], [], [], [], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->not->toContain('## Context Files');
        });
    });

    // -------------------------------------------------------------------
    // Style references
    // -------------------------------------------------------------------

    describe('style references', function (): void {
        it('inlines like file contents', function (): void {
            $this->createFile('app/ProfilePage.php', '<?php class ProfilePage {}');

            $payload = new InstructPayload('desc', [], [], [], ['app/ProfilePage.php'], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('## Style References');
            expect($prompt)->toContain('### app/ProfilePage.php');
            expect($prompt)->toContain('<?php class ProfilePage {}');
        });

        it('shows file not found for missing like files', function (): void {
            $payload = new InstructPayload('desc', [], [], [], ['missing.php'], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->toContain('(file not found)');
        });

        it('omits style section when no like entries', function (): void {
            $payload = new InstructPayload('desc', [], [], [], [], [], [], []);
            $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

            expect($prompt)->not->toContain('## Style References');
        });
    });

    // -------------------------------------------------------------------
    // Other sections
    // -------------------------------------------------------------------

    it('includes tests section', function (): void {
        $payload = new InstructPayload('desc', [], [], [], [], [], [], ['tests/WidgetTest.php']);
        $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

        expect($prompt)->toContain('## Tests');
        expect($prompt)->toContain('- tests/WidgetTest.php');
    });

    it('includes rules section', function (): void {
        $payload = new InstructPayload('desc', [], [], [], [], ['Use strict types', 'No globals'], [], []);
        $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

        expect($prompt)->toContain('## Rules');
        expect($prompt)->toContain('- Use strict types');
        expect($prompt)->toContain('- No globals');
    });

    it('includes project context section', function (): void {
        $payload = new InstructPayload('desc', [], [], [], [], [], ['framework' => 'Livewire', 'php' => '8.3'], []);
        $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

        expect($prompt)->toContain('## Project Context');
        expect($prompt)->toContain('framework: Livewire');
        expect($prompt)->toContain('php: 8.3');
    });

    it('omits empty sections entirely', function (): void {
        $payload = new InstructPayload('just a task', [], [], [], [], [], [], []);
        $prompt = (new InstructPromptBuilder)->build($payload, context(workingDirectory: $this->tempPath));

        expect($prompt)->toContain('## Task');
        expect($prompt)->not->toContain('## Scope');
        expect($prompt)->not->toContain('## Context Files');
        expect($prompt)->not->toContain('## Style References');
        expect($prompt)->not->toContain('## Tests');
        expect($prompt)->not->toContain('## Rules');
        expect($prompt)->not->toContain('## Project Context');
    });
});
