<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @RequiredPermissions(permissions={"ROLE_ADMIN"})
 * @note kept for older clients
 */
class ClassDocblockWithUnknownTagController
{
    #[Route('/class-docblock-with-unknown-tag', methods: ['GET'])]
    public function show(): void
    {
    }
}
