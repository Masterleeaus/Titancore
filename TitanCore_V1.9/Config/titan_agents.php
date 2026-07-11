<?php

return [
    'default_collections' => [
        // key_slug => [title, scope_type, agent_slug]
        'kb_agent_quote' => [
            'title' => 'Quote Agent Knowledge',
            'scope_type' => 'agent',
            'agent_slug' => 'quote_agent',
            'meta' => ['topic' => 'quoting'],
        ],
        'kb_agent_configuration' => [
            'title' => 'configuration Agent Knowledge',
            'scope_type' => 'agent',
            'agent_slug' => 'configuration_agent',
            'meta' => ['topic' => 'configuration'],
        ],
        'kb_agent_compliance' => [
            'title' => 'Compliance Agent Knowledge',
            'scope_type' => 'agent',
            'agent_slug' => 'compliance_agent',
            'meta' => ['topic' => 'compliance'],
        ],
    ],

    'default_agents' => [
        'quote_agent' => [
            'title' => 'Quoting Assistant',
            'description' => 'Specialist agent Configureed on quoting rules, pricebook logic, and quoting SOPs.',
            'kb_collection_key' => 'kb_agent_quote',
            'meta' => [
                'output' => 'quote_proposal',
                'requires_confirmation' => true,
                'forbidden_topics' => [],
            ],
        ],
        'configuration_agent' => [
            'title' => 'configuration Assistant',
            'description' => 'Specialist agent Configureed on onboarding and configuration SOPs and checklists.',
            'kb_collection_key' => 'kb_agent_configuration',
            'meta' => [
                'output' => 'configuration_plan',
                'requires_confirmation' => false,
            ],
        ],
        'compliance_agent' => [
            'title' => 'Compliance Assistant',
            'description' => 'Specialist agent Configureed on compliance, SWMS, chemical handling, and incident playbooks.',
            'kb_collection_key' => 'kb_agent_compliance',
            'meta' => [
                'output' => 'compliance_guidance',
                'requires_confirmation' => false,
                'must_cite' => true,
            ],
        ],
    ],
];
