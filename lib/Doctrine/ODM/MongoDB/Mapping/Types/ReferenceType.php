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

namespace Doctrine\ODM\MongoDB\Mapping\Types;

use Doctrine\ODM\MongoDB\UnitOfWork;

use Doctrine\ODM\MongoDB\DocumentManager;

use Doctrine\ODM\MongoDB\MongoDBException;

/**
 * The Type interface.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ReferenceType implements ValueConverterInterface
{
    private $dm;
    private $unitOfWork;
    private $cmd;

    public function __construct(DocumentManager $dm, UnitOfWork $unitOfWork, $cmd)
    {
        $this->dm         = $dm;
        $this->unitOfWork = $unitOfWork;
        $this->cmd        = $cmd;
    }

    public function convertToDatabaseValue($value, array $mapping)
    {
        $class = $this->dm->getClassMetadata(get_class($value));
        $id = $this->unitOfWork->getDocumentIdentifier($value);

        $dbRef = array(
            $this->cmd . 'ref' => $class->getCollection(),
            $this->cmd . 'id'  => $class->getDatabaseIdentifierValue($id),
            $this->cmd . 'db'  => $class->getDatabase()
        );

        // add a discriminator value if the referenced document is not mapped explicitely to a targetDocument
        if ($mapping && ! isset($mapping['targetDocument'])) {
            $discriminatorField = isset($mapping['discriminatorField']) ? $mapping['discriminatorField'] : '_doctrine_class_name';
            $discriminatorValue = isset($mapping['discriminatorMap']) ? array_search($class->getName(), $mapping['discriminatorMap']) : $class->getName();
            $dbRef[$discriminatorField] = $discriminatorValue;
        }

        return $dbRef;
    }

    public function convertToPHPValue($value, array $mapping)
    {
        $documentName = $this->dm->getClassNameFromDiscriminatorValue($mapping, $value);
        return $this->dm->getReference($documentName, $value[$this->cmd . 'id']);
    }

    public function compile(array $mapping)
    {
        return <<<EOF
\$className = \$this->dm->getClassNameFromDiscriminatorValue(\$this->class->fieldMappings['{$mapping['fieldName']}'], \$value);
\$targetMetadata = \$this->dm->getClassMetadata(\$className);
\$id = \$targetMetadata->getPHPIdentifierValue(\$value['\$id']);
\$return = \$this->dm->getReference(\$className, \$id);
EOF
;
    }
}
