<?php

namespace FQT\DBCoreManagerBundle\DependencyInjection;

use Symfony\Component\CssSelector\Exception\InternalErrorException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;
use FQT\DBCoreManagerBundle\Core\Action;
use FQT\DBCoreManagerBundle\Core\Access;
use FQT\DBCoreManagerBundle\Core\EntityInfo;

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

        $this->loadMethods($config, $container);
        $this->loadEntities($config, $container);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    private function loadMethods(array &$config, ContainerBuilder $container)
    {
        foreach ($config['methods']['content'] as $name => $values) {
            $values["isDefault"] = false;
            $values['id'] = $name;
            $values['access'] = null;
            if (in_array($name, Conf::DEFAULT_METHODS))
                throw new \Exception("Impossible to custom method '".$name."'. This is a default method.");

            if (!isset($values['service']))
                $values['service'] = $config['methods']['service'];

            if (!isset($values['fullName']))
                $values['fullName'] = $name;

            $config['methods']['content'][$name] = $values;
        }
        $container->setParameter($this->getAlias().'.methods', $config['methods']);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    private function loadEntities(array &$config, ContainerBuilder $container)
    {
        foreach ($config['entities'] as $name => &$values) {
            $arr = explode(":", $values['fullName'], 2);
            $values['bundle'] = $arr[0];
            $values['name'] = $arr[1];
            $values['id'] = $name;

            if (!isset($values['fullPath']))
                $values['fullPath'] = $values['bundle']."\\Entity\\".$values['name'];

            if (!isset($values['formType']))
                $values['formType'] = $values['name'] . "Type";

            if (!isset($values['fullFormType']))
                $values['fullFormType'] = $values['bundle'] . "\\Form\\" . $values['formType'];

            $this->checkArrayContentOfKey($values, "access");


            // == TODO: Final definition
            if (isset($values['methods'])) { // TODO: Methods not optionnal
                $this->loadEntityMethods($values, $config['methods']);
            }

            if ($this->checkArrayContentOfKey($values, "access_details"))
                $this->processAccessDetails($values);
        }

        $container->setParameter($this->getAlias().'.entities', $config['entities']);
    }

    private function loadEntityMethods(array &$values, array $configMethods){
        $methods = array();
        foreach ($values['methods'] as $method) {
            if (array_key_exists($method, $configMethods['content']))
                $methods[$method] = $configMethods['content'][$method];
            elseif (in_array($method, Conf::DEFAULT_METHODS))
                $methods[$method] = Conf::getDefaultMethodInformation($method);
            else
                throw new \Exception("Method '" . $method . "' doesn't exist in ".$values['fullPath'].".");
            $values['permissions'][$method] = true; // TODO: Custom method permissions
        }
        $values['methods'] = $methods;
    }

    private function processAccessDetails(array &$values){
        foreach ($values['access_details'] as &$action) {
            $this->checkArrayContentOfKey($action, "check");
            $this->checkArrayContentOfKey($action, "roles");
            $methodID = $action['method'];
            if (key_exists($methodID, $values['methods']))
                $values['methods'][$methodID]['access'] = $action;
            else
                throw new \Exception("Impossible to details access of undefined method : " . $methodID . " in " . $values['fullPath'] . ".");
        }
        unset($values['access_details']);
    }

    private function checkArrayContentOfKey(array &$array, $key) {
        if (key_exists($key, $array) && !empty($array[$key]))
            return true;
        $array[$key] = null;
        return false;
    }
}
