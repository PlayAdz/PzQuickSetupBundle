<?php

namespace Pz\QuickSetupBundle\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Yaml\Yaml;

use Pz\QuickSetupBundle\Bundle\BundleInfo;

function pr($obj)
{
    print_r($obj);die();
}

/**
 * Setup new project.
 *
 * @author Sven Gaubert
 */
class SetupProjectCommand extends ContainerAwareCommand
{
    static $DEFAULT_ARGS_GENERATE_BUNDLE = array(
        //'namespace' => null,
        'bundle-name' => null,
        'dir'       => 'src',
        'format'    => 'annotation',
        'structure' => true,
    );
    static $DEFAULT_ARGS_GENERATE_ENTITY = array(
        'fields'            => null,
        'format'            => 'annotation',
        'with-repository'   => true,
    );
    static $DEFAULT_ARGS_GENERATE_CONTROLLER = array(
        'fields'            => null,
        'route_format'            => 'annotation',
        'template_format'            => 'twig',
        'with-repository'   => true,
    );

    static  $MAPPING_FORM_FIELD_TOMODEL_COLUMN = array(
            'collection'    => 'array',
            'checkbox'      => 'boolean',
            'vardatetime'   => 'datetime',
            'datetimetz'    => 'datetime',
            'date'          => 'date',
            'time'          => 'time',
            'number'        => 'float',
            'integer'       => 'smallint',
            'textarea'      => 'text',
            'email'         => 'string(255)',
            'phone'         => 'string(50)',
            'country'       => 'string(50)',
            'file'          => 'string(255)',
            'image'         => 'string(255)',
            'ip'            => 'string(50)',
            'language'      => 'string(50)',
            'url'           => 'string(255)',
            );

    protected function configure()
    {
        $this
            ->setName('pz:setup')
            ->setDescription('')
            ->addOption('override', null, InputOption::VALUE_NONE, 'Override the configured asset root')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Set the polling period in seconds (used with --watch)', 'setup.yml')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        ini_set('memory_limit', '500M');
        $this->override = $input->getOption('override');
        $this->verbose  = $input->getOption('verbose');
        $this->input    = $input;
        $this->output   = $output;

        $this->root_dir = $this->getContainer()->get('kernel')->getRootdir();

        $this->filesystem   = $this->getContainer()->get('filesystem');
        $this->filesystem->remove($this->root_dir . '/../src/Aa');
        $this->filesystem->remove($this->root_dir . '/../src/Bb');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf("Setup project using file '<comment>%s</comment>'.", $input->getOption('file')));
        $output->writeln('');
/*
        $bundle = $this->getContainer()->get('kernel')->getBundles();
        //pr(array_keys($bundle));
        $bundle = $this->getContainer()->get('kernel')->getBundle('AaTestBundle');
        $entityClass = $this->getContainer()->get('doctrine')->getEntityNamespace($bundle->getName());
        $output->writeln($bundle->getPath());
        $output->writeln($bundle->getName());
        $output->writeln($bundle->getNameSpace());
        $output->writeln($entityClass);
        die();
*/
        if (!file_exists($this->root_dir . '/setup.yml'))
        {
            throw new \RuntimeException('Create a setup.yml file');
        }
        $yaml = Yaml::parse($this->root_dir . '/setup.yml');
        //print_r($yaml);

