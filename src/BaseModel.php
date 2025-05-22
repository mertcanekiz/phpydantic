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
}
