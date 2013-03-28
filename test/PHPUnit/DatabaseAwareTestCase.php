<?php

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\Common\Tester\DataFixture\ReferenceRepositorySerializer;

use Doctrine\Common\Tester\Tester;

class DatabaseAwareTestCase extends ContainerAwareTestCase {
	/**
	 * @var \Doctrine\Common\Tester\Tester
	 */
	protected static $dbTester;

	protected static $unserializedReferenceRepository;

	protected function setUp() {
		parent::setUp();

		static::$dbTester = new Tester();
		//here youshould register passes
		static::$dbTester->useEm(static::$container->get('doctrine.orm.entity_manager'));

		if (null == static::$unserializedReferenceRepository) {
			static::$unserializedReferenceRepository = unserialize(file_get_contents(
				static::$container->getParameter('kernel.cache_dir') . '/commonReferenceRepository'
			));
		}

		static::$dbTester->setReferenceRepositoryData(static::$unserializedReferenceRepository);

		$this->startTransaction();
	}

	protected function tearDown() {
		$this->rollbackTransaction();

		parent::tearDown();
	}

	/**
	 * @param $name Fixture name
	 *
	 * @return Object
	 */
	protected function getFixtureReference($name) {
		return static::$dbTester->get($name);
	}

	protected function startTransaction() {
		/**
		 * @var $em \Doctrine\ORM\EntityManager
		 */
		$em = static::$container->get('doctrine.orm.entity_manager');
		$em->clear();
		$em->getConnection()->beginTransaction();
	}

	protected function rollbackTransaction() {
		$em = static::$container->get('doctrine.orm.entity_manager');

		$connection = $em->getConnection();

		while ($connection->isTransactionActive()) {
			$connection->rollback();
		}
	}

}
