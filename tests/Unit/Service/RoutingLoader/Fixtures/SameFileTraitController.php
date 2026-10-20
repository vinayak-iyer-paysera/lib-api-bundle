<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\SameFile {

    use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;

    trait AnnotationImportingHelperTrait
    {
        /**
         * @RequiredPermissions(permissions={"ROLE_USER"})
         */
        public function helper(): void
        {
        }
    }
}

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures {

    use Paysera\Bundle\ApiBundle\Attribute\RequiredPermissions;
    use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\SameFile\AnnotationImportingHelperTrait;
    use Symfony\Component\Routing\Attribute\Route;

    class SameFileTraitController
    {
        use AnnotationImportingHelperTrait;

        #[RequiredPermissions(permissions: ['ROLE_ADMIN'])]
        #[Route('/same-file-trait', methods: ['GET'])]
        public function show(): void
        {
        }
    }
}
