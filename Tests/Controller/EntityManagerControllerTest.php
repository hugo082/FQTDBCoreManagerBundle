<?php

namespace FQT\DBCoreManagerBundle\Tests\Controller;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use FQT\DBCoreManagerBundle\Checker\EntityManager as Checker;
use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;
use DB\ManagerBundle\DependencyInjection\Configuration as Conf;

class EntityManagerControllerTest extends WebTestCase
{
    const NotFoundException = -1;
    const NotAllowedException = -2;

    const RUSER = 'ROLE_USER';
    const RADMIN = 'ROLE_ADMIN';
    const RSUPADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @var Checker
     */
    private $checker = null;
    private $client = null;

    /**
     * {@inheritDoc}
     */
    protected function setUp() {
        self::bootKernel(array('environment' => 'test'));
        $this->client = static::createClient();
    }

    /*
    public function testEditAction() {
        $this->logIn('user', array(self::RUSER));
        $e = $this->entityObjectGetterCatch('Flight',Conf::PERM_EDIT);
        $this->assertTrue(is_object($e));

        $crawler = $client->request('GET', '/post/hello-world');
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Hello World")')->count()
        );
    }
    */

    public function testListEntities() {
        $this->logIn('user', array(self::RUSER));
        $entities = $this->checker->getEntities();
        $this->assertCount(1, $entities);
        $this->logIn('user', array(self::RSUPADMIN));
        $entities = $this->checker->getEntities();
        $this->assertCount(2, $entities);
    }

    public function testEntityAccess() {
        $this->logIn('user', array(self::RUSER));
        $e = $this->entityGetterCatch('NonExistEntity',Conf::PERM_ADD);
        $this->assertTrue($e == self::NotFoundException);
        $e = $this->entityGetterCatch('Flight',Conf::PERM_REMOVE);
        $this->assertTrue($e == self::NotAllowedException);
        $e = $this->entityGetterCatch('Flight',Conf::PERM_ADD);
        $this->assertTrue(is_array($e));
        $e = $this->entityGetterCatch('Bill',Conf::PERM_ADD);
        $this->assertTrue($e == self::NotAllowedException);
        $this->logIn('user', array(self::RUSER, self::RADMIN, self::RSUPADMIN));
        $e = $this->entityGetterCatch('Bill',Conf::PERM_ADD);
        $this->assertTrue(is_array($e));
    }

    public function testEntityParser() {
        $this->logIn('user', array(self::RUSER));
        $e = $this->entityGetterCatch('Flight',Conf::PERM_ADD);
        $this->assertTrue($e["fullName"] == $e["bundle"] . ":" . $e["name"]);
        $this->assertTrue($e["listingMethod"] == "getConstraints");
    }

    /**
     * Catch exception during getEntity
     * @param string $name
     * @param string $action
     * @return int|array
     */
    private function entityGetterCatch(string $name, string $action) {
        try {
            return $this->checker->getEntity($name,$action);
        } catch (NotFoundException $e) {
            return self::NotFoundException;
        } catch (NotAllowedException $e) {
            return self::NotAllowedException;
        }
    }

    /**
     * Catch exception during getEntityObject
     * @param string $name
     * @param string $action
     * @return array|int|null|object
     */
    private function entityObjectGetterCatch(string $name, string $action) {
        try {
            $ent = $this->checker->getEntity($name,$action);
            return $this->checker->getEntityObject($ent);
        } catch (NotFoundException $e) {
            return self::NotFoundException;
        } catch (NotAllowedException $e) {
            return self::NotAllowedException;
        }
    }

    private function logIn(string $username, array $roles) {
        $session = $this->client->getContainer()->get('session');

        // the firewall context defaults to the firewall name
        $firewallContext = 'main';

        $token = new UsernamePasswordToken($username, null, $firewallContext, $roles);
        static::$kernel->getContainer()->get('security.token_storage')->setToken($token);
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);

        $this->updateChecker();
    }

    private function updateChecker() {
        $this->checker = static::$kernel->getContainer()->get('fqt.dbcm.checker');
    }
}

?>
