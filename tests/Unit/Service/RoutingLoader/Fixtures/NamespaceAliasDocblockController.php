<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation as Rest;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @Rest\RequiredPermissions(permissions={"ROLE_ADMIN"})
 */
class NamespaceAliasDocblockController
{
    #[Route('/namespace-alias', methods: ['GET'])]
    public function show()
    {
    }
}
