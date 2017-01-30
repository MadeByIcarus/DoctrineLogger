<?php

namespace Icarus\Doctrine\Logger;

use Kdyby\Doctrine\Entities\Attributes\Identifier;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Log
{

    const EVENT_INSERT = "insert";
    const EVENT_UPDATE = "update";
    const EVENT_DELETE = "delete";

    const EVENT_LOGIN = "login";
    const EVENT_LOGOUT = "logout";

    use Identifier;

    /**
     * @ORM\Column(type="datetime")
     */
    private $datetime;

    /**
     * @ORM\Column(type="string")
     */
    private $user;

    /**
     * @ORM\Column(type="string")
     */
    private $event;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $tag;

    /**
     * @ORM\Column(type="string", nullable=true, name="`table`")
     */
    private $table;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $data;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $entityId;



    function __construct()
    {
        $this->datetime = new \DateTime();
    }



    public function setUser($id)
    {
        $this->user = $id;
    }



    public function setEvent($event)
    {
        $this->event = $event;
    }



    public function setData($data)
    {
        $this->data = $data;
    }



    public function setEntityId($id)
    {
        $this->entityId = $id;
    }



    public function setTable($name)
    {
        $this->table = $name;
    }
}