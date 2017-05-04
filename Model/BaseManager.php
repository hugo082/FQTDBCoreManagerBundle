<?php

/*
 * This file is part of the FQTDBCoreManagerBundle package.
 *
 * (c) FOUQUET <https://github.com/hugo082/DBManagerBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Hugo Fouquet <hugo.fouquet@epita.fr>
 */

namespace FQT\DBCoreManagerBundle\Model;

use FQT\DBCoreManagerBundle\Model\ListingEntityInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Storage agnostic manager full compatible object.
 *
 * @author Hugo Fouquet <hugo.fouquet@epita.fr>
 */
abstract class BaseManager implements ListingEntityInterface
{
    private $bloquedMethods = array('getVars', 'getProperties');

    /**
     * @return All properties values
     */
    public function getVars(){
        return $this->getProp(true);
    }

    /**
     * @return All properties names
     */
    public function getProperties(){
        return $this->getProp(false);
    }

    private function getProp(bool $execute) {
        $reflect = new \ReflectionClass($this);
        $methods = $reflect->getMethods(\ReflectionProperty::IS_PUBLIC);

        $tmp = array();
        foreach ($methods as $met) {
            $metName = $met->getName();
            if (0 === strpos($metName, 'get') && !in_array($metName, $this->bloquedMethods)) {
                if ($execute) {
                    $item = $this->$metName();
                    if (!is_array($item) && (!is_object($item) && settype($item, 'string') !== false ) || is_object($item) && method_exists($item, '__toString'))
                        $tmp[] = $item;
                    else
                        $tmp[] = "Unknown";
                }
                else
                    $tmp[] = str_replace('get', '', $metName);
            }
        }
        return $tmp;
    }
}

