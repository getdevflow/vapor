<?php

declare(strict_types=1);

namespace Theme\Vapor\Controllers;

use App\Application\Devflow;
use App\Domain\Content\Model\Content;
use App\Infrastructure\Services\Options;
use App\Infrastructure\Services\Paginator;
use App\Infrastructure\Services\UserAuth;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Config\ConfigContainer;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use Qubus\Http\Session\SessionService;
use Qubus\Routing\Router;
use Qubus\View\Renderer;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_all_content_types;
use function App\Shared\Helpers\get_all_content_with_filters;
use function App\Shared\Helpers\get_content_by;
use function App\Shared\Helpers\sort_list;
use function count;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;

class VaporThemeController extends BaseController
{
    public function __construct(
        SessionService $sessionService,
        Router $router,
        protected ConfigContainer $configContainer,
        protected UserAuth $user,
        ?Renderer $view = null
    ) {
        parent::__construct($sessionService, $router, $view);
    }

    /**
     * @return string|null
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws UnresolvableQueryHandlerException
     */
    public function index(): ?string
    {
        $options = Options::factory()->read(optionKey: 'vapor_theme_settings');
        $content = get_all_content_with_filters(
            contentTypeSlug: $options['vapor_content_type'] ?? '',
            limit: (int) Options::factory()->read(optionKey: 'content_per_page') ?? 6,
            offset: isset($options['vapor_content_offset']) ? (int) $options['vapor_content_offset'] : 0,
            status: $options['vapor_content_status'] ?? 'all',
        );

        if (empty(array_filter($content))) {
            return $this->view->render(
                'theme::Vapor/views/no-content',
                ['title' => t__(msgid: '404: Not Found', domain: 'vapor-theme'), 'single' => false]
            );
        }

        $sort = sort_list(
            $content,
            $options['vapor_content_orderby'] ?? 'published',
            $options['vapor_content_order'] ?? 'desc',
        );
        return $this->view->render(
            'theme::Vapor/views/index',
            [
                'title' => Options::factory()->read(optionKey: 'sitename'),
                'content' => $sort,
                'paginator' => new Paginator(
                    totalItems: (int) count(array_filter($sort)),
                    itemsPerPage: (int) Options::factory()->read(optionKey: 'content_per_page'),
                    currentPage: 0,
                ),
                'single' => false,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @return string|ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws SessionException
     */
    public function show(ServerRequest $request): string|ResponseInterface
    {
        if (false === $this->user->can(permissionName: 'manage:themes', request: $request)) {
            Devflow::inst()::$APP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'vapor-theme')
            );
            return $this->redirect(admin_url());
        }

        if ($request->getMethod() === 'POST') {
            $options = [
                'vapor_content_type' => $request->get('vapor_content_type') ?? null,
                'vapor_content_offset' => $request->get('vapor_content_offset') ?? 0,
                'vapor_content_status' => $request->get('vapor_content_status') ?? 'all',
                'vapor_content_orderby' => $request->get('vapor_content_orderby') ?? null,
                'vapor_content_order' => $request->get('vapor_content_order') ?? 'desc',
            ];

            $update = Options::factory()->update('vapor_theme_settings', $options);

            if ($update === false) {
                Devflow::inst()::$APP->flash->error(
                    message: t__(msgid: 'Update error.', domain: 'vapor-theme')
                );
            } else {
                Devflow::inst()::$APP->flash->success(
                    message: t__(msgid: 'Updated successfully.', domain: 'vapor-theme')
                );
            }

            return $this->redirect($request->getServerParams()['HTTP_REFERER']);
        }

        return $this->view->render(
            'theme::Vapor/views/show',
            [
                'types' => get_all_content_types()
            ]
        );
    }

    /**
     * @param string $contentSlug
     * @return string|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function single(string $contentSlug): ?string
    {
        /** @var Content $content */
        $content = get_content_by('slug', $contentSlug);
        if (is_false__($content)) {
            return $this->view->render(
                'theme::Vapor/views/no-content',
                ['title' => t__(msgid: '404: Not Found', domain: 'vapor-theme'), 'single' => false]
            );
        }

        return $this->view->render(
            'theme::Vapor/views/single',
            [
                'title' => $content->title,
                'content' => $content,
                'single' => true,
            ]
        );
    }
}
