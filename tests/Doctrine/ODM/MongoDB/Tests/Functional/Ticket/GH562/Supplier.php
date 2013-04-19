<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH562;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** @ODM\Document */
class Supplier extends Document
{
    
    /** @ODM\String */
    public $name;
    
    /**
     * @ODM\ReferenceOne(targetDocument="Product", simple="true", cascade={"all"})
     */
    protected $mainProduct;
    
    public function getMainProduct() {
        return $this->mainProduct;
    }
    
    public function setMainProduct($product) {
        $this->mainProduct = $product;
    }

    public function __construct($name)
    {
        $this->name = $name;
    }
}