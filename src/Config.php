<?php

namespace SsWiking\ElasticOrm;

use Illuminate\Config\Repository;

class Config implements Contracts\Config
{
    /**
     * Host list
     *
     * @var array
     */
    private array $hosts;

    /**
     * Auth username
     *
     * @var string|null
     */
    private ?string $username;

    /**
     * Auth password
     *
     * @var string|null
     */
    private ?string $password;

    public function __construct(Repository $config)
    {
        $credentials = $config->get('elastic-orm');

        $this->hosts = $credentials['hosts'];
        $this->username = $credentials['username'];
        $this->password = $credentials['password'];
    }

    /**
     * @inheritDoc
     */
    public function hosts(): array
    {
        return $this->hosts;
    }

    /**
     * @inheritDoc
     */
    public function username(): ?string
    {
        return $this->username;
    }

    /**
     * @inheritDoc
     */
    public function password(): ?string
    {
        return $this->password;
    }
}
