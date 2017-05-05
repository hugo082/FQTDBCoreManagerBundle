<?php

namespace FQT\DBCoreManagerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    public const PERM_ADD = 'add';
    public const PERM_EDIT = 'edit';
    public const PERM_REMOVE = 'remove';
    public const PERM_LIST = 'list';
    public const PERMISSIONS = array(Configuration::PERM_ADD, Configuration::PERM_EDIT, Configuration::PERM_REMOVE, Configuration::PERM_LIST);

    public const DEF_ADD = 'add';
    public const DEF_EDIT = 'edit';
    public const DEF_REMOVE = 'remove';
    public const DEF_LIST = 'list';
    public const DEFAULT_METHODS = array(Configuration::DEF_ADD, Configuration::DEF_EDIT, Configuration::DEF_REMOVE, Configuration::DEF_LIST);

    public const DISP_ELEM_FORM = 'form';
    public const DISP_ELEM_LIST = 'list';
    public const DISP_ELEM_ADDLINK = 'addLink';
    public const DISP_ELEM_REMOVELINK = 'removeLink';
    public const DISP_ELEM_EDITLINK = 'editLink';

    public const ENV_GLOBAL = 'global';
    public const ENV_OBJECT = 'object';

    static public function getDefaultMethodInformation($name) {
        if ($name == self::DEF_ADD)
            return array("fullName" => "Ajouter", "environment" => self::ENV_GLOBAL);
        elseif ($name == self::DEF_EDIT)
            return array("fullName" => "Editer", "environment" => self::ENV_OBJECT);
        elseif ($name == self::DEF_REMOVE)
            return array("fullName" => "Supprimer", "environment" => self::ENV_OBJECT);
        elseif ($name == self::DEF_LIST)
            return array("fullName" => "Lister", "environment" => self::ENV_GLOBAL);
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('fqtdb_core_manager');

        $this->addEntitiesSection($rootNode);
        $this->addMethodsSection($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    private function addMethodsSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('methods')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('service')->isRequired()->cannotBeEmpty()->end()
                        ->arrayNode('content')
                            ->prototype('array')->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('fullName')->end()
                                    ->scalarNode('service')->end()
                                    ->scalarNode('method')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('view')->end()
                                    ->enumNode('environment')
                                        ->isRequired()->cannotBeEmpty()
                                        ->values(array(self::ENV_GLOBAL, self::ENV_OBJECT))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    private function addEntitiesSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('entities')
                    ->prototype('array')->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('fullName')->isRequired()->cannotBeEmpty()
                                ->validate()
                                ->ifTrue(function ($s) {
                                    return preg_match('#^[a-zA-Z]+Bundle:[a-zA-Z]+$#', $s) !== 1;
                                })
                                ->thenInvalid('Invalid fullName. (Ex: AppBundle:RealName)')
                                ->end()
                            ->end()
                            ->scalarNode('fullPath')->end()     // Auto
                            ->scalarNode('formType')->end()     // Auto
                            ->scalarNode('fullFormType')->end() // Auto
                            ->scalarNode('listView')->defaultValue('DBManagerBundle:Manage:list.html.twig')->end() // Auto
                            ->scalarNode('formView')->defaultValue('DBManagerBundle:Manage:form.html.twig')->end() // Auto
                            ->scalarNode('mainView')->defaultValue('DBManagerBundle:Manage:entity.html.twig')->end() // Auto
                            ->scalarNode('listingMethod')->defaultNull()->end() // Auto

                            ->arrayNode('access')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) { return array($v); })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()

                            ->arrayNode('methods')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) { return array($v); })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()

                            ->arrayNode('access_details')
                                ->children()
                                    ->scalarNode(Configuration::PERM_ADD.'Method')->defaultNull()->end() // Auto
                                    ->arrayNode(Configuration::PERM_ADD)->isRequired()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode(Configuration::PERM_EDIT.'Method')->defaultNull()->end() // Auto
                                    ->arrayNode(Configuration::PERM_EDIT)->isRequired()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode(Configuration::PERM_REMOVE.'Method')->defaultNull()->end() // Auto
                                    ->arrayNode(Configuration::PERM_REMOVE)->isRequired()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode(Configuration::PERM_LIST.'Method')->defaultNull()->end() // Auto
                                    ->arrayNode(Configuration::PERM_LIST)->isRequired()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function ($v) { return array($v); })
                                        ->end()
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
