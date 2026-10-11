<?php

// Generates src/Models (one read-only class per object the API returns) and src/Enums (one class of
// constants per string enum) from spec/v1.yaml. Never edit the output by hand: CI regenerates it and
// fails on any difference (check-drift.php).
// Usage: composer generate

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$spec = Yaml::parseFile("$root/spec/v1.yaml");
$schemas = $spec['components']['schemas'];

/** Objects the API returns inline (no named schema), by operation: the class each one becomes. */
$inlineResponses = [
    'createWebhookEndpoint' => 'WebhookEndpointWithSecret',
    'rotateWebhookSecret' => 'WebhookSecret',
];
/** Schemas that only describe request bodies, list envelopes or error bodies (those become exceptions); no model. */
$isSkipped = static fn(string $name): bool => (bool) preg_match('/(Request|Input)$/', $name) || in_array($name, ['ListMeta', 'Error'], true);

$header = "<?php\n\n// GENERATED from spec/v1.yaml by `composer generate`. Do not edit.\n\ndeclare(strict_types=1);\n\n";

$resolve = static function (array $schema) use ($schemas): array {
    while (isset($schema['$ref'])) {
        $schema = $schemas[basename($schema['$ref'])];
    }

    return $schema;
};
$isModel = static fn(string $name): bool => !$isSkipped($name)
    && ($schemas[$name]['type'] ?? null) === 'object' && isset($schemas[$name]['properties']);

/** The model class a property holds (or holds a list of), if any. */
$nestedClass = static function (array $schema) use (&$nestedClass, $isModel): ?string {
    if (isset($schema['$ref'])) {
        $name = basename($schema['$ref']);

        return $isModel($name) ? $name : null;
    }
    if (($schema['type'] ?? null) === 'array' && isset($schema['items'])) {
        return $nestedClass($schema['items']);
    }
    foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
        if (isset($schema[$key])) {
            $classes = array_values(array_filter(array_map($nestedClass, $schema[$key])));
            $refs = array_filter($schema[$key], static fn(array $s): bool => isset($s['$ref']));

            return count($classes) === 1 && count($refs) === 1 ? $classes[0] : null;
        }
    }

    return null;
};

/** The PHPDoc type of a schema, as the SDK hydrates it. */
$phpType = static function (array $schema) use (&$phpType, $isModel, $resolve): string {
    if (isset($schema['$ref'])) {
        $name = basename($schema['$ref']);

        return $isModel($name) ? $name : $phpType($resolve($schema));
    }
    foreach (['oneOf', 'anyOf'] as $key) {
        if (isset($schema[$key])) {
            return implode('|', array_unique(array_merge(...array_map(
                static fn(array $s): array => explode('|', $phpType($s)),
                $schema[$key],
            ))));
        }
    }
    if (isset($schema['allOf'])) {
        return count($schema['allOf']) === 1 ? $phpType($schema['allOf'][0]) : '\KetaPay\KetaPayObject';
    }
    $types = (array) ($schema['type'] ?? 'mixed');
    $out = [];
    foreach ($types as $type) {
        $out[] = match ($type) {
            'string' => 'string',
            'integer' => 'int',
            'number' => 'int|float',
            'boolean' => 'bool',
            'null' => 'null',
            'array' => 'list<' . (isset($schema['items']) ? $phpType($schema['items']) : 'mixed') . '>',
            'object' => '\KetaPay\KetaPayObject',
            default => 'mixed',
        };
    }

    return implode('|', array_unique($out));
};

$oneLine = static fn(?string $text): string => $text === null ? '' : ' ' . trim((string) preg_replace('/\s+/', ' ', $text));
$block = static fn(string $text): string => implode("\n * ", explode("\n", wordwrap(trim($text), 100)));
/** "See \KetaPay\Enums\X." for a field holding one of an enum's values. */
$enumHint = static function (array $schema) use ($schemas): string {
    $ref = $schema['$ref'] ?? (isset($schema['allOf']) && count($schema['allOf']) === 1 ? ($schema['allOf'][0]['$ref'] ?? null) : null);
    $name = $ref === null ? null : basename($ref);

    return $name !== null && isset($schemas[$name]['enum']) ? " See \\KetaPay\\Enums\\$name." : '';
};

/** @return array<string, array<string, mixed>> properties, with allOf parts merged */
$properties = static function (array $schema) use (&$properties, $resolve): array {
    if (isset($schema['allOf'])) {
        $merged = [];
        foreach ($schema['allOf'] as $part) {
            $merged = array_merge($merged, $properties($resolve($part)));
        }

        return $merged;
    }

    return $schema['properties'] ?? [];
};

/**
 * An object written inline in a property (not a named schema), directly or as a nullable oneOf, or as
 * the items of a list. It gets its own model, named after its parent: Event.data is EventData.
 *
 * @return array{schema: array<string, mixed>, list: bool, nullable: bool}|null
 */
