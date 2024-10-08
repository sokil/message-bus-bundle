<?php

declare(strict_types=1);

namespace Sokil\MessageBusBundle;

use Sokil\MessageBusBundle\DependencyInjection\CompilerPass\MessageBusCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MessageBusBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MessageBusCompilerPass());
    }
}
