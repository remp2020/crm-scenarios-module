<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Emitter;
use Tracy\Debugger;

class InvalidJobException extends \Exception
{
}
