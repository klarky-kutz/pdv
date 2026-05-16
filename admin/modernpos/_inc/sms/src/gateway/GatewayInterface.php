<?php

namespace Sms\Gateway;

interface GatewayInterface 
{
  public function send($to, $message);
}