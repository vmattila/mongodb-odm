<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

class AlsoLoadTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testUnsetMainFieldGetsAlsoLoadValue()
    {
        $a = array(
            'also_load_value_in_document' => 'also_load_value_in_document'
        );
        $this->dm->getConnection()->AlsoLoadTestDocument_test->collection->insert($a);

        $in_db = $this->dm->find(__NAMESPACE__.'\AlsoLoadTestDocument', $a['_id']);
        $this->assertEquals('also_load_value_in_document', $in_db->main_field);
    }
    
    public function testAlreadySetMainFieldDoesNotGetOverridden()
    {
        $a = array(
            'main_field' => 'main_field',
            'also_load_value_in_document' => 'also_load_value_in_document'
        );
        $this->dm->getConnection()->AlsoLoadTestDocument_test->collection->insert($a);

        $in_db = $this->dm->find(__NAMESPACE__.'\AlsoLoadTestDocument', $a['_id']);
        $this->assertEquals('main_field', $in_db->main_field);
    }
}

/** @ODM\Document(db="AlsoLoadTestDocument_test", collection="collection") */
class AlsoLoadTestDocument
{
    /** @ODM\Id */
    public $id;

    /** @ODM\String
     * @ODM\AlsoLoad("also_load_value_in_document")
     */
    public $main_field;
    
    function getId() {return $this->id;}
}