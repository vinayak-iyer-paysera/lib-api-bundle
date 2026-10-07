<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Service\RoutingLoader;

use Doctrine\Common\Annotations\AnnotationReader;
use Paysera\Bundle\ApiBundle\Annotation\RestAnnotationInterface;
use Paysera\Bundle\ApiBundle\Attribute\RestAttributeInterface;
use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RestRequestHelper;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
class RoutingAttributeLoader extends AttributeRouteControllerLoader
{
    /**
     * @var RestRequestHelper
     */
    private $restRequestHelper;

    /**
     * @var RestRequestAnnotationOptionsBuilder
     */
    private $annotationOptionsBuilder;

    /**
     * @var RestRequestAttributeOptionsBuilder
     */
    private $attributeOptionsBuilder;

    public function setRequestHelper(RestRequestHelper $restRequestHelper)
    {
        $this->restRequestHelper = $restRequestHelper;
    }

    public function setRestRequestAnnotationOptionsBuilder(RestRequestAnnotationOptionsBuilder $annotationOptionsBuilder
    ) {
        $this->annotationOptionsBuilder = $annotationOptionsBuilder;
    }

    public function setRestRequestAttributeOptionsBuilder(RestRequestAttributeOptionsBuilder $attributeOptionsBuilder)
    {
        $this->attributeOptionsBuilder = $attributeOptionsBuilder;
    }

    protected function configureRoute(
        Route $route,
        ReflectionClass $class,
        ReflectionMethod $method,
        object $annot
    ): void {
        parent::configureRoute($route, $class, $method, $annot);

        $this->loadAnnotations($route, $class, $method);
        $this->loadAttributes($route, $class, $method);
    }

    /**
     * @throws ConfigurationException
     */
    private function loadAnnotations(Route $route, ReflectionClass $class, ReflectionMethod $method): void
    {
        if (!property_exists($this, 'reader') || !isset($this->reader)) {
            $this->refuseDocblockAnnotations($class, $method);

            return;
        }

        $annotations = [];
        foreach ($this->reader->getClassAnnotations($class) as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $annotations[] = $annotation;
            }
        }

        foreach ($this->reader->getMethodAnnotations($method) as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $annotations[] = $annotation;
            }
        }

        if ($annotations === []) {
            return;
        }

        $this->restRequestHelper->setOptionsForRoute(
            $route,
            $this->annotationOptionsBuilder->buildOptions($annotations, $method)
        );
    }

    /**
     * @throws ConfigurationException
     */
    private function refuseDocblockAnnotations(ReflectionClass $class, ReflectionMethod $method): void
    {
        $reader = new AnnotationReader();
        $annotations = array_merge($reader->getClassAnnotations($class), $reader->getMethodAnnotations($method));
        $names = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $names[] = (new ReflectionClass($annotation))->getShortName();
            }
        }

        if ($names === []) {
            return;
        }

        throw new ConfigurationException(sprintf(
            '%s::%s() uses docblock annotations of paysera/lib-api-bundle (@%s), which are not read because no '
            . 'annotation reader is available. Use the attributes of the same name from '
            . 'Paysera\\Bundle\\ApiBundle\\Attribute instead.',
            $class->getName(),
            $method->getName(),
            implode(', @', array_unique($names))
        ));
    }

    private function loadAttributes(Route $route, ReflectionClass $class, ReflectionMethod $method): void
    {
        $attributes = array_merge($class->getAttributes(), $method->getAttributes());

        $restAttributes = [];
        foreach ($attributes as $attribute) {
            if (is_subclass_of($attribute->getName(), RestAttributeInterface::class)) {
                /** @var RestAttributeInterface $instance */
                $instance = $attribute->newInstance();
                $restAttributes[] = $instance;
            }
        }

        if ($restAttributes === []) {
            return;
        }

        $this->restRequestHelper->setOptionsForRoute(
            $route,
            $this->attributeOptionsBuilder->buildOptions($restAttributes, $method)
        );
    }
}
