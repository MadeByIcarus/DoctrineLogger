<?php

namespace Icarus\Doctrine\Logger;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Kdyby\Doctrine\Events;
use Kdyby\Events\Subscriber;
use Nette\Security\User;
use Nette\Utils\Json;

class Logger implements Subscriber
{

    private $entityNamesToLog = [];

    /** @var User */
    private $user;

    /**
     * @var EntityManager
     */
    private $entityManager;



    function __construct(EntityManager $entityManager, User $user)
    {
        $this->user = $user;
        $this->entityManager = $entityManager;

        $user->onLoggedIn[] = [$this, 'userLoggedIn'];
        $user->onLoggedOut[] = [$this, 'userLoggedOut'];
    }



    public function setEntityNamesToLog(array $classes)
    {
        $this->entityNamesToLog = $classes;
    }



    public function getSubscribedEvents()
    {
        return [Events::onFlush];
    }



    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        if (!$this->entityNamesToLog) {
            return;
        }
        
        $em = $eventArgs->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $model) {
            $class = get_class($model);
            if (!in_array($class, $this->entityNamesToLog)) {
                continue;
            }
            $this->saveLog($em, $unitOfWork, $model, Log::EVENT_INSERT);
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $model) {
            $class = get_class($model);
            if (!in_array($class, $this->entityNamesToLog)) {
                continue;
            }
            $this->saveLog($em, $unitOfWork, $model, Log::EVENT_UPDATE);
        }
        foreach ($unitOfWork->getScheduledEntityDeletions() as $model) {
            $class = get_class($model);
            if (!in_array($class, $this->entityNamesToLog)) {
                continue;
            }
            $this->saveLog($em, $unitOfWork, $model, Log::EVENT_DELETE);
        }
    }



    protected function saveLog(EntityManager $entityManager, UnitOfWork $unitOfWork, $entity, $event)
    {
        $log = new Log();
        $log->setUser($this->user->isLoggedIn() ? $this->user->getId() : 0);
        $log->setEvent($event);

        if ($event != Log::EVENT_DELETE) {
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            $data = $this->processChanges($changeSet);
            $log->setData($data);
        }

        $metadata = $entityManager->getClassMetadata(get_class($entity));
        $log->setTable($metadata->getTableName());

        if (method_exists($entity, "getId")) {
            $log->setEntityId($entity->getId());
        }

        $entityManager->persist($log);
        $unitOfWork->computeChangeSet(
            $entityManager->getClassMetadata(get_class($log)), $log
        );
    }



    private function processChanges($changeSet)
    {
        return Json::encode($changeSet);
    }



    public function userLoggedIn(User $user)
    {
        $log = new Log();
        $log->setUser($user->getId());
        $log->setEvent(Log::EVENT_LOGIN);
        $this->entityManager->persist($log);
    }



    public function userLoggedOut(User $user)
    {
        $log = new Log();
        $log->setUser($user->getId());
        $log->setEvent(Log::EVENT_LOGOUT);
        $this->entityManager->persist($log);
    }
}