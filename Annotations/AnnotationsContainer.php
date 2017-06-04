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


class AnnotationsContainer
{
    private $object_id;
    /**
     * @var array
     */
    private $annotations;

    public function __construct($object_id)
    {
        $this->annotations = array();
        $this->object_id = $object_id;
    }

    /**
     * @return array
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @param array $annotations
     */
    public function setAnnotations(array $annotations)
    {
        $this->annotations = $annotations;
    }

    public function pushAnnotation(Viewable $annotation) {
        if ($annotation->getIndex() !== null) {
            $this->annotations[$annotation->getIndex()] = $annotation;
            //array_splice($this->annotations, $annotation->getIndex(), 0, array($annotation));
        }
        else
            $this->annotations[] = $annotation;
    }

    public function sort() {
        asort($this->annotations);
    }

    public function getObjectId()
    {
        return $this->object_id;
    }
}