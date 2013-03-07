<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

class PersistentCollectionCloneTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{

	private $mainDocumentId;
	private $mainRepo;

	public function setUp() {
		parent::setUp();
		
		$this->dm->getConnection()->PersistentCollectionCloneTest->testing->drop();
		
		$mainDocument = new MainDocument();
		$firstEmbedded = new EmbeddedDocument();
		$firstEmbedded->value = 'first';
		$secondEmbedded = new EmbeddedDocument();
		$secondEmbedded->value = 'second';
		
		$mainDocument->embedMany->add($firstEmbedded);
		$mainDocument->embedMany->add($secondEmbedded);
		
		$this->dm->persist($mainDocument);
		$this->dm->flush();
		
		$this->mainDocumentId = $mainDocument->id;
		
		$this->dm->clear();
		
		$this->mainRepo = $this->dm->getRepository('Doctrine\ODM\MongoDB\Tests\Functional\Ticket\MainDocument');
	}
	
	public function tearDown() {
		$this->dm->getConnection()->PersistentCollectionCloneTest->testing->drop();
	}
	
	public function testMainDocumentIsProperlyPersisted() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		$this->assertSame($mainDocument->id, $this->mainDocumentId);
	}
	
	public function testMainDocumentHasTwoEmbeddedDocuments() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		$this->assertEquals($mainDocument->embedMany->count(), 2);
	}
	
	private $newDocumentId;
	private function doSimpleCloneAndFlush($mainDocument) {
		$newDocument = clone $mainDocument;
		$newDocument->id = null;
		$this->dm->persist($newDocument);
		$this->dm->flush();
		
		$this->newDocumentId = $newDocument->id;
	}
	
	public function testCollectionHasTwoDocumentsAfterNewDocumentIsPersisted() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		
		$this->doSimpleCloneAndFlush($mainDocument);
		
		$collectionCount = $this->dm->getConnection()->PersistentCollectionCloneTest->testing->count();
		$this->assertEquals($collectionCount, 2);
	}
	
	public function testNewDocumentIsPersistedWithNewId() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		
		$this->doSimpleCloneAndFlush($mainDocument);
		$this->assertNotEquals($this->mainDocumentId, $this->newDocumentId);
	}
	
	public function testEmbeddedDocumentsAreClonedWhenParentDocumentIsCloned() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		
		$this->doSimpleCloneAndFlush($mainDocument);
		
		$newDocument = $this->mainRepo->findOneById($this->newDocumentId);
		
		$this->assertEquals($newDocument->embedMany->count(), 2);
	}
	
	public function testClonedCollectionHasSameAmountOfDocumentsThanOriginal() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		$this->doSimpleCloneAndFlush($mainDocument);
		$newDocument = $this->mainRepo->findOneById($this->newDocumentId);
		
		$embeddedDocumentCountInMain = $mainDocument->embedMany->count();
		$this->assertEquals($embeddedDocumentCountInMain, 2);
		
		$newEmbeddedCollection = clone $mainDocument->embedMany;
		
		$this->assertEquals($newEmbeddedCollection->count(), $embeddedDocumentCountInMain);
	}
	
	public function testOriginalDocumentHasSameAmountOfEmbeddedDocumentsAfterClone() {
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		$this->doSimpleCloneAndFlush($mainDocument);
		$newDocument = $this->mainRepo->findOneById($this->newDocumentId);
		$newDocument->embedMany = clone $mainDocument->embedMany;
		$this->dm->flush($newDocument);
		
		$this->dm->clear();
		
		$mainDocument = $this->mainRepo->findOneById($this->mainDocumentId);
		$newDocument = $this->mainRepo->findOneById($this->newDocumentId);
		
		$this->assertEquals($mainDocument->embedMany->count(), 2);
		$this->assertEquals($newDocument->embedMany->count(), 2);
	}

    private function xxxxxtestMainDocumentHasSameId()
    {
		$mainDocument = $this->dm->getRepository('Doctrine\ODM\MongoDB\Tests\Functional\Ticket\MainDocument')->findOneById($mainId);
		$this->assertSame($mainDocument->id, $mainId);
		
		$mainDocument = new MainDocument();
		$firstEmbedded = new EmbeddedDocument();
		$firstEmbedded->value = 'first';
		$secondEmbedded = new EmbeddedDocument();
		$secondEmbedded->value = 'second';
		
		$mainDocument->embedMany->add($firstEmbedded);
		$mainDocument->embedMany->add($secondEmbedded);
		
        $this->dm->persist($mainDocument);
		$this->dm->flush();
		
		$mainId = $mainDocument->id;
		$this->assertNotNull($mainId);
		
		$this->dm->clear();
		
		
		
		$this->assertEquals($mainDocument->embedMany->count(), 2);
		
		$this->dm->clear();
		
		$newDocument = clone $mainDocument;
		$newDocument->id = null;
		$this->dm->persist($newDocument);
		$this->dm->flush();
		
		$newId = $newDocument->id;
		$this->assertNotSame($newDocument->id, $mainId);
		
		$this->dm->clear();
		
		$newDocument = $this->dm->getRepository('Doctrine\ODM\MongoDB\Tests\Functional\Ticket\MainDocument')->findOneById($newId);
		$newMainDocument = $this->dm->getRepository('Doctrine\ODM\MongoDB\Tests\Functional\Ticket\MainDocument')->findOneById($mainId);
		$this->assertNotSame($newDocument->id, $newMainDocument->id);
		
		$this->assertEquals($newDocument->embedMany->count(), 2);
    }
}

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** @ODM\Document(collection="testing",db="PersistentCollectionCloneTest") */
class MainDocument
{
    /** @ODM\Id */
    public $id;
	
	public function __construct() {
		$this->embedMany = new \Doctrine\Common\Collections\ArrayCollection();
	}

    /** @ODM\EmbedMany(targetDocument="Doctrine\ODM\MongoDB\Tests\Functional\Ticket\EmbeddedDocument") */
    public $embedMany;
}

/** @ODM\EmbeddedDocument */
class EmbeddedDocument
{
    /** @ODM\String */
    public $value;
}