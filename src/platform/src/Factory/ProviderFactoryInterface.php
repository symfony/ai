<?php

namespace Symfony\AI\Platform\Factory;

interface ProviderFactoryInterface
{
    public function fromDsn(string $dsn): object;
}
