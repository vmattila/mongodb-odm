<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

use Doctrine\Common\Collections\ArrayCollection;

class EmbeddedInheritedTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testEmbeddedInheritedDocument()
    {
        /* PARENT DOCUMENT */
        $supplier = new Supplier('some company');
        /* END PARENT DOCUMENT */

        /* ADD EMBEDDED DOCUMENT */
        $simple = new SimpleProduct('simple product');
        $supplier->mainProduct = $simple;
        /* END ADD EMBEDDED DOCUMENT */

        // persist & flush
        $this->dm->persist($supplier);
        $this->dm->flush();
        $this->dm->clear();

        $offer = $this->dm->getRepository(__NAMESPACE__ . '\Supplier')->findOneByName('some company');

        // Should be: 1 Link, 5 referenced documents
        // Actual Result: 1 link, 10 referenced documents
        $this->assertEquals(1, $offer->links->count());
        $this->assertEquals(5, $offer->links[0]->referencedDocuments->count());
    }
}

/**
 * @ODM\MappedSuperclass
 */
abstract class Document
{
    /** @ODM\Id */
    protected $id;
}

/** @ODM\Document */
class Supplier extends Document
{
	/** @ODM\String */
    public $name;
	
    /**
	 * @ODM\ReferenceOne(targetDocument="Product", simple="true", cascade={"all"})
	 */
    public $mainProduct;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

/**
 * @ODM\Document
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 * @ODM\DiscriminatorField(fieldName="type")
 * @ODM\DiscriminatorMap({"simple"="SimpleProduct", "advanced"="AdvancedProduct"})
 */
abstract class Product extends Document
{
    
}

/**
 * @ODM\Document
 */
class SimpleProduct extends Product
{
    /** @ODM\String */
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

/**
 * @ODM\Document
 */
class AdvancedProduct extends Product
{
    /** @ODM\Id */
    protected $id;

    /** @ODM\String */
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}
