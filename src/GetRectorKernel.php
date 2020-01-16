<?php

declare(strict_types=1);

namespace Rector\Website;

use Doctrine\Common\EventSubscriber;
use Iterator;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symplify\AutoBindParameter\DependencyInjection\CompilerPass\AutoBindParameterCompilerPass;
use Symplify\Autodiscovery\Discovery;
use Symplify\AutowireArrayParameter\DependencyInjection\CompilerPass\AutowireArrayParameterCompilerPass;
use Symplify\FlexLoader\Flex\FlexLoader;
use Symplify\PackageBuilder\DependencyInjection\CompilerPass\AutoReturnFactoryCompilerPass;

final class GetRectorKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @var FlexLoader
     */
    private $flexLoader;

    /**
     * @var Discovery
     */
    private $discovery;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        $this->flexLoader = new FlexLoader($environment, $this->getProjectDir());
        $this->discovery = new Discovery($this->getProjectDir());
    }

    public function registerBundles(): Iterator
    {
        return $this->flexLoader->loadBundles();
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader): void
    {
        // WTF fix: https://github.com/doctrine/DoctrineBundle/issues/674
        $containerBuilder->registerForAutoconfiguration(EventSubscriber::class)
            ->addTag('doctrine.event_subscriber');

        $this->discovery->discoverEntityMappings($containerBuilder);
        $this->discovery->discoverTemplates($containerBuilder);
        $this->discovery->discoverTranslations($containerBuilder);

        $this->flexLoader->loadConfigs($containerBuilder, $loader, [
            // project's packages
            $this->getProjectDir() . '/packages/*/config/*',
        ]);
    }

    protected function configureRoutes(RouteCollectionBuilder $routeCollectionBuilder): void
    {
        $this->discovery->discoverRoutes($routeCollectionBuilder);

        $this->flexLoader->loadRoutes($routeCollectionBuilder, [
            // project's packages
            //            $this->getProjectDir() . '/packages/*/src/Controller/*',
        ]);
    }

    protected function build(ContainerBuilder $containerBuilder): void
    {
        // needs to be first, since it's adding new service definitions
        $containerBuilder->addCompilerPass(new AutoReturnFactoryCompilerPass());

        // autowiring
        $containerBuilder->addCompilerPass(new AutoBindParameterCompilerPass());
        $containerBuilder->addCompilerPass(new AutowireArrayParameterCompilerPass());
    }
}
