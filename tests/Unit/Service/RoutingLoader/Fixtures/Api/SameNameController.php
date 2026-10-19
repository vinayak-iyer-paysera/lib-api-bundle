<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\Api;

use Paysera\Bundle\ApiBundle\Attribute\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

class SameNameController
{
    #[RequiredPermissions(permissions: ['ROLE_USER'])]
    #[Route('/api/same-name', methods: ['GET'])]
    public function show(): void
    {
    }
}
