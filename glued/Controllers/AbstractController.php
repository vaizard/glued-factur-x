<?php
declare(strict_types=1);
namespace Glued\Controllers;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $c;

    /**
     * AbstractController constructor. We're passing the whole container to the constructor to be
     * able to do stuff like $this->c->db->method(). This is considered bad pracise that makes
     * the whole app more memory hungry / less efficient. Dependency injection should be rewritten
     * to take advantage of PHP-DI's autowiring.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
    }


    /**
     * __get is a magic method that allows us to always get the correct property out of the
     * container, allowing to write $this->db->method() instead of $this->c->db->method()
     * @param string $property Container property
     * @return mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __get($property)
    {
        if ($this->c->get($property)) {
            return $this->c->get($property);
        }
    }

}
