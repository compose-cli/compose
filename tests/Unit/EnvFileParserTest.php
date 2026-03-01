<?php

use Compose\Support\TextFile\EnvFileParser;

describe('EnvFileParser', function (): void {

    // -------------------------------------------------------------------
    // Parsing
    // -------------------------------------------------------------------

    describe('parsing', function (): void {

        it('parses basic key-value pairs', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nAPP_ENV=local");

            expect($parser->get('APP_NAME'))->toBe('Laravel');
            expect($parser->get('APP_ENV'))->toBe('local');
        });

        it('parses double-quoted values', function (): void {
            $parser = EnvFileParser::parse('APP_NAME="My Application"');

            expect($parser->get('APP_NAME'))->toBe('My Application');
        });

        it('parses single-quoted values', function (): void {
            $parser = EnvFileParser::parse("APP_KEY='base64:abc123=='");

            expect($parser->get('APP_KEY'))->toBe('base64:abc123==');
        });

        it('parses empty values', function (): void {
            $parser = EnvFileParser::parse('DB_PASSWORD=');

            expect($parser->get('DB_PASSWORD'))->toBe('');
        });

        it('parses bare keys with no equals sign', function (): void {
            $parser = EnvFileParser::parse('BARE_KEY');

            expect($parser->has('BARE_KEY'))->toBeTrue();
            expect($parser->get('BARE_KEY'))->toBeNull();
        });

        it('preserves comments and blank lines', function (): void {
            $input = "# Database\nDB_HOST=127.0.0.1\n\n# Cache\nCACHE_DRIVER=file";

            $parser = EnvFileParser::parse($input);

            expect($parser->toString())->toBe($input);
        });

        it('handles export prefix', function (): void {
            $parser = EnvFileParser::parse('export APP_NAME=Laravel');

            expect($parser->get('APP_NAME'))->toBe('Laravel');
        });

        it('handles inline comments on unquoted values', function (): void {
            $parser = EnvFileParser::parse('API_KEY=abc123 # secret');

            expect($parser->get('API_KEY'))->toBe('abc123');
        });

        it('handles equals signs in values', function (): void {
            $parser = EnvFileParser::parse('SOME_KEY=foo=bar=baz');

            expect($parser->get('SOME_KEY'))->toBe('foo=bar=baz');
        });

        it('handles escaped characters in double quotes', function (): void {
            $parser = EnvFileParser::parse('MSG="hello \"world\""');

            expect($parser->get('MSG'))->toBe('hello "world"');
        });

        it('does not process escapes in single quotes', function (): void {
            $parser = EnvFileParser::parse("PATH='C:\\\\Users'");

            expect($parser->get('PATH'))->toBe('C:\\\\Users');
        });

        it('handles empty input', function (): void {
            $parser = EnvFileParser::parse('');

            expect($parser->toString())->toBe('');
        });

        it('handles special characters in unquoted values', function (): void {
            $parser = EnvFileParser::parse('MAIL_FROM=foo@bar.com');

            expect($parser->get('MAIL_FROM'))->toBe('foo@bar.com');
        });

        it('handles URLs in values', function (): void {
            $parser = EnvFileParser::parse('APP_URL=http://localhost:8000');

            expect($parser->get('APP_URL'))->toBe('http://localhost:8000');
        });

    });

    // -------------------------------------------------------------------
    // Get / Has
    // -------------------------------------------------------------------

    describe('get and has', function (): void {

        it('returns null for missing keys', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            expect($parser->get('MISSING'))->toBeNull();
        });

        it('returns false for missing keys with has()', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            expect($parser->has('MISSING'))->toBeFalse();
        });

        it('returns true for existing keys with has()', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            expect($parser->has('APP_NAME'))->toBeTrue();
        });

    });

    // -------------------------------------------------------------------
    // Set
    // -------------------------------------------------------------------

    describe('set', function (): void {

        it('updates an existing key', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->set('APP_NAME', 'My App');

            expect($parser->get('APP_NAME'))->toBe('My App');
            expect($parser->toString())->toBe('APP_NAME=My App');
        });

        it('appends a new key', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->set('APP_ENV', 'production');

            expect($parser->get('APP_ENV'))->toBe('production');
            expect($parser->toString())->toBe("APP_NAME=Laravel\nAPP_ENV=production");
        });

        it('inserts after a specific key', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nAPP_DEBUG=true");

            $parser->set('APP_ENV', 'local', afterKey: 'APP_NAME');

            expect($parser->toString())->toBe("APP_NAME=Laravel\nAPP_ENV=local\nAPP_DEBUG=true");
        });

        it('appends when afterKey does not exist', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->set('NEW_KEY', 'value', afterKey: 'NONEXISTENT');

            expect($parser->toString())->toBe("APP_NAME=Laravel\nNEW_KEY=value");
        });

        it('updates in place without changing position', function (): void {
            $parser = EnvFileParser::parse("FIRST=1\nSECOND=2\nTHIRD=3");

            $parser->set('SECOND', 'updated');

            expect($parser->toString())->toBe("FIRST=1\nSECOND=updated\nTHIRD=3");
        });

    });

    // -------------------------------------------------------------------
    // Remove
    // -------------------------------------------------------------------

    describe('remove', function (): void {

        it('removes an existing key', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nAPP_ENV=local");

            $parser->remove('APP_ENV');

            expect($parser->has('APP_ENV'))->toBeFalse();
            expect($parser->toString())->toBe('APP_NAME=Laravel');
        });

        it('is a no-op for missing keys', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->remove('MISSING');

            expect($parser->toString())->toBe('APP_NAME=Laravel');
        });

    });

    // -------------------------------------------------------------------
    // Comment / Uncomment
    // -------------------------------------------------------------------

    describe('comment and uncomment', function (): void {

        it('comments out an existing key', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nAPP_DEBUG=true");

            $parser->comment('APP_DEBUG');

            expect($parser->has('APP_DEBUG'))->toBeFalse();
            expect($parser->toString())->toBe("APP_NAME=Laravel\n# APP_DEBUG=true");
        });

        it('uncomments a previously commented key', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\n# APP_DEBUG=true");

            $parser->uncomment('APP_DEBUG');

            expect($parser->has('APP_DEBUG'))->toBeTrue();
            expect($parser->get('APP_DEBUG'))->toBe('true');
        });

        it('is a no-op when commenting a missing key', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->comment('MISSING');

            expect($parser->toString())->toBe('APP_NAME=Laravel');
        });

        it('is a no-op when uncommenting a key that is not commented', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->uncomment('MISSING');

            expect($parser->toString())->toBe('APP_NAME=Laravel');
        });

        it('round-trips comment then uncomment', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nAPP_DEBUG=true");

            $parser->comment('APP_DEBUG');
            $parser->uncomment('APP_DEBUG');

            expect($parser->has('APP_DEBUG'))->toBeTrue();
            expect($parser->get('APP_DEBUG'))->toBe('true');
        });

    });

    // -------------------------------------------------------------------
    // Section
    // -------------------------------------------------------------------

    describe('section', function (): void {

        it('adds a section at the end', function (): void {
            $parser = EnvFileParser::parse('APP_NAME=Laravel');

            $parser->addSection('# Permissions', ['TEAMS' => 'true', 'ROLES' => 'true']);

            $expected = "APP_NAME=Laravel\n\n# Permissions\nTEAMS=true\nROLES=true";
            expect($parser->toString())->toBe($expected);
        });

        it('adds a section after a specific key', function (): void {
            $parser = EnvFileParser::parse("APP_NAME=Laravel\nCACHE_DRIVER=file");

            $parser->addSection('# Redis', ['REDIS_HOST' => '127.0.0.1'], afterKey: 'APP_NAME');

            $expected = "APP_NAME=Laravel\n\n# Redis\nREDIS_HOST=127.0.0.1\nCACHE_DRIVER=file";
            expect($parser->toString())->toBe($expected);
        });

    });

    // -------------------------------------------------------------------
    // Round-trip Serialization
    // -------------------------------------------------------------------

    describe('round-trip', function (): void {

        it('preserves the original file when no changes are made', function (): void {
            $input = "APP_NAME=Laravel\nAPP_ENV=local\nAPP_KEY=base64:abc123\n\n# Database\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=laravel\nDB_USERNAME=root\nDB_PASSWORD=";

            $parser = EnvFileParser::parse($input);

            expect($parser->toString())->toBe($input);
        });

        it('only re-serializes changed lines', function (): void {
            $input = "APP_NAME=\"My App\"\nAPP_ENV=local";

            $parser = EnvFileParser::parse($input);
            $parser->set('APP_ENV', 'production');

            expect($parser->toString())->toBe("APP_NAME=\"My App\"\nAPP_ENV=production");
        });

    });

});
