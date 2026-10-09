<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

use Symfony\Component\Routing\Attribute\Route;

class OwnActionUsingAnnotatedTraitController
{
    use AnnotatedHelperTrait;

    #[Route('/own-action-using-annotated-trait', methods: ['GET'])]
    public function show()
    {
    }
}
