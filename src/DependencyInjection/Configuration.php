<?php declare(strict_types = 1);
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('rest_e_tags');

        $rootNode
            ->children()
            ->booleanNode('concurrency_control')->defaultFalse()->end()
            ->scalarNode('child_invalidation_constraint')->defaultValue('\/[0-9]+$')->end()
            ->scalarNode('cache')->isRequired()->defaultFalse()->end()
        ;

        return $treeBuilder;
    }
}
