<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\MongoDB\Persisters;

use Doctrine\ODM\MongoDB\DocumentManager,
    Doctrine\ODM\MongoDB\PersistentCollection;

/**
 * Base class for all collection persisters.
 *
 * @since 1.0
 * @author Roman Borschel <roman@code-factory.org>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
abstract class AbstractCollectionPersister extends AbstractPersister
{
    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * The UnitOfWork instance.
     *
     * @var Doctrine\ODM\MongoDB\UnitOfWork
     */
    protected $uow;

    /**
     * Initializes a new instance of a class derived from AbstractCollectionPersister.
     *
     * @param Doctrine\ODM\MongoDB\DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        $this->uow = $dm->getUnitOfWork();
        $this->cmd = $this->dm->getConfiguration()->getMongoCmd();
    }

    /**
     * Deletes the persistent state represented by the given collection.
     *
     * @param PersistentCollection $coll
     */
    public function delete(PersistentCollection $coll)
    {
        $owner = $coll->getOwner();
        $class = $this->dm->getClassMetadata(get_class($owner));
        $collection = $this->dm->getDocumentCollection(get_class($owner));
        if ($class->isEmbeddedDocument) {
            return;
        }
        $id = $class->getDatabaseIdentifierValue($owner);
        $collection->update($id, $this->getDeleteQuery($coll));
    }

    /**
     * Updates the given collection, synchronizing it's state with the database
     * by inserting, updating and deleting individual elements.
     *
     * @param PersistentCollection $coll
     */
    public function update(PersistentCollection $coll)
    {
        $this->deleteDocuments($coll);
        $this->updateDocuments($coll);
        $this->insertDocuments($coll);
    }

    public function deleteDocuments(PersistentCollection $coll)
    {
        $owner = $coll->getOwner();
        $class = $this->dm->getClassMetadata(get_class($owner));
        $collection = $this->dm->getDocumentCollection(get_class($owner));
        if ($class->isEmbeddedDocument) {
            return;
        }
        $id = $class->getDatabaseIdentifierValue($owner);
        if ($coll->getDeleteDiff()) {
            $collection->update($id, $this->getDeleteDocumentsQuery($coll));
        }
    }

    public function updateDocuments(PersistentCollection $coll)
    {
    }

    public function insertDocuments(PersistentCollection $coll)
    {
        $owner = $coll->getOwner();
        $class = $this->dm->getClassMetadata(get_class($owner));
        $collection = $this->dm->getDocumentCollection(get_class($owner));
        if ($class->isEmbeddedDocument) {
            return;
        }
        $id = $class->getDatabaseIdentifierValue($owner);
        if ($coll->getInsertDiff()) {
            $collection->update($id, $this->getInsertDocumentsQuery($coll));
        }
    }

    protected function getDeleteQuery(PersistentCollection $coll)
    {
        $mapping = $coll->getMapping();
        return array('$unset' => array($mapping['name'] => 1));
    }

    protected function getDeleteDocumentsQuery(PersistentCollection $coll)
    {
        $mapping = $coll->getMapping();
        return array('$pullAll' => array($mapping['name'] => $this->prepareValue($mapping, $coll->getDeleteDiff())));
    }

    protected function getUpdateDocumentQuery(PersistentCollection $coll)
    {
        return array();
    }

    protected function getInsertDocumentsQuery(PersistentCollection $coll)
    {
        $mapping = $coll->getMapping();
        return array('$pushAll' => array($mapping['name'] => $this->prepareValue($mapping, $coll->getInsertDiff())));
    }
}