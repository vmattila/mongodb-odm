<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

require_once __DIR__ . '/../../../../../../TestInit.php';

use Doctrine\Common\Collections\ArrayCollection;

class MODM70Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{

    public function testTest()
    {
        $avatar = new Avatar('Test', 1, array(new AvatarPart('#000')));

        $this->dm->persist($avatar);
        $this->dm->flush();
        $this->dm->refresh($avatar);

        $avatar->addAvatarPart(new AvatarPart('#FFF'));

        $this->dm->flush();
        $this->dm->refresh($avatar);

        $parts = $avatar->getAvatarParts();
        $this->assertEquals(2, count($parts));
        $this->assertEquals('#FFF', $parts[1]->getColor());
    }

}

/**
 * @Document(db="tests", collection="avatars")
 */
class Avatar
{

    /**
     * @Id
     */
    protected $_id;
    /**
     * @String(name="na")
     * @var string
     */
    protected $_name;
    /**
     * @int(name="sex")
     * @var int
     */
    protected $_sex;
    /**
     * @EmbedMany(
     * 	targetDocument="AvatarPart",
     * 	name="aP"
     * )
     * @var array AvatarPart
     */
    protected $_avatarParts;

    public function __construct($name, $sex, $avatarParts = null)
    {
        $this->_name = $name;
        $this->_sex = $sex;
        $this->_avatarParts = $avatarParts;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function setName($name)
    {
        $this->_name = $name;
    }

    public function getSex()
    {
        return $this->_sex;
    }

    public function setSex($sex)
    {
        $this->_sex = $sex;
    }

    public function getAvatarParts()
    {
        return $this->_avatarParts;
    }

    public function addAvatarPart($part)
    {
        $this->_avatarParts[] = $part;
    }

    public function setAvatarParts($parts)
    {
        $this->_avatarParts = $parts;
    }

    public function removeAvatarPart($part)
    {
        $key = array_search($this->_avatarParts, $part);
        if ($key !== false) {
            unset($this->_avatarParts[$key]);
        }
    }

}

/**
 * @EmbeddedDocument
 */
class AvatarPart
{

    /**
     * @String(name="col")
     * @var string
     */
    protected $_color;

    public function __construct($color = null)
    {
        $this->_color = $color;
    }

    public function getColor()
    {
        return $this->_color;
    }

    public function setColor($color)
    {
        $this->_color = $color;
    }

}