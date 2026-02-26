<?php

namespace Symfony\AI\Platform\Connector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultInterface
{
    /**
     * Returns an array representation of the raw result data.
     *
     * @return array<string, mixed>
     */
    public function getRawData(): array;

    /**
     * Returns the raw result object.
     *
     * @return object
     */
    public function getRawObject(): object;
}
