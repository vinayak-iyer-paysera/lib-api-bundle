<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\Admin;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @RequiredPermissions(permissions={"ROLE_ADMIN"})
 */
class SameNameController
{
    #[Route('/admin/same-name', methods: ['GET'])]
    public function show(): void
    {
    }
}
