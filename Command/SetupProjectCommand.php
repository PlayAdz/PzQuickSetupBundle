<?php

namespace Pz\QuickSetupBundle\Command;
use Pz\QuickSetupBundle\Util\TypeGuesser;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use \Doctrine\ORM\Mapping\ClassMetadata;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;

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

    protected $log_pattern_ok = '%-20s <comment>%-60s</comment> OK   %s';
    protected $log_pattern_ko = '<error>%-20s %-60s KO   %s</error>';

    protected $verbose, $override, $input, $output;

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
    static $DEFAULT_ARGS_GENERATE_CRUD = array(
        'format'            => 'annotation',
        'route-prefix'      => '',
        'actions'           => array('index', 'show', 'new', 'edit', 'delete'),
    );
    static $DEFAULT_ARGS_GENERATE_CONTROLLER = array(
        'route_format'            => 'annotation',
        'template_format'         => 'twig',
        'with-repository'         => true,
    );



    protected function configure()
    {
        $this
            ->setName('pz:setup')
            ->setDescription('')
            ->addOption('route', null, InputOption::VALUE_NONE, 'Update the routing of the app')
            ->addOption('kernel', null, InputOption::VALUE_NONE, 'Update the App Kernel')
            ->addOption('override', null, InputOption::VALUE_NONE, 'Override the configured asset root')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Set the polling period in seconds (used with --watch)', 'setup.yml')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        ini_set('memory_limit', '500M');
        $this->override = $input->getOption('override');
        $this->verbose  = $input->getOption('verbose');
        $this->input    = $input;
        $this->output   = $output;

        $this->root_dir = $this->getContainer()->get('kernel')->getRootdir();
        $this->kernel   = $this->getContainer()->get('kernel');
        $this->filesystem   = $this->getContainer()->get('filesystem');
        $this->filesystem->remove($this->root_dir . '/../src/MyNamespace1');
        $this->filesystem->remove($this->root_dir . '/../src/MyNamespace2');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf("Setup project using file '<comment>%s</comment>'.", $input->getOption('file')));
        $output->writeln('');
