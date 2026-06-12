<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Cookbook\Cookbook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CookbookController extends AbstractController
{
    #[Route('/cookbook/{slug}', name: 'cookbook_article', requirements: ['slug' => '[a-z0-9-]+'])]
    public function article(Cookbook $repository, string $slug): Response
    {
        return $this->render('cookbook/article.html.twig', [
            'page' => $repository->getPage($slug),
        ]);
    }
}
