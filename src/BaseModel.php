<?php

namespace Phpydantic;

use ReflectionClass;
use ReflectionProperty;

abstract class BaseModel
{
    public static function schema(): array
    {
        $rc = new ReflectionClass(static::class);
        $props = $rc->getProperties(ReflectionProperty::IS_PUBLIC);
        $schema = [
            'name' => $rc->getShortName(),
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
            'required' => [],
        ];

        foreach ($props as $prop) {
            $name = $prop->getName();
            $type = $prop->getType();
            $nullable = $type && $type->allowsNull();

            // 1) Handle nested BaseModels
            if ($type && ! $type->isBuiltin() && is_subclass_of($type->getName(), BaseModel::class)) {
                $entry = $type->getName()::schema();
            } elseif ($type && $type->getName() == 'array') {
                $entry = ['type' => 'array'];
                if ($doc = $prop->getDocComment()) {
                    if (preg_match('/@var\s+([\w\\\\]+)\[\]/', $doc, $m)) {
                        $itemClass = $m[1];
                        // Try to resolve the namespace
                        if (! str_contains($itemClass, '\\')) {
                            // If no namespace provided, try to resolve from current class namespace
                            $currentNamespace = $rc->getNamespaceName();
                            $itemClass = $currentNamespace . '\\' . $itemClass;
                        }
                        if (class_exists($itemClass) && is_subclass_of($itemClass, BaseModel::class)) {
                            $entry['items'] = $itemClass::schema();
                        }
                    }
                }
                // fallback to untyped array
                if (! isset($entry['items'])) {
                    $entry['items'] = ['type' => 'string'];
                }
            } else {
                // 2) Primitive fallback
                $phpType = $type ? $type->getName() : 'string';
                $jsType = match ($phpType) {
                    'int' => 'integer',
                    'float' => 'number',
                    'bool' => 'boolean',
                    default => 'string',
                };
                $entry = [
                    'type' => $nullable
                        ? [$jsType, 'null']
                        : $jsType,
                ];
            }

            // 3) Pull @Description from DocBlock
            if ($doc = $prop->getDocComment()) {
                if (preg_match('/@Description\s+(.+?)(?:\r?\n|\*\/)/', $doc, $m)) {
                    $entry['description'] = trim($m[1], " \t\n\r\0\x0B\"");
                }
            }

            $schema['properties'][$name] = $entry;
            $schema['required'][] = $name;
        }

        return $schema;
    }

    public static function openAiSchema(): array
    {
        $schema = static::schema();
        $name = $schema['name'];
        unset($schema['name']);

        return [
            'name' => $name,
            'schema' => $schema,
            'strict' => true,
        ];
    }

    public static function jsonSchema(): string
    {
        return json_encode(static::schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $rc = new ReflectionClass(static::class);
        // Skip the constructor entirely
        $instance = $rc->newInstanceWithoutConstructor();

        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (! array_key_exists($name, $data)) {
                continue;
            }
            $value = $data[$name];
            $type  = $prop->getType();

            // Allow nulls
            if ($value === null) {
                $instance->$name = null;
                continue;
            }

            if ($type && ! $type->isBuiltin() && is_subclass_of($type->getName(), BaseModel::class)) {
                // Nested BaseModel
                $className = $type->getName();
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Property '$name' must be an object, got " . gettype($value));
                }
                // Re-encode so that fromJson can accept arrays as well
                $instance->$name = $className::fromJson(json_encode($value));
            } elseif ($type && $type->getName() === 'array') {
                // Array of BaseModels
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Property '$name' must be an array, got " . gettype($value));
                }
                $items = [];
                $itemClass = null;
                if ($doc = $prop->getDocComment()) {
                    if (preg_match('/@var\s+([\w\\\\]+)\[\]/', $doc, $m)) {
                        $ic = $m[1];
                        if (! str_contains($ic, '\\')) {
                            $ic = $rc->getNamespaceName() . '\\' . $ic;
                        }
                        if (class_exists($ic) && is_subclass_of($ic, BaseModel::class)) {
                            $itemClass = $ic;
                        }
                    }
                }
                if ($itemClass) {
                    foreach ($value as $item) {
                        if (!is_array($item)) {
                            throw new \InvalidArgumentException("Array item in '$name' must be an object, got " . gettype($item));
                        }
                        $items[] = $itemClass::fromJson(json_encode($item));
                    }
                    $instance->$name = $items;
                } else {
                    // just a normal PHP array
                    $instance->$name = $value;
                }
            } else {
                // Primitive
                $phpType = $type?->getName() ?? 'string';
                // cast to int/float/bool/string
                settype($value, $phpType);
                $instance->$name = $value;
            }
        }

        return $instance;
    }
}
