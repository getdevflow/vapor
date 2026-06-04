<?php

declare(strict_types=1);

namespace Theme\Vapor\Controllers;

use App\Application\Devflow;
use App\Domain\Content\Model\Content;
use App\Infrastructure\Services\Paginator;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_content_types;
use function App\Shared\Helpers\get_all_content_with_filters;
use function App\Shared\Helpers\get_content_by;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\sort_list;
use function App\Shared\Helpers\update_option;
use function Codefy\Framework\Helpers\view;
use function count;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;

class VaporThemeController extends BaseController
{
    /**
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function index(): ResponseInterface
    {
        $options = get_option(key: 'vapor_theme_settings');
        $content = get_all_content_with_filters(
            contentTypeSlug: $options['vapor_content_type'] ?? '',
            limit: (int) get_option(key: 'content_per_page') ?? 6,
            offset: isset($options['vapor_content_offset']) ? (int) $options['vapor_content_offset'] : 0,
            status: $options['vapor_content_status'] ?? 'all',
        );

        if (empty(array_filter($content))) {
            return view(
                'theme::Vapor/views/no-content',
                ['title' => t__(msgid: '404: Not Found', domain: 'vapor-theme'), 'single' => false]
            );
        }

        $sort = sort_list(
            $content,
            $options['vapor_content_orderby'] ?? 'published',
            $options['vapor_content_order'] ?? 'desc',
        );
        return view(
            'theme::Vapor/views/index',
            [
                'title' => get_option(key: 'sitename'),
                'content' => $sort,
                'paginator' => new Paginator(
                    totalItems: (int) count(array_filter($sort)),
                    itemsPerPage: (int) get_option(key: 'content_per_page'),
                    currentPage: 1,
                ),
                'single' => false,
            ]
        );
    }

    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function show(ServerRequest $request): ResponseInterface
    {
        if (false === current_user_can(perm: 'manage:themes')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'vapor-theme')
            );
            return $this->redirect(admin_url());
        }

        if ($request->getMethod() === 'POST') {
            $options = [
                'vapor_content_type' => $request->get('vapor_content_type'),
                'vapor_content_offset' => $request->get('vapor_content_offset') ?? 0,
                'vapor_content_status' => $request->get('vapor_content_status') ?? 'all',
                'vapor_content_orderby' => $request->get('vapor_content_orderby'),
                'vapor_content_order' => $request->get('vapor_content_order') ?? 'desc',
            ];

            $update = update_option('vapor_theme_settings', $options);

            if ($update === false) {
                Devflow::$PHP->flash->error(
                    message: t__(msgid: 'Update error.', domain: 'vapor-theme')
                );
            } else {
                Devflow::$PHP->flash->success(
                    message: t__(msgid: 'Updated successfully.', domain: 'vapor-theme')
                );
            }

            return $this->redirect($request->getHeaderLine(name: 'Referer'));
        }

        return view(
            'theme::Vapor/views/show',
            [
                'types' => get_all_content_types()
            ]
        );
    }

    /**
     * @param string $contentSlug
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Exception
     */
    public function single(string $contentSlug): ResponseInterface
    {
        /** @var Content $content */
        $content = get_content_by('slug', $contentSlug);
        if (is_false__($content)) {
            return view(
                'theme::Vapor/views/no-content',
                ['title' => t__(msgid: '404: Not Found', domain: 'vapor-theme'), 'single' => false]
            );
        }

        return view(
            'theme::Vapor/views/single',
            [
                'title' => $content->title,
                'content' => $content,
                'single' => true,
            ]
        );
    }
}
