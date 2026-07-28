<?php

// Managed by "mate discover" and the "mate skills:*" commands, which can change
// every setting in here — prefer them over editing by hand. If you do edit: "enabled"
// and "mode" are kept, every other key is rewritten on the next install.

return [
    'symfony/ai-mate' => [
        'enabled' => true,
        'skills' => [
            'system-information' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-mate/skills/system-information',
                'source_hash' => 'sha256:8c348ddb1f10a453325894749fb7c82f8606b7fa1cde1382727edf006216a127',
                'hash' => 'sha256:1a2d29552eb46a634e430ed7547a392e559bce64b3b50e8155c1c100070370c2',
                'targets' => [
                    '.agents/skills/mate-system-information',
                    '.claude/skills/mate-system-information',
                ],
            ],
        ],
    ],
    'symfony/ai-monolog-mate-extension' => ['enabled' => true],
    'symfony/ai-symfony-mate-extension' => ['enabled' => true],
];
