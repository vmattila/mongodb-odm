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
    Doctrine\ODM\MongoDB\UnitOfWork,
    Doctrine\ODM\MongoDB\Mapping\ClassMetadata,
    Doctrine\ODM\MongoDB\MongoCursor,
    Doctrine\ODM\MongoDB\Mapping\Types\Type,
    Doctrine\Common\Collections\Collection,
    Doctrine\ODM\MongoDB\ODMEvents,
    Doctrine\ODM\MongoDB\Event\OnUpdatePreparedArgs,
    Doctrine\ODM\MongoDB\MongoDBException,
    Doctrine\ODM\MongoDB\PersistentCollection;

/**
 * AbstractPersister
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class AbstractPersister
{
    /**
     * The DocumentManager instance.
     *
     * @var Doctrine\ODM\MongoDB\DocumentManager
     */
    protected $dm;

    /**
     * The UnitOfWork instance.
     *
     * @var Doctrine\ODM\MongoDB\UnitOfWork
     */
    protected $uow;

    /**
     * Prepares insert data for document
     *
     * @param mixed $document
     * @return array
     */
    public function prepareInsertData($document)
    {
        $oid = spl_object_hash($document);
        $changeset = $this->uow->getDocumentChangeSet($document);
        $insertData = array();
        foreach ($this->class->fieldMappings as $mapping) {
            if (isset($mapping['notSaved']) && $mapping['notSaved'] === true) {
                continue;
            }
            $new = isset($changeset[$mapping['fieldName']][1]) ? $changeset[$mapping['fieldName']][1] : null;
            if ($new === null && $mapping['nullable'] === false) {
                continue;
            }
            if ($this->class->isIdentifier($mapping['fieldName'])) {
                $insertData['_id'] = $this->prepareValue($mapping, $new);
                continue;
            }
            $current = $this->class->getFieldValue($document, $mapping['fieldName']);
            $value = $this->prepareValue($mapping, $current);
            if ($value === null && $mapping['nullable'] === false) {
                continue;
            }

            $insertData[$mapping['name']] = $value;
            if (isset($mapping['reference'])) {
                $scheduleForUpdate = false;
                if ($mapping['type'] === 'one') {
                    if ( ! isset($insertData[$mapping['name']][$this->cmd . 'id'])) {
                        $scheduleForUpdate = true;
                    }
                } elseif ($mapping['type'] === 'many') {
                    foreach ($insertData[$mapping['name']] as $ref) {
                        if ( ! isset($ref[$this->cmd . 'id'])) {
                            $scheduleForUpdate = true;
                            break;
                        }
                    }
                }
                if ($scheduleForUpdate) {
                    unset($insertData[$mapping['name']]);
                    $id = spl_object_hash($document);

                    $this->uow->scheduleExtraUpdate($document, array(
                        $mapping['fieldName'] => array(null, $new)
                    ));
                }
            }
        }
        // add discriminator if the class has one
        if ($this->class->hasDiscriminator()) {
            $insertData[$this->class->discriminatorField['name']] = $this->class->discriminatorValue;
        }
        return $insertData;
    }

    /**
     * Prepares update array for document, using atomic operators
     *
     * @param mixed $document
     * @return array
     */
    public function prepareUpdateData($document)
    {
        $oid = spl_object_hash($document);
        $class = $this->dm->getClassMetadata(get_class($document));
        $changeset = $this->uow->getDocumentChangeSet($document);
        $result = array();
        foreach ($class->fieldMappings as $mapping) {
            if (isset($mapping['notSaved']) && $mapping['notSaved'] === true) {
                continue;
            }
            $old = isset($changeset[$mapping['fieldName']][0]) ? $changeset[$mapping['fieldName']][0] : null;
            $new = isset($changeset[$mapping['fieldName']][1]) ? $changeset[$mapping['fieldName']][1] : null;
            $current = $class->getFieldValue($document, $mapping['fieldName']);

            if ($mapping['type'] === 'many' || $mapping['type'] === 'collection') {
                $mapping['strategy'] = isset($mapping['strategy']) ? $mapping['strategy'] : 'pushPull';
                if ($mapping['strategy'] === 'pushPull') {
                    if (isset($mapping['embedded']) && $new) {
                        foreach ($new as $k => $v) {
                            if ( ! isset($old[$k])) {
                                continue;
                            }
                            $update = $this->prepareUpdateData($current[$k]);
                            foreach ($update as $cmd => $values) {
                                foreach ($values as $key => $value) {
                                    $result[$cmd][$mapping['name'] . '.' . $k . '.' . $key] = $value;
                                }
                            }
                        }
                    }
                    if ($old !== $new) {
                        if ($mapping['type'] === 'collection') {
                            $old = $old ? $old : array();
                            $new = $new ? $new : array();
                            $compare = function($a, $b) {
                                return $a === $b ? 0 : 1;
                            };
                            $deleteDiff = array_udiff_assoc($old, $new, $compare);
                            $insertDiff = array_udiff_assoc($new, $old, $compare);
                        } elseif (isset($mapping['embedded']) || isset($mapping['reference'])) {
                            $deleteDiff = $current->getDeleteDiff();
                            $insertDiff = $current->getInsertDiff();
                        }

                        // insert diff
                        if ($insertDiff) {
                            $result[$this->cmd . 'pushAll'][$mapping['name']] = $this->prepareValue($mapping, $insertDiff);
                        }
                        // delete diff
                        if ($deleteDiff) {
                            $result[$this->cmd . 'pullAll'][$mapping['name']] = $this->prepareValue($mapping, $deleteDiff);
                        }
                    }
                } elseif ($mapping['strategy'] === 'set') {
                    if ($old !== $new) {
                        $new = $this->prepareValue($mapping, $current);
                        $result[$this->cmd . 'set'][$mapping['name']] = $new;
                    }
                }
            } else {
                if ($old !== $new) {
                    if ($mapping['type'] === 'increment') {
                        $new = $this->prepareValue($mapping, $new);
                        $old = $this->prepareValue($mapping, $old);
                        if ($new >= $old) {
                            $result[$this->cmd . 'inc'][$mapping['name']] = $new - $old;
                        } else {
                            $result[$this->cmd . 'inc'][$mapping['name']] = ($old - $new) * -1;
                        }
                    } else {
                        // Single embedded
                        if (isset($mapping['embedded']) && $mapping['type'] === 'one') {
                            // If we didn't have a value before and now we do
                            if ( ! $old && $new) {
                                $new = $this->prepareValue($mapping, $current);
                                if (isset($new) || $mapping['nullable'] === true) {
                                    $result[$this->cmd . 'set'][$mapping['name']] = $new;
                                }
                            // If we had an old value before and it has changed
                            } elseif ($old && $new) {
                                $update = $this->prepareUpdateData($current);
                                foreach ($update as $cmd => $values) {
                                    foreach ($values as $key => $value) {
                                        $result[$cmd][$mapping['name'] . '.' . $key] = $value;
                                    }
                                }
                            // If we had an old value before and now we don't
                            } elseif ($old && !$new) {
                                if ($mapping['nullable'] === true) {
                                    $result[$this->cmd . 'set'][$mapping['name']] = null;
                                }
                            }
                        // $set all other fields
                        } else {
                            $new = $this->prepareValue($mapping, $current);
                            if (isset($new) || $mapping['nullable'] === true) {
                                $result[$this->cmd . 'set'][$mapping['name']] = $new;
                            } else {
                                $result[$this->cmd . 'unset'][$mapping['name']] = true;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     *
     * @param array $mapping
     * @param mixed $value
     */
    protected function prepareValue(array $mapping, $value)
    {
        if ($value === null) {
            return null;
        }
        if ($mapping['type'] === 'many') {
            $prepared = array();
            $oneMapping = $mapping;
            $oneMapping['type'] = 'one';
            foreach ($value as $rawValue) {
                $prepared[] = $this->prepareValue($oneMapping, $rawValue);
            }
            if (empty($prepared)) {
                $prepared = null;
            }
        } elseif (isset($mapping['reference']) || isset($mapping['embedded'])) {
            if (isset($mapping['embedded'])) {
                $prepared = $this->prepareEmbeddedDocValue($mapping, $value);
            } elseif (isset($mapping['reference'])) {
                $prepared = $this->prepareReferencedDocValue($mapping, $value);
            }
        } else {
            $prepared = Type::getType($mapping['type'])->convertToDatabaseValue($value);
        }
        return $prepared;
    }

    /**
     * Returns the reference representation to be stored in mongodb or null if not applicable.
     *
     * @param array $referenceMapping
     * @param Document $document
     * @return array|null
     */
    protected function prepareReferencedDocValue(array $referenceMapping, $document)
    {
        $id = null;
        if (is_array($document)) {
            $className = $referenceMapping['targetDocument'];
        } else {
            if (!is_object($document)) {
                print_r($referenceMapping);
                exit('test');
            }
            $className = get_class($document);
            $id = $this->uow->getDocumentIdentifier($document);
        }
        $class = $this->dm->getClassMetadata($className);
        if (null !== $id) {
            $id = $class->getDatabaseIdentifierValue($id);
        }
        $ref = array(
            $this->cmd . 'ref' => $class->getCollection(),
            $this->cmd . 'id' => $id,
            $this->cmd . 'db' => $class->getDB()
        );
        if ( ! isset($referenceMapping['targetDocument'])) {
            $discriminatorField = isset($referenceMapping['discriminatorField']) ? $referenceMapping['discriminatorField'] : '_doctrine_class_name';
            $discriminatorValue = isset($referenceMapping['discriminatorMap']) ? array_search($class->getName(), $referenceMapping['discriminatorMap']) : $class->getName();
            $ref[$discriminatorField] = $discriminatorValue;
        }
        return $ref;
    }

    /**
     * Prepares array of values to be stored in mongo to represent embedded object.
     *
     * @param array $embeddedMapping
     * @param Document $embeddedDocument
     * @return array
     */
    protected function prepareEmbeddedDocValue(array $embeddedMapping, $embeddedDocument)
    {
        $className = get_class($embeddedDocument);
        $class = $this->dm->getClassMetadata($className);
        $embeddedDocumentValue = array();
        foreach ($class->fieldMappings as $mapping) {
            // Skip not saved fields
            if (isset($mapping['notSaved']) && $mapping['notSaved'] === true) {
                continue;
            }

            $rawValue = $class->getFieldValue($embeddedDocument, $mapping['fieldName']);

            // Don't store null values unless nullable is specified
            if ($rawValue === null && $mapping['nullable'] === false) {
                continue;
            }
            if (isset($mapping['embedded']) || isset($mapping['reference'])) {
                if (isset($mapping['embedded'])) {
                    if ($mapping['type'] == 'many') {
                        $value = array();
                        foreach ($rawValue as $embeddedDoc) {
                            $value[] = $this->prepareEmbeddedDocValue($mapping, $embeddedDoc);
                        }
                        if (empty($value)) {
                            $value = null;
                        }
                    } elseif ($mapping['type'] == 'one') {
                        $value = $this->prepareEmbeddedDocValue($mapping, $rawValue);
                    }
                } elseif (isset($mapping['reference'])) {
                    if ($mapping['type'] == 'many') {
                        $value = array();
                        foreach ($rawValue as $referencedDoc) {
                            $value[] = $this->prepareReferencedDocValue($mapping, $referencedDoc);
                        }
                        if (empty($value)) {
                            $value = null;
                        }
                    } else {
                        $value = $this->prepareReferencedDocValue($mapping, $rawValue);
                    }
                }
            } else {
                $value = Type::getType($mapping['type'])->convertToDatabaseValue($rawValue);
            }
            if ($value === null && $mapping['nullable'] === false) {
                continue;
            }
            $embeddedDocumentValue[$mapping['name']] = $value;
        }
        if ( ! isset($embeddedMapping['targetDocument'])) {
            $discriminatorField = isset($embeddedMapping['discriminatorField']) ? $embeddedMapping['discriminatorField'] : '_doctrine_class_name';
            $discriminatorValue = isset($embeddedMapping['discriminatorMap']) ? array_search($class->getName(), $embeddedMapping['discriminatorMap']) : $class->getName();
            $embeddedDocumentValue[$discriminatorField] = $discriminatorValue;
        }
        return $embeddedDocumentValue;
    }
}