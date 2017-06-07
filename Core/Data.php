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

namespace FQT\DBCoreManagerBundle\Core;

use FQT\DBCoreManagerBundle\Core\Model\iEncodable;
use FQT\DBRestManagerBundle\Manager\RestManager;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

use FQT\DBCoreManagerBundle\Core\Action;

use Symfony\Component\Config\Definition\Exception\Exception;
use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

class Data implements iEncodable
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var null|array
     */
    private $redirection = null;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function encode(string $entityName = ""): array
    {
        return array(
            "redirection" => $this->getRedirection($entityName),
            "flash" => $this->getFlash(),
            "data" => RestManager::Encode($this->data)
        );
    }

    public function getFlash() {
        if (key_exists('flash', $this->data))
            return $this->data["flash"];
        return array();
    }

    public function getRedirection(string $entityName, bool $force = false) {
        if ($this->redirection == null || $force)
            $this->redirection = $this->computeRedirection($entityName);
        return $this->redirection;
    }

    /**
     * Get the redirection information of data $data. null is no redirection.
     * @param string $entityName
     * @return array|mixed|null
     * @throws \Exception
     */
    private function computeRedirection(string $entityName) {
        if (!key_exists("redirect", $this->data))
            return null;
        $red = $this->data["redirect"];
        if (is_array($red) and self::isValidRedirection($red))
            return $red;
        if (is_bool($red)) {
            if ($red)
                return array("route_name" => 'db.manager.list', "data" => array('name' => $entityName));
            return null;
        }
        throw new \Exception("Redirection found it's invalid. 'redirect' must be an array or boolean,  " . gettype($red) . " given.");
    }

    /**
     * Check if array is a valid redirection.
     * @param array $redirection
     * @return bool
     */
    public static function isValidRedirection(array $redirection) {
        if (!key_exists("route_name", $redirection) || !is_string($redirection["route_name"]))
            return false;
        if (!key_exists("data", $redirection) || !is_array($redirection["data"]))
            return false;
        return true;
    }
}