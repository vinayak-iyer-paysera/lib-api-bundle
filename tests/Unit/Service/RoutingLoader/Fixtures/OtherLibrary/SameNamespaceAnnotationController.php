<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibrary;

use Symfony\Component\Routing\Attribute\Route;

class SameNamespaceAnnotationController
{
    /**
     * @Query(parameterName={"filter"})
     */
    #[Route('/same-namespace-annotation', methods: ['GET'])]
    public function show()
    {
    }
}
