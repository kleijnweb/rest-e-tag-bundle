<?php declare(strict_types = 1);
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class KleijnWebRestETagExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $container->setParameter('rest_e_tags.concurrency_control', $config['concurrency_control']);
        $container->setParameter('rest_e_tags.child_invalidation_constraint', $config['child_invalidation_constraint']);
        $container->setAlias('rest_e_tags.cache', $config['cache']);
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return "rest_e_tags";
    }
}
