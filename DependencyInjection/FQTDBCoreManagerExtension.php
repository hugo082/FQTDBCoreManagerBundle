<?php

namespace FQT\DBCoreManagerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class FQTDBCoreManagerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        //$this->loadViews($config, $container);
        $this->loadEntities($config, $container, Configuration::PERMISSIONS);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function loadViews(array $config, ContainerBuilder $container)
    {
        $config['views'][Configuration::PERM_EDIT][Configuration::DISP_ELEM_FORM] = true;
        $config['views'][Configuration::PERM_ADD][Configuration::DISP_ELEM_FORM] = true;
        $config['views'][Configuration::PERM_ADD][Configuration::DISP_ELEM_LIST] = false;
        $config['views'][Configuration::PERM_LIST][Configuration::DISP_ELEM_LIST] = true;
        $config['views'][Configuration::PERM_REMOVE][Configuration::DISP_ELEM_FORM] = false;
        $config['views'][Configuration::PERM_REMOVE][Configuration::DISP_ELEM_LIST] = false;

        $container->setParameter($this->getAlias().'.views', $config['views']);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param array            $permissions
     */
    private function loadEntities(array $config, ContainerBuilder $container, array $permissions)
    {
        foreach ($config['entities'] as $name => $values) {
            $arr = explode(":", $values['fullName'], 2);
            $values['bundle'] = $arr[0];
            $values['name'] = $arr[1];

            if (!isset($values['fullPath']))
                $values['fullPath'] = $values['bundle']."\\Entity\\".$values['name'];

            if (!isset($values['formType']))
                $values['formType'] = $values['name'] . "Type";

            if (!isset($values['fullFormType']))
                $values['fullFormType'] = $values['bundle'] . "\\Form\\" . $values['formType'];

            $tmp = array();
            foreach ($permissions as $p)
                $tmp[$p] = in_array($p, $values['permissions']);
            $values['permissions'] = $tmp;

            if (!isset($values['access']) || empty($values['access']))
                $values['access'] = NULL;

            if (!isset($values['access_details']) || empty($values['access_details']))
                $values['access_details'] = NULL;

            $config['entities'][$name] = $values;
        }

        $container->setParameter($this->getAlias().'.entities', $config['entities']);
    }
}
