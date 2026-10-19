<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Attribute\RequiredPermissions;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\Audited as Body;
use Symfony\Component\Routing\Attribute\Route;

class OtherLibraryBodyController
{
    /**
     * @Body
     */
    #[RequiredPermissions(permissions: ['ROLE_ADMIN'])]
    #[Route('/other-library-body', methods: ['GET'])]
    public function show(): void
    {
    }
}
