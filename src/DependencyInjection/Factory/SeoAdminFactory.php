<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2016 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\SonataAdminIntegrationBundle\DependencyInjection\Factory;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class SeoAdminFactory implements AdminFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'seo';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeBuilder $builder)
    {
        $builder->scalarNode('form_group')->defaultValue('form.group_seo')->end();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('seo.xml');

        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['BurgovKeyValueFormBundle'])) {
            throw new InvalidConfigurationException(
                'To use advanced menu options, you need the burgov/key-value-form-bundle in your project.'
            );
        }

        $container->setParameter('cmf_sonata_admin_integration.seo.form_group', $config['form_group']);
    }
}