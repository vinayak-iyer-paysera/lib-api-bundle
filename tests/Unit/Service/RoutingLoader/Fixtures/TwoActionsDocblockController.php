<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @RequiredPermissions(permissions={"ROLE_USER"})
 */
class TwoActionsDocblockController
{
    #[Route('/two-actions/first', methods: ['GET'])]
    public function first(): void
    {
    }

    #[Route('/two-actions/second', methods: ['GET'])]
    public function second(): void
    {
    }
}
