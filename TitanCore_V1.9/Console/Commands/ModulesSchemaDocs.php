<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\TitanCore\Support\ManifestSchemaValidator;

/**
 * Generates Markdown documentation from the Titan JSON Schema files.
 *
 * Usage:
 *   php artisan modules:schema-docs
 *   php artisan modules:schema-docs --output=docs/my-schemas
 *
 * Output is one .md file per schema, written to the configured output directory.
 */
class ModulesSchemaDocs extends Command
{
    protected $signature = 'modules:schema-docs
                            {--output= : Output directory (overrides config titan-modules.schema_docs_output_path)}
                            {--force : Overwrite existing files without prompting}';

    protected $description = 'Generate Markdown documentation from Titan JSON Schema files.';

    public function handle(): int
    {
        $schemasPath = resource_path('schemas/titan');

        if (! is_dir($schemasPath)) {
            $this->components->error("Schema directory not found: {$schemasPath}");

            return self::FAILURE;
        }

        $outputDir = $this->option('output')
            ?? base_path(config('titan-modules.schema_docs_output_path', 'docs/schemas'));

        File::ensureDirectoryExists($outputDir);

        $schemaFiles = glob($schemasPath . '/*.schema.json') ?: [];

        if (empty($schemaFiles)) {
            $this->components->warn('No schema files found in ' . $schemasPath);

            return self::SUCCESS;
        }

        $this->components->info('Generating Markdown docs from JSON Schemas…');
        $this->newLine();

        $generated = 0;

        foreach ($schemaFiles as $schemaFile) {
            $schema = json_decode(file_get_contents($schemaFile), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->components->warn(sprintf('Skipping %s — invalid JSON.', basename($schemaFile)));

                continue;
            }

            $outputFile = $outputDir . '/' . $this->schemaFileToDocName($schemaFile);

            if (File::exists($outputFile) && ! $this->option('force')) {
                if (! $this->confirm(sprintf('File %s already exists. Overwrite?', $outputFile), false)) {
                    $this->line(sprintf('  <fg=yellow>skipped</> %s', basename($outputFile)));

                    continue;
                }
            }

            $markdown = $this->renderSchemaMarkdown($schema, $schemaFile);

            File::put($outputFile, $markdown);

            $this->line(sprintf('  <fg=green>✓</> %s', $outputFile));

            $generated++;
        }

        $this->newLine();
        $this->components->info(sprintf('Generated %d Markdown file(s) in %s', $generated, $outputDir));

        return self::SUCCESS;
    }

    /**
     * Convert a schema file path to an output Markdown file name.
     * e.g. "module.json.schema.json" → "module-json.md"
     */
    private function schemaFileToDocName(string $schemaFile): string
    {
        $base = basename($schemaFile, '.schema.json');

        return str_replace('.', '-', $base) . '.md';
    }

    /**
     * Render a JSON Schema array as Markdown documentation.
     *
     * @param  array<string,mixed>  $schema
     */
    private function renderSchemaMarkdown(array $schema, string $schemaFile): string
    {
        $title       = $schema['title'] ?? basename($schemaFile);
        $description = $schema['description'] ?? '';
        $id          = $schema['$id'] ?? '';
        $version     = ManifestSchemaValidator::CURRENT_SCHEMA_VERSION;

        $lines = [];
        $lines[] = "# {$title}";
        $lines[] = '';

        if ($description) {
            $lines[] = $description;
            $lines[] = '';
        }

        if ($id) {
            $lines[] = '| Attribute | Value |';
            $lines[] = '|-----------|-------|';
            $lines[] = "| Schema ID | `{$id}` |";
            $lines[] = "| Schema Version | `{$version}` |";
            $lines[] = "| Source File | `resources/schemas/titan/" . basename($schemaFile) . "` |";
            $lines[] = '';
        }

        // Required fields
        $required = $schema['required'] ?? [];

        if (! empty($required)) {
            $lines[] = '## Required Fields';
            $lines[] = '';
            foreach ($required as $req) {
                $lines[] = "- `{$req}`";
            }
            $lines[] = '';
        }

        // Properties table
        $properties = $schema['properties'] ?? [];

        if (! empty($properties)) {
            $lines[] = '## Properties';
            $lines[] = '';
            $lines[] = '| Property | Type | Required | Description |';
            $lines[] = '|----------|------|----------|-------------|';

            foreach ($properties as $name => $propSchema) {
                $type     = $this->formatType($propSchema);
                $isReq    = in_array($name, $required, true) ? '✓' : '';
                $desc     = $propSchema['description'] ?? '';
                $enum     = isset($propSchema['enum']) ? 'One of: `' . implode('`, `', $propSchema['enum']) . '`' : '';

                $fullDesc = implode(' ', array_filter([$desc, $enum]));

                $lines[] = "| `{$name}` | {$type} | {$isReq} | {$fullDesc} |";
            }

            $lines[] = '';
        }

        // schema_version note
        $lines[] = '## Schema Versioning';
        $lines[] = '';
        $lines[] = 'Manifests may include an optional `schema_version` field declaring which schema version they conform to.';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = '{';
        $lines[] = '  "schema_version": "' . $version . '"';
        $lines[] = '}';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = sprintf('Supported versions: `%s`', implode('`, `', ManifestSchemaValidator::SUPPORTED_SCHEMA_VERSIONS));
        $lines[] = '';
        $lines[] = '> Manifests declaring an unknown `schema_version` will be **rejected** by the validator.';
        $lines[] = '';

        // Example
        $example = $this->buildExample($schema);
        if (! empty($example)) {
            $lines[] = '## Minimal Example';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $lines[] = '```';
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = sprintf('*Generated by `php artisan modules:schema-docs` — do not edit manually.*');

        return implode("\n", $lines) . "\n";
    }

    /**
     * Format the type of a JSON Schema property for display.
     *
     * @param  array<string,mixed>  $propSchema
     */
    private function formatType(array $propSchema): string
    {
        $type = $propSchema['type'] ?? null;

        if (is_array($type)) {
            return implode('\|', $type);
        }

        if ($type === 'array') {
            $itemType = $propSchema['items']['type'] ?? 'mixed';

            return "`array<{$itemType}>`";
        }

        return $type ?? 'mixed';
    }

    /**
     * Build a minimal example from the schema's required fields.
     *
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     */
    private function buildExample(array $schema): array
    {
        $required   = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];
        $example    = [];

        foreach ($required as $field) {
            $propSchema = $properties[$field] ?? [];
            $example[$field] = $this->exampleValue($propSchema);
        }

        $example['schema_version'] = ManifestSchemaValidator::CURRENT_SCHEMA_VERSION;

        return $example;
    }

    /**
     * Generate a placeholder example value for a property schema.
     *
     * @param  array<string,mixed>  $propSchema
     */
    private function exampleValue(array $propSchema): mixed
    {
        $type = $propSchema['type'] ?? 'string';
        $enum = $propSchema['enum'] ?? null;

        if ($enum !== null) {
            return $enum[0];
        }

        return match ($type) {
            'string'  => 'example',
            'integer' => 0,
            'number'  => 0.0,
            'boolean' => true,
            'array'   => [],
            'object'  => new \stdClass(),
            default   => null,
        };
    }
}
