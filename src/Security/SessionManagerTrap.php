<?php

namespace Fydellys\InertiaStatic\Security;

use Illuminate\Contracts\Container\Container;
use Illuminate\Session\SessionManager;

/**
 * Session manager whose every driver is the SessionTrap.
 */
class SessionManagerTrap extends SessionManager
{
    public function __construct(Container $container, protected SessionTrap $trap)
    {
        parent::__construct($container);
    }

    public function driver($driver = null)
    {
        return $this->trap;
    }
}
