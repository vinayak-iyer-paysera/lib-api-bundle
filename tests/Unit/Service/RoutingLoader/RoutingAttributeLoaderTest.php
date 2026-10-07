<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\FullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryDocblockController;
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
            'namespace alias, on the class' => [
                NamespaceAliasDocblockController::class,
                self::buildRefusalMessage(NamespaceAliasDocblockController::class, '@RequiredPermissions'),
            ],
            'fully qualified name' => [
                FullyQualifiedDocblockController::class,
                self::buildRefusalMessage(FullyQualifiedDocblockController::class, '@RequiredPermissions'),
            ],
            'class alias' => [
                ClassAliasDocblockController::class,
                self::buildRefusalMessage(ClassAliasDocblockController::class, '@RequiredPermissions'),
            ],
            'annotation of another library with the same short name' => [
                OtherLibraryDocblockController::class,
                null,
            ],
            'no annotation of the bundle' => [
                NoBundleAnnotationController::class,
                null,
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
