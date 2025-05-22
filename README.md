# Phpydantic

## Installation

```bash
composer require mertcanekiz/phpydantic
```

## Example

### OpenAI structured outputs

#### 1. Define your models:

```php
use Phpydantic\BaseModel;

class Step extends BaseModel
{
    public string $explanation;
    public string $output;
}

class MathReasoning extends BaseModel
{
    /**
     * @var Step[]
     */
    public array $steps;

    public string $finalAnswer;
}
```

#### 2. Use the schema in OpenAI client:

```php
$client = OpenAI::client(...);

$schema = MathReasoning::openAiSchema();

$client->chat()->create([
    'model' => 'gpt-4o-2024-08-06',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful math tutor. Guide the user through the solution step by step.'],
        ['role' => 'user', 'content' => 'how can I solve 8x + 7 = -23']
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => $schema,
    ]
]);
```

# Phpydantic

[![Packagist Version](https://img.shields.io/packagist/v/mertcanekiz/phpydantic.svg)](https://packagist.org/packages/mertcanekiz/phpydantic)
[![PHP Version](https://img.shields.io/packagist/php-v/mertcanekiz/phpydantic.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/mertcanekiz/phpydantic.svg)](LICENSE)

A PHP library for generating JSON schemas from PHP models, inspired by Python's pydantic. Seamlessly integrate with **OpenAI function calling** or any JSON-schema–driven tooling.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)

  - [Defining Models](#defining-models)
  - [Generating Standard JSON Schema](#generating-standard-json-schema)
  - [OpenAI Structured Outputs](#openai-structured-outputs)

- [API Reference](#api-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- **Automatic Type Inference**: Infers JSON Schema types from PHP property types (`int`, `float`, `string`, `bool`).
- **Nullable Support**: Handles nullable types (`?string`) as `type: ["string", "null"]`.
- **Nested Models & Arrays**: Nested `BaseModel` support and arrays of models via `@var ModelName[]` annotations.
- **Property Descriptions**: Add `@Description` tags in docblocks to include descriptions in schemas.
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

### Defining Models

Extend `Phpydantic\BaseModel` and declare your public properties:

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

### Generating Standard JSON Schema

Use the static `schema()` or `jsonSchema()` methods:

```php
$schemaArray = MathReasoning::schema();
// Generates a PHP array representation of the JSON Schema

$schemaJson = MathReasoning::jsonSchema();
// Pretty-printed JSON string of the schema
```

```json
// Example output of jsonSchema():
{
  "name": "MathReasoning",
  "type": "object",
  "properties": {
    "steps": { "type": "array", "items": { "$ref": "#/definitions/Step" } },
    "finalAnswer": { "type": "string" }
  },
  "required": ["steps", "finalAnswer"],
  "additionalProperties": false
}
```

### OpenAI Structured Outputs

Generate schemas compatible with OpenAI function calling or JSON mode:

```php
$client = OpenAI::client('YOUR_API_KEY');

$schema = MathReasoning::openAiSchema();
$response = $client->chat()->create([
    'model'           => 'gpt-4o-2024-08-06',
    'messages'        => [
        ['role' => 'system', 'content' => 'You are a helpful math tutor. Guide the user through the solution step by step.'],
        ['role' => 'user',   'content' => 'How can I solve 8x + 7 = -23?'],
    ],
    'response_format' => [
        'type'       => 'json_schema',
        'json_schema'=> $schema,
    ],
]);

$data = json_decode($response['choices'][0]['message']['content'], true);
// $data['steps'] will be an array of Step objects
```

## API Reference

| Method                    | Description                                                 |
| ------------------------- | ----------------------------------------------------------- |
| `::schema(): array`       | Returns the schema as a PHP array.                          |
| `::jsonSchema(): string`  | Returns the pretty-printed JSON schema string.              |
| `::openAiSchema(): array` | Wraps `schema()` for OpenAI function-calling compatibility. |

## Testing

Run the automated tests with PHPUnit:

```bash
composer test
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/YourFeature`)
3. Commit your changes (`git commit -m 'Add new feature'`)
4. Push to the branch (`git push origin feature/YourFeature`)
5. Open a Pull Request

Please ensure all tests pass and follow PSR-12 coding standards.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
