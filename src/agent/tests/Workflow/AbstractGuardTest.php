<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\AbstractGuard;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

final class AbstractGuardTest extends TestCase
{
    public function testSupportsEveryPlaceWhenNoPlacesGiven()
    {
        $guard = $this->createGuard();

        $this->assertTrue($guard->supports('draft'));
        $this->assertTrue($guard->supports('review'));
    }

    public function testSupportsOnlyTheGivenPlaces()
    {
        $guard = $this->createGuard(['review', 'publish']);

        $this->assertTrue($guard->supports('review'));
        $this->assertTrue($guard->supports('publish'));
        $this->assertFalse($guard->supports('draft'));
    }

    /**
     * @param list<non-empty-string> $places
     */
    private function createGuard(array $places = []): AbstractGuard
    {
        return new class($places) extends AbstractGuard {
            public function allows(WorkflowStateInterface $state, string $place): bool
            {
                return true;
            }
        };
    }
}
