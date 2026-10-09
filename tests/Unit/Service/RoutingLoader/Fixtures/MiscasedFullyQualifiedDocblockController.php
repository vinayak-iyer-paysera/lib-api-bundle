<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Symfony\Component\Routing\Attribute\Route;

class MiscasedFullyQualifiedDocblockController
{
    /**
     * @\paysera\bundle\apibundle\annotation\RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/miscased-fully-qualified', methods: ['GET'])]
    public function show()
    {
    }
}
