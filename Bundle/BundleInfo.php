<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pz\QuickSetupBundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * An implementation of BundleInterface that adds a few conventions
 * for DependencyInjection extensions and Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class BundleInfo implements BundleInterface
{
    protected $name;
    protected $namespace;
    protected $path;


    /**
     * Boots the Bundle.
     *
     * @api
     */
    public function boot()
    {
    }

    /**
     * Shutdowns the Bundle.
     *
     * @api
     */
    public function shutdown()
    {
    }

    /**
     * Builds the bundle.
     *
     * It is only ever called once when the cache is empty.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @api
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * Returns the container extension that should be implicitly loaded.
     *
     * @return ExtensionInterface|null The default extension or null if there is none
     *
     * @api
     */
    public function getContainerExtension()
    {
        return null;
    }

    /**
     * Returns the bundle name that this bundle overrides.
     *
     * Despite its name, this method does not imply any parent/child relationship
     * between the bundles, just a way to extend and override an existing
     * bundle.
     *
     * @return string The Bundle name it overrides or null if no parent
     *
     * @api
     */
    public function getParent()
    {
        return null;
    }

    /**
     * Returns the bundle name (the class short name).
     * @Example PzBlogBundle
     *
     * @return string The Bundle name
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getShortName()
    {
            return preg_filter('#(.*)\\\\(.*)Bundle#', "$2", $this->getNamespace());
    }

    /**
     * Gets the Bundle namespace.
     * @Example Pz\BlogBundle
     * @doc http://symfony.com/doc/master/cookbook/bundles/best_practices.html
     *
     * @return string The Bundle namespace
     *
     * @api
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Gets the Bundle directory path.
     *
     * The path should always be returned as a Unix path (with /).
     *
     * @return string The Bundle absolute path
     *
     * @api
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }


}
