<?php
namespace XiaoZhu\RabbitXzBundle\RabbitMq;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class PigConsumer
{
    protected $resultFlag;
    
    protected $error;
    
    use ContainerAwareTrait;
    
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }
    
    public function setError(string $error) : PigConsumer
    {
        $this->error = $error;
        return $this;
    }
    
    public function getError() : string
    {
        return $this->error;
    }
    
    public function clearError() : PigConsumer
    {
        $this->error = 'success';
        return $this;
    }
    
    public function setFlag(bool $result) : PigConsumer
    {
        $this->resultFlag = $result;
        return $this;
    }
    
    public function getFlag() : bool
    {
        return $this->resultFlag;
    }
}