<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

abstract class DocblockOptionsParentController
{
    /**
     * @RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/inherited', methods: ['GET'])]
    public function show()
    {
    }
}
