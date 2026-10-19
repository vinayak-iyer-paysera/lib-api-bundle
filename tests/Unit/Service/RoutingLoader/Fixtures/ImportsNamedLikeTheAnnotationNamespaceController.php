<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Bundle\ApiBundle;
use Paysera\Bundle\Api;
use Paysera\Bundle\ApiBundle\AnnotationExtra\Helper as ExtraHelper;
use Paysera\Bundle\ApiBundle\Attribute\RequiredPermissions;
use Symfony\Component\Routing\Attribute\Route;
use Vendor\Paysera\Bundle\ApiBundle\Annotation\Helper as VendorHelper;

class ImportsNamedLikeTheAnnotationNamespaceController
{
    #[RequiredPermissions(permissions: ['ROLE_ADMIN'])]
    #[Route('/imports-named-like-the-annotation-namespace', methods: ['GET'])]
    public function show(): void
    {
    }
}
