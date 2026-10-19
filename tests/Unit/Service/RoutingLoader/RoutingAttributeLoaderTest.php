<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\Reader;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Paysera\Bundle\ApiBundle\Annotation\RequiredPermissions;
use Paysera\Bundle\ApiBundle\Entity\RestRequestOptions;
use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RestRequestHelper;
use Paysera\Bundle\ApiBundle\Service\RestRequestOptionsRegistry;
use Paysera\Bundle\ApiBundle\Service\RestRequestOptionsValidator;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RestRequestAnnotationOptionsBuilder;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RestRequestAttributeOptionsBuilder;
use Paysera\Bundle\ApiBundle\Service\RoutingLoader\RoutingAttributeLoader;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\Admin\SameNameController as AdminSameName;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\AliasedTraitMethodController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\Api\SameNameController as ApiSameName;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\AttributeOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassAndActionDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassDocblockAndMethodAttributeController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassDocblockOnInheritedActionController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ClassDocblockWithUnknownTagController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsOnAttributeRouteController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\DocblockOptionsTrait;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\IgnoredBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\IgnoredImportedBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\IgnoredNoteTagController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ImportsNamedLikeTheAnnotationNamespaceController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\InheritedDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\InheritedTraitActionController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\LeadingBackslashImportController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\MethodDocblockAndAttributeController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\MiscasedImportController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NestedTraitActionController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\NoBundleAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryAnnotationController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OtherLibraryBodyController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\OverriddenTraitMethodController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\ParentNamespaceAliasDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TraitDocblockOptionsController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\TwoActionsDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnimportedAnnotationDocblockController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnimportedNoteTagController;
use Paysera\Bundle\ApiBundle\Tests\Unit\Service\RoutingLoader\Fixtures\UnusedAnnotationImportController;
use ReflectionClass;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Loader\AttributeClassLoader;

class RoutingAttributeLoaderTest extends MockeryTestCase
{
    private const MIXED_ROUTE_MESSAGE = 'The route of method %s() is configured with both docblock annotations (%s)'
        . ' and attributes (%s) that implement the bundle\'s interfaces, on the method or its class: configure the'
        . ' method and its class with attributes only, or with docblock annotations only. No route is loaded, rather'
        . ' than one kind being applied and the other dropped';
    private const IMPORTED_ANNOTATIONS_MESSAGE = '%s imports %s, but the bundle\'s docblock annotations are not read'
        . ' where Symfony passes no annotation reader and paysera_api.docblock_annotations is false: set the option to'
        . ' true, or remove the import and use the bundle\'s attributes. No route is loaded; the check stopped at'
        . ' method %s()';
    private const READ_FAILURE_MESSAGE = 'Reading the docblock annotations with paysera_api.docblock_annotations: true'
        . ' failed: %s Fix what this message names (a tag that is not an annotation can be listed with'
        . ' @IgnoreAnnotation in the class docblock), or replace the bundle\'s docblock annotations with its attributes'
        . ' and set the option to false. No route is loaded; reading stopped at method %s()';
    private const NEVER_IMPORTED_MESSAGE = '[Semantical Error] The annotation "@%s" in %s was never imported. Did you'
        . ' maybe forget to add a "use" statement for this annotation?';
    private const ANNOTATION_NAMESPACE = 'Paysera\\Bundle\\ApiBundle\\Annotation';

