<?php

declare(strict_types=1);

namespace Phpydantic\Tests;

use Phpydantic\BaseModel;
use PHPUnit\Framework\TestCase;


//
// Test model definitions
//
class Address extends BaseModel
{
    public string $street;
    public string $city;
    public string $state;
    public string $zip;
    public ?string $phone = null;
}

class User extends BaseModel
{
    public string $id;
    public string $name;
    public Address $address;
    public ?string $nickname = null;
}

class Tag extends BaseModel
{
    /** @Description A human-readable tag label */
    public string $label;
}

class Product extends BaseModel
{
    public string $id;

    public int $quantity;
    public float $price;
    public bool $inStock;
    public array $data;

    /**
     * @var Tag[]
     * @Description All tags associated with this product
     */
    public array $tags;
}


/**
 * @covers \Phpydantic\BaseModel
 */
class BaseModelTest extends TestCase
{
    public function testPrimitiveSchema()
    {
        $schema = Address::schema();

        $this->assertSame('Address', $schema['name']);
        $this->assertSame('object', $schema['type']);

        $this->assertArrayHasKey('street', $schema['properties']);
        $this->assertArrayHasKey('city', $schema['properties']);
        $this->assertArrayHasKey('state', $schema['properties']);
        $this->assertArrayHasKey('zip', $schema['properties']);
        $this->assertArrayHasKey('phone', $schema['properties']);

        $this->assertEquals(['string'], (array) $schema['properties']['street']['type']);
        $this->assertEquals(['string'], (array) $schema['properties']['city']['type']);
        $this->assertEquals(['string'], (array) $schema['properties']['state']['type']);
        $this->assertEquals(['string'], (array) $schema['properties']['zip']['type']);
        $this->assertEquals(['string', 'null'], (array) $schema['properties']['phone']['type']);

        $this->assertArrayHasKey('required', $schema);
        $this->assertEquals(['street', 'city', 'state', 'zip', 'phone'], $schema['required']);
    }

    public function testNestedSchema()
    {
        $schema = User::schema();

        $this->assertArrayHasKey('address', $schema['properties']);

        $addr = $schema['properties']['address'];

        $addrSchema = Address::schema();

        $this->assertSame('Address', $addr['name']);
        $this->assertSame('object', $addr['type']);
        $this->assertSame($addrSchema, $addr);
    }

    public function testArrayOfModelsSchema()
    {
        $schema = Product::schema();

        $this->assertArrayHasKey('tags', $schema['properties']);
        $tags = $schema['properties']['tags'];

        $this->assertSame('array', $tags['type']);
        $this->assertArrayHasKey('items', $tags);

        // items should inline Tag
        $this->assertSame('Tag', $tags['items']['name']);
        $this->assertSame('object', $tags['items']['type']);
        $this->assertEquals(['label'], $tags['items']['required']);
    }

    public function testDescriptionParsing()
    {
        $schemaUser = User::schema();
        $this->assertArrayNotHasKey('description', $schemaUser['properties']['id']);

        $schemaTag = Tag::schema();
        $this->assertSame(
            'A human-readable tag label',
            $schemaTag['properties']['label']['description']
        );

        $schemaProduct = Product::schema();
        $this->assertSame(
            'All tags associated with this product',
            $schemaProduct['properties']['tags']['description']
        );
    }

    public function testPrimitiveValues()
    {
        $schema = Product::schema();
        $this->assertSame(
            'integer',
            $schema['properties']['quantity']['type']
        );
        $this->assertSame(
            'number',
            $schema['properties']['price']['type']
        );
        $this->assertSame(
            'boolean',
            $schema['properties']['inStock']['type']
        );
        $this->assertSame(
            'array',
            $schema['properties']['data']['type']
        );
    }

    public function testUntypedArraySchema()
    {
        $schema = Product::schema();
        $this->assertSame(
            'array',
            $schema['properties']['data']['type']
        );
    }

    public function testJsonSchema()
    {
        $schema = Product::schema();
        $jsonSchema = Product::jsonSchema();
        $this->assertSame(
            json_encode($schema, JSON_PRETTY_PRINT),
            $jsonSchema
        );
    }

    public function testOpenAiSchema()
    {
        $schema = Product::schema();
        $openAiSchema = Product::openAiSchema();

        unset($schema['name']);

        $this->assertArrayHasKey('name', $openAiSchema);
        $this->assertSame('Product', $openAiSchema['name']);
        $this->assertArrayHasKey('schema', $openAiSchema);
        $this->assertSame($schema, $openAiSchema['schema']);
        $this->assertTrue($openAiSchema['strict']);
    }

    public function testFromJsonWithInvalidJson()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');
        Product::fromJson('{invalid json}');
    }

    public function testFromJsonWithInvalidNestedModelType()
    {
        $json = json_encode([
            'id' => '123',
            'name' => 'John Doe',
            'address' => 42, // Should be an object, not a number
            'nickname' => null
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'address' must be an object, got integer");
        User::fromJson($json);
    }

    public function testFromJsonWithInvalidArrayItemType()
    {
        $json = json_encode([
            'id' => '123',
            'quantity' => 10,
            'price' => 100.0,
            'inStock' => true,
            'data' => [],
            'tags' => [
                42, // Should be an object, not a number
                ['label' => 'valid tag']
            ]
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Array item in 'tags' must be an object, got integer");
        Product::fromJson($json);
    }

    public function testFromJsonWithValidNestedModel()
    {
        $json = json_encode([
            'id' => 'u1',
            'name' => 'Alice',
            'address' => [
                'street' => '1 Infinite Loop',
                'city' => 'Cupertino',
                'state' => 'CA',
                'zip' => '95014',
                'phone' => null,
            ],
        ]);

        $user = User::fromJson($json);
        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(Address::class, $user->address);
        $this->assertSame('Cupertino', $user->address->city);
        $this->assertNull($user->address->phone);
    }

    public function testFromJsonWithValidArrayOfModels()
    {
        $json = json_encode([
            'id' => 'p1',
            'quantity' => 5,
            'price' => 19.99,
            'inStock' => true,
            'data' => [],
            'tags' => [
                ['label' => 'new'],
                ['label' => 'sale'],
            ],
        ]);

        $product = Product::fromJson($json);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertIsArray($product->tags);
        $this->assertCount(2, $product->tags);
        $this->assertContainsOnlyInstancesOf(Tag::class, $product->tags);
        $this->assertSame('new', $product->tags[0]->label);
    }

    public function testFromJsonWithNonArrayForArrayProperty()
    {
        $json = json_encode([
            'id' => 'p2',
            'quantity' => 1,
            'price' => 9.99,
            'inStock' => true,
            'data' => [],
            'tags' => 'should be array',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'tags' must be an array");
        Product::fromJson($json);
    }
}
