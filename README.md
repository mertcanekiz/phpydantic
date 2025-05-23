# Phpydantic

[![Packagist Version](https://img.shields.io/packagist/v/mertcanekiz/phpydantic.svg)](https://packagist.org/packages/mertcanekiz/phpydantic)
[![PHP Version](https://img.shields.io/packagist/php-v/mertcanekiz/phpydantic.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/mertcanekiz/phpydantic.svg)](LICENSE)

A lightweight PHP library for generating JSON Schemas from PHP models, inspired by Python's Pydantic. Ideal for integrating with OpenAI structured outputs, function calling, or any other JSON-schema–driven tooling.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [1. Defining Models](#1-defining-models)
  - [2. Generating JSON Schema](#2-generating-json-schema)
  - [3. OpenAI Structured Outputs](#3-openai-structured-outputs)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- **Automatic Type Inference**: Infers JSON Schema types from PHP property types (`int`, `float`, `string`, `bool`).
- **Nullable Support**: Handles nullable types (e.g., `?string`) as `type: ["string", "null"]`.
- **Nested Models & Arrays**: Supports nested `BaseModel` and arrays of models via `@var ModelName[]` annotations.
- **Property Descriptions**: Leverage `@Description` tags in docblocks to include descriptions in schemas.
- **Strict Mode**: Generate strict schemas for OpenAI function calling or JSON validation.

## Requirements

- PHP **8.0** or higher
- Composer

## Installation

Install via Composer:

```bash
composer require mertcanekiz/phpydantic
```

## Usage

### 1. Defining Models

Extend `Phpydantic\BaseModel` and declare public properties. Use docblocks for metadata:

```php
use Phpydantic\BaseModel;

class Step extends BaseModel
{
    /** @Description A detailed explanation for this step */
    public string $explanation;

    /** @Description The result or output of this step */
    public string $output;
}

class MathReasoning extends BaseModel
{
    /**
     * @var Step[]
     * @Description List of reasoning steps
     */
    public array $steps;

    /** @Description The final answer to the problem */
    public string $finalAnswer;
}
```

### 2. Generating JSON Schema

Use static methods to generate schema definitions:

```php
// Returns schema as PHP array
$schemaArray = MathReasoning::schema();

// Returns pretty-printed JSON string
$schemaJson = MathReasoning::jsonSchema();
```

**Example JSON Schema**

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "MathReasoning",
  "type": "object",
  "properties": {
    "steps": {
      "type": "array",
      "items": { "$ref": "#/definitions/Step" }
    },
    "finalAnswer": { "type": "string" }
  },
  "required": ["steps", "finalAnswer"],
  "additionalProperties": false
}
```

### 3. OpenAI Structured Outputs

Generate schemas compatible with OpenAI function calling or JSON mode:

```php
use OpenAI\OpenAI;

$client = OpenAI::client('YOUR_API_KEY');
$schema = MathReasoning::openAiSchema();

$response = $client->chat()->create([
    'model' => 'gpt-4o-2024-08-06',
    'messages' => [
        ['role' => 'system',  'content' => 'You are a helpful math tutor. Guide the user step by step.'],
        ['role' => 'user',    'content' => 'How can I solve 8x + 7 = -23?'],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => $schema,
    ],
]);

$data = json_decode($response['choices'][0]['message']['content'], true);
// Access results: $data['steps'], $data['finalAnswer']
```

## API Reference

| Method                    | Description                                                 |
| ------------------------- | ----------------------------------------------------------- |
| `::schema(): array`       | Returns schema as a PHP array.                              |
| `::jsonSchema(): string`  | Returns pretty-printed JSON schema.                         |
| `::openAiSchema(): array` | Wraps `schema()` for OpenAI function-calling compatibility. |

## Testing

Run PHPUnit tests:

```bash
composer test
```

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a new branch: `git checkout -b feature/YourFeature`
3. Commit your changes: `git commit -m 'Add new feature'`
4. Push to your branch: `git push origin feature/YourFeature`
5. Open a Pull Request

Ensure all tests pass and adhere to PSR-12 coding standards.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
