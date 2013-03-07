<?php

namespace Documents;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 */
class EmbeddingCmsUser
{
    /**
     * @ODM\Id
     */
    public $id;
	
	/**
     * @ODM\String
     */
    public $name;

    /**
     * @ODM\EmbedMany(targetDocument="EmbeddedCmsGroup")
     */
    public $groups;
    
    public function __construct() {
        $this->groups = new ArrayCollection;
    }

    public function getId() {
        return $this->id;
    }

    public function addGroup(EmbeddedCmsGroup $group) {
        $this->groups[] = $group;
    }

    public function getGroups() {
        return $this->groups;
    }
}
