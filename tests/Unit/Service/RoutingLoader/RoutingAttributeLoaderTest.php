<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

class RoutingAttributeLoaderTest extends TestCase
{
    /**
     * @dataProvider loadWithoutAnnotationReaderDataProvider
     */
    public function testLoadWithoutAnnotationReader(string $controller, ?string $expectedExceptionMessage): void
    {
        if (!class_exists(AttributeRouteControllerLoader::class)) {
            $this->markTestSkipped('Symfony 6.3 and older load routes through RoutingAnnotationLoader');
        }

        if ($expectedExceptionMessage !== null) {
            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $routes = (new RoutingAttributeLoader())->load($controller);

        $this->assertSame(
            [$controller . '::show'],
            array_values(array_map(static function (Route $route): string {
                return $route->getDefault('_controller');
            }, $routes->all()))
        );
    }

    public static function loadWithoutAnnotationReaderDataProvider(): array
    {
        return [
            'short name' => [
                DocblockOptionsOnAttributeRouteController::class,
                self::buildRefusalMessage(DocblockOptionsOnAttributeRouteController::class, '@RequiredPermissions'),
            ],
        ];
    }

    private static function buildRefusalMessage(string $controller, string $annotations): string
    {
        return $controller . '::show() uses docblock annotations of paysera/lib-api-bundle (' . $annotations . '), '
            . 'which are not read because no annotation reader is available. '
            . 'Use the attributes of the same name from Paysera\Bundle\ApiBundle\Attribute instead.';
    }
}
