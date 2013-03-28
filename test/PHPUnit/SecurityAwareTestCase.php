<?php
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\ORM\Proxy\Proxy;

abstract class SecurityAwareTestCase extends DatabaseAwareTestCase
{
    /**
     * @var \Symfony\Component\Security\Core\User\UserInterface
     */
    protected $authenticatedUser = null;

    protected function setUp()
    {
        session_id(hash('md5', uniqid(mt_rand(), true)));
        parent::setUp();
    }

    /**
     * @return string
     */
    abstract public function getFirewallName();

    protected function authenticateAnonymous()
    {
        $token = new AnonymousToken(
            $this->getFirewallName(),
            'anonymous',
            array()
        );

        $this->authenticateToken($token);
    }

    protected function authenticateUser(UserInterface $user, array $roles = array())
    {
        if ($user instanceof Proxy) {
            $user->__load();
            $user = $this->cast($user, get_parent_class($user));
        }

        $token = new UsernamePasswordToken(
            $user,
            $credentials = array(),
            $providerKey = $this->getFirewallName(),
            $roles ? $roles : $user->getRoles()
        );

        $this->authenticateToken($token);
    }

    protected function authenticateAdmin(UserInterface $user, array $roles = array())
    {
        $this->authenticateUser($user, array(
            'ROLE_ADMIN'
        ));
    }

    protected function authenticateSuperAdmin(UserInterface $user, array $roles = array())
    {
        $this->authenticateUser($user, array(
            'ROLE_SUPER_ADMIN'
        ));
    }

    protected function authenticateToken(AbstractToken $token)
    {
        static::$client->getCookieJar()->set(new Cookie('MOCKSESSID', session_id()));

        $container = static::$client->getContainer();
        $dispatcher = $container->get('event_dispatcher');
        $session = $container->get('session');
        $session->setId(session_id());
        $listener = function() use ($dispatcher, $session, $token, &$listener) {
            $dispatcher->removeListener(KernelEvents::REQUEST, $listener);
            $session->set('_security_' . $this->getFirewallName(), serialize($token));
        };
        $dispatcher->addListener(KernelEvents::REQUEST, $listener, 191);

        $this->authenticatedUser = $token->getUser();
    }

    protected function generateCsrfToken($intention = 'unknown')
    {
        $secret = static::$container->getParameter('secret');
        return sha1($secret.$intention.session_id());
    }

    /**
     * Cast $instance to $className
     *
     * @param $instance
     * @param $className
     *
     * @throws \InvalidArgumentException
     *
     * @return object
     */
    protected function cast($instance, $className)
    {
        if (false == is_subclass_of($instance, $className)) {
            throw new \InvalidArgumentException(
                sprintf('Class "%s" should extends "%s" class', get_class($instance), $className)
            );
        }

        $baseClass = new \ReflectionClass($className);
        $baseInstance = $baseClass->newInstanceWithoutConstructor();

        $class = new \ReflectionClass($instance);

        /** @var $property \ReflectionProperty */
        foreach ($baseClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (false == $property->isPublic()) {
                $property->setAccessible(true);
            }

            $property->setValue($baseInstance, $property->getValue($instance));

            if (false == $property->isPublic()) {
                $property->setAccessible(false);
            }
        }

        return $baseInstance;
    }
}
