<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Tests\Fixture\Tool;

use Symfony\AI\AIBundle\Security\Attribute\IsGrantedTool;
use Symfony\Component\ExpressionLanguage\Expression;

final class ToolWithIsGrantedOnMethod
{
    #[IsGrantedTool('ROLE_USER', message: 'No access to simple tool.')]
    public function simple(): bool
    {
        return true;
    }

    #[IsGrantedTool('test:permission', 'itemId', message: 'No access to argumentAsSubject tool.')]
    public function argumentAsSubject(int $itemId): int
    {
        return $itemId;
    }

    #[IsGrantedTool('test:permission', new Expression('args["itemId"]'), message: 'No access to expressionAsSubject tool.')]
    public function expressionAsSubject(int $itemId): int
    {
        return $itemId;
    }
}
