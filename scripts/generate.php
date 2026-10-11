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

// Request parameter shapes (src/Types/Params.php): PHPStan array shapes for every request body and
// list filter, so `$ketapay->checkoutSessions->create([...])` is checked and autocompleted. The SDK
// still sends arrays as given; KetaPay validates them.
$aliases = [];
$requestNames = array_values(array_filter(array_keys($schemas), static fn(string $n): bool => (bool) preg_match('/(Request|Input)$/', $n)));
$key = static fn(string $k): string => preg_match('/^[a-z_][a-z0-9_]*$/i', $k) ? $k : "'$k'";
$shape = static fn(array $schema): string => ''; // replaced below; declared first so $paramType can call it
/** The PHPDoc type of a schema as a request parameter. */
$paramType = static function (array $schema) use (&$paramType, &$shape, $schemas, $requestNames): string {
    if (isset($schema['$ref'])) {
        $name = basename($schema['$ref']);
        if (in_array($name, $requestNames, true)) {
            return $name;
        }

        return $paramType($schemas[$name]);
    }
    if (isset($schema['enum'])) {
        return implode('|', array_map(static fn(mixed $v): string => is_string($v) ? "'$v'" : var_export($v, true), $schema['enum']));
    }
    if (isset($schema['const']) && is_string($schema['const'])) {
        return "'{$schema['const']}'";
    }
    foreach (['oneOf', 'anyOf'] as $k) {
        if (isset($schema[$k]) && !isset($schema['properties'])) {
            return implode('|', array_unique(array_map($paramType, $schema[$k])));
        }
    }
    if (isset($schema['allOf']) && count($schema['allOf']) === 1) {
        return $paramType($schema['allOf'][0]);
    }
    if (isset($schema['properties'])) {
        return $shape($schema);
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
            'array' => 'list<' . (isset($schema['items']) ? $paramType($schema['items']) : 'mixed') . '>',
            'object' => isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])
                ? 'array<string, ' . $paramType($schema['additionalProperties']) . '>'
                : 'array<string, mixed>',
            default => 'mixed',
        };
    }

    return implode('|', array_unique($out));
};
/**
 * An object as an array shape. "One of these fields" rules (anyOf/oneOf branches that each require a
 * field) become a union of shapes: with oneOf, a branch leaves out the fields the others require.
 */
$shape = static function (array $schema) use (&$paramType, $key): string {
    $props = array_filter($schema['properties'] ?? [], static fn(mixed $p): bool => is_array($p) && empty($p['readOnly']));
    $required = $schema['required'] ?? [];
    $branches = $schema['anyOf'] ?? $schema['oneOf'] ?? null;
    $build = static function (array $required, array $exclude = []) use ($props, $paramType, $key): string {
        $fields = [];
        foreach ($props as $name => $prop) {
            if (in_array($name, $exclude, true)) {
                continue;
            }
            $fields[] = $key((string) $name) . (in_array($name, $required, true) ? '' : '?') . ': ' . $paramType($prop);
        }

        return 'array{' . implode(', ', $fields) . '}';
    };
    if ($branches === null) {
        return $build($required);
    }
    $branchRequired = array_map(static fn(array $b): array => $b['required'] ?? [], $branches);
    $out = [];
    foreach ($branchRequired as $i => $own) {
        $exclude = [];
        if (isset($schema['oneOf'])) {
            foreach ($branchRequired as $j => $other) {
                if ($j !== $i) {
                    $exclude = array_merge($exclude, array_diff($other, $own));
                }
            }
        }
        $out[] = $build(array_merge($required, $own), $exclude);
    }

    return implode('|', array_unique($out));
};
foreach ($requestNames as $name) {
    $aliases[$name] = $shape($schemas[$name]);
}
foreach ($spec['paths'] as $ops) {
    foreach ($ops as $op) {
        if (!is_array($op) || !isset($op['operationId']) || !str_starts_with($op['operationId'], 'list')) {
            continue;
        }
        $fields = [];
        foreach ($op['parameters'] ?? [] as $param) {
            if (isset($param['$ref'])) {
                $param = $spec['components']['parameters'][basename($param['$ref'])];
            }
            if (($param['in'] ?? null) === 'query') {
                $fields[] = $key($param['name']) . '?: ' . $paramType($param['schema'] ?? []);
            }
        }
        if ($fields !== []) {
            $aliases[ucfirst($op['operationId']) . 'Params'] = 'array{' . implode(', ', $fields) . '}';
        }
    }
}
ksort($aliases);
$lines = array_map(static fn(string $n, string $t): string => " * @phpstan-type $n $t", array_keys($aliases), $aliases);
$out['Types'] = ['Params.php' => $header . "namespace KetaPay\\Types;\n\n/**\n * Request parameter shapes, for static analysis and autocompletion (PHPStan, Psalm, PhpStorm). The SDK\n * sends arrays as given and KetaPay validates them, so these only help you catch mistakes earlier.\n *\n"
    . implode("\n", $lines) . "\n */\nfinal class Params\n{\n}\n"];

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
