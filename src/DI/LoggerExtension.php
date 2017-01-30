<?php

namespace Icarus\Doctrine\Logger\DI;


use Icarus\Doctrine\Logger\Logger;
use Kdyby\Doctrine\DI\IEntityProvider;
use Nette\DI\CompilerExtension;
use Nette\Utils\Validators;

class LoggerExtension extends CompilerExtension implements IEntityProvider
{

    public function loadConfiguration()
    {
        $config = $this->getConfig();
        Validators::assertField($config, 'entities', 'array');

        $this->getContainerBuilder()->addDefinition($this->prefix("DoctrineLogger"))
            ->setClass(Logger::class)
            ->setTags(['kdyby.subscriber' => TRUE, 'run' => TRUE])
            ->addSetup('setEntityNamesToLog', [$config['entities']]);
    }



    /**
     * Returns associative array of Namespace => mapping definition
     *
     * @return array
     */
    function getEntityMappings()
    {
        return [
            'Icarus\Doctrine\Logger' => __DIR__ . '/../'
        ];
    }
}