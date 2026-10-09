<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Doctrine\Common\Annotations\Reader;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Paysera\Bundle\ApiBundle\Entity\RestRequestOptions;
use Paysera\Bundle\ApiBundle\NamespaceRelativeDocblockController;
use Paysera\Bundle\ApiBundle\Service\RestRequestHelper;
use Paysera\Bundle\ApiBundle\Service\RestRequestOptionsRegistry;
use Paysera\Bundle\ApiBundle\Service\RestRequestOptionsValidator;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RestRequestAnnotationOptionsBuilder;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RestRequestAttributeOptionsBuilder;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\AliasedTraitMethodController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\BundleAnnotationNextToUnrelatedTagsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\BundleAnnotationOnUnroutedMethodController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAndActionDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAnnotationOnInheritedRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassImportingForNestedTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassImportingForTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassNotImportingForTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DoubleBackslashDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\FullyQualifiedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\InheritedDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\MiscasedImportDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NestedTraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibrary\SameNamespaceAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OwnActionUsingAnnotatedTraitController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ParentNamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\SplitNameDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TagsOnlyDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TwoTraitsInOneFileController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnimportedAnnotationDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnrelatedDocblockTagsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnusedBundleImportController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Loader\AttributeClassLoader;

class RoutingAttributeLoaderTest extends MockeryTestCase
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
     * @param array<string, RestRequestOptions|null> $expectedOptions
     */
    public function testLoadWithoutAnnotationReader(string $controller, array $expectedOptions): void
    {
        $this->assertEquals($expectedOptions, $this->loadOptions($this->createLoader(), $controller));
    }

    public static function loadWithoutAnnotationReaderDataProvider(): array
    {
        $adminOnly = (new RestRequestOptions())->setRequiredPermissions(['ROLE_ADMIN']);

        return [
            'short name' => [
                DocblockOptionsOnAttributeRouteController::class,
                [DocblockOptionsOnAttributeRouteController::class . '::show' => $adminOnly],
            ],
            'namespace alias, on the class' => [
                NamespaceAliasDocblockController::class,
                [NamespaceAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'fully qualified name' => [
                FullyQualifiedDocblockController::class,
                [FullyQualifiedDocblockController::class . '::show' => $adminOnly],
            ],
            'fully qualified name with two leading backslashes' => [
                DoubleBackslashDocblockController::class,
                [DoubleBackslashDocblockController::class . '::show' => $adminOnly],
            ],
            'namespace alias with the name continued on the next line' => [
                SplitNameDocblockController::class,
                [SplitNameDocblockController::class . '::show' => $adminOnly],
            ],
            'class alias' => [
                ClassAliasDocblockController::class,
                [ClassAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'alias of a parent namespace, imported with a leading backslash' => [
                ParentNamespaceAliasDocblockController::class,
                [ParentNamespaceAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'annotation on both the class and the action' => [
                ClassAndActionDocblockController::class,
                [
                    ClassAndActionDocblockController::class . '::show' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_USER', 'ROLE_ADMIN'])
                        ->disableBodyValidation(),
                ],
            ],
            'method inherited from a parent class that imports the annotation' => [
                InheritedDocblockOptionsController::class,
                [InheritedDocblockOptionsController::class . '::show' => $adminOnly],
            ],
            'annotation on a class whose route is inherited' => [
                ClassAnnotationOnInheritedRouteController::class,
                [ClassAnnotationOnInheritedRouteController::class . '::show' => $adminOnly],
            ],
            'method of a trait that imports the annotation' => [
                TraitDocblockOptionsController::class,
                [TraitDocblockOptionsController::class . '::show' => $adminOnly],
            ],
            'method of a trait whose annotation the class imports' => [
                ClassImportingForTraitController::class,
                [ClassImportingForTraitController::class . '::index' => $adminOnly],
            ],
            'method of a trait used by a trait that imports the annotation' => [
                NestedTraitDocblockOptionsController::class,
                [NestedTraitDocblockOptionsController::class . '::show' => $adminOnly],
            ],
            'method of a trait used by a trait whose annotation the class imports' => [
                ClassImportingForNestedTraitController::class,
                [ClassImportingForNestedTraitController::class . '::index' => $adminOnly],
            ],
            'method of a trait under an alias' => [
                AliasedTraitMethodController::class,
                [
                    AliasedTraitMethodController::class . '::show' => $adminOnly,
                    AliasedTraitMethodController::class . '::index' => $adminOnly,
                ],
            ],
            'method of the second of two traits in one file' => [
                TwoTraitsInOneFileController::class,
                [TwoTraitsInOneFileController::class . '::show' => $adminOnly],
            ],
            'annotation named through the namespace of the controller' => [
                NamespaceRelativeDocblockController::class,
                [NamespaceRelativeDocblockController::class . '::show' => $adminOnly],
            ],
            'annotation of the bundle next to tags that Doctrine rejects' => [
                BundleAnnotationNextToUnrelatedTagsController::class,
                [BundleAnnotationNextToUnrelatedTagsController::class . '::show' => $adminOnly],
            ],
            'annotation on a method of the file that has no route' => [
                BundleAnnotationOnUnroutedMethodController::class,
                [BundleAnnotationOnUnroutedMethodController::class . '::show' => null],
            ],
            'own action of a class whose trait has an annotated method without a route' => [
                OwnActionUsingAnnotatedTraitController::class,
                [OwnActionUsingAnnotatedTraitController::class . '::show' => null],
            ],
            'annotation of another library with the same short name' => [
                OtherLibraryDocblockController::class,
                [OtherLibraryDocblockController::class . '::show' => null],
            ],
            'no annotation of the bundle' => [
                NoBundleAnnotationController::class,
                [NoBundleAnnotationController::class . '::show' => null],
            ],
            'un-imported annotation of the bundle' => [
                UnimportedAnnotationDocblockController::class,
                [UnimportedAnnotationDocblockController::class . '::show' => null],
            ],
            'only @param, @return and @throws' => [
                TagsOnlyDocblockController::class,
                [TagsOnlyDocblockController::class . '::show' => null],
            ],
            'imported but unused annotation of the bundle' => [
                UnusedBundleImportController::class,
                [UnusedBundleImportController::class . '::show' => null],
            ],
            'unknown tags and an annotation of another library that Doctrine rejects' => [
                UnrelatedDocblockTagsController::class,
                [UnrelatedDocblockTagsController::class . '::show' => null],
            ],
            'annotation of another library from the namespace of the controller' => [
                SameNamespaceAnnotationController::class,
                [SameNamespaceAnnotationController::class . '::show' => null],
            ],
        ];
    }

    public function testLoadWithoutAnnotationReaderResolvesMiscasedImportOfLoadedAnnotation(): void
    {
        class_exists(RequiredPermissions::class);

        $this->assertEquals(
            [
                MiscasedImportDocblockController::class . '::show' => (new RestRequestOptions())
                    ->setRequiredPermissions(['ROLE_ADMIN']),
            ],
            $this->loadOptions($this->createLoader(), MiscasedImportDocblockController::class)
        );
    }

    public function testLoadWithAnnotationReader(): void
    {
        if (!property_exists(AttributeClassLoader::class, 'reader')) {
            $this->markTestSkipped('Symfony 7 passes no annotation reader to the route loader');
        }

        $reader = Mockery::mock(Reader::class);
        $reader->shouldReceive('getClassAnnotation')->andReturn(null);
        $reader->shouldReceive('getClassAnnotations')->andReturn([]);
        $reader->shouldReceive('getMethodAnnotations')->andReturn([
            new RequiredPermissions(['permissions' => ['ROLE_ADMIN']]),
        ]);

        $this->assertEquals(
            [
                NoBundleAnnotationController::class . '::show' => (new RestRequestOptions())
                    ->setRequiredPermissions(['ROLE_ADMIN']),
            ],
            $this->loadOptions($this->createLoader($reader), NoBundleAnnotationController::class)
        );
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderAfterAnotherControllerDataProvider
     * @param array<string, RestRequestOptions|null> $expectedOptions
     */
    public function testLoadWithoutAnnotationReaderAfterAnotherController(
        string $firstController,
        string $controller,
        array $expectedOptions
    ): void {
        $loader = $this->createLoader();
        $loader->load($firstController);

        $this->assertEquals($expectedOptions, $this->loadOptions($loader, $controller));
    }

    public static function loadWithoutAnnotationReaderAfterAnotherControllerDataProvider(): array
    {
        $adminOnly = (new RestRequestOptions())->setRequiredPermissions(['ROLE_ADMIN']);

        return [
            'after a controller that imports the annotation without using it' => [
                UnusedBundleImportController::class,
                ClassAliasDocblockController::class,
                [ClassAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'after a controller that uses the same trait without importing its annotation' => [
                ClassNotImportingForTraitController::class,
                ClassImportingForTraitController::class,
                [ClassImportingForTraitController::class . '::index' => $adminOnly],
            ],
            'after a controller that imports the annotation of the same trait' => [
                ClassImportingForTraitController::class,
                ClassNotImportingForTraitController::class,
                [ClassNotImportingForTraitController::class . '::index' => null],
            ],
        ];
    }

    private function createLoader(?Reader $reader = null): RoutingAttributeLoader
    {
        $loader = $reader === null ? new RoutingAttributeLoader() : new RoutingAttributeLoader($reader);
        $loader->setRequestHelper(new RestRequestHelper(new RestRequestOptionsRegistry()));
        $loader->setRestRequestAnnotationOptionsBuilder(
            new RestRequestAnnotationOptionsBuilder(Mockery::spy(RestRequestOptionsValidator::class))
        );
        $loader->setRestRequestAttributeOptionsBuilder(
            new RestRequestAttributeOptionsBuilder(Mockery::spy(RestRequestOptionsValidator::class))
        );

        return $loader;
    }

    /**
     * @return array<string, RestRequestOptions|null>
     */
    private function loadOptions(RoutingAttributeLoader $loader, string $controller): array
    {
        $options = [];
        foreach ($loader->load($controller)->all() as $route) {
            $serializedOptions = $route->getDefault(RestRequestHelper::SERIALIZED_REST_OPTIONS_KEY);
            $options[$route->getDefault('_controller')] = $serializedOptions === null
                ? null
                : unserialize($serializedOptions);
        }

        return $options;
    }
}
