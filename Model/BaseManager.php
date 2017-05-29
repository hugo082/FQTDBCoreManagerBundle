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
     * All properties values
     * @return array
     */
    public function getVars(){
        return $this->getProp(true);
    }

    /**
     * All properties name
     * @return array
     */
    public function getProperties(){
        return $this->getProp(false);
    }

    /**
     * List all properties and return values if $execute, name else.
     * @param bool $execute
     * @return array
     */
    private function getProp(bool $execute) {
        $reflect = new \ReflectionClass($this);
        $methods = $reflect->getMethods(\ReflectionProperty::IS_PUBLIC);

        $tmp = array();
        foreach ($methods as $met) {
            $metName = $met->getName();
            if (0 === strpos($metName, 'get') && !in_array($metName, $this->bloquedMethods)) {
                if ($execute) {
                    $tmp[] = $this->getString($this->$metName());
                }
                else
                    $tmp[] = str_replace('get', '', $metName);
            }
        }
        return $tmp;
    }

    /**
     * Get string representation of $item
     * @param $item
     * @return string
     */
    private function getString($item): string {
        if ($item === null)
            return "null";
        if (!is_object($item) && settype($item, 'string') !== false)
            return $item;
        if (is_object($item) && method_exists($item, '__toString'))
            return $item;
        if (is_array($item))
            return count($item);
        if ($item instanceof \DateTime)
            return $item->format("d/m/y H:i");
        return "Unknown";
    }
}

