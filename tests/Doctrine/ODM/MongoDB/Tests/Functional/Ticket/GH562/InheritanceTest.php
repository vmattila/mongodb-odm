<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH562;

class InheritanceTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testQueryingTheAbstractParentReturnsInheritedClass()
    {
        $simple = new SimpleProduct('simple product', 5);

        // persist & flush
        $this->dm->persist($simple);
        $this->dm->flush();
        $this->dm->clear();

        $productInDb = $this->dm->getRepository(__NAMESPACE__ . '\SimpleProduct')->findOneByName('simple product');
        $this->assertInstanceOf(__NAMESPACE__ . '\SimpleProduct', $productInDb);

        $this->assertEquals('simple product', $productInDb->name); // Asserting that properties in inherited class work
        $this->assertEquals(5, $productInDb->getPrice()); // Checking that function calls to inherited class work
    }
}
