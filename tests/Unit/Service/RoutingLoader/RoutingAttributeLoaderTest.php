<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\BundleAnnotationNextToUnrelatedTagsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAnnotationOnInheritedRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\FullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\InheritedDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ParentNamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TagsOnlyDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnimportedAnnotationDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnrelatedDocblockTagsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnusedBundleImportController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

class RoutingAttributeLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AttributeRouteControllerLoader::class)) {
            $this->markTestSkipped('Symfony 6.3 and older load routes through RoutingAnnotationLoader');
        }
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderDataProvider
     */
    public function testLoadWithoutAnnotationReader(
        string $controller,
        ?string $expectedException,
        ?string $expectedExceptionMessage
    ): void {
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
            'alias of a parent namespace, imported with a leading backslash' => [
                ParentNamespaceAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ParentNamespaceAliasDocblockController::class, '@RequiredPermissions'),
            ],
            'method inherited from a parent class that imports the annotation' => [
                InheritedDocblockOptionsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(InheritedDocblockOptionsController::class, '@RequiredPermissions'),
            ],
            'annotation on a class whose route is inherited' => [
                ClassAnnotationOnInheritedRouteController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ClassAnnotationOnInheritedRouteController::class, '@RequiredPermissions'),
            ],
            'method of a trait that imports the annotation' => [
                TraitDocblockOptionsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(TraitDocblockOptionsController::class, '@RequiredPermissions'),
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
                null,
                null,
            ],
            'only @param, @return and @throws' => [
                TagsOnlyDocblockController::class,
                null,
                null,
            ],
            'imported but unused annotation of the bundle' => [
                UnusedBundleImportController::class,
                null,
                null,
            ],
            'unknown tags and an annotation of another library that Doctrine rejects' => [
                UnrelatedDocblockTagsController::class,
                null,
                null,
            ],
            'annotation of the bundle next to tags that Doctrine rejects' => [
                BundleAnnotationNextToUnrelatedTagsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(BundleAnnotationNextToUnrelatedTagsController::class, '@RequiredPermissions'),
            ],
        ];
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderAfterAnotherControllerDataProvider
     */
    public function testLoadWithoutAnnotationReaderAfterAnotherController(
        string $firstController,
        string $controller
    ): void {
        $loader = new RoutingAttributeLoader();
        $loader->load($firstController);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(self::buildRefusalMessage($controller, '@RequiredPermissions'));

        $loader->load($controller);
    }

    public static function loadWithoutAnnotationReaderAfterAnotherControllerDataProvider(): array
    {
        return [
            'after a controller that is not read' => [
                NoBundleAnnotationController::class,
                DocblockOptionsOnAttributeRouteController::class,
            ],
            'after a controller that is read' => [
                UnusedBundleImportController::class,
                ClassAliasDocblockController::class,
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
