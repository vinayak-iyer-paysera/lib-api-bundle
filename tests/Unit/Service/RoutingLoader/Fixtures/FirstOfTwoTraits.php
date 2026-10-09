<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Symfony\Component\Routing\Attribute\Route;

trait FirstOfTwoTraits
{
    public function other()
    {
    }
}

use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;

trait SecondOfTwoTraits
{
    /**
     * @RequiredPermissions(permissions={"ROLE_ADMIN"})
     */
    #[Route('/second-of-two-traits', methods: ['GET'])]
    public function show()
    {
    }
}
