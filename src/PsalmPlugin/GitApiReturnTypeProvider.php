<?php

namespace Psalm\Shepherd\PsalmPlugin;

use PhpParser;
use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\StatementsSource;
use Psalm\Type;
use Github\Api;

class GitApiProvider implements \Psalm\Plugin\Hook\MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames() : array
    {
        return [\Github\Client::class];
    }

    /**
     * @param  array<PhpParser\Node\Arg>    $call_args
     * @return ?Type\Union
     */
    public static function getMethodReturnType(
        StatementsSource $source,
        string $fq_classlike_name,
        string $method_name_lowercase,
        array $call_args,
        Context $context,
        CodeLocation $code_location,
        array $template_type_parameters = null,
        string $called_fq_classlike_name = null,
        string $called_method_name_lowercase = null
    ) {
        if ($method_name_lowercase === 'api'
            && isset($call_args[0]->value->inferredType)
            && $call_args[0]->value->inferredType->isSingleStringLiteral()
        ) {
            switch ($call_args[0]->value->inferredType->getSingleStringLiteral()->value) {
                case 'me':
                case 'current_user':
                case 'currentUser':
                    $api = Api\CurrentUser::class;
                    break;
                case 'codeOfConduct':
                    $api = Api\Miscellaneous\CodeOfConduct::class;
                    break;

                case 'deployment':
                case 'deployments':
                    $api = Api\Deployment::class;
                    break;

                case 'ent':
                case 'enterprise':
                    $api = Api\Enterprise::class;
                    break;

                case 'emojis':
                    $api = Api\Miscellaneous\Emojis::class;
                    break;

                case 'git':
                case 'git_data':
                case 'gitData':
                    $api = Api\GitData::class;
                    break;

                case 'gist':
                case 'gists':
                    $api = Api\Gists::class;
                    break;

                case 'gitignore':
                    $api = Api\Miscellaneous\Gitignore::class;
                    break;

                case 'integration':
                case 'integrations':
                    $api = Api\Integrations::class;
                    break;

                case 'apps':
                    $api = Api\Apps::class;
                    break;

                case 'issue':
                case 'issues':
                    $api = Api\Issue::class;
                    break;

                case 'markdown':
                    $api = Api\Markdown::class;
                    break;

                case 'licenses':
                    $api = Api\Miscellaneous\Licenses::class;
                    break;

                case 'notification':
                case 'notifications':
                    $api = Api\Notification::class;
                    break;

                case 'organization':
                case 'organizations':
                    $api = Api\Organization::class;
                    break;

                case 'org_project':
                case 'orgProject':
                case 'org_projects':
                case 'orgProjects':
                case 'organization_project':
                case 'organizationProject':
                case 'organization_projects':
                case 'organizationProjects':
                    $api = Api\Organization\Projects::class;
                    break;

                case 'pr':
                case 'pulls':
                case 'pullRequest':
                case 'pull_request':
                case 'pullRequests':
                case 'pull_requests':
                    $api = Api\PullRequest::class;
                    break;

                case 'rateLimit':
                case 'rate_limit':
                    $api = Api\RateLimit::class;
                    break;

                case 'repo':
                case 'repos':
                case 'repository':
                case 'repositories':
                    $api = Api\Repo::class;
                    break;

                case 'search':
                    $api = Api\Search::class;
                    break;

                case 'team':
                case 'teams':
                    $api = Api\Organization\Teams::class;
                    break;

                case 'member':
                case 'members':
                    $api = Api\Organization\Members::class;
                    break;

                case 'user':
                case 'users':
                    $api = Api\User::class;
                    break;

                case 'authorization':
                case 'authorizations':
                    $api = Api\Authorizations::class;
                    break;

                case 'meta':
                    $api = Api\Meta::class;
                    break;

                case 'graphql':
                    $api = Api\GraphQL::class;
                    break;

                default:
                    return null;
            }
            
            return Type::parseString($api);
        }
    }
}