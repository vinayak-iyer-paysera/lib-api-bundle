<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Attribute\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;

class OverriddenTraitMethodController
{
    use AnnotationImportingTrait;

    #[RequiredPermissions(permissions: ['ROLE_ADMIN'])]
    #[Route('/overridden-trait-method', methods: ['GET'])]
    public function show(): void
    {
    }
}
