<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibrary\Query;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SomeCustomTag
 */
class BundleAnnotationNextToUnrelatedTagsController
{
    /**
     * @note kept for older clients
     * @Query(parameterName={"filter"})
     * @RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/bundle-annotation-next-to-unrelated-tags', methods: ['GET'])]
    public function show()
    {
    }
}
