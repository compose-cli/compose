<?php

declare(strict_types=1);

namespace Compose\Support\PhpFile;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Constant;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property;
use RuntimeException;

class ClassManipulator
{
    public function __construct(
        private readonly ClassType $class,
        private readonly ?PhpNamespace $namespace = null,
    ) {}

    public function addTrait(string $trait): static
    {
        if (str_contains($trait, '\\')) {
            $this->namespace?->addUse($trait);
        }

        $this->class->addTrait($trait);

        return $this;
    }

    public function removeTrait(string $trait): static
    {
        $name = str_contains($trait, '\\') ? $this->shortName($trait) : $trait;
        $this->class->removeTrait($name);

        return $this;
    }

    public function addInterface(string $interface): static
    {
        if (str_contains($interface, '\\')) {
            $this->namespace?->addUse($interface);
        }

        $this->class->addImplement($interface);

        return $this;
    }

    public function addImport(string $class): static
    {
        $this->namespace?->addUse($class);

        return $this;
    }

    public function removeImport(string $class): static
    {
        $this->namespace?->removeUse($class);

        return $this;
    }

    public function addMethod(
        string $name,
        string $body,
        string $visibility = 'public',
        ?string $returnType = null,
    ): static {
        $method = $this->class->addMethod($name)
            ->setBody($body);

        $this->applyVisibility($method, $visibility);

        if ($returnType !== null) {
            $method->setReturnType($returnType);
        }

        return $this;
    }

    public function addProperty(
        string $name,
        mixed $default = null,
        string $visibility = 'public',
        ?string $type = null,
    ): static {
        $property = $this->class->addProperty($name);

        if ($default !== null) {
            $property->setValue($default);
        }

        $this->applyVisibility($property, $visibility);

        if ($type !== null) {
            $property->setType($type);
        }

        return $this;
    }

    public function addConstant(
        string $name,
        mixed $value,
        string $visibility = 'public',
    ): static {
        $constant = $this->class->addConstant($name, $value);
        $this->applyVisibility($constant, $visibility);

        return $this;
    }

    /**
     * @param  array<mixed>  $values
     */
    public function addToArray(string $property, array $values): static
    {
        if (! $this->class->hasProperty($property)) {
            throw new RuntimeException("Property \${$property} does not exist on class {$this->class->getName()}.");
        }

        $prop = $this->class->getProperty($property);
        $current = $prop->getValue();

        if (! is_array($current)) {
            $current = [];
        }

        if (array_is_list($values) && array_is_list($current)) {
            $merged = array_values(array_unique(array_merge($current, $values)));
        } else {
            $merged = array_merge($current, $values);
        }

        $prop->setValue($merged);

        return $this;
    }

    public function addToMethod(string $method, string $code): static
    {
        if (! $this->class->hasMethod($method)) {
            throw new RuntimeException("Method {$method}() does not exist on class {$this->class->getName()}.");
        }

        $m = $this->class->getMethod($method);
        $m->addBody($code);

        return $this;
    }

    public function removeMethod(string $name): static
    {
        $this->class->removeMethod($name);

        return $this;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    /**
     * @param  Method|Property|Constant  $member
     */
    private function applyVisibility(object $member, string $visibility): void
    {
        match ($visibility) {
            'public' => $member->setPublic(),
            'protected' => $member->setProtected(),
            'private' => $member->setPrivate(),
            default => throw new RuntimeException("Invalid visibility: {$visibility}"),
        };
    }
}
