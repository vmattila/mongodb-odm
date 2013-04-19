<?php
namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH562;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 * @ODM\DiscriminatorField(fieldName="type")
 * @ODM\DiscriminatorMap({"simple"="SimpleProduct"})
 */
class Product extends Document
{
    
}

/**
 * @ODM\Document
 */
class SimpleProduct extends Product
{
    /** @ODM\String */
    public $name;
    
    /** @ODM\Float */
    public $price;

    public function getPrice()
    {
        return $this->price;
    }
    
    public function __construct($name, $price)
    {
        $this->name = $name;
        $this->price = $price;
    }
}