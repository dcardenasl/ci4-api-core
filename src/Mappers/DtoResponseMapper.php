<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Mappers;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

class DtoResponseMapper implements ResponseMapperInterface
{
    /**
     * @param class-string<DataTransferObjectInterface> $responseDtoClass
     */
    public function __construct(
        private readonly string $responseDtoClass
    ) {
    }

    public function map(object|array $source): DataTransferObjectInterface
    {
        if (!class_exists($this->responseDtoClass)) {
            throw new \RuntimeException(lang('Api.responseDtoNotDefined', [$this->responseDtoClass]));
        }

        $data = $this->extractData($source);

        // Priority 1: Manual fromArray() method if defined in the DTO
        if (method_exists($this->responseDtoClass, 'fromArray')) {
            $instance = ($this->responseDtoClass)::fromArray($data);
            if (!$instance instanceof DataTransferObjectInterface) {
                throw new \RuntimeException(lang('Api.responseDtoMustImplement', [$this->responseDtoClass]));
            }
            return $instance;
        }

        // Priority 2: Automatic mapping via Reflection (PHP 8.2+)
        return $this->autoMap($data);
    }

    /**
     * Automatically instantiate a DTO using Reflection to match array keys to constructor params.
     *
     * @param array<string, mixed> $data
     */
    private function autoMap(array $data): DataTransferObjectInterface
    {
        $reflection = new \ReflectionClass($this->responseDtoClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = new ($this->responseDtoClass)();
            if (!$instance instanceof DataTransferObjectInterface) {
                throw new \RuntimeException(lang('Api.responseDtoMustImplement', [$this->responseDtoClass]));
            }
            return $instance;
        }

        $params = $constructor->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            // Try camelCase first, then snake_case
            $key = array_key_exists($name, $data) ? $name : $this->toSnakeCase($name);

            if (array_key_exists($key, $data)) {
                $args[] = $this->castValue($data[$key], $param);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                // Fallback for missing required fields (prevent crash)
                $args[] = $this->getDefaultValueForType($param->getType());
            }
        }

        $instance = $reflection->newInstanceArgs($args);
        if (!$instance instanceof DataTransferObjectInterface) {
            throw new \RuntimeException(lang('Api.responseDtoMustImplement', [$this->responseDtoClass]));
        }
        return $instance;
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input) ?? $input);
    }

    private function castValue(mixed $value, \ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        // Handle common casts
        return match ($typeName) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => (bool) $value,
            'string' => (string) $value,
            'array'  => (array) $value,
            default  => $value,
        };
    }

    private function getDefaultValueForType(?\ReflectionType $type): mixed
    {
        if ($type === null || !($type instanceof \ReflectionNamedType)) {
            return null;
        }

        return match ($type->getName()) {
            'int'    => 0,
            'float'  => 0.0,
            'bool'   => false,
            'string' => '',
            'array'  => [],
            default  => null,
        };
    }

    /**
     * Normalize the input source into a plain associative array.
     *
     * @param object|array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function extractData(object|array $source): array
    {
        if (is_array($source)) {
            return $source;
        }

        if (method_exists($source, 'toArray')) {
            return $source->toArray();
        }

        return (array) $source;
    }
}
