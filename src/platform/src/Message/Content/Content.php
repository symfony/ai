<?php

namespace Symfony\AI\Platform\Message\Content;

use Symfony\AI\Platform\Metadata\MetadataAwareInterface;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;

/**
 * @internal
 */
abstract class Content implements ContentInterface, MetadataAwareInterface
{
    use MetadataAwareTrait;
}
