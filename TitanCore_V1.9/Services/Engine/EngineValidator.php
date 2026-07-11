<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Events\EngineValidated;
use Modules\TitanCore\Support\AssetManifestValidator;

class EngineValidator
{
    public function __construct(
        private readonly AssetManifestValidator $validator,
    ) {}

    public function validateManifest(array $manifest): array
    {
        $result = $this->validator->validateData($manifest, 'engine', 'engine.json');

        return [
            'valid'    => $result->isValid(),
            'errors'   => $result->errors(),
            'warnings' => $result->warnings(),
        ];
    }

    public function validateEngine(array $engine): array
    {
        $errors = [];

        foreach ([
            'id',
            'name',
            'version',
            'sdk_version',
            'type',
            'description',
            'author',
            'class',
            'lifecycle',
            'dependencies',
            'permissions',
            'capabilities',
            'providers',
            'widgets',
            'resources',
            'settings',
            'health_checks',
            'upgrade_handlers',
            'install_handlers',
        ] as $field) {
            if (! isset($engine[$field]) || $engine[$field] === '') {
                $errors[] = sprintf('Missing required field "%s".', $field);
            }
        }

        $valid = $errors === [];

        if (function_exists('event') && isset($engine['id']) && is_string($engine['id'])) {
            event(new EngineValidated($engine['id'], $valid, $errors));
        }

        return [
            'valid'  => $valid,
            'errors' => $errors,
        ];
    }
}
