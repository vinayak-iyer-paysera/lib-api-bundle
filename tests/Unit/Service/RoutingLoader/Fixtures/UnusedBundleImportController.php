<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Paysera\Bundle\ApiBundle\Annotation\Body;
use Symfony\Component\Routing\Attribute\Route;

class UnusedBundleImportController
{
    #[Route('/unused-bundle-import', methods: ['GET'])]
    public function show()
    {
    }
}
