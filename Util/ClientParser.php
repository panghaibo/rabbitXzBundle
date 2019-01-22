<?php
namespace XiaoZhu\RabbitXzBundle\Util;

class ClientParser extends Parser
{
    /**
     * 解析客户端命令
     * {@inheritDoc}
     * @see \XiaoZhu\RabbitXzBundle\Util\Parser::parseCommand()
     */
    public function parseCommand(Client $client) : bool
    {
        $command = trim($client->getRbuffer());
        if (empty($command)) {
            return false;
        }
        if ($command == self::QUIT) {
            return $this->quitCommand($client);
        }else {
            $prefix = strtolower(substr($command, 0, strpos($command, ' ')));
            $suffix = substr($command, strpos($command, ' ') + 1);
        }
        $commandMethod = $prefix.'Command';
        if (!method_exists(__CLASS__, $commandMethod)) {
            return false;
        }
        return call_user_func(array($this, $commandMethod), $client, $suffix);
    }
    
    public function okCommand($socket) :bool
    {
        return true;
    }
    
    public function errorCommand($socket) :bool
    {
        return false;
    }
}