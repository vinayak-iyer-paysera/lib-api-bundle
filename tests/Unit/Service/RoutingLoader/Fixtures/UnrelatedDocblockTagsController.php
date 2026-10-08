<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibrary\Query;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SomeCustomTag
 */
class UnrelatedDocblockTagsController
{
    /**
     * @note kept for older clients
     * @Query(parameterName={"filter"})
     */
    #[Route('/unrelated-tags', methods: ['GET'])]
    public function show()
    {
    }
}
