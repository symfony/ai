<?php

// This file is managed by Mate - use `discover` or `skills:*` commands
// over manual editing. Only changes to `mode` or `enabled` are kept,
// every other key is overwritten by Mate.

return [
    'symfony/ai-mate' => [
        'enabled' => true,
        'skills' => [
            'php-environment-check' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-mate/skills/php-environment-check',
                'source_hash' => 'sha256:475400c87571d228335fb80f414c56a80110e182268df65f9f84bc9bfb2aa6f3',
                'hash' => 'sha256:56de92962de6233284c439de64b1cef66487c56c07e6c3357658139cf4d82527',
                'targets' => [
                    '.agents/skills/mate-php-environment-check',
                    '.claude/skills/mate-php-environment-check',
                ],
            ],
            'system-information' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-mate/skills/system-information',
                'source_hash' => 'sha256:7249544b603ecd416fae22cd92b465257619306f88a50dc8efd9653606e9e460',
                'hash' => 'sha256:fcbfe6ea831b35299f5ba646bf63dbd761e184e7ad196087a350a060f9915973',
                'targets' => [
                    '.agents/skills/mate-system-information',
                    '.claude/skills/mate-system-information',
                ],
            ],
        ],
    ],
    'symfony/ai-monolog-mate-extension' => [
        'enabled' => true,
        'skills' => [
            'symfony-log-investigation' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-monolog-mate-extension/skills/symfony-log-investigation',
                'source_hash' => 'sha256:42c6c989f8b7a3b655b57175cdf7cfdefc646d8aca51dd96dfbcbb654f5ef195',
                'hash' => 'sha256:3e58dbae491d13e0569851f070e0253b43485c20be086e22fadfcca6fa1534a9',
                'targets' => [
                    '.agents/skills/mate-symfony-log-investigation',
                    '.claude/skills/mate-symfony-log-investigation',
                ],
            ],
        ],
    ],
    'symfony/ai-symfony-mate-extension' => [
        'enabled' => true,
        'skills' => [
            'symfony-dotenv-diagnostics' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-dotenv-diagnostics',
                'source_hash' => 'sha256:527b1145e7386d15260509f88da26cb3158e17ae140f14c672ed299da1878ab0',
                'hash' => 'sha256:f4a3a9983496173dcdb8e27ed402a73e0f03d763060cbb668db1b993016d1f62',
                'targets' => [
                    '.agents/skills/mate-symfony-dotenv-diagnostics',
                    '.claude/skills/mate-symfony-dotenv-diagnostics',
                ],
            ],
            'symfony-profiler-debugging' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-profiler-debugging',
                'source_hash' => 'sha256:bf384469e0e92af2b40ede7367090e4a41ff48f75a11f2ac5e64a2e4335068be',
                'hash' => 'sha256:adddef46d3cf8613d2d664f5f214e91572b6ae859406bd4d1d0196c11af1f7cb',
                'targets' => [
                    '.agents/skills/mate-symfony-profiler-debugging',
                    '.claude/skills/mate-symfony-profiler-debugging',
                ],
            ],
            'symfony-request-triage' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-request-triage',
                'source_hash' => 'sha256:3b10b98407be5b6081423aeb8146b16eb81984264e50f2a5a38ac1706cc65581',
                'hash' => 'sha256:5d2c994451acdd9c07b18eba18833a15d7598b7f2cab3b7f8c5346ebc3928f4e',
                'targets' => [
                    '.agents/skills/mate-symfony-request-triage',
                    '.claude/skills/mate-symfony-request-triage',
                ],
            ],
            'symfony-service-inspection' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-service-inspection',
                'source_hash' => 'sha256:6fa685f2b72129b96754ca1aff0e533f1c04fb5ff3a9ceddafab9059142bc0c8',
                'hash' => 'sha256:d4f0247d8c0355d714a1b6c8a738b2200bc802cf3c1f402a73b6866f164f14c1',
                'targets' => [
                    '.agents/skills/mate-symfony-service-inspection',
                    '.claude/skills/mate-symfony-service-inspection',
                ],
            ],
        ],
    ],
];
