<?php

declare(strict_types=1);

namespace Theme\Vapor;

use App\Infrastructure\Services\Options;
use App\Infrastructure\Services\Theme;
use App\Shared\Services\Registry;
use App\Shared\Services\Utils;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\add_themes_submenu;
use function App\Shared\Helpers\cms_enqueue_css;
use function App\Shared\Helpers\css_directory_uri;
use function App\Shared\Helpers\get_all_content_types;
use function App\Shared\Helpers\theme_root;
use function App\Shared\Helpers\theme_url;
use function basename;
use function Qubus\Security\Helpers\t__;

class VaporTheme extends Theme
{
    /**
     * @inheritDoc
     * @throws ReflectionException|Exception
     */
    public function meta(): array
    {
        $theme = [
            'name' => t__(msgid: 'Vapor', domain: 'vapor-theme'),
            'id' => 'vapor-theme',
            'author' => 'Joshua Parker',
            'version' => '1.0.0',
            'description' => t__(msgid: 'Ported from Ghost, Vapor is a minimal and responsive theme with a focus on typography.', domain: 'vapor-theme'),
            'basename' => basename(dirname(__FILE__)),
            'path' => theme_root(__FILE__),
            'url' => theme_url('', __CLASS__),
            'themeUri' => 'https://github.com/getdevflow/vapor',
            'authorUri' => 'https://nomadicjosh.com/',
            'className' => get_class($this),
            'screenshot' => theme_url('Vapor/screenshot.png'),
        ];

        Registry::getInstance()->set('vapor-theme', $theme);

        return $theme;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException|UnresolvableQueryHandlerException
     */
    public function handle(): void
    {
        if (count(get_all_content_types()) <= 0) {
            return;
        }
        Action::getInstance()->addAction('cms_head', [$this, 'enqueueFrontEndCss']);
        Action::getInstance()->addAction('themes_submenu', [$this, 'registerSubmenu']);
        Action::getInstance()->addAction('after_setup_theme', [$this, 'frontendRender']);
        Action::getInstance()->addAction('theme_loaded', [$this, 'backendRender']);
    }

    /**
     * @return void
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function registerSubmenu(): void
    {
        echo add_themes_submenu(
            menuTitle: $this->meta()['name'],
            menuRoute: 'theme/' . $this->meta()['id'],
            screen: $this->meta()['id'],
            permission: 'manage:themes'
        );
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function enqueueFrontEndCss(): void
    {
        if (Utils::isAdmin()) {
            return;
        }

        cms_enqueue_css(
            config: 'theme',
            asset: css_directory_uri() . 'normalize.css',
            slug: $this->id()
        );
        cms_enqueue_css(
            config: 'theme',
            asset: css_directory_uri() . 'screen.css',
            slug: $this->id()
        );
        cms_enqueue_css(
            config: 'theme',
            asset: css_directory_uri() . 'font-awesome.min.css',
            slug: $this->id()
        );
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function registerDefaultOptions(): void
    {
        $options = Options::factory();
        if ($options->read(optionKey: 'vapor_theme_settings') !== false) {
            return;
        }
        $defaults = [
            'vapor_content_type' => null,
            'vapor_content_offset' => 0,
            'vapor_content_status' => 'all',
            'vapor_content_orderby' => null,
            'vapor_content_order' => 'desc',
        ];

        $options->update(optionKey: 'vapor_theme_settings', newvalue: $defaults);
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function backendRender(): void
    {
        if (!Utils::isAdmin()) {
            return;
        }
        $this->registerDefaultOptions();

        Filter::getInstance()->addFilter(hook: 'theme_route', callback: function ($router) {
            $router->setDefaultNamespace('\\Theme\\Vapor\\Controllers');
            $router->map(['GET', 'POST'], '/admin/theme/vapor-theme/', 'VaporThemeController@show');
        }, priority: 5);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function frontendRender(): void
    {
        if (Utils::isAdmin()) {
            return;
        }

        Filter::getInstance()->addFilter(hook: 'theme_route', callback: function ($router) {
            $router->setDefaultNamespace('\\Theme\\Vapor\\Controllers');
            $router->get('/', 'VaporThemeController@index');
            $router->get('/{contentSlug}/', 'VaporThemeController@single');
        }, priority: 5);
    }
}