    protected function setUp(): void
    {
        if (!class_exists(AttributeRouteControllerLoader::class)) {
            $this->markTestSkipped('Symfony 6.3 and older load routes through RoutingAnnotationLoader');
        }
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderDataProvider
     * @param array<string, RestRequestOptions|null> $expectedOptions
     */
    public function testLoadWithoutAnnotationReader(string $controller, array $expectedOptions): void
    {
        $loader = $this->createLoader();
        $loader->setDocblockAnnotationsEnabled(true);

        $this->assertEquals($expectedOptions, $this->loadOptions($loader, $controller));
    }

    public static function loadWithoutAnnotationReaderDataProvider(): array
    {
        $adminOnly = (new RestRequestOptions())->setRequiredPermissions(['ROLE_ADMIN']);

        return [
            'annotation on the action' => [
                DocblockOptionsOnAttributeRouteController::class,
                [DocblockOptionsOnAttributeRouteController::class . '::show' => $adminOnly],
            ],
            'annotations on the class and the action' => [
                ClassAndActionDocblockController::class,
                [
                    ClassAndActionDocblockController::class . '::show' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_USER', 'ROLE_ADMIN'])
                        ->disableBodyValidation(),
                ],
            ],
            'action of a trait that imports the annotation' => [
                TraitDocblockOptionsController::class,
                [TraitDocblockOptionsController::class . '::show' => $adminOnly],
            ],
            'attribute' => [
                AttributeOptionsController::class,
                [AttributeOptionsController::class . '::show' => $adminOnly],
            ],
            'no annotation of the bundle' => [
                NoBundleAnnotationController::class,
                [NoBundleAnnotationController::class . '::show' => null],
            ],
            'annotation of another library next to the one of the bundle' => [
                OtherLibraryAnnotationController::class,
                [OtherLibraryAnnotationController::class . '::show' => $adminOnly],
            ],
            'tag listed in IgnoreAnnotation on the class' => [
                IgnoredNoteTagController::class,
                [IgnoredNoteTagController::class . '::show' => $adminOnly],
            ],
            'unimported short name of the bundle in IgnoreAnnotation: skipped, as by Doctrine\'s reader' => [
                IgnoredBundleAnnotationController::class,
                [IgnoredBundleAnnotationController::class . '::show' => null],
            ],
            'imported annotation of the bundle listed in IgnoreAnnotation: applied, as by Doctrine\'s reader' => [
                IgnoredImportedBundleAnnotationController::class,
                [IgnoredImportedBundleAnnotationController::class . '::show' => $adminOnly],
            ],
            'annotation namespace imported with an alias' => [
                NamespaceAliasDocblockController::class,
                [NamespaceAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'parent namespace imported with an alias' => [
                ParentNamespaceAliasDocblockController::class,
                [ParentNamespaceAliasDocblockController::class . '::show' => $adminOnly],
            ],
            'inherited action of a class that imports the annotation' => [
                InheritedDocblockController::class,
                [InheritedDocblockController::class . '::show' => $adminOnly],
            ],
            'unused import of an annotation of the bundle next to an attribute' => [
                UnusedAnnotationImportController::class,
                [UnusedAnnotationImportController::class . '::show' => $adminOnly],
            ],
            'import written with a leading backslash' => [
                LeadingBackslashImportController::class,
                [LeadingBackslashImportController::class . '::show' => $adminOnly],
            ],
            'inherited action of a class that uses a trait importing the annotation' => [
                InheritedTraitActionController::class,
                [InheritedTraitActionController::class . '::show' => $adminOnly],
            ],
            'class docblock of a class that imports the annotation, on an inherited action' => [
                ClassDocblockOnInheritedActionController::class,
                [ClassDocblockOnInheritedActionController::class . '::show' => $adminOnly],
            ],
        ];
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderFailsToReadDataProvider
     */
    public function testLoadWithoutAnnotationReaderFailsToReadADocblock(string $controller, string $tag): void
    {
        $loader = $this->createLoader();
        $loader->setDocblockAnnotationsEnabled(true);
        $method = $controller . '::show';

        $this->assertSame(
            [
                ConfigurationException::class,
                sprintf(
                    self::READ_FAILURE_MESSAGE,
                    sprintf(self::NEVER_IMPORTED_MESSAGE, $tag, 'method ' . $method . '()'),
                    $method
                ),
                AnnotationException::class,
            ],
            $this->describeLoadFailure($loader, $controller)
        );
    }

    public static function loadWithoutAnnotationReaderFailsToReadDataProvider(): array
    {
        return [
            'annotation of the bundle that is not imported, refused as by Doctrine\'s reader' => [
                UnimportedAnnotationDocblockController::class,
                'RequiredPermissions',
            ],
            'tag that is not imported and not an annotation, refused as by Doctrine\'s reader' => [
                UnimportedNoteTagController::class,
                'note',
            ],
        ];
    }

    public function testLoadWithoutAnnotationReaderFailsAgainAfterAFailedReadOfTheClassDocblock(): void
    {
        $loader = $this->createLoader();
        $loader->setDocblockAnnotationsEnabled(true);
        $failure = [
            ConfigurationException::class,
            sprintf(
                self::READ_FAILURE_MESSAGE,
                sprintf(self::NEVER_IMPORTED_MESSAGE, 'note', 'class ' . ClassDocblockWithUnknownTagController::class),
                ClassDocblockWithUnknownTagController::class . '::show'
            ),
            AnnotationException::class,
        ];

        $this->assertSame(
            [$failure, $failure],
            [
                $this->describeLoadFailure($loader, ClassDocblockWithUnknownTagController::class),
                $this->describeLoadFailure($loader, ClassDocblockWithUnknownTagController::class),
            ]
        );
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderFailureDataProvider
     */
    public function testLoadWithoutAnnotationReaderFails(string $controller, string $message): void
    {
        $loader = $this->createLoader();
        $loader->setDocblockAnnotationsEnabled(true);

        $this->assertSame(
            [ConfigurationException::class, $message, null],
            $this->describeLoadFailure($loader, $controller)
        );
    }

    public static function loadWithoutAnnotationReaderFailureDataProvider(): array
    {
        return [
            'annotation on the class and attribute on the action' => [
                ClassDocblockAndMethodAttributeController::class,
                sprintf(
                    self::MIXED_ROUTE_MESSAGE,
                    ClassDocblockAndMethodAttributeController::class . '::update',
                    'RequiredPermissions',
                    'Body'
                ),
            ],
            'annotation and attribute on the action' => [
                MethodDocblockAndAttributeController::class,
                sprintf(
                    self::MIXED_ROUTE_MESSAGE,
                    MethodDocblockAndAttributeController::class . '::show',
                    'Validation',
                    'RequiredPermissions'
                ),
            ],
        ];
    }

    /**
     * @dataProvider loadWithAnnotationReaderDataProvider
     */
    public function testLoadWithAnnotationReader(bool $docblockAnnotationsEnabled): void
    {
        $loader = $this->createLoaderWithAnnotationReader($docblockAnnotationsEnabled);

        $this->assertEquals(
            [
                NoBundleAnnotationController::class . '::show' => (new RestRequestOptions())
                    ->setRequiredPermissions(['ROLE_ADMIN']),
            ],
            $this->loadOptions($loader, NoBundleAnnotationController::class)
        );
    }

    public static function loadWithAnnotationReaderDataProvider(): array
    {
        return [
            'docblock annotations on' => [true],
            'docblock annotations off, which applies only where no reader is injected' => [false],
        ];
    }

    public function testLoadWithAnnotationReaderReadsImportedAnnotationsWithTheOptionOff(): void
    {
        $loader = $this->createLoaderWithAnnotationReader(false);

        $this->assertEquals(
            [
                DocblockOptionsOnAttributeRouteController::class . '::show' => (new RestRequestOptions())
                    ->setRequiredPermissions(['ROLE_ADMIN']),
            ],
            $this->loadOptions($loader, DocblockOptionsOnAttributeRouteController::class)
        );
    }

    public function testLoadWithAnnotationReaderPassesItsExceptionThrough(): void
    {
        if (!property_exists(AttributeClassLoader::class, 'reader')) {
            $this->markTestSkipped('Symfony 7 passes no annotation reader to the route loader');
        }

        $reader = Mockery::mock(Reader::class);
        $reader
            ->shouldReceive('getClassAnnotation')
            ->andReturn(null)
        ;
        $reader
            ->shouldReceive('getClassAnnotations')
            ->andThrow(AnnotationException::semanticalError('The class docblock cannot be read'))
        ;
        $reader
            ->shouldReceive('getMethodAnnotations')
            ->andReturn([])
        ;

        $this->assertSame(
            [AnnotationException::class, '[Semantical Error] The class docblock cannot be read', null],
            $this->describeLoadFailure($this->createLoader($reader), DocblockOptionsOnAttributeRouteController::class)
        );
    }

    public function testLoadWithAnnotationReaderReadsTheClassAnnotationsOncePerClass(): void
    {
        if (!property_exists(AttributeClassLoader::class, 'reader')) {
            $this->markTestSkipped('Symfony 7 passes no annotation reader to the route loader');
        }

        $classReads = [];
        $reader = Mockery::mock(Reader::class);
        $reader
            ->shouldReceive('getClassAnnotation')
            ->andReturn(null)
        ;
        $reader
            ->shouldReceive('getClassAnnotations')
            ->andReturnUsing(static function (ReflectionClass $class) use (&$classReads): array {
                $classReads[] = $class->getName();

                return [];
            })
        ;
        $reader
            ->shouldReceive('getMethodAnnotations')
            ->andReturn([])
        ;
        $loader = $this->createLoader($reader);

        $loader->load(TwoActionsDocblockController::class);
        $loader->load(DocblockOptionsOnAttributeRouteController::class);

        $this->assertSame(
            [TwoActionsDocblockController::class => 1, DocblockOptionsOnAttributeRouteController::class => 1],
            array_count_values($classReads)
        );
    }

    /**
     * @dataProvider loadAfterAnotherControllerDataProvider
     * @param array<string, RestRequestOptions|null> $expectedOptions
     */
    public function testLoadAfterAnotherControllerKeepsEachClassAnnotations(
        string $firstController,
        string $controller,
        array $expectedOptions
    ): void {
        $loader = $this->createLoader();
        $loader->setDocblockAnnotationsEnabled(true);
        $loader->load($firstController);

        $this->assertEquals($expectedOptions, $this->loadOptions($loader, $controller));
    }

    public static function loadAfterAnotherControllerDataProvider(): array
    {
        return [
            'a controller without class annotations after one with them' => [
                ClassAndActionDocblockController::class,
                DocblockOptionsOnAttributeRouteController::class,
                [
                    DocblockOptionsOnAttributeRouteController::class . '::show' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_ADMIN']),
                ],
            ],
            'a controller with class annotations after one without them' => [
                DocblockOptionsOnAttributeRouteController::class,
                ClassAndActionDocblockController::class,
                [
                    ClassAndActionDocblockController::class . '::show' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_USER', 'ROLE_ADMIN'])
                        ->disableBodyValidation(),
                ],
            ],
            'a controller after one of the same short name in another namespace' => [
                ApiSameName::class,
                AdminSameName::class,
                [
                    AdminSameName::class . '::show' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_ADMIN']),
                ],
            ],
            'each action of a controller after another controller' => [
                ClassAndActionDocblockController::class,
                TwoActionsDocblockController::class,
                [
                    TwoActionsDocblockController::class . '::first' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_USER']),
                    TwoActionsDocblockController::class . '::second' => (new RestRequestOptions())
                        ->setRequiredPermissions(['ROLE_USER']),
                ],
            ],
        ];
    }

    /**
     * @dataProvider loadWithAnnotationReaderDataProvider
     */
    public function testLoadWithAnnotationReaderFailsOnMixedRoute(bool $docblockAnnotationsEnabled): void
    {
        $loader = $this->createLoaderWithAnnotationReader($docblockAnnotationsEnabled);

        $this->assertSame(
            [
                ConfigurationException::class,
                sprintf(
                    self::MIXED_ROUTE_MESSAGE,
                    AttributeOptionsController::class . '::show',
                    'RequiredPermissions',
                    'RequiredPermissions'
                ),
                null,
            ],
            $this->describeLoadFailure($loader, AttributeOptionsController::class)
        );
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderByDefaultDataProvider
     * @param array<string, RestRequestOptions|null> $expectedOptions
     */
    public function testLoadWithoutAnnotationReaderReadsOnlyAttributesByDefault(
        string $controller,
        array $expectedOptions
    ): void {
        $this->assertEquals($expectedOptions, $this->loadOptions($this->createLoader(), $controller));
    }

    public static function loadWithoutAnnotationReaderByDefaultDataProvider(): array
    {
        $adminOnly = (new RestRequestOptions())->setRequiredPermissions(['ROLE_ADMIN']);

        return [
            'attribute' => [
                AttributeOptionsController::class,
                [AttributeOptionsController::class . '::show' => $adminOnly],
            ],
            'no annotation of the bundle' => [
                NoBundleAnnotationController::class,
                [NoBundleAnnotationController::class . '::show' => null],
            ],
            'annotation of another library imported as Body, next to an attribute' => [
                OtherLibraryBodyController::class,
                [OtherLibraryBodyController::class . '::show' => $adminOnly],
            ],
            'imports whose names only resemble the annotation namespace' => [
                ImportsNamedLikeTheAnnotationNamespaceController::class,
                [ImportsNamedLikeTheAnnotationNamespaceController::class . '::show' => $adminOnly],
            ],
            'action that overrides the method of a trait importing the annotations' => [
                OverriddenTraitMethodController::class,
                [OverriddenTraitMethodController::class . '::show' => $adminOnly],
            ],
            'annotation of the bundle with no import at all: not read, as an attribute with no import is not' => [
                UnimportedAnnotationDocblockController::class,
                [UnimportedAnnotationDocblockController::class . '::show' => null],
            ],
        ];
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderFailsByDefaultDataProvider
     */
    public function testLoadWithoutAnnotationReaderFailsByDefaultWhereTheBundleAnnotationsAreImported(
        string $controller,
        string $importingClass,
        string $import,
        string $method
    ): void {
        $this->assertSame(
            [
                ConfigurationException::class,
                sprintf(self::IMPORTED_ANNOTATIONS_MESSAGE, $importingClass, $import, $controller . '::' . $method),
                null,
            ],
            $this->describeLoadFailure($this->createLoader(), $controller)
        );
    }

    public static function loadWithoutAnnotationReaderFailsByDefaultDataProvider(): array
    {
        $requiredPermissions = self::ANNOTATION_NAMESPACE . '\\RequiredPermissions';
        $validation = self::ANNOTATION_NAMESPACE . '\\Validation';

        return [
            'annotation on the action' => [
                DocblockOptionsOnAttributeRouteController::class,
                DocblockOptionsOnAttributeRouteController::class,
                $requiredPermissions,
                'show',
            ],
            'annotation on the class and attribute on the action' => [
                ClassDocblockAndMethodAttributeController::class,
                ClassDocblockAndMethodAttributeController::class,
                $requiredPermissions,
                'update',
            ],
            'annotation and attribute on the action' => [
                MethodDocblockAndAttributeController::class,
                MethodDocblockAndAttributeController::class,
                $validation,
                'show',
            ],
            'action of a trait that imports the annotation' => [
                TraitDocblockOptionsController::class,
                DocblockOptionsTrait::class,
                $requiredPermissions,
                'show',
            ],
            'action of a trait used by another trait' => [
                NestedTraitActionController::class,
                DocblockOptionsTrait::class,
                $requiredPermissions,
                'show',
            ],
            'trait action renamed in the use statement' => [
                AliasedTraitMethodController::class,
                DocblockOptionsTrait::class,
                $requiredPermissions,
                'index',
            ],
            'inherited action of a class that imports the annotation' => [
                InheritedDocblockController::class,
                DocblockOptionsOnAttributeRouteController::class,
                $requiredPermissions,
                'show',
            ],
            'inherited action of a class that uses a trait importing the annotation' => [
                InheritedTraitActionController::class,
                DocblockOptionsTrait::class,
                $requiredPermissions,
                'show',
            ],
            'class docblock of a class that imports the annotation, on an inherited action' => [
                ClassDocblockOnInheritedActionController::class,
                ClassDocblockOnInheritedActionController::class,
                $requiredPermissions,
                'show',
            ],
            'annotation namespace imported with an alias' => [
                NamespaceAliasDocblockController::class,
                NamespaceAliasDocblockController::class,
                self::ANNOTATION_NAMESPACE,
                'show',
            ],
            'parent namespace imported with an alias' => [
                ParentNamespaceAliasDocblockController::class,
                ParentNamespaceAliasDocblockController::class,
                'Paysera\\Bundle\\ApiBundle',
                'show',
            ],
            'import written with a leading backslash' => [
                LeadingBackslashImportController::class,
                LeadingBackslashImportController::class,
                $requiredPermissions,
                'show',
            ],
            'import in another letter case' => [
                MiscasedImportController::class,
                MiscasedImportController::class,
                'Paysera\\Bundle\\APIBundle\\Annotation\\RequiredPermissions',
                'show',
            ],
            'unused import next to an attribute: refused, as the check reads only the imports' => [
                UnusedAnnotationImportController::class,
                UnusedAnnotationImportController::class,
                $validation,
                'show',
            ],
            'unknown tag next to an imported annotation: refused before any docblock is parsed' => [
                UnimportedNoteTagController::class,
                UnimportedNoteTagController::class,
                $requiredPermissions,
                'show',
            ],
        ];
    }

    public function testLoadWithoutAnnotationReaderChecksEachClassOfTheSameShortName(): void
    {
        $loader = $this->createLoader();
        $loader->load(ApiSameName::class);

        $this->assertSame(
            [
                ConfigurationException::class,
                sprintf(
                    self::IMPORTED_ANNOTATIONS_MESSAGE,
                    AdminSameName::class,
                    self::ANNOTATION_NAMESPACE . '\\RequiredPermissions',
                    AdminSameName::class . '::show'
                ),
                null,
            ],
            $this->describeLoadFailure($loader, AdminSameName::class)
        );
    }

    /**
     * @dataProvider loadWithoutAnnotationReaderUnderOpcacheDataProvider
     * @param string[] $ini
     * @param string[] $arguments
     */
    public function testLoadWithoutAnnotationReaderUnderOpcache(
        array $ini,
        string $expectedOutput,
        array $arguments
    ): void {
        $lines = $this->runFixtureScript($ini, 'load-routes.php', $arguments);
        $output = (string)end($lines);
        if ($output === 'no opcache') {
            $this->markTestSkipped('This PHP has no OPcache to strip docblocks');
        }

        $this->assertSame($expectedOutput, $output, implode("\n", $lines));
    }

    public static function loadWithoutAnnotationReaderUnderOpcacheDataProvider(): array
    {
        $docblocksStripped = [
            '-d',
            'opcache.enable_cli=1',
            '-d',
            'opcache.save_comments=0',
            '-d',
            'opcache.file_update_protection=0',
        ];

        return [
            'attributes only, docblocks stripped, default configuration' => [
                $docblocksStripped,
                '["ROLE_ADMIN"]',
                [AttributeOptionsController::class],
            ],
            'annotation of the bundle imported, docblocks stripped, default configuration: refused' => [
                $docblocksStripped,
                ConfigurationException::class,
                [DocblockOptionsOnAttributeRouteController::class],
            ],
            'docblock annotations on, docblocks stripped: Doctrine\'s reader refuses to run' => [
                $docblocksStripped,
                ConfigurationException::class,
                [DocblockOptionsOnAttributeRouteController::class, 'docblock-annotations'],
            ],
            'docblock annotations on, docblocks kept' => [
                [
                    '-d',
                    'opcache.enable_cli=1',
                    '-d',
                    'opcache.save_comments=1',
                    '-d',
                    'opcache.file_update_protection=0',
                ],
                '["ROLE_ADMIN"]',
                [DocblockOptionsOnAttributeRouteController::class, 'docblock-annotations'],
            ],
            'docblock annotations on, save_comments off with OPcache off for the command line: refused as well' => [
                ['-d', 'opcache.enable_cli=0', '-d', 'opcache.save_comments=0'],
                ConfigurationException::class,
                [DocblockOptionsOnAttributeRouteController::class, 'docblock-annotations'],
            ],
        ];
    }

    /**
     * @param string[] $ini
     * @param string[] $arguments
     * @return string[] the lines the script printed, standard error included
     */
    private function runFixtureScript(array $ini, string $script, array $arguments): array
    {
        $autoload = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $command = array_merge([PHP_BINARY], $ini, [__DIR__ . '/Fixtures/' . $script, $autoload], $arguments);
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>&1', $lines);

        return $lines;
    }

    private function createLoaderWithAnnotationReader(bool $docblockAnnotationsEnabled): RoutingAttributeLoader
    {
        if (!property_exists(AttributeClassLoader::class, 'reader')) {
            $this->markTestSkipped('Symfony 7 passes no annotation reader to the route loader');
        }

        $reader = Mockery::mock(Reader::class);
        $reader
            ->shouldReceive('getClassAnnotation')
            ->andReturn(null)
        ;
        $reader
            ->shouldReceive('getClassAnnotations')
            ->andReturn([])
        ;
        $reader
            ->shouldReceive('getMethodAnnotations')
            ->andReturn([new RequiredPermissions(['permissions' => ['ROLE_ADMIN']])])
        ;
        $loader = $this->createLoader($reader);
        $loader->setDocblockAnnotationsEnabled($docblockAnnotationsEnabled);

        return $loader;
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
     * @return array{string, string, string|null}|null the class, message and previous class of the failure
     */
    private function describeLoadFailure(RoutingAttributeLoader $loader, string $controller): ?array
    {
        try {
            $loader->load($controller);
        } catch (Throwable $exception) {
            $previous = $exception->getPrevious();

            return [get_class($exception), $exception->getMessage(), $previous === null ? null : get_class($previous)];
        }

        return null;
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