/*
        $bundle = $this->getContainer()->get('kernel')->getBundles();
        $bundle = $this->getContainer()->get('kernel')->getBundle('AaTestBundle');
        $entityClass = $this->getContainer()->get('doctrine')->getEntityNamespace($bundle->getName());
*/
        $this->generateAll();
    }

    /**
     * @throws \RuntimeException
     */
    protected function generateAll()
    {
        if (!file_exists($this->root_dir . '/setup'))
        {
            throw new \RuntimeException('Create a setup.yml file in your app folder');
        }
        $finder = new Finder();
        $finder->depth(0)->files()->name('*.yml')->in($this->root_dir . '/setup');

        $yaml = array();

        foreach ($finder as $file)
        {
            if ($this->verbose)
            {
                $this->output->writeln(sprintf('Load <comment>%s</comment>', $file));
            }
            $yaml = array_merge_recursive($yaml, Yaml::parse($file));
        }
        //print_r($yaml);

        if (isset($yaml['bundles']))
        {
            foreach($yaml['bundles'] as $bundle_ns => $bundle_config)
            {
                $args = $bundle_config['config'];
                $args = array_merge(self::$DEFAULT_ARGS_GENERATE_BUNDLE, $args);

                $bundle = $this->generateBundle($bundle_ns, $args['bundle-name'], $args['dir'], $args['format'], $args['structure']);

                // register the bundle in the Kernel class
                $this->updateKernel($bundle->getFullName());

                if (isset($bundle_config["models"]))
                {
                    foreach($bundle_config["models"] as $model_name => $model_config)
                    {
                        $args = $model_config['config'];
                        $full_model_name =  sprintf("%s:%s", $bundle->getName(), $model_name);

                        if (!isset($model_config['fields'])) throw new Exception('could not create entity without fields');

                        $form_fields    = $model_config['fields'];
                        //$fields         = $this->getModelColumns($model_config['fields']);

                        $this->generateEntity($bundle, $model_name, $model_config, $args['format'], $args['with-repository']);
                        $this->registerEntityNamespace($bundle, $args['format']);

                        $metadata       = $this->getEntityClassMetadata($full_model_name);

                        if (isset($model_config["config"]['with-form']) && $model_config["config"]['with-form'] !== false)
                        {
                            $this->generateForm($bundle, $model_name, $form_fields,$metadata);
                        }
                        if (isset($model_config["config"]['with-crud']) && $model_config["config"]['with-crud'] !== false)
                        {
                            $crud_config = array_merge(self::$DEFAULT_ARGS_GENERATE_CRUD, $model_config["config"]['with-crud']);
                            if(empty($crud_config['route-prefix'])) $crud_config['route-prefix'] = strtolower(str_replace(array('\\', '/'), '_', $model_name));
                            $this->generateCrud($bundle, $model_name, $metadata, $crud_config['format'], $crud_config['actions'], $crud_config['route-prefix'], $this->override);
                            // routing
                            $this->updateRouting($bundle->getName(), $crud_config['format']);
                        }
                    }
                }

                if (isset($bundle_config["routing"]))
                {
                    $global_config = $bundle_config["routing"]['config'];

                    $controllers = $this->parseControllers($bundle, $bundle_config["routing"]['controllers']);

                    foreach($controllers as $controller_name => $controller_config)
                    {
                        $controller_config['config'] = array_merge($global_config, $controller_config['config']);
                        $this->generateController($bundle, $controller_name, $controller_config['config']['route_format'], $controller_config['config']['template_format'], $controller_config['actions']);

                        // routing
                        $this->updateRouting($bundle->getName(), $controller_config['config']['route_format']);
                    }
                }
            }
        }

    }

    /**
     * This function parse controllers configuration, and return an array containing all settings, like:
     * array(
     *   [route]       => /blog/{id}/{id2}
     *   [template]     => MyNamespace1Sample2Bundle:Controller1:action1.html.twig
     *   [name]         => action2Action
     *   [placeholders] => array('id', 'id2')
     * )
     * @param $bundle
     * @param $bundle_config_controllers
     * @return mixed
     */
    protected function parseControllers($bundle, $bundle_config_controllers)
    {
        foreach($bundle_config_controllers as $controller_name => &$controller_config)
        {
            if (!isset($controller_config['actions']))
            {
                $controller_config['actions'] = array();
            }
            if (!isset($controller_config['config']))
            {
                $controller_config['config'] = array();
            }
            else
            {
                $controller_config['config'] = array_merge(self::$DEFAULT_ARGS_GENERATE_CONTROLLER, $controller_config['config']);
            }

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
     * @param $entity
     * @param $fields
     */
    public function getEntityClassMetadata($full_model_name)
    {
        $em     = $this->getContainer()->get('doctrine.orm.entity_manager');
        return $em->getClassMetadata($full_model_name);
    }

    /**
     * @param $entity
     * @param $format
     */
    public function registerEntityNamespace(BundleInfo $bundle, $format)
    {
        $path = $this->root_dir . '/../'.$bundle->getPath();
        $namespace = $bundle->getNamespace();

        if ('annotation' === $format)
        {
            $reader = $this->getContainer()->get('annotation_reader');
            $driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($reader, array($path . '/Entity'));
        }
        else if ('yml' === $format)
        {
            $driver = new \Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver(array($path . '/Resources/config/doctrine' => $namespace. '\Entity'));
            $driver->setGlobalBasename('mapping');
        }
        else if ('xml' === $format)
        {
            $driver = new \Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver(array($path . '/Resources/config/doctrine' => $namespace. '\Entity'));
            $driver->setGlobalBasename('mapping');
        }
        $em     = $this->getContainer()->get('doctrine.orm.entity_manager');
        $cache_driver = $em->getConfiguration()->getMetadataDriverImpl();
        $em->getConfiguration()->addEntityNamespace($bundle->getName(), $namespace.'\Entity');
        $cache_driver->addDriver($driver, $namespace.'\Entity');
    }


    /**
     * return doctrine type
     *
     * @param $fields
     * @return string
     */
/*    public function getModelColumns($fields)
    {
        $column = array();
        foreach($fields as $name => $data)
        {
            $data['fieldName'] = $name;

            $data = array_merge($data, TypeGuesser::getEntityType($data['type']));
            $column[] = $data;
        }
        return $column;
    }
*/

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

        $verbose_info = ($this->verbose) ? sprintf('-> %-50s %-10s %-10s %-3s', $namespace, $dir, $format, $structure) : '';
        $this->output->writeln(sprintf($this->log_pattern_ok, 'generate bundle', $name, $verbose_info));

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
    protected function generateEntity($bundle, $entity, $model, $format, $with_repository)
    {
        if (!isset($this->generator_entity)) {
            $this->generator_entity = new \Pz\QuickSetupBundle\Generator\DoctrineEntityGenerator($this->filesystem, $this->getContainer()->get('doctrine'));
        }

        $this->generator_entity->generate($bundle, $entity, $format, $model, $with_repository);

        $verbose_info = ($this->verbose) ? sprintf('-> %-50s %-10s %s %-3s', $entity, sizeof($model['fields']) . ' field(s)', $format, $with_repository) : '';
        $this->output->writeln(sprintf($this->log_pattern_ok, 'generate entity', $bundle->getName() . ':' . $entity, $verbose_info));
    }


    /**
     *
     * @param BundleInterface   $bundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     */
    protected function generateForm(BundleInterface $bundle, $entity, $fields, ClassMetadata $metadata)
    {
        if (!isset($this->generator_form)) {
            $this->generator_form = new \Pz\QuickSetupBundle\Generator\DoctrineFormGenerator($this->filesystem, __DIR__.'/../Resources/skeleton/form');
        }

        try{
            $this->generator_form->generate($bundle, $entity, $fields, $metadata);
            $verbose_info = '';
            $this->output->writeln(sprintf($this->log_pattern_ok, 'generate form', $bundle->getName() . ':' . $entity . 'Type', $verbose_info));
        }
        catch(\Exception $e)
        {
            $this->output->writeln(sprintf($this->log_pattern_ko, 'generate form', $bundle->getName() . ':' . $entity . 'Type', $e->getMessage()));
        }
    }

    /**
     *
     * Generate a doctrine CRUD controller
     *
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     * @param $entity
     */
    protected function generateCrud(BundleInterface $bundle, $entity, ClassMetadata $metadata, $format, $actions, $routePrefix, $forceOverwrite)
    {
        if (!isset($this->generator_crud)) {
            $this->generator_crud = new \Pz\QuickSetupBundle\Generator\DoctrineCrudGenerator($this->filesystem, __DIR__.'/../Resources/skeleton/crud');
        }
        try{
            $this->generator_crud->setActions($actions);
            $this->generator_crud->generate($bundle, $entity, $metadata, $format, $routePrefix, true, $forceOverwrite);
            $verbose_info = ($this->verbose) ? sprintf('-> %-50s %-10s %-3s', sizeof($actions) . ' action(s)', $routePrefix, $format ) : '';
            $this->output->writeln(sprintf($this->log_pattern_ok, 'generate crud', $bundle->getName() . ':' . $entity, $verbose_info));
        }
        catch(\Exception $e)
        {
            $this->output->writeln(sprintf($this->log_pattern_ko, 'generate crud', $bundle->getName() . ':' . $entity, $e->getMessage()));
        }
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
            $this->generator_controller = new \Sensio\Bundle\GeneratorBundle\Generator\ControllerGenerator($this->filesystem, __DIR__.'/../Resources/skeleton/controller');
        }

        $this->generator_controller->generate($bundle, $controller, $route_format, $template_format, $actions);
        $verbose_info = ($this->verbose) ? sprintf('-> %-50s %-10s %-3s', sizeof($actions) . ' action(s)', $template_format, $route_format ) : '';
        $this->output->writeln(sprintf($this->log_pattern_ok, 'generate controller', $bundle->getName() . ':' .$controller, $verbose_info));
    }


    /**
     * @param $namespace
     */
    protected function updateKernel($namespace)
    {
        if (!$this->input->getOption('kernel')) return;

        $manip = new KernelManipulator($this->kernel);
        try {
            $ret = $manip->addBundle($namespace);
        } catch (\RuntimeException $e) {
            $this->output->writeln(sprintf($this->log_pattern_ko, 'Update Kernel', $namespace, $e->getMessage()));
        }
    }

    /**
     * @param $bundle
     * @param $format
     * @return array
     */
    protected function updateRouting($bundle, $format)
    {
        if (!$this->input->getOption('route')) return;

        $routing = new RoutingManipulator($this->root_dir.'/config/routing.yml');
        try {
            $ret = $routing->addResource($bundle, $format);
        } catch (\RuntimeException $e) {
            $this->output->writeln(sprintf($this->log_pattern_ko, 'Update route', $bundle, $e->getMessage()));
        }
    }
}
