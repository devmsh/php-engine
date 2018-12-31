<?php
/**
 * This file is part of event-engine/php-json-schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema;

use EventEngine\JsonSchema\Exception\InvalidArgumentException;
use EventEngine\JsonSchema\Type\ObjectType;
use EventEngine\Schema\InputTypeSchema;
use EventEngine\Schema\MessageBox\CommandMap;
use EventEngine\Schema\MessageBox\EventMap;
use EventEngine\Schema\MessageBox\QueryMap;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\ResponseTypeSchema;
use EventEngine\Schema\Schema;
use EventEngine\Schema\TypeSchemaMap;
use EventEngine\Util\VariableType;
use JsonSchema\Validator;

final class JustinRainbowJsonSchema implements Schema
{
    private static $jsonValidator;

    public function assertPayload(string $messageName, array $payload, PayloadSchema $payloadSchema, TypeSchemaMap $typeSchemaMap): void
    {
        $this->assertPayloadSchema($messageName, $payloadSchema);

        $payloadSchemaArr = array_merge($payloadSchema->toArray(), [JsonSchema::DEFINITIONS => $typeSchemaMap->toArray()]);

        $this->assert("$messageName payload", $payload, $payloadSchemaArr);
    }

    public function assertPayloadSchema(string $messageName, PayloadSchema $payloadSchema): void
    {
        if(!$payloadSchema instanceof ObjectType && !$payloadSchema instanceof JsonSchemaArray) {
            throw new InvalidArgumentException(
                "Payload schema for $messageName should be an " . ObjectType::class . ". Got " . VariableType::determine($payloadSchema)
            );
        }
    }

    public function buildPayloadSchemaFromArray(array $payloadSchema): PayloadSchema
    {
        return new JsonSchemaArray($payloadSchema);
    }

    public function emptyPayloadSchema(): PayloadSchema
    {
        return JsonSchema::object([]);
    }

    public function assertResponseTypeSchema(string $typeName, ResponseTypeSchema $responseTypeSchema): void
    {
        if(!$responseTypeSchema instanceof Type && !$responseTypeSchema instanceof JsonSchemaArray) {
            throw new InvalidArgumentException(
                "Response type schema $typeName should be a " . Type::class . ". Got " . VariableType::determine($responseTypeSchema)
            );
        }
    }

    public function buildResponseTypeSchemaFromArray(string $typeName, array $typeSchema): ResponseTypeSchema
    {
        return new JsonSchemaArray($typeSchema);
    }

    public function assertInputTypeSchema(string $typeName, InputTypeSchema $inputTypeSchema): void
    {
        if(!$inputTypeSchema instanceof Type && !$inputTypeSchema instanceof JsonSchemaArray) {
            throw new InvalidArgumentException(
                "Input type schema $typeName should be a " . Type::class . ". Got " . VariableType::determine($inputTypeSchema)
            );
        }
    }

    public function buildInputTypeSchemaFromArray(string $typeName, array $typeSchema): InputTypeSchema
    {
        return new JsonSchemaArray($typeSchema);
    }

    public function buildMessageBoxSchema(CommandMap $commandMap, EventMap $eventMap, QueryMap $queryMap, TypeSchemaMap $typeSchemaMap): array
    {
        $commandSchemas = [];

        foreach ($commandMap->commands() as $command) {
            $commandSchemas[$command->name()] = $command->payloadSchema()->toArray();
        }

        $eventSchemas = [];

        foreach ($eventMap->events() as $event) {
            $eventSchemas[$event->name()] = $event->payloadSchema()->toArray();
        }

        $querySchemas = [];
        foreach ($queryMap->queries() as $query) {
            $querySchemas[$query->name()] = array_merge($query->payloadSchema()->toArray(), ['response' => $query->returnType()->toArray()]);
        }

        return [
            'title' => 'Event Engine MessageBox',
            'description' => 'A mechanism for handling Event Engine messages',
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            'type' => 'object',
            'properties' => [
                'commands' => $commandSchemas,
                'events' => $eventSchemas,
                'queries' => $querySchemas,
            ],
            'definitions' => $typeSchemaMap->toArray(),
        ];
    }

    public function assert(string $objectName, array $data, array $jsonSchema)
    {
        if ($data === [] && JsonSchema::isObjectType($jsonSchema)) {
            $data = new \stdClass();
        }

        $enforcedObjectData = \json_decode(\json_encode($data));
        $jsonSchema = \json_decode(\json_encode($jsonSchema));

        $this->jsonValidator()->validate($enforcedObjectData, $jsonSchema);

        if (! $this->jsonValidator()->isValid()) {
            $errors = $this->jsonValidator()->getErrors();

            $this->jsonValidator()->reset();

            foreach ($errors as $i => $error) {
                $errors[$i] = \sprintf("[%s] %s\n", $error['property'], $error['message']);
            }

            throw new InvalidArgumentException(
                "Validation of $objectName failed: " . \implode("\n", $errors)
            );
        }

        $this->jsonValidator()->reset();
    }

    private function jsonValidator(): Validator
    {
        if (null === self::$jsonValidator) {
            self::$jsonValidator = new Validator();
        }

        return self::$jsonValidator;
    }
}
