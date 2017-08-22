<?php

namespace Icarus\Doctrine\Logger;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Kdyby\Doctrine\Events;
use Kdyby\Events\Subscriber;
use Nette\Http\Request;
use Nette\InvalidArgumentException;
use Nette\Security\IAuthenticator;
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

    /**
     * @var Request
     */
    private $request;



    function __construct(EntityManager $entityManager, User $user, Request $request)
    {
        $this->entityManager = $entityManager;
        $this->user = $user;
        $this->request = $request;
    }



    public function setEntityNamesToLog(array $classes)
    {
        $this->entityNamesToLog = $classes;
    }



    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
            User::class . "::onLoggedIn",
            User::class . "::onLoggedOut"
        ];
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

        $log->setAccessUrl($this->request->getUrl()->getAbsoluteUrl());
        $log->setIpAddress($this->request->getRemoteAddress());

        $entityManager->persist($log);
        $unitOfWork->computeChangeSet(
            $entityManager->getClassMetadata(get_class($log)), $log
        );
    }



    private function processChanges($changeSet)
    {
        foreach ($changeSet as $key => $changes) {
            foreach ($changes as $index => $change) {
                if (is_object($change) && !$change instanceof \Serializable && method_exists($change, "getId") && $change->getId()) {
                    $changeSet[$key][$index] = $change->getId();
                }
            }
        }
        return Json::encode($changeSet);
    }



    public function onLoggedIn(User $user)
    {
        $log = new Log();
        $log->setUser($user->getId());
        $log->setEvent(Log::EVENT_LOGIN);
        $log->setAccessUrl($this->request->getUrl()->getAbsoluteUrl());
        $log->setIpAddress($this->request->getRemoteAddress());
        $this->entityManager->persist($log);
    }



    public function onLoggedOut(User $user)
    {
        $log = new Log();
        $log->setUser($user->getId());
        $log->setEvent(Log::EVENT_LOGOUT);
        $log->setAccessUrl($this->request->getUrl()->getAbsoluteUrl());
        $log->setIpAddress($this->request->getRemoteAddress());
        $this->entityManager->persist($log);
    }



    public function onInvalidAuthentication($login, $code)
    {
        switch ($code) {
            case IAuthenticator::IDENTITY_NOT_FOUND:
                $event = Log::EVENT_INVALID_LOGIN;
                break;
            case IAuthenticator::INVALID_CREDENTIAL:
                $event = Log::EVENT_INVALID_PASSWORD;
                break;
            case IAuthenticator::NOT_APPROVED:
                $event = Log::EVENT_ACCESS_DENIED;
                break;
            case IAuthenticator::FAILURE:
                $event = Log::EVENT_TOO_MANY_LOGIN_ATTEMPTS;
                break;
            default:
                throw new InvalidArgumentException("Invalid code $code for invalid authentication log.");
        }

        $log = new Log();
        $log->setUser($login);
        $log->setEvent($event);
        $log->setAccessUrl($this->request->getUrl()->getAbsoluteUrl());
        $log->setIpAddress($this->request->getRemoteAddress());
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}