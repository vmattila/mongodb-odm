<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH562;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\MappedSuperclass
 */
abstract class Document
{
    /** @ODM\Id */
    protected $id;
}
