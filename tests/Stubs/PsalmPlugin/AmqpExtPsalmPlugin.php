<?php

declare(strict_types=1);

namespace Sokil\MessageBus\Stubs\PsalmPlugin;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;

class AmqpExtPsalmPlugin implements PluginEntryPointInterface
{
    /** @return void */
    public function __invoke(RegistrationInterface $registration, ?\SimpleXMLElement $config = null): void
    {
        $registration->addStubFile(__DIR__ . '/../Ext/AmqpExtStub.php');
    }
}
