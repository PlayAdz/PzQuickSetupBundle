<?php
namespace Playadz\Bundle\QuickSetupBundle\Generator;

use Doctrine\Common\Inflector\Inflector;
use Playadz\QuickSetupBundle\Util\TypeGuesser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;

/**
 * Generates a Doctrine entity class based on its name, fields and format.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineEntityGenerator extends \Sensio\Bundle\GeneratorBundle\Generator\DoctrineEntityGenerator
{
    private $filesystem;
    private $registry;


    public function __construct(Filesystem $filesystem, RegistryInterface $registry)
    {
        $this->filesystem   = $filesystem;
        $this->registry     = $registry;
        $this->skeletonDir  = __DIR__.'/../Resources/skeleton/entity';
    }

    /**
     * @param BundleInterface $bundle
     * @param $entity
     * @param $format
     * @param array $fields
     * @param $withRepository
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $entity, $format, array $model, $withRepository)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getEntityManager(null)->getConfiguration();
        $config->setEntityNamespaces(array_merge(
            array($bundle->getName() => $bundle->getNamespace().'\\Entity'),
            $config->getEntityNamespaces()
        ));

        $entityClass = $this->registry->getEntityNamespace($bundle->getName()).'\\'.$entity;
        $entityPath = $bundle->getPath().'/Entity/'.str_replace('\\', '/', $entity).'.php';
        if (file_exists($entityPath)) {
            throw new \RuntimeException(sprintf('Entity "%s" already exists.', $entityClass));
        }
        // create meta data class
        $class = $this->getEntityClassMetadata($entity, $model, $withRepository);

        $this->setSkeletonDirs($this->skeletonDir);

        // generate PHP entity file
        $this->renderFile('Entity.php.twig', sprintf('%s/Entity/%s.php', $bundle->getPath(), $entity), array(
            'namespace'        => $bundle->getNamespace(),
            'fields'           => $this->getFieldsFromMetadata($class),
            'relations'        => $this->getFieldsFromMetadata($class),
            'indexes'          => (isset($model['indexes'])) ? $this->getAnnotationIndexes($model['indexes']) : '',
            'entity_class'     => $entity,
            'table'            => Inflector::tableize($entity),
            'format'           => $format,
        ));

        // generate PHP or YML or XML File, if needed
        if ('annotation' !== $format)
        {
            $cme = new ClassMetadataExporter();
            $exporter = $cme->getExporter('yml' == $format ? 'yaml' : $format);
            $mappingPath = $bundle->getPath().'/Resources/config/doctrine/'.str_replace('\\', '.', $entity).'.orm.'.$format;

            if (file_exists($mappingPath)) {
                throw new \RuntimeException(sprintf('Cannot generate entity when mapping "%s" already exists.', $mappingPath));
            }

            $mappingCode = $exporter->exportClassMetadata($class);
            if ($mappingPath) {
                $this->filesystem->mkdir(dirname($mappingPath));
                file_put_contents($mappingPath, $mappingCode);
            }
            // generate entity
            $entityGenerator = $this->getEntityGenerator();
            $entityGenerator->setGenerateAnnotations(false);
            $entityCode = $entityGenerator->generateEntityClass($class);

            $this->filesystem->mkdir(dirname($entityPath));
            file_put_contents($entityPath, $entityCode);
        }

        if ($withRepository) {
//            $path = $bundle->getPath().'/../..';
//            $path = $bundle->getPath().str_repeat('/..', substr_count(get_class($bundle), '\\'));
//            $this->getRepositoryGenerator()->writeEntityRepositoryClass($class->customRepositoryClassName, $path);

            $this->renderFile('Repository.php.twig', sprintf('%s/Entity/%sRepository.php', $bundle->getPath(), $entity), array(
                'namespace'        => $bundle->getNamespace(),
                'entity_class'     => $entity,
            ));

        }
    }


    /**
     * @param $array
     * @return mixed
     */
    public function getAnnotationIndexes($array)
    {
       $indexes = array();
       foreach($array as $index_name => $config)
       {
           // TODO use $config
            $indexes[] = sprintf('@ORM\Index(name="%s_idx", columns={"%s"})', $index_name, $index_name);
       }
       return implode(",/n", $indexes);
    }
    /**
     * @param $entity
     * @param $model
     * @param $withRepository
     * @return mixed
     */
    public function getEntityClassMetadata($entityClass, $model, $withRepository)
    {
        $class = new ClassMetadataInfo($entityClass);
        if ($withRepository) {
            $class->customRepositoryClassName = $entityClass.'Repository';
        }
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);

        foreach ($model['fields'] as $name => &$field)
        {
            $field['fieldName'] = $name;
            $field = array_merge($field, TypeGuesser::getFormType($field['type']));
            $class->mapField($field);
    }
return $class;
    }


    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param ClassMetadataInfo $metadata
     * @return array $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldMappings;

        // Remove the primary key field if it's not managed manually
        //if (!$metadata->isIdentifierNatural()) {
        //    $fields = array_diff($fields, $metadata->identifier);
        //}

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $metadata->getFieldMapping($fieldName);
            }
        }

        foreach($fields as &$field)
        {
            $field['fieldNameCamelized'] = Inflector::camelize($field['fieldName']);
           // $field['actAs'] = array('@Gedmo\Timestampable()');
        }
        //print_r($fields);die();
        return $fields;
    }

    /**
     * @return EntityGenerator
     */
    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        $entityGenerator->setAnnotationPrefix('ORM\\');

        return $entityGenerator;
    }

}
