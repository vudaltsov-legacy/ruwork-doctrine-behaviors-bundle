<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\Events as ORMEvents;
use Ruvents\DoctrineBundle\Command\SearchIndexUpdateCommand;
use Ruvents\DoctrineBundle\EventListener\AuthorListener;
use Ruvents\DoctrineBundle\EventListener\PersistTimestampListener;
use Ruvents\DoctrineBundle\EventListener\SearchIndexListener;
use Ruvents\DoctrineBundle\EventListener\TranslatableListener;
use Ruvents\DoctrineBundle\EventListener\UpdateTimestampListener;
use Ruvents\DoctrineBundle\Metadata\LazyLoadingMetadataFactory;
use Ruvents\DoctrineBundle\Metadata\MetadataFactory;
use Ruvents\DoctrineBundle\Metadata\MetadataFactoryInterface;
use Ruvents\DoctrineBundle\Strategy\AuthorStrategy\AuthorStrategyInterface;
use Ruvents\DoctrineBundle\Strategy\AuthorStrategy\SecurityTokenAuthorStrategy;
use Ruvents\DoctrineBundle\Strategy\TimestampStrategy\FieldTypeTimestampStrategy;
use Ruvents\DoctrineBundle\Strategy\TimestampStrategy\TimestampStrategyInterface;
use Symfony\Component\HttpKernel\KernelEvents;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->set('cache.ruvents_doctrine_bundle')
        ->parent('cache.system')
        ->private()
        ->tag('cache.pool');

    $services = $container->services()
        ->defaults()
        ->private();

    $services->set(MetadataFactory::class)
        ->args([
            '$annotationReader' => ref('annotation_reader'),
        ]);

    $services->set(LazyLoadingMetadataFactory::class)
        ->args([
            '$factory' => ref(MetadataFactory::class),
            '$cache' => ref('cache.ruvents_doctrine_bundle'),
        ]);

    $services->alias(MetadataFactoryInterface::class, LazyLoadingMetadataFactory::class);

    $services->set(SecurityTokenAuthorStrategy::class)
        ->args([
            '$tokenStorage' => ref('security.token_storage'),
        ]);

    $services->alias(AuthorStrategyInterface::class, SecurityTokenAuthorStrategy::class);

    $services->set(FieldTypeTimestampStrategy::class);

    $services->alias(TimestampStrategyInterface::class, FieldTypeTimestampStrategy::class);

    $services->set(AuthorListener::class)
        ->args([
            '$factory' => ref(MetadataFactoryInterface::class),
            '$strategy' => ref(AuthorStrategyInterface::class),
        ])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::prePersist, 'lazy' => true]);

    $services->set(PersistTimestampListener::class)
        ->args([
            '$factory' => ref(MetadataFactoryInterface::class),
            '$strategy' => ref(TimestampStrategyInterface::class),
        ])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::prePersist, 'lazy' => true]);

    $services->set(SearchIndexListener::class)
        ->args([
            '$factory' => ref(MetadataFactoryInterface::class),
            '$accessor' => ref('property_accessor')->nullOnInvalid(),
        ])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::loadClassMetadata, 'lazy' => true])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::prePersist, 'lazy' => true])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::preUpdate, 'lazy' => true]);

    $services->set(TranslatableListener::class)
        ->args([
            '$factory' => ref(MetadataFactoryInterface::class),
            '$requestStack' => ref('request_stack'),
        ])
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::prePersist, 'lazy' => true])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::postLoad, 'lazy' => true]);

    $services->set(UpdateTimestampListener::class)
        ->args([
            '$factory' => ref(MetadataFactoryInterface::class),
            '$strategy' => ref(TimestampStrategyInterface::class),
        ])
        ->tag('doctrine.event_listener', ['event' => ORMEvents::preUpdate, 'lazy' => true]);

    $services->set(SearchIndexUpdateCommand::class)
        ->args([
            '$metadataFactory' => ref(MetadataFactoryInterface::class),
            '$doctrine' => ref('doctrine'),
        ])
        ->tag('console.command');
};
