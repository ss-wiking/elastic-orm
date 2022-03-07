<?php

namespace SsWiking\ElasticOrm\Contracts;

interface Config
{
    /**
     * Get Elasticsearch host list
     *
     * @return array
     */
    public function hosts(): array;

    /**
     * Get Elasticsearch username if set
     *
     * @return string|null
     */
    public function username(): ?string;

    /**
     * Get Elasticsearch password if set
     *
     * @return string|null
     */
    public function password(): ?string;
}
