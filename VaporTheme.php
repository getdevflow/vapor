<?php

declare(strict_types=1);

namespace Theme\Vapor;

use App\Application\Devflow;
use App\Infrastructure\Services\Theme;
use App\Shared\Services\Registry;
use App\Shared\Services\Utils;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Routing\Exceptions\TooLateToAddNewRouteException;
use ReflectionException;
use Theme\Vapor\Controllers\VaporThemeController;

use function App\Shared\Helpers\add_themes_submenu;
use function App\Shared\Helpers\cms_enqueue_css;
use function App\Shared\Helpers\css_directory_uri;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\theme_root;
use function App\Shared\Helpers\theme_url;
use function App\Shared\Helpers\update_option;
use function basename;
use function dirname;
use function get_class;
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
            'slug' => 'Vapor',
            'id' => 'vapor-theme',
            'author' => 'Joshua Parker',
            'version' => '2.0.0',
            'description' => t__(
                msgid: 'Ported from Ghost, Vapor is a minimal and responsive theme with a focus on typography.',
                domain: 'vapor-theme'
            ),
            'basename' => basename(dirname(__FILE__)),
            'path' => theme_root(__FILE__),
            'url' => theme_url('', __CLASS__),
            'themeUri' => 'https://github.com/getdevflow/vapor',
            'authorUri' => 'https://joshuaparker.dev/',
            'className' => get_class($this),
            'screenshot' => theme_url('Vapor/images/screenshot.png'),
        ];

        Registry::getInstance()->set('vapor-theme', $theme);

        return $theme;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function handle(): void
    {
        Action::getInstance()->addAction('cms_head', [$this, 'enqueueFrontEndCss']);
        Action::getInstance()->addAction('themes_submenu', [$this, 'registerSubmenu']);
        Action::getInstance()->addAction('after_setup_theme', [$this, 'frontendRender']);
        Action::getInstance()->addAction('theme_loaded', [$this, 'backendRender']);
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
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
            minify: true,
            slug: $this->slug()
        );
        cms_enqueue_css(
            config: 'theme',
            asset: css_directory_uri() . 'screen.css',
            minify: true,
            slug: $this->slug()
        );
        cms_enqueue_css(
            config: 'theme',
            asset: css_directory_uri() . 'font-awesome.min.css',
            slug: $this->slug()
        );
    }

    /**
     * @return void
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeException
     */
    public function registerDefaultOptions(): void
    {
        if (get_option(key: 'vapor_theme_settings') !== false) {
            return;
        }
        $defaults = [
            'vapor_content_type' => null,
            'vapor_content_offset' => 0,
            'vapor_content_status' => 'all',
            'vapor_content_orderby' => null,
            'vapor_content_order' => 'desc',
        ];

        update_option(key: 'vapor_theme_settings', value: $defaults);
    }

    /**
     * @return void
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TooLateToAddNewRouteException
     * @throws TypeException
     */
    public function backendRender(): void
    {
        if (!Utils::isAdmin()) {
            return;
        }
        $this->registerDefaultOptions();

        $router = Devflow::$PHP->router;

        $router->map(
            ['GET', 'POST'],
            '/admin/theme/vapor-theme/',
            function (ServerRequest $request, VaporThemeController $controller) {
                return $controller->show($request);
            }
        );
    }

    /**
     * @return void
     * @throws TooLateToAddNewRouteException
     */
    public function frontendRender(): void
    {
        if (Utils::isAdmin()) {
            return;
        }

        $router = Devflow::$PHP->router;

        $router->get('/', function (VaporThemeController $controller) {
            return $controller->index();
        });

        $router->get('/{contentSlug}', function (string $contentSlug, VaporThemeController $controller) {
            return $controller->single($contentSlug);
        });
    }
}
