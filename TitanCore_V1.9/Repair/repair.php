<?php

return [
    'missing_provider' => function ($module) {
        return ['status' => 'report_only', 'message' => 'Provider repair requires module-specific generation.'];
    },
    'invalid_filament_signature' => function ($module) {
        return ['status' => 'report_only', 'message' => 'Run the Filament signature normalizer before boot.'];
    },
    'missing_manifest' => function ($module) {
        return ['status' => 'report_only', 'message' => 'Manifest can be regenerated from module metadata.'];
    },
];
