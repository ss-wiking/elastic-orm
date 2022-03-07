<?php

namespace SsWiking\ElasticOrm;

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

    public function __construct()
    {
        $config = config('elastic-orm');

        $this->hosts = $config['hosts'];
        $this->username = $config['username'];
        $this->password = $config['password'];
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
