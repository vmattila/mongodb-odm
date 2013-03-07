<?php

namespace Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\EmbeddedDocument
 */
class EmbeddedCmsGroup
{
    /**
     * @ODM\Id
     */
    public $id;
    /**
     * @ODM\String
     */
    public $name;

}