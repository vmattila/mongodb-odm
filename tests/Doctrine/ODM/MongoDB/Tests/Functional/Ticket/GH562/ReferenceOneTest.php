<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH562;

class ReferenceOneTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testReferenceToInheritedDocument()
    {
        $supplier = new Supplier('some company');
        $simple = new SimpleProduct('simple product', 5);
        $supplier->setMainProduct($simple);

        // persist & flush
        $this->dm->persist($supplier);
        $this->dm->flush();
        $this->dm->clear();

        $supplier = $this->dm->getRepository(__NAMESPACE__ . '\Supplier')->findOneByName('some company');
        $this->assertInstanceOf(__NAMESPACE__ . '\Supplier', $supplier);

        $mainProduct = $supplier->getMainProduct();
        $this->assertInstanceOf(__NAMESPACE__ . '\SimpleProduct', $mainProduct);
        $this->assertEquals('simple product', $mainProduct->name); // Asserting that properties in inherited class work
        $this->assertEquals(5, $mainProduct->getPrice()); // Checking that function calls to inherited class work
    }

}
