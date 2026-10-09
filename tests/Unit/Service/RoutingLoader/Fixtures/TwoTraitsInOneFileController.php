<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures;

class TwoTraitsInOneFileController
{
    use FirstOfTwoTraits;
    use SecondOfTwoTraits;
}
