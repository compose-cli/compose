<?php

declare(strict_types=1);

namespace Compose\Support\PhpFile;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use RuntimeException;

class PhpFileEditor
{
    private ClassType $class;

    private ?PhpNamespace $namespace = null;

    private function __construct(
        private readonly PhpFile $file,
    ) {
        [$this->namespace, $this->class] = $this->extractClass();
    }

    public static function fromCode(string $code): static
    {
        return new static(PhpFile::fromCode($code));
    }

    public function manipulator(): ClassManipulator
    {
        return new ClassManipulator($this->class, $this->namespace);
    }

    public function render(): string
    {
        return (new PsrPrinter)->printFile($this->file);
    }

    /**
     * @return array{?PhpNamespace, ClassType}
     */
    private function extractClass(): array
    {
        foreach ($this->file->getNamespaces() as $namespace) {
            foreach ($namespace->getClasses() as $class) {
                return [$namespace, $class];
            }
        }

        foreach ($this->file->getClasses() as $class) {
            return [null, $class];
        }

        throw new RuntimeException('No class found in the PHP file.');
    }
}
