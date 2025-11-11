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

use App\Crop\Image\Ratio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\UX\Dropzone\Form\DropzoneType;

final class CropForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originalImage', DropzoneType::class, [
                'label' => 'Image to crop',
                'required' => false,
                'attr' => [
                    'data-controller' => 'dropzone',
                    'placeholder' => 'Drag and drop an image or click to browse',
                ],
            ])
            ->add('ratio', EnumType::class, [
                'class' => Ratio::class,
                'choice_label' => 'label',
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('width', ChoiceType::class, [
                'choices' => [
                    'Mini (100px)' => 100,
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
