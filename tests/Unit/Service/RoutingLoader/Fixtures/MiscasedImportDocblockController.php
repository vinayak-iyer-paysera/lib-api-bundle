<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

class MiscasedImportDocblockController
{
    /**
     * @RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/miscased-import', methods: ['GET'])]
    public function show()
    {
    }
}
