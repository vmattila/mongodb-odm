<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

class GH523Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{

	public function tearDown() {
		// Persist collectionn.....
	}

    public function testEmbeddedDocumentWithoutValuesIsNotSavedToDb()
    {
        $doc = new GH523Document();
        $emptyEmbedded = new GH523EmbeddedDocumentLevel1();
        $doc->mainEmbedded = $emptyEmbedded;

        $this->dm->persist($doc);
        $this->dm->flush();
		$mainId = $doc->_id;
        $this->dm->clear();

		$mongoCollection = $this->dm->getDocumentCollection(get_class($doc));
        $firstDocument = $mongoCollection->findOne(array('_id' => new \MongoId($mainId)));
        $this->assertArrayHasKey('mainEmbedded', $firstDocument);
        $this->assertArrayNotHasKey('level1StringField', $firstDocument['mainEmbedded']);
    }
	
	public function testEmbeddedNullableDocumentCanSaveValueLater()
    {
        $doc = new GH523Document();
        $emptyEmbedded = new GH523EmbeddedDocumentLevel1();
        $doc->mainEmbedded = $emptyEmbedded;

        $this->dm->persist($doc);
        $this->dm->flush();
		$mainId = $doc->_id;
        $this->dm->clear();

        $refetchedDoc = $this->dm->getRepository(get_class($doc))->find($mainId);
        $refetchedDoc->getMainEmbedded()->setLevel1StringField('new value');
        $this->dm->flush($refetchedDoc);

		// Checking values in the DB
		$mongoCollection = $this->dm->getDocumentCollection(get_class($doc));
        $firstDocument = $mongoCollection->findOne(array('_id' => new \MongoId($mainId)));
        $this->assertArrayHasKey('mainEmbedded', $firstDocument);
        $this->assertArrayHasKey('level1StringField', $firstDocument['mainEmbedded']);
		$this->assertEquals('new value', $firstDocument['mainEmbedded']['level1StringField']);
    }
	
	public function testTwoLevelEmbeddedDocumentCanSaveValueLater()
    {
        $doc = new GH523Document();
        $emptyEmbedded = new GH523EmbeddedDocumentLevel1();
        $doc->mainEmbedded = $emptyEmbedded;
		$emptyEmbedded->secondEmbedded = new GH523EmbeddedDocumentLevel2();
		
        $this->dm->persist($doc);
        $this->dm->flush();
		$mainId = $doc->_id;
        $this->dm->clear();

        $refetchedDoc = $this->dm->getRepository(get_class($doc))->find($mainId);
        $refetchedDoc->getMainEmbedded()->getSecondEmbedded()->setLevel2StringField('level 2');
        $this->dm->flush($refetchedDoc);

		// Checking values in the DB
		$mongoCollection = $this->dm->getDocumentCollection(get_class($doc));
        $firstDocument = $mongoCollection->findOne(array('_id' => new \MongoId($mainId)));
        $this->assertArrayHasKey('mainEmbedded', $firstDocument);
		$this->assertArrayHasKey('secondEmbedded', $firstDocument['mainEmbedded']);
		$this->assertArrayHasKey('level2StringField', $firstDocument['mainEmbedded']['secondEmbedded']);
		$this->assertEquals('level 2', $firstDocument['mainEmbedded']['secondEmbedded']['level2StringField']);
    }
}

/** @ODM\Document */
class GH523Document
{
    /** @ODM\Id */
    public $_id;

	/**
	 * @return GH523EmbeddedDocumentLevel1
	 */
    public function getMainEmbedded() {
        return $this->mainEmbedded;
    }

    /** @ODM\EmbedOne(targetDocument="GH523EmbeddedDocumentLevel1", strategy="set") */
    public $mainEmbedded;
}

/** @ODM\EmbeddedDocument */
class GH523EmbeddedDocumentLevel1
{
    /** @ODM\String */
    public $level1StringField;
	
	public function setLevel1StringField($newValue) {
		$this->level1StringField = $newValue;
	}
	
	/** @ODM\EmbedOne(targetDocument="GH523EmbeddedDocumentLevel2", strategy="set") */
    public $secondEmbedded;
	
	/**
	 * @return GH523EmbeddedDocumentLevel2
	 */
    public function getSecondEmbedded() {
        return $this->secondEmbedded;
    }
}

/** @ODM\EmbeddedDocument */
class GH523EmbeddedDocumentLevel2
{
    /** @ODM\String */
    public $level2StringField;
	
	public function setLevel2StringField($newValue) {
		$this->level2StringField = $newValue;
	}
}
