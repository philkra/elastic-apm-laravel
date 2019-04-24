<?php


namespace PhilKra\ElasticApmLaravel\Apm;


class SpanDbContext
{

    /**
     * @var string
     */
    private $instance;
    /**
     * @var string
     */
    private $statement;
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $user;

    public function __construct(string $instance, string $statement, string $type, string $user)
    {
        $this->instance = $instance;
        $this->statement = $statement;
        $this->type = $type;
        $this->user = $user;
    }

    public static function fromArray(array $data): self
    {
        $instance = $data['instance'] ?? '';
        $statement = $data['statement'] ?? '';
        $type = $data['type'] ?? '';
        $user = $data['user'] ?? '';

        return new self($instance, $statement, $type, $user);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}