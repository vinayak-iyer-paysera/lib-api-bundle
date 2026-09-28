<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

class RoutingAttributeLoaderTest extends TestCase
{
    public function testRefusesTheBundleDocblockAnnotationsWhereSymfonyReadsNone()
    {
        if (!class_exists(AttributeRouteControllerLoader::class)
            || property_exists(AttributeRouteControllerLoader::class, 'reader')
        ) {
            $this->markTestSkipped('Symfony 6.4 and older read docblock annotations through an annotation reader');
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            DocblockOptionsOnAttributeRouteController::class . '::show() uses docblock annotations of '
            . 'paysera/lib-api-bundle (@RequiredPermissions), which Symfony 7 does not read. Use the attributes of the '
            . 'same name from Paysera\Bundle\ApiBundle\Attribute instead.'
        );

        (new RoutingAttributeLoader())->load(DocblockOptionsOnAttributeRouteController::class);
    }
}
