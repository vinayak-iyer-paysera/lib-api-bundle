<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions as Permissions;
use Symfony\Component\Routing\Attribute\Route;

class ClassAliasDocblockController
{
    /**
     * @Permissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/class-alias', methods: ['GET'])]
    public function show()
    {
    }
}
