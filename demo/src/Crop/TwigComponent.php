<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Crop;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('crop')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $originalImage = null;

    #[LiveProp(writable: true)]
    public ?string $imageData = null;

    #[LiveProp(writable: true)]
    public string $format = '1:1';

    #[LiveProp(writable: true)]
    public int $width = 800;

    #[LiveProp]
    public ?string $croppedImage = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ImageCropper $imageCropper,
    ) {
    }

    public function getForm(): FormView
    {
        return $this->formFactory
            ->create(CropForm::class, [
                'format' => $this->format,
                'width' => $this->width,
            ])
            ->createView();
    }

    #[LiveAction]
    public function crop(): void
    {
        if (null === $this->imageData) {
            throw new \RuntimeException('No image data to crop.');
        }

        $this->croppedImage = $this->imageCropper->crop($this->imageData, $this->format, $this->width);
    }
}
