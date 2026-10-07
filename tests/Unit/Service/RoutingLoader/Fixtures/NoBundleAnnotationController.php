<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Symfony\Component\Routing\Attribute\Route;

class NoBundleAnnotationController
{
    #[Route('/no-bundle-annotation', methods: ['GET'])]
    public function show()
    {
    }
}
