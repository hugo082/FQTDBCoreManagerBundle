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


/**
 * @Annotation
 */
class Viewable
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

    private $value;

    public function __construct(array $container)
    {
        $this->title = $this->getKeySecure("title", $container);
        $this->index = $this->getKeySecure("index", $container);
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
        $this->value = $value;
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