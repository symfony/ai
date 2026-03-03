<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getBody(): string;

    public function getMetadata(): SkillMetadataInterface;

    public function loadScript(string $script): mixed;

    public function loadReference(string $reference): mixed;

    public function loadAsset(string $asset): mixed;
}