$inlineObject = static function (array $prop) use (&$inlineObject): ?array {
    if (($prop['type'] ?? null) === 'object' && isset($prop['properties'])) {
        return ['schema' => $prop, 'list' => false, 'nullable' => false];
    }
    if (($prop['type'] ?? null) === 'array' && isset($prop['items'])) {
        $item = $inlineObject($prop['items']);

        return $item === null ? null : ['schema' => $item['schema'], 'list' => true, 'nullable' => false];
    }
    foreach (['oneOf', 'anyOf'] as $key) {
        if (isset($prop[$key])) {
            $objects = array_values(array_filter(array_map($inlineObject, $prop[$key])));
            $others = array_filter($prop[$key], static fn(array $s): bool => ($s['type'] ?? null) !== 'null' && $inlineObject($s) === null);

            return count($objects) === 1 && $others === [] ? ['schema' => $objects[0]['schema'], 'list' => false, 'nullable' => true] : null;
        }
    }

    return null;
};

$models = [];
foreach ($schemas as $name => $schema) {
    if ($isModel($name)) {
        $models[$name] = ['schema' => $schema, 'description' => $schema['description'] ?? null, 'named' => true];
    }
}
foreach ($spec['paths'] as $ops) {
    foreach ($ops as $op) {
        if (!is_array($op) || !isset($op['operationId'])) {
            continue;
        }
        $response = $op['responses']['200'] ?? $op['responses']['201'] ?? [];
        $schema = $response['content']['application/json']['schema'] ?? null;
        if ($schema === null || isset($schema['$ref'])) {
            continue;
        }
        $props = $properties($schema);
        if (isset($props['data']) && ($props['data']['type'] ?? null) === 'array') {
            continue; // a list: Page or Collection
        }
        $class = $inlineResponses[$op['operationId']] ?? null;
        if ($class === null) {
            fwrite(STDERR, "{$op['operationId']} returns an inline object. Name its class in \$inlineResponses in scripts/generate.php.\n");
            exit(1);
        }
        $models[$class] = ['schema' => $schema, 'description' => $response['description'] ?? null, 'named' => false];
    }
}
$out = ['Models' => [], 'Enums' => []];
$objectTypes = [];
$queue = $models;
while ($queue !== []) {
    $name = array_key_first($queue);
    ['schema' => $schema, 'description' => $description, 'named' => $named] = $queue[$name];
    unset($queue[$name]);
    $docs = [];
    $nested = [];
    foreach ($properties($schema) as $field => $prop) {
        $inline = $inlineObject($prop);
        $type = $phpType($prop);
        $class = $nestedClass($prop);
        if ($inline !== null) {
            $class = $name . str_replace('_', '', ucwords((string) $field, '_'));
            $queue[$class] = ['schema' => $inline['schema'], 'description' => $prop['description'] ?? null, 'named' => false];
            $type = $inline['list'] ? "list<$class>" : $class . ($inline['nullable'] ? '|null' : '');
        }
        $docs[] = " * @property-read $type \${$field}{$oneLine($prop['description'] ?? null)}{$enumHint($prop)}";
        if ($class !== null) {
            $nested[] = "        '$field' => $class::class,";
        }
        $const = $resolve($prop)['const'] ?? null;
        if ($field === 'object' && is_string($const) && $named) {
            $objectTypes[$const] = $name;
        }
    }
    $summary = $block(trim($oneLine($description)) ?: $name);
    $body = $header . "namespace KetaPay\\Models;\n\n/**\n * $summary\n *\n" . implode("\n", $docs) . "\n */\n"
        . "final class $name extends \\KetaPay\\KetaPayObject\n{\n";
    if ($nested !== []) {
        $body .= "    protected const NESTED = [\n" . implode("\n", $nested) . "\n    ];\n";
    }
    $out['Models']["$name.php"] = $body . "}\n";
}

ksort($objectTypes);
$map = implode("\n", array_map(static fn(string $type, string $class): string => "        '$type' => $class::class,", array_keys($objectTypes), $objectTypes));
$out['Models']['ObjectTypes.php'] = $header . "namespace KetaPay\\Models;\n\n/** The model class for each `object` value. Internal. */\nfinal class ObjectTypes\n{\n"
    . "    /** @var array<string, class-string<\\KetaPay\\KetaPayObject>> */\n    public const MAP = [\n$map\n    ];\n}\n";

foreach ($schemas as $name => $schema) {
    if (($schema['type'] ?? null) !== 'string' || !isset($schema['enum'])) {
        continue;
    }
    $consts = [];
    foreach ($schema['enum'] as $value) {
        $const = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value));
        $consts[] = "    public const $const = '$value';";
    }
    $summary = $block(trim($oneLine($schema['description'] ?? null)) ?: "Values of $name.");
    $out['Enums']["$name.php"] = $header . "namespace KetaPay\\Enums;\n\n/**\n * $summary\n *\n * New values can be added to the API at any time: treat unknown ones gracefully.\n */\n"
        . "final class $name\n{\n" . implode("\n", $consts) . "\n}\n";
}

foreach ($out as $dir => $files) {
    $path = "$root/src/$dir";
    if (!is_dir($path)) {
        mkdir($path, 0o755, true);
    }
    foreach (glob("$path/*.php") ?: [] as $old) {
        unlink($old);
    }
    foreach ($files as $file => $code) {
        file_put_contents("$path/$file", $code);
    }
    echo 'Wrote ' . count($files) . " files to src/$dir\n";
}
