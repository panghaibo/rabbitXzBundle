<?php

namespace XiaoZhu\RabbitXzBundle\RabbitMq;

use XiaoZhu\RabbitXzBundle\Provider\ConnectionParametersProviderInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class AMQPConnectionFactory
{
    /** @var \ReflectionClass */
    private $class;

    /** @var array */
    private $parameters = array(
        'url'                => '',
        'cluster'            => array('localhost:5672'),
        'user'               => 'guest',
        'password'           => 'guest',
        'vhost'              => '/',
        'connection_timeout' => 3,
        'read_write_timeout' => 3,
        'ssl_context'        => null,
        'keepalive'          => false,
        'heartbeat'          => 0,
    );

    /**
     * Constructor
     *
     * @param string                                $class              FQCN of AMQPConnection class to instantiate.
     * @param array                                 $parameters         Map containing parameters resolved by
     *                                                                  Extension.
     * @param ConnectionParametersProviderInterface $parametersProvider Optional service providing/overriding
     *                                                                  connection parameters.
     */
    public function __construct(
        $class,
        array $parameters,
        ConnectionParametersProviderInterface $parametersProvider = null
    ) {
        $this->class = $class;
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->parameters = $this->parseUrl($this->parameters);
        if (is_array($this->parameters['ssl_context'])) {
            $this->parameters['ssl_context'] = ! empty($this->parameters['ssl_context'])
                ? stream_context_create(array('ssl' => $this->parameters['ssl_context']))
                : null;
        }
        if ($parametersProvider) {
            $this->parameters = array_merge($this->parameters, $parametersProvider->getConnectionParameters());
        }
    }

    /**
     * Creates the appropriate connection using current parameters.
     *
     * @return AbstractConnection
     */
    public function createConnection()
    {
        if (isset($this->parameters['constructor_args']) && is_array($this->parameters['constructor_args'])) {
            $ref = new \ReflectionClass($this->class);
            return $ref->newInstanceArgs($this->parameters['constructor_args']);
        }
        $lastException = null;
        //所有的链接都走rabbitMq自动寻找可靠的链接，使用随机进行集群间的负载均衡
        $hostConf = [];
        foreach ($this->parameters['cluster'] as $clusterId => $cluster) {
            $servers = explode(',', $cluster);
            foreach ($servers as $server) {
                list($host, $port) = explode(':', $server);
                if (empty($host) || empty($port)) throw new \InvalidArgumentException("Cluster Config Err");
                $hostConf[$clusterId][] = ['host' => $host, 'port' => $port, 'user' => $this->parameters['user'],'password' => $this->parameters['password'],'vhost' => $this->parameters['vhost']];  
            }
        }
        $option = [
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'login_response' => null,
            'locale' => 'en_US',
            'keepalive' => $this->parameters['keepalive'],
            'heartbeat' => $this->parameters['heartbeat']
        ];
        if ($this->class == 'PhpAmqpLib\Connection\AMQPSocketConnection' || is_subclass_of($this->class , 'PhpAmqpLib\Connection\AMQPSocketConnection')) {
            $option['read_timeout'] = isset($this->parameters['read_timeout']) ? $this->parameters['read_timeout'] : $this->parameters['read_write_timeout'];
            $option['write_timeout'] = isset($this->parameters['write_timeout']) ? $this->parameters['write_timeout'] : $this->parameters['read_write_timeout'];
        } else {
            $option['connection_timeout'] = $this->parameters['connection_timeout'];
            $option['read_write_timeout'] = $this->parameters['read_write_timeout'];
            $option['ssl_options'] = $this->parameters['ssl_context'];
        }
        shuffle($hostConf);
        foreach ($hostConf as $hosts) {
            try {
                shuffle($hosts);
                $connection = $this->class::create_connection($hosts, $option);
                return $connection;
            } catch(\Exception $e) {
                $lastException = $e;
            }
         }
         throw $lastException;
    }

    /**
     * Parses connection parameters from URL parameter.
     *
     * @param array $parameters
     *
     * @return array
     */
    private function parseUrl(array $parameters)
    {
        if (!$parameters['url']) {
            return $parameters;
        }

        $url = parse_url($parameters['url']);

        if ($url === false || !isset($url['scheme']) || $url['scheme'] !== 'amqp') {
            throw new InvalidConfigurationException('Malformed parameter "url".');
        }

        // See https://www.rabbitmq.com/uri-spec.html
        if (isset($url['host'])) {
            $parameters['host'] = urldecode($url['host']);
        }
        if (isset($url['port'])) {
            $parameters['port'] = (int)$url['port'];
        }
        if (isset($url['user'])) {
            $parameters['user'] = urldecode($url['user']);
        }
        if (isset($url['pass'])) {
            $parameters['password'] = urldecode($url['pass']);
        }
        if (isset($url['path'])) {
            $parameters['vhost'] = urldecode(ltrim($url['path'], '/'));
        }

        if (isset($url['query'])) {
            $query = array();
            parse_str($url['query'], $query);
            $parameters = array_merge($parameters, $query);
        }

        unset($parameters['url']);

        return $parameters;
    }
}
