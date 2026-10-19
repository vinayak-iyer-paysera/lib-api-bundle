<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;

trait AnnotationImportingTrait
{
    /**
     * @RequiredPermissions(permissions={"ROLE_USER"})
     */
    public function show(): void
    {
    }
}
