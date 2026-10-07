<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibrary\Query;
use Symfony\Component\Routing\Attribute\Route;

class OtherLibraryDocblockController
{
    /**
     * @Query(parameterName="filter")
     */
    #[Route('/other-library', methods: ['GET'])]
    public function show()
    {
    }
}
