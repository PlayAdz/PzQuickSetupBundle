<?php


namespace Playadz\Bundle\QuickSetupBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand as BaseGenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Playadz\Bundle\QuickSetupBundle\Generator\DoctrineFormGenerator;


/**
 * Generates a form type class for a given Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class GenerateDoctrineFormCommand extends BaseGenerateDoctrineCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('entity', InputArgument::REQUIRED, 'The entity class name to initialize (shortcut notation)'),
            ))
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force to overwrite existing files.')
            ->setDescription('Generates a form type class based on a Doctrine entity')
            ->setHelp(<<<EOT
The <info>pz:generate:form</info> command generates a form class based on a Doctrine entity.

<info>php app/console doctrine:generate:form AcmeBlogBundle:Post</info>

Every generated file is based on a template. There are default templates but they can be overriden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/SensioGeneratorBundle/skeleton/form
APP_PATH/Resources/SensioGeneratorBundle/skeleton/form</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT
            )
            ->setName('pz:generate:form')
            ->setAliases(array('pz:doctrine:form'))
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $entity = Validators::validateEntityName($input->getArgument('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;

        $metadata = $this->getEntityMetadata($entityClass);
        $bundle   = $this->getApplication()->getKernel()->getBundle($bundle);

        $generator = new DoctrineFormGenerator($this->getContainer()->get('filesystem'));
        $generator->setSkeletonDirs(__DIR__.'/../Resources/skeleton');

        if ($input->getOption('force'))
        {
            $dirPath    = $bundle->getPath().'/Form';
            $classPath  = $dirPath.'/'.str_replace('\\', '/', $entity).'Type.php';

            $output->writeln(sprintf('Delete %s',$classPath));

            $this->getContainer()->get('filesystem')->remove($classPath);
        }

        $generator->generate($bundle, $entity, $metadata[0]);

        $output->writeln(sprintf( 'The new %s.php class file has been created under %s.',  $generator->getClassName(), $generator->getClassPath() ));
    }

    protected function createGenerator()
    {
        return new DoctrineFormGenerator($this->getContainer()->get('filesystem'));
    }


}
