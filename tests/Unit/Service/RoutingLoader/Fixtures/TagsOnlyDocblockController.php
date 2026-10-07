<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use RuntimeException;
use Symfony\Component\Routing\Attribute\Route;

class TagsOnlyDocblockController
{
    /**
     * @param string $id
     *
     * @return array
     *
     * @throws RuntimeException
     */
    #[Route('/tags-only/{id}', methods: ['GET'])]
    public function show(string $id): array
    {
        return [];
    }
}
