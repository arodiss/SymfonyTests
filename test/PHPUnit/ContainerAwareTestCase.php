<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ContainerAwareTestCase extends WebTestCase {
	/**
	 * @var \Symfony\Bundle\FrameworkBundle\Client
	 */
	protected static $client;

	/**
	 * @var \Symfony\Component\DependencyInjection\ContainerInterface
	 */
	protected static $container;

	protected function setUp() {
		static::$client = parent::createClient();
		static::$container = static::$kernel->getContainer();
	}

	protected function tearDown() {
		parent::tearDown();

		foreach ($this->getTearDownProperties() as $prop) {
			$prop->setValue($this, null);
		}
	}

	static protected function getKernelClass() {
		require_once __DIR__ . '/../../../../../app/AppKernel.php';

		return 'AppKernel';
	}

	static protected function createKernel(array $options = array()) {
		if (null === static::$class) {
			static::$class = static::getKernelClass();
		}

		return new static::$class(
			isset($options['environment']) ? $options['environment'] : 'test',
			isset($options['debug']) ? $options['debug'] : true,
			isset($options['catch']) ? $options['catch'] : false
		);
	}

	/**
	 * Returns an array of ReflectionProperty objects for tear down.
	 */
	private function getTearDownProperties() {
		static $cache = array();

		$class = get_class($this);
		if (!isset($cache[$class])) {
			$cache[$class] = array();
			$refl = new \ReflectionClass($class);
			$filter = \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE;
			foreach ($refl->getProperties($filter) as $prop) {
				if ($prop->isStatic()) {
					continue;
				}
				if (0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
					$prop->setAccessible(true);
					$cache[$class][] = $prop;
				}
			}
		}

		return $cache[$class];
	}
}
