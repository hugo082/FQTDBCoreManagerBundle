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

namespace FQT\DBCoreManagerBundle\Annotations;

use FQT\DBCoreManagerBundle\Core\Model\iEncodable;

/**
 * @Annotation
 */
class Viewable implements iEncodable
{
    /**
     * @var int
     */
    private $index;
    /**
     * @var string
     */
    private $method_name;
    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $value;

    public function __construct(array $container)
    {
        $this->title = $this->getKeySecure("title", $container);
        $this->index = $this->getKeySecure("index", $container);
    }

    public function encode(): array
    {
        return array(
            "index" => $this->index,
            "title" => $this->title,
            "value" => $this->value
        );
    }

    /**
     * @return string
     */
    public function getMethodName(): string
    {
        return $this->method_name;
    }

    /**
     * @param string $method_name
     */
    public function setMethodName(string $method_name)
    {
        $this->method_name = $method_name;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $this->getString($value);
    }

    /**
     * Convert value to string
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

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    public static function fromViewable(Viewable $a) {
        $v = new Viewable(array("title" => $a->title, "index" => $a->index));
        $v->method_name = $a->method_name;
        $v->value = $a->value;
        return $v;
    }

    public function getKeySecure($key, array $array) {
        if (key_exists($key, $array))
            return $array[$key];
        return null;
    }
}