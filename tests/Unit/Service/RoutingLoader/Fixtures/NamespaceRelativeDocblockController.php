<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle;

use Symfony\Component\Routing\Attribute\Route;

class NamespaceRelativeDocblockController
{
    /**
     * @Annotation\RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/namespace-relative', methods: ['GET'])]
    public function show()
    {
    }
}
