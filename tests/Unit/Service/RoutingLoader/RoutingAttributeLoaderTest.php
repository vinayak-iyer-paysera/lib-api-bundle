<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Doctrine\Common\Annotations\AnnotationException;
use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\FullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TagsOnlyDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnimportedAnnotationDocblockController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

class RoutingAttributeLoaderTest extends TestCase
{
    /**
     * @dataProvider loadWithoutAnnotationReaderDataProvider
     */
    public function testLoadWithoutAnnotationReader(
        string $controller,
        ?string $expectedException,
        ?string $expectedExceptionMessage
    ): void {
        if (!class_exists(AttributeRouteControllerLoader::class)) {
            $this->markTestSkipped('Symfony 6.3 and older load routes through RoutingAnnotationLoader');
        }

        if ($expectedException !== null) {
            $this->expectException($expectedException);
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
                ConfigurationException::class,
                self::buildRefusalMessage(DocblockOptionsOnAttributeRouteController::class, '@RequiredPermissions'),
            ],
            'namespace alias, on the class' => [
                NamespaceAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(NamespaceAliasDocblockController::class, '@RequiredPermissions'),
            ],
            'fully qualified name' => [
                FullyQualifiedDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(FullyQualifiedDocblockController::class, '@RequiredPermissions'),
            ],
            'class alias' => [
                ClassAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ClassAliasDocblockController::class, '@RequiredPermissions'),
            ],
            'annotation of another library with the same short name' => [
                OtherLibraryDocblockController::class,
                null,
                null,
            ],
            'no annotation of the bundle' => [
                NoBundleAnnotationController::class,
                null,
                null,
            ],
            'un-imported annotation of the bundle' => [
                UnimportedAnnotationDocblockController::class,
                AnnotationException::class,
                '[Semantical Error] The annotation "@RequiredPermissions" in method '
                . UnimportedAnnotationDocblockController::class . '::show() was never imported.',
            ],
            'only @param, @return and @throws' => [
                TagsOnlyDocblockController::class,
                null,
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
