<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

class BundleAnnotationOnUnroutedMethodController
{
    #[Route('/bundle-annotation-on-unrouted-method', methods: ['GET'])]
    public function show()
    {
    }

    /**
     * @RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    public function unrouted()
    {
    }
}
