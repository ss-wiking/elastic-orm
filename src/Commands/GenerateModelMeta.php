<?php

namespace App\Console\Commands\Dev;

use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Context;
use Barryvdh\Reflection\DocBlock\Serializer as DocBlockSerializer;
use Barryvdh\Reflection\DocBlock\Tag;
use Elasticsearch\Client;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionException;
use SsWiking\ElasticOrm\Model;

class GenerateModelMeta extends Command
{
    /**
     * PHP to Elasticsearch type mappings
     */
    public const TYPES = [
        'array' => [
            'nested',
        ],
        'int' => [
            'long',
            'integer',
        ],
        'float' => [
            'float',
            'double',
        ],
        'string' => [
            'text',
            'keyword',
            'string',
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic-orm:meta {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Short way to generate Elastic Model's phpDoc";

    /**
     * Filesystem driver
     *
     * @var Filesystem $files
     */
    protected Filesystem $files;

    /**
     * Elasticsearch client
     *
     * @var Client
     */
    protected Client $client;

    /**
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws ReflectionException|FileNotFoundException
     */
    public function handle(): int
    {
        $model = $this->argument('model');

        $this->generateFor($model);

        return 0;
    }

    /**
     * Generate phpDoc for provided model's namespace
     *
     * @param string $class
     * @return void
     * @throws ReflectionException
     * @throws FileNotFoundException
     */
    private function generateFor(string $class): void
    {
        if (!class_exists($class)) {
            $this->error("Model [$class] doesn't exist");
        }

        /** @var Model $model */
        $model = new $class();

        $index = $model->getIndex();
        $mapping = $this->getMapping($index);

        $reflection = new ReflectionClass($class);
        $namespace = $reflection->getNamespaceName();
        $classname = $reflection->getShortName();
        $originalDoc = $reflection->getDocComment();

        $phpDoc = new DocBlock('', new Context($namespace));

        $phpDoc->setText($class);

        $casts = $reflection->getDefaultProperties()['casts'];

        foreach ($mapping as $name => $property) {
            $type = $this->resolveType($property['type']);
            if (!$type) {
                $this->error("Unknown type [{$property['type']}] in mapping for model $classname");
            }

            $cast = $casts[$name] ?? null;

            if ($cast === 'array') {
                $type .= '[]';
            }

            $tagLine = trim("@property $type \$$name");
            $tag = Tag::createInstance($tagLine, $phpDoc);
            $phpDoc->appendTag($tag);
        }

        $serializer = new DocBlockSerializer();
        $docComment = $serializer->getDocComment($phpDoc);

        $filename = $reflection->getFileName();
        $contents = $this->files->get($filename);

        if ($originalDoc) {
            $contents = str_replace($originalDoc, $docComment, $contents);
        } else {
            $replace = "$docComment\n";
            $pos = strpos($contents, "final class $classname") ?? strpos($contents, "class $classname");
            if ($pos !== false) {
                $contents = substr_replace($contents, $replace, $pos, 0);
            }
        }
        if ($this->files->put($filename, $contents)) {
            $this->info("Written new phpDocBlock to $filename");
        }
    }

    /**
     * Get index mappings from Elasticsearch
     *
     * @param string $index
     * @return array
     */
    public function getMapping(string $index): array
    {
        return collect(
                $this->client->indices()->getMapping(['index' => $index])
            )
                ->first()['mappings']['properties'] ?? [];
    }

    /**
     * Resolve PHP's type from Elasticsearch type
     *
     * @param string $type
     * @return string|null
     */
    private function resolveType(string $type): ?string
    {
        foreach (self::TYPES as $phpType => $elasticTypes) {
            if (in_array($type, $elasticTypes, true)) {
                return $phpType;
            }
        }

        return null;
    }
}
