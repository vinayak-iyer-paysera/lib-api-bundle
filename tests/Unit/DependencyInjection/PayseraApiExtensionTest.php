<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Tests\Unit\DependencyInjection;

use Paysera\Bundle\ApiBundle\DependencyInjection\PayseraApiExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\Extension as HttpKernelExtension;

class PayseraApiExtensionTest extends TestCase
{
    public function testExtensionDoesNotExtendHttpKernelsInternalExtension(): void
    {
        $this->assertNotInstanceOf(HttpKernelExtension::class, new PayseraApiExtension());
    }
}
