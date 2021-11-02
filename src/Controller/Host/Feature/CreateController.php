<?php

declare(strict_types=1);

namespace Nusje2000\FeatureToggleBundle\Controller\Host\Feature;

use Nusje2000\FeatureToggleBundle\Feature\Feature;
use Nusje2000\FeatureToggleBundle\Feature\SimpleFeature;
use Nusje2000\FeatureToggleBundle\Feature\State;
use Nusje2000\FeatureToggleBundle\Http\RequestParser;
use Nusje2000\FeatureToggleBundle\Repository\FeatureRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateController
{
    /**
     * @var RequestParser
     */
    private $requestParser;

    /**
     * @var FeatureRepository
     */
    private $repository;

    public function __construct(RequestParser $requestParser, FeatureRepository $repository)
    {
        $this->requestParser = $requestParser;
        $this->repository = $repository;
    }

    public function __invoke(Request $request, string $environment): Response
    {
        $json = $this->requestParser->json($request);
        $feature = $this->createFeatureFromJson($json);
        $name = $feature->name();

        $this->repository->add($environment, $feature);

        return new Response(sprintf('Created feature named "%s" in environment "%s".', $name, $environment));
    }

    /**
     * @param array<mixed> $json
     */
    private function createFeatureFromJson(array $json): Feature
    {
        /** @var mixed $name */
        $name = $json['name'] ?? null;
        if (!is_string($name)) {
            throw new BadRequestHttpException('Missing/Invalid environment name, please provide a string value for the "name" key.');
        }

        /** @var mixed $enabled */
        $enabled = $json['enabled'] ?? null;
        if (!is_bool($enabled)) {
            throw new BadRequestHttpException('Missing/Invalid feature state, please provide a boolean value for the "enabled" key.');
        }

        return new SimpleFeature($name, State::fromBoolean($enabled));
    }
}
