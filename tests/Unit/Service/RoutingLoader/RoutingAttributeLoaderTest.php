<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\NamespaceRelativeDocblockController;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\BundleAnnotationNextToUnrelatedTagsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\BundleAnnotationOnUnroutedMethodController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAndActionDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassImportingForNestedTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassImportingForTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassNotImportingForTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAnnotationOnInheritedRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DoubleBackslashDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\FullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\InheritedDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\MiscasedFullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\MiscasedImportDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NestedTraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OwnActionUsingAnnotatedTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ParentNamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\SplitNameDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TagsOnlyDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TwoTraitsInOneFileController;
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

        require_once __DIR__ . '/Fixtures/NamespaceRelativeDocblockController.php';
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
                self::buildRefusalMessage(DocblockOptionsOnAttributeRouteController::class),
            ],
            'namespace alias, on the class' => [
                NamespaceAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(NamespaceAliasDocblockController::class),
            ],
            'fully qualified name' => [
                FullyQualifiedDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(FullyQualifiedDocblockController::class),
            ],
            'import whose namespace differs in case' => [
                MiscasedImportDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(MiscasedImportDocblockController::class),
            ],
            'fully qualified name that differs in case' => [
                MiscasedFullyQualifiedDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(MiscasedFullyQualifiedDocblockController::class),
            ],
            'fully qualified name with two leading backslashes' => [
                DoubleBackslashDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(DoubleBackslashDocblockController::class),
            ],
            'namespace alias with the name continued on the next line' => [
                SplitNameDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(SplitNameDocblockController::class),
            ],
            'class alias' => [
                ClassAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ClassAliasDocblockController::class),
            ],
            'alias of a parent namespace, imported with a leading backslash' => [
                ParentNamespaceAliasDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ParentNamespaceAliasDocblockController::class),
            ],
            'annotation on both the class and the action' => [
                ClassAndActionDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(
                    ClassAndActionDocblockController::class,
                    null,
                    '@RequiredPermissions, @Validation'
                ),
            ],
            'method inherited from a parent class that imports the annotation' => [
                InheritedDocblockOptionsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(InheritedDocblockOptionsController::class, 'DocblockOptionsParentController'),
            ],
            'annotation on a class whose route is inherited' => [
                ClassAnnotationOnInheritedRouteController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(ClassAnnotationOnInheritedRouteController::class),
            ],
            'method of a trait used by a trait that imports the annotation' => [
                NestedTraitDocblockOptionsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(NestedTraitDocblockOptionsController::class, 'DocblockOptionsTrait'),
            ],
            'annotation on a method of the file that has no route' => [
                BundleAnnotationOnUnroutedMethodController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(BundleAnnotationOnUnroutedMethodController::class),
            ],
            'annotation named through the namespace of the controller' => [
                NamespaceRelativeDocblockController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(NamespaceRelativeDocblockController::class),
            ],
            'method of a trait whose annotation the class imports' => [
                ClassImportingForTraitController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(
                    ClassImportingForTraitController::class,
                    'UnimportingDocblockOptionsTrait',
                    '@RequiredPermissions',
                    'index'
                ),
            ],
            'method of a trait used by a trait whose annotation the class imports' => [
                ClassImportingForNestedTraitController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(
                    ClassImportingForNestedTraitController::class,
                    'UnimportingDocblockOptionsTrait',
                    '@RequiredPermissions',
                    'index'
                ),
            ],
            'method of the second of two traits in one file' => [
                TwoTraitsInOneFileController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(TwoTraitsInOneFileController::class, 'FirstOfTwoTraits'),
            ],
            'own action of a class whose trait has an annotated method without a route' => [
                OwnActionUsingAnnotatedTraitController::class,
                null,
                null,
            ],
            'method of a trait that imports the annotation' => [
                TraitDocblockOptionsController::class,
                ConfigurationException::class,
                self::buildRefusalMessage(TraitDocblockOptionsController::class, 'DocblockOptionsTrait'),
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
                self::buildRefusalMessage(BundleAnnotationNextToUnrelatedTagsController::class),
            ],
        ];
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderAfterAnotherControllerDataProvider
     */
    public function testLoadWithoutAnnotationReaderAfterAnotherController(
        string $firstController,
        string $controller,
        string $expectedExceptionMessage
    ): void {
        $loader = new RoutingAttributeLoader();
        $loader->load($firstController);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $loader->load($controller);
    }

    public static function loadWithoutAnnotationReaderAfterAnotherControllerDataProvider(): array
    {
        return [
            'after a controller that is not read' => [
                NoBundleAnnotationController::class,
                DocblockOptionsOnAttributeRouteController::class,
                self::buildRefusalMessage(DocblockOptionsOnAttributeRouteController::class),
            ],
            'after a controller that is read' => [
                UnusedBundleImportController::class,
                ClassAliasDocblockController::class,
                self::buildRefusalMessage(ClassAliasDocblockController::class),
            ],
            'after a controller that uses the same trait without importing its annotation' => [
                ClassNotImportingForTraitController::class,
                ClassImportingForTraitController::class,
                self::buildRefusalMessage(
                    ClassImportingForTraitController::class,
                    'UnimportingDocblockOptionsTrait',
                    '@RequiredPermissions',
                    'index'
                ),
            ],
        ];
    }

    private static function buildRefusalMessage(
        string $controller,
        ?string $fixture = null,
        string $annotations = '@RequiredPermissions',
        string $action = 'show'
    ): string {
        $file = __DIR__ . '/Fixtures/' . ($fixture ?? substr($controller, strrpos($controller, '\\') + 1)) . '.php';

        return 'Cannot load the route of ' . $controller . '::' . $action . '(): ' . $file . ' uses docblock '
            . 'annotations of paysera/lib-api-bundle (' . $annotations . '), which are not read because no annotation '
            . 'reader is available. Use the attributes of the same name from Paysera\\Bundle\\ApiBundle\\Attribute '
            . 'instead.';
    }
}
