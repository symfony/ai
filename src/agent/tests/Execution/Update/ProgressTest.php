<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution\Update;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\Update\Progress;

final class ProgressTest extends TestCase
{
    /**
     * The constants are part of the public API contract now; changing a value would silently
     * change the wire stage every existing consumer matches on.
     */
    public function testTheStageConstantsMatchTheStagesThisPackageActuallyReports(): void
    {
        $this->assertSame('model_request', Progress::STAGE_MODEL_REQUEST);
        $this->assertSame('delta', Progress::STAGE_DELTA);
        $this->assertSame('tool_call', Progress::STAGE_TOOL_CALL);
        $this->assertSame('handoff', Progress::STAGE_HANDOFF);
    }

    public function testGetStageReturnsWhateverWasConstructedWith(): void
    {
        $progress = new Progress(Progress::STAGE_TOOL_CALL, 'Executing tool "search".', 'payload');

        $this->assertSame(Progress::STAGE_TOOL_CALL, $progress->getStage());
        $this->assertSame('Executing tool "search".', $progress->getMessage());
        $this->assertSame('payload', $progress->getPayload());
    }
}
