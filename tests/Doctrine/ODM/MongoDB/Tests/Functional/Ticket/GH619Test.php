<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @author Ville Mattila <ville@eventio.fi>
 */
class GH619Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{

    /**
     * This test runs the actual test case WITHOUT setting @ReferenceOne'd primary email address
     */
    public function testDoNotSetReferenceOne() {
        $this->doActualTesting(false);
    }

    /**
     * This in turn sets the @ReferenceOne'd primary email address
     */
    public function testSetReferenceOne() {
        $this->doActualTesting(true);
    }

    /**
     * This function includes the actual test code
     * @param bool $setPrimaryAddress Whether we set the primary address for the user
     */
    private function doActualTesting($setPrimaryAddress)
    {
        // Inserting one user with two email addresses
        $user = new GH619_User();

        $email = new GH619_EmailAddress('address1@example.com');
        $email->user = $user;

        $email2 = new GH619_EmailAddress('address2@example.com');
        $email2->user = $user;

        $user->addEmailAddress($email);
        $user->addEmailAddress($email2);

        // Depending on the passed parameter, we set or not the primary (@ReferenceOne) address
        if ($setPrimaryAddress) {
            $user->setPrimaryEmailAddress($email);
        }

        $this->dm->persist($user);
        $this->dm->flush();

        $this->dm->clear();

        // Fetching the user from DB for cloning
        $qb = $this->dm->createQueryBuilder(__NAMESPACE__ . '\GH619_User')
            ->field('id')
            ->equals($user->id);
        $query = $qb->getQuery();
        $userToClone = $query->getSingleResult();

        // Getting original user's primary email address, proxy object is returned
        $pp = $userToClone->getPrimaryEmailAddress();

        // Cloning the user
        $newUser = clone $userToClone;
        $newUser->id = null;
        $newUser->setPrimaryEmailAddress(null);
        $newUser->emailAddresses = new ArrayCollection();

        // Finding old user's email addresses to clone from
        $qb = $this->dm->createQueryBuilder(__NAMESPACE__ . '\GH619_EmailAddress')
            ->field('user')->equals($user->id);
        $emailsToClone = $qb->getQuery()->execute();
        foreach ($emailsToClone as $oldEmail) {
            $newEmail = clone $oldEmail;
            $newEmail->id = null;
            $newEmail->user = $newUser;
            $newUser->addEmailAddress($newEmail);

            $this->dm->persist($newEmail);
            $this->dm->flush($newEmail);

            // newEmail should have now id as it's persisted
            #$this->assertNotNull($newEmail->id);
        }

        $newUser->setPrimaryEmailAddress($newEmail);

        $this->dm->persist($newUser);
        $this->dm->flush($newUser);

        $this->dm->clear();

        // Fetching the email addresses of the new user
        $qb = $this->dm->createQueryBuilder(__NAMESPACE__ . '\GH619_EmailAddress')
            ->field('user')
            ->equals($newUser->id);
        $query = $qb->getQuery();
        $newUserEmails = $query->execute();

        // We should get two email addresses
        $this->assertEquals(2, $newUserEmails->count());
    }

}

/**
 * @ODM\Document
 */
class GH619_User
{
    /** @ODM\Id */
    public $id;

    /** @ODM\Field(type="string") */
    public $username;

    /** @ODM\ReferenceOne(targetDocument="GH619_EmailAddress", simple="true", cascade={"all"}) */
    protected $primaryEmailAddress;

    public function getPrimaryEmailAddress() {
        return $this->primaryEmailAddress;
    }
    public function setPrimaryEmailAddress($email) {
        $this->primaryEmailAddress = $email;
    }

    /** @ODM\ReferenceMany(targetDocument="GH619_EmailAddress", simple="true", cascade={"all"}) */
    public $emailAddresses;

    public function __construct($name = '') {
        $this->username = $name;
        $this->emailAddresses = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function addEmailAddress(GH619_EmailAddress $email)
    {
        $this->emailAddresses->add($email);
    }
}

/**
 * @ODM\Document
 */
class GH619_EmailAddress
{
    /** @ODM\Id */
    public $id;

    /** @ODM\Field(type="string") */
    public $address;

    /** @ODM\ReferenceOne(targetDocument="GH619_User", simple="true", cascade={"all"}) */
    public $user;

    public function __construct($address = '') {
        $this->address = $address;
    }

}