        if (isset($yaml['bundles']))
        {
            foreach($yaml['bundles'] as $bundle_ns => $bundle_config)
            {
                // generate:bundle --namespace=Acme/BlogBundle --dir=src [--bundle-name=...] --no-interaction

                $args = $bundle_config['config'];
                $args = array_merge($args, self::$DEFAULT_ARGS_GENERATE_BUNDLE);

                $bundle = $this->generateBundle($bundle_ns, $args['bundle-name'], $args['dir'], $args['format'], $args['structure']);
                //$this->executeTask('generate:bundle', $args, $bundle_ns);

                if (isset($bundle_config["models"]))
                {

                    foreach($bundle_config["models"] as $model_name => $model_config)
                    {
                        $args = $model_config['config'];
                        $full_model_name =  sprintf("%s:%s", $bundle->getName(), $model_name);

                        if (isset($model_config['fields']))
                        {
                            $args['fields'] = $this->getModelColumns($model_config['fields']);
                            // doctrine:generate:entity --entity=AcmeBlogBundle:Blog/Post --format=annotation --fields="title:string(255) body:text" --with-repository
                            $args['entity']   = $full_model_name;
                            //pr($args);
                            //$this->executeTask('doctrine:generate:entity', $args, $model_name);
                            //$b = $this->getContainer()->get('kernel')->getBundle($bundle_ns);

                            $this->generateEntity($bundle, $model_name, $args['format'], $args['fields'], $args['with-repository']);
                        }
                        // form
                        //$this->generateForm($bundle, $full_model_name);

                        //$this->executeTask('doctrine:generate:form', $args, $model_name);
                    }
                }

                if (isset($bundle_config["routing"]))
                {
                    $args = $bundle_config["routing"]['config'];
                    $args = array_merge($args, self::$DEFAULT_ARGS_GENERATE_CONTROLLER);

                    $controllers = $this->parseControllers($bundle, $bundle_config["routing"]['controllers']);

                    foreach($controllers as $controller_name => $controller_config)
                    {
                        $this->generateController($bundle, $controller_name, $args['route_format'], $args['template_format'], $controller_config['actions']);
                    }
                }
            }
        }
    }

    protected function parseControllers($bundle, $bundle_config_controllers)
    {
        foreach($bundle_config_controllers as $controller_name => &$controller_config)
        {
            foreach($controller_config['actions'] as $action_name => &$action_config)
            {
                if (!isset($action_config['template']))
                {
                    $action_config['template'] = 'default';
                }
                else {
                    // example: PzBlogBundle:Post3:index.html.twig
                    $action_config['template'] = sprintf('%s:%s:%s.html.twig', $bundle->getName(), $controller_name, $action_config['template']);
                }
                $action_config['name'] = $action_name . 'Action';
                preg_match_all('/{(.*?)}/', $action_config['route'], $placeholders);
                $action_config['placeholders'] = $placeholders[1];
            }
        }
        //pr($bundle_config_controllers);
        return $bundle_config_controllers;
    }

    /**
     *
     *
     * @param $fields
     * @return string
     */
    public function getModelColumns($fields)
    {
        $column = array();

        foreach($fields as $name => $data)
        {
            $data['fieldName'] = $name;
            if (isset(self::$MAPPING_FORM_FIELD_TOMODEL_COLUMN[$data['type']]))
            {
                $data['type'] = self::$MAPPING_FORM_FIELD_TOMODEL_COLUMN[$data['type']];
            }
            if (preg_match('/(.*)\((.*)\)/', $data['type'], $m))
            {
                $data['type']    = $m[1];
                $data['length']  = $m[2];
            }
            $column[] = $data;
        }
        return $column;
    }

    /**
     * @param $namespace
     * @param $bundle
     * @param $dir
     * @param $format
     * @param $structure
     */
    protected function generateBundle($namespace, $name, $dir, $format, $structure)
    {
        $namespace = str_ireplace('/', '\\', $namespace);
        if (null === $name)
        {
            $name = strtr($namespace, array('\\' => ''));
            $name = \Sensio\Bundle\GeneratorBundle\Command\Validators::validateBundleName($name);
        }
        if (!isset($this->generator_bundle)) {
            $this->generator_bundle = new \Sensio\Bundle\GeneratorBundle\Generator\BundleGenerator($this->filesystem, __DIR__.'/../Resources/skeleton/bundle');
        }
        $this->generator_bundle->generate($namespace, $name, $dir, $format, $structure);
        $this->output->writeln(sprintf('generate bundle %-20s OK', $name));

        $dir .= '/'.strtr($namespace, '\\', '/');
        $bundle_info = new BundleInfo();
        $bundle_info->setName($name);
        $bundle_info->setNamespace($namespace);
        $bundle_info->setPath($dir);
        return $bundle_info;
    }

    /**
     * @param $namespace
     * @param $bundle
     * @param $dir
     * @param $format
     * @param $structure
     */
    protected function generateEntity($bundle, $entity, $format, $fields, $with_repository)
    {
        if (!isset($this->generator_entity)) {
            $this->generator_entity = new \Pz\QuickSetupBundle\Generator\DoctrineEntityGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
//            $this->generator_entity = new \Sensio\Bundle\GeneratorBundle\Generator\DoctrineEntityGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
        }

        $this->generator_entity->generate($bundle, $entity, $format, $fields, $with_repository);
        $this->output->writeln(sprintf('generate entity %-20s OK', $bundle->getName() . ':' . $entity));
    }



    /**
     *
     * Exemple for param 2: Aa\TestBundle\Entity\Post
     *
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     * @param $entity
     */
    protected function generateForm(BundleInterface $bundle, $entity)
    {
        if (!isset($this->generator_form)) {
            $this->generator_form = new \Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
//            $this->generator_form = new \Pz\QuickSetupBundle\Generator\DoctrineEntityGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
        }
        $factory = new \Doctrine\Bundle\DoctrineBundle\Mapping\MetadataFactory($this->getContainer()->get('doctrine'));
        $entity = 'Aa\TestBundle\Entity\Post';
        //pr(get_class($this->getContainer()->get('doctrine')->getEntityManager()));
        $metadata = $factory->getClassMetadata($entity)->getMetadata();
        $metadata = new \Doctrine\ORM\Mapping\ClassMetadata($entity);


        $this->generator_form->generate($bundle, $entity, $metadata[0]);
        $this->output->writeln(sprintf('generate form %-20s OK', $bundle->getName() . ':' . $entity));
    }

    /**
     * @param $bundle
     * @param $controller
     * @param $route_format
     * @param $template_format
     * @param $actions
     */
    protected function generateController($bundle, $controller, $route_format, $template_format, $actions)
    {
        if (!isset($this->generator_controller)) {
//            $this->generator_controller = new \Pz\QuickSetupBundle\Generator\DoctrineEntityGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
            $this->generator_controller = new \Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator($this->filesystem, __DIR__.'/../Resources/skeleton/controller');
        }

        $this->generator_controller->generate($bundle, $controller, $route_format, $template_format, $actions);
        $this->output->writeln(sprintf('generate controller %-20s OK', $bundle->getName() . ':' .$controller));
    }


    /**
     *
     * @throws \RuntimeException
     */
    protected function executeTask($cmd, $arguments, $string_to_display = '')
    {

        // http://symfony.com/doc/2.0/components/console/introduction.html#calling-an-existing-command
        $command = $this->getApplication()->find($cmd);
        $def = $command->getDefinition();
        $args = $options = '';
        foreach($arguments as $key => $value)
        {
            if ($def->hasOption($key))
            {
                $options .= sprintf(' --%s="%s"', $key, $value);
            }
            else if ($def->hasArgument($key))
            {
                $args .= ' ' . $value;
            }
        }

        try {
            $fullcommand = sprintf('php app/console %s %s %s', $cmd, $args, $options);
            $this->output->writeln(" > " . $fullcommand);
            return; ///////////////////
            $process = new \Symfony\Component\Process\Process($fullcommand);
            $process->start();
            $process->wait();
            $returnCode = $process->getExitCode();
            if ($this->verbose)
            {
                $this->output->writeln($process->getOutput());
                $this->output->writeln($process->getErrorOutput());
            }
            $this->output->writeln(sprintf('%-30s %-20s', $cmd, $returnCode));

            if($returnCode == 0) {
                $this->output->writeln(sprintf('%-30s %-20s OK', $cmd, $string_to_display));
            }
        }catch (\Exception $e)
        {
            $this->output->writeln(sprintf('%-30s %-20s ERROR %s', $cmd, $string_to_display, $e->getMessage()));
        }
    }

}
