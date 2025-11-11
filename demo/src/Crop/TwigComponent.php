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

use App\Crop\Image\CroppingTarget;
use App\Crop\Image\Ratio;
use App\Crop\Preset\PresetImage;
use App\Crop\Preset\PresetImageRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('crop')]
final class TwigComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $originalImage = null;

    #[LiveProp(writable: true)]
    public ?string $imageData = null;

    #[LiveProp(writable: true)]
    public ?string $presetId = null;

    #[LiveProp(writable: true)]
    public Ratio $ratio = Ratio::SQUARE;

    #[LiveProp(writable: true)]
    public int $width = 800;

    #[LiveProp]
    public ?string $croppedImage = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ImageCropper $imageCropper,
        private readonly PresetImageRegistry $presetRegistry,
    ) {
    }

    public function getForm(): FormView
    {
        return $this->formFactory
            ->create(CropForm::class, [
                'ratio' => $this->ratio,
                'width' => $this->width,
            ])
            ->createView();
    }

    /**
     * @return array<string, PresetImage>
     */
    public function getPresets(): array
    {
        return $this->presetRegistry->all();
    }

    public function getSelectedPreset(): ?PresetImage
    {
        return null !== $this->presetId ? $this->presetRegistry->get($this->presetId) : null;
    }

    public function hasImage(): bool
    {
        return null !== $this->imageData || null !== $this->presetId;
    }

    #[LiveAction]
    public function selectPreset(#[LiveArg] string $id): void
    {
        $this->presetRegistry->get($id);

        $this->presetId = $id;
        $this->imageData = null;
        $this->originalImage = null;
        $this->croppedImage = null;
        $this->dispatchBrowserEvent('crop:upload-cleared');
    }

    #[LiveAction]
    public function crop(): void
    {
        $imageDataUrl = null !== $this->presetId
            ? $this->presetRegistry->loadAsDataUrl($this->presetId)
            : $this->imageData;

        if (null === $imageDataUrl) {
            throw new \RuntimeException('No image data to crop.');
        }

        $this->croppedImage = $this->imageCropper->crop($imageDataUrl, new CroppingTarget($this->ratio, $this->width));
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->originalImage = null;
        $this->imageData = null;
        $this->presetId = null;
        $this->ratio = Ratio::SQUARE;
        $this->width = 800;
        $this->croppedImage = null;
        $this->dispatchBrowserEvent('crop:upload-cleared');
    }
}
