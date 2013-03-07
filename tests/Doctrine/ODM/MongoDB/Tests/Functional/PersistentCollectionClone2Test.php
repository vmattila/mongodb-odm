<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Documents\EmbeddingCmsUser;
use Documents\EmbeddedCmsGroup;

class PersistentCollectionClone2Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    private $user1;

    public function setUp()
    {
        parent::setUp();

        $user1 = new EmbeddingCmsUser();
        $user1->name = "Benjamin";
        $group1 = new EmbeddedCmsGroup();
        $group1->name = "test";
        $group2 = new EmbeddedCmsGroup();
        $group2->name = "test";
        $user1->addGroup($group1);
        $user1->addGroup($group2);

        $this->dm->persist($user1);
        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->flush();
        $this->dm->clear();

        $this->user1 = $this->dm->find(get_class($user1), $user1->id);
    }

    public function testClonedObjectHasDifferentId()
    {
        $user2 = clone $this->user1;
        $user2->id = null;
        $this->dm->persist($user2);
        $this->dm->flush();
        $this->dm->clear();

        $this->assertNotEquals($this->user1->id, $user2->id);

    }

    public function testCloningAndAttachingPersistedCollectionToNewObjectWorks()
    {
        $user2 = clone $this->user1;
        $user2->id = null;
        $this->dm->persist($user2);
        $this->dm->flush();
        $this->dm->clear();

        $user1 = $this->dm->find(get_class($this->user1), $this->user1->id);
        $user2 = $this->dm->find(get_class($user2), $user2->id);

        $user2->groups = clone $user1->groups;;

		$this->dm->persist($user1);
		$this->dm->persist($user2);
        $this->dm->flush();
        $this->dm->clear();

		$user1 = $this->dm->find(get_class($this->user1), $this->user1->id);
        $user2 = $this->dm->find(get_class($user2), $user2->id);

        $this->assertEquals(2, count($user1->groups));
        $this->assertEquals(2, count($user2->groups));
    }
}