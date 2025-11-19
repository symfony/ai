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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\UX\Dropzone\Form\DropzoneType;

final class CropForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originalImage', DropzoneType::class, [
                'label' => 'Image to crop',
                'attr' => [
                    'data-controller' => 'dropzone',
                    'placeholder' => 'Drag and drop an image or click to browse',
                ],
            ])
            ->add('format', ChoiceType::class, [
                'choices' => [
                    'Square (1:1)' => '1:1',
                    'Landscape (16:9)' => '16:9',
                    'Portrait (9:16)' => '9:16',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('width', ChoiceType::class, [
                'choices' => [
                    'Small (400px)' => 400,
                    'Medium (800px)' => 800,
                    'Large (1200px)' => 1200,
                ],
                'expanded' => true,
                'multiple' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